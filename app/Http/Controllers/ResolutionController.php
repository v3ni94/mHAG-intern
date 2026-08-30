<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Enums\ResolutionStatus;
use App\Enums\ResolutionType;
use App\Http\Requests\Holding\StoreResolutionRequest;
use App\Http\Requests\Holding\StoreResolutionLinkRequest;
use App\Models\Contract;
use App\Models\CorporateBody;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Resolution;
use App\Models\ResolutionLink;
use App\Models\Security;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Services\AuditService;
use App\Services\Holding\ResolutionService;
use App\Services\Storage\DocumentStorageInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Beschlussverwaltung (Abschnitte 88 bis 98): Register mit Filtern und
 * PDF-Export, Workflow von der Erfassung bis zur Signatur.
 */
class ResolutionController extends Controller
{
    /**
     * Whitelist der Verknüpfungsziele (Abschnitt 96), analog zur
     * Dokumentverknüpfung: Schlüssel => [Klasse, deutsches Label].
     */
    public const LINKABLE_TYPES = [
        'entity' => [Entity::class, 'Person/Unternehmen'],
        'loan' => [Loan::class, 'Darlehen'],
        'share-transaction' => [ShareTransaction::class, 'Aktienbewegung'],
        'investment' => [Investment::class, 'Beteiligung'],
        'contract' => [Contract::class, 'Vertrag'],
        'security' => [Security::class, 'Sicherheit'],
        'corporate-body' => [CorporateBody::class, 'Organ'],
    ];

    /** Manuell setzbare Status (Signaturstatus nur über den Signaturprozess). */
    private const MANUAL_STATUSES = [
        ResolutionStatus::Submitted,
        ResolutionStatus::Review,
        ResolutionStatus::Voting,
        ResolutionStatus::Accepted,
        ResolutionStatus::Rejected,
        ResolutionStatus::Postponed,
        ResolutionStatus::Withdrawn,
        ResolutionStatus::Completed,
        ResolutionStatus::Archived,
    ];

    /** Statuswerte, die zugleich das Ergebnis dokumentieren (Abschnitt 91). */
    private const RESULT_STATUSES = [
        'accepted' => 'accepted',
        'rejected' => 'rejected',
        'postponed' => 'postponed',
        'withdrawn' => 'withdrawn',
    ];

    public function __construct(private readonly ResolutionService $resolutions)
    {
    }

    public function index(Request $request)
    {
        $query = Resolution::query()->with(['company']);

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->where(function ($q) use ($year) {
                $q->whereYear('resolved_on', $year)
                    ->orWhere(function ($inner) use ($year) {
                        $inner->whereNull('resolved_on')->whereYear('recorded_at', $year);
                    });
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('q')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('resolution_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('motion', 'like', $term)
                    ->orWhere('reasoning', 'like', $term)
                    ->orWhere('resolution_text', 'like', $term);
            });
        }

        $query->orderByDesc('resolved_on')->orderByDesc('id');

        // Registerexport als PDF (Abschnitt 98), Filter werden übernommen
        if ($request->input('format') === 'pdf') {
            $resolutions = $query->get();

            return Pdf::loadView('resolutions.pdf.register', [
                'resolutions' => $resolutions,
                'filters' => $request->only(['year', 'type', 'status', 'q']),
                'generatedAt' => Carbon::now(),
            ])->stream('beschlussregister.pdf');
        }

        $resolutions = $query->paginate(25)->withQueryString();

        return view('resolutions.index', [
            'resolutions' => $resolutions,
            'types' => ResolutionType::cases(),
            'statuses' => ResolutionStatus::cases(),
            'filters' => $request->only(['year', 'type', 'status', 'q']),
        ]);
    }

    public function create(Request $request)
    {
        $companyEntityId = Setting::get('holding', 'company_entity_id');

        return view('resolutions.create', [
            'types' => ResolutionType::cases(),
            'preselectedType' => $request->input('type'),
            'companies' => Entity::query()->where('type', EntityType::Company)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']),
            'entities' => Entity::query()->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']),
            'defaultCompanyId' => $companyEntityId,
        ]);
    }

    public function store(StoreResolutionRequest $request)
    {
        $data = $request->validated();
        $type = ResolutionType::from($data['type']);

        $resolution = DB::transaction(function () use ($data, $type) {
            $resolution = Resolution::create([
                ...$data,
                'conflict_of_interest' => (bool) ($data['conflict_of_interest'] ?? false),
                'resolution_number' => $this->resolutions->nextNumber($type),
                // Getrennte Daten (Abschnitt 90): resolved_on = tatsächliches
                // Beschlussdatum (Eingabe), recorded_at = Erfassungszeitpunkt.
                'recorded_at' => Carbon::now(),
                'status' => ResolutionStatus::Draft->value,
            ]);

            $this->prefillParticipants($resolution, $type);

            return $resolution;
        });

        AuditService::log('resolutions.created', $resolution, [], [
            'resolution_number' => $resolution->resolution_number,
            'type' => $type->value,
            'title' => $resolution->title,
        ]);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', sprintf('Beschluss %s wurde als Entwurf angelegt.', $resolution->resolution_number));
    }

    public function show(Resolution $resolution)
    {
        $resolution->load([
            'company',
            'applicant',
            'participants.entity',
            'participants.vote',
            'links.linkable',
            'document',
            'signatureRequests.participants.entity',
        ]);

        return view('resolutions.show', [
            'resolution' => $resolution,
            'summary' => $this->resolutions->voteSummary($resolution),
            'manualStatuses' => self::MANUAL_STATUSES,
            'linkableTypes' => self::LINKABLE_TYPES,
        ]);
    }

    public function edit(Resolution $resolution)
    {
        if (! $this->isEditable($resolution)) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('warning', 'Der Beschluss kann in diesem Status nicht mehr bearbeitet werden.');
        }

        $resolution->load('company');

        return view('resolutions.edit', [
            'resolution' => $resolution,
            'types' => ResolutionType::cases(),
            'companies' => Entity::query()->where('type', EntityType::Company)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']),
            'entities' => Entity::query()->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function update(StoreResolutionRequest $request, Resolution $resolution)
    {
        if (! $this->isEditable($resolution)) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('warning', 'Der Beschluss kann in diesem Status nicht mehr bearbeitet werden.');
        }

        $data = $request->validated();
        // Beschlussart und Nummer bleiben nach Anlage stabil.
        unset($data['type']);

        $old = $resolution->only(array_keys($data));
        $resolution->update([
            ...$data,
            'conflict_of_interest' => (bool) ($data['conflict_of_interest'] ?? false),
        ]);

        AuditService::log('resolutions.updated', $resolution, $old, $data);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'Beschluss wurde aktualisiert.');
    }

    /** Statusaktion im Workflow (Abschnitt 93). */
    public function updateStatus(Request $request, Resolution $resolution)
    {
        $validated = $request->validate(
            ['status' => ['required', Rule::in(array_map(fn ($s) => $s->value, self::MANUAL_STATUSES))]],
            ['status.in' => 'Dieser Statuswechsel ist nicht zulässig.'],
        );

        $target = ResolutionStatus::from($validated['status']);

        if (in_array($resolution->status, [ResolutionStatus::Signed, ResolutionStatus::Completed, ResolutionStatus::Archived], true)
            && ! in_array($target, [ResolutionStatus::Completed, ResolutionStatus::Archived], true)) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('danger', 'Ein unterschriebener oder abgeschlossener Beschluss kann nicht zurückgesetzt werden.');
        }

        $old = ['status' => $resolution->status?->value, 'result' => $resolution->result];

        $resolution->status = $target;
        if (isset(self::RESULT_STATUSES[$target->value])) {
            $resolution->result = self::RESULT_STATUSES[$target->value];
        }
        $resolution->save();

        AuditService::log('resolutions.status-changed', $resolution, $old, [
            'status' => $target->value,
            'result' => $resolution->result,
        ]);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', sprintf('Status wurde auf "%s" gesetzt.', $target->label()));
    }

    /**
     * Finalisieren (Abschnitt 93, Schritte 7/8): PDF im CI erzeugen,
     * ablegen und den Beschluss zur Unterschrift stellen.
     */
    public function finalize(Resolution $resolution)
    {
        if ($resolution->result === null) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('danger', 'Bitte zuerst das Ergebnis der Abstimmung erfassen (angenommen, abgelehnt, vertagt oder zurückgezogen).');
        }

        if (in_array($resolution->status, [ResolutionStatus::Signed, ResolutionStatus::Completed, ResolutionStatus::Archived], true)) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('warning', 'Der Beschluss ist bereits finalisiert.');
        }

        $document = $this->resolutions->generatePdf($resolution);

        $old = ['status' => $resolution->status?->value];
        $resolution->status = ResolutionStatus::ForSignature;
        $resolution->save();

        AuditService::log('resolutions.finalized', $resolution, $old, [
            'status' => ResolutionStatus::ForSignature->value,
            'document_id' => $document->id,
        ]);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', sprintf('Beschluss-PDF %s wurde erzeugt und abgelegt. Der Beschluss steht zur Unterschrift.', $resolution->resolution_number));
    }

    /** Beschluss-PDF abrufen: abgelegte Fassung oder Vorschau. */
    public function pdf(Resolution $resolution, DocumentStorageInterface $storage)
    {
        $document = $resolution->document()->first();

        if ($document) {
            $content = $storage->retrieve($document);

            return response($content, 200, [
                'Content-Type' => $document->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$document->original_filename.'"',
            ]);
        }

        // Vorschau ohne Ablage (noch nicht finalisiert)
        $resolution->load(['company.company', 'applicant', 'participants.entity', 'participants.vote', 'links.linkable']);

        return Pdf::loadView('resolutions.pdf.resolution', [
            'resolution' => $resolution,
            'summary' => $this->resolutions->voteSummary($resolution),
            'preview' => true,
        ])->stream($resolution->resolution_number.'-vorschau.pdf');
    }

    /** Verknüpfung anlegen (Abschnitt 96, Whitelist). */
    public function storeLink(StoreResolutionLinkRequest $request, Resolution $resolution)
    {
        [$class] = self::LINKABLE_TYPES[$request->validated('linkable_type')];

        $target = $class::query()->find($request->validated('linkable_id'));
        if (! $target) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('danger', 'Der ausgewählte Vorgang wurde nicht gefunden.');
        }

        $link = ResolutionLink::firstOrCreate([
            'resolution_id' => $resolution->id,
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
        ]);

        AuditService::log('resolutions.link-added', $resolution, [], [
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
        ]);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'Verknüpfung wurde angelegt.');
    }

    public function destroyLink(Resolution $resolution, ResolutionLink $link)
    {
        abort_unless($link->resolution_id === $resolution->id, 404);

        AuditService::log('resolutions.link-removed', $resolution, [
            'linkable_type' => $link->linkable_type,
            'linkable_id' => $link->linkable_id,
        ], []);

        $link->delete();

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'Verknüpfung wurde entfernt.');
    }

    private function isEditable(Resolution $resolution): bool
    {
        return in_array($resolution->status, [
            ResolutionStatus::Draft,
            ResolutionStatus::Submitted,
            ResolutionStatus::Review,
            ResolutionStatus::Voting,
            ResolutionStatus::Postponed,
        ], true);
    }

    /**
     * Teilnehmer aus den Organ-Mitgliedern vorbelegen (Abschnitt 89):
     * Vorstandsbeschluss -> Vorstand, Aufsichtsratsbeschluss -> Aufsichtsrat,
     * Hauptversammlung -> Aktionäre, Umlaufbeschluss -> Vorstand.
     */
    private function prefillParticipants(Resolution $resolution, ResolutionType $type): void
    {
        if ($type === ResolutionType::GeneralMeeting) {
            Shareholder::query()
                ->with('entity')
                ->where('status', 'active')
                ->get()
                ->each(function (Shareholder $shareholder) use ($resolution) {
                    if ($shareholder->entity_id) {
                        $resolution->participants()->create([
                            'entity_id' => $shareholder->entity_id,
                            'role' => 'Aktionär',
                        ]);
                    }
                });

            return;
        }

        $bodyType = match ($type) {
            ResolutionType::Board, ResolutionType::Circular => 'board',
            ResolutionType::SupervisoryBoard => 'supervisory_board',
            default => null,
        };

        if (! $bodyType) {
            return;
        }

        $body = CorporateBody::query()
            ->where('company_entity_id', $resolution->company_entity_id)
            ->where('type', $bodyType)
            ->first();

        $body?->activeMembers()->with('person')->get()->each(function ($member) use ($resolution) {
            $resolution->participants()->create([
                'entity_id' => $member->person_entity_id,
                'role' => $member->role ?: ($member->is_chair ? 'Vorsitz' : 'Mitglied'),
            ]);
        });
    }
}
