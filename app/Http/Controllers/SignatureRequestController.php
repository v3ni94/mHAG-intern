<?php

namespace App\Http\Controllers;

use App\Enums\SignatureParticipantStatus;
use App\Enums\SignatureRequestStatus;
use App\Http\Requests\Holding\AttachSignedDocumentRequest;
use App\Http\Requests\Holding\MarkSignatureParticipantRequest;
use App\Http\Requests\Holding\StoreSignatureRequestRequest;
use App\Models\Contract;
use App\Models\Entity;
use App\Models\Resolution;
use App\Models\ShareholderListSnapshot;
use App\Models\ShareTransaction;
use App\Models\SignatureRequest;
use App\Services\AuditService;
use App\Services\Signature\SignatureServiceInterface;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Digitale Signaturen (Abschnitte 99 bis 102): Anfragen erstellen,
 * versenden, Teilnehmerstatus manuell pflegen (manueller Adapter) und
 * signierte Fassung übernehmen.
 */
class SignatureRequestController extends Controller
{
    /** Whitelist signierbarer Vorgänge: Schlüssel => [Klasse, deutsches Label]. */
    public const SUBJECT_TYPES = [
        'resolution' => [Resolution::class, 'Beschluss'],
        'contract' => [Contract::class, 'Vertrag'],
        'share-transaction' => [ShareTransaction::class, 'Aktienbewegung'],
        'shareholder-list' => [ShareholderListSnapshot::class, 'Aktionärsliste'],
    ];

    /** Unterzeichnerrollen (Abschnitt 101). */
    public const SIGNER_ROLES = [
        'Vorstand', 'Weiteres Vorstandsmitglied', 'Aufsichtsratsvorsitzender',
        'Aufsichtsratsmitglied', 'Aktionär', 'Darlehensgeber', 'Darlehensnehmer',
        'Käufer', 'Verkäufer', 'Sonstiger Unterzeichner',
    ];

    private const OPEN_STATUSES = [
        SignatureRequestStatus::Draft,
        SignatureRequestStatus::Sent,
        SignatureRequestStatus::InProgress,
    ];

    public function __construct(private readonly SignatureServiceInterface $signatures)
    {
    }

    public function index(Request $request)
    {
        $open = SignatureRequest::query()
            ->with(['participants.entity', 'subject', 'document'])
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_STATUSES))
            ->orderByDesc('created_at')
            ->get();

        $closed = SignatureRequest::query()
            ->with(['participants.entity', 'subject', 'document'])
            ->whereNotIn('status', array_map(fn ($s) => $s->value, self::OPEN_STATUSES))
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('signatures.index', [
            'open' => $open,
            'closed' => $closed,
            'subjectTypes' => self::SUBJECT_TYPES,
        ]);
    }

    public function create(Request $request)
    {
        $subject = null;
        $subjectKey = $request->input('subject_type');
        $document = null;
        $prefill = [];

        if ($subjectKey && isset(self::SUBJECT_TYPES[$subjectKey]) && $request->filled('subject_id')) {
            [$class] = self::SUBJECT_TYPES[$subjectKey];
            $subject = $class::query()->find($request->input('subject_id'));
            if ($subject) {
                $document = $this->subjectDocument($subject);
                $prefill = $this->prefillParticipants($subject);
            }
        }

        return view('signatures.create', [
            'subjectTypes' => self::SUBJECT_TYPES,
            'subjectKey' => $subjectKey,
            'subject' => $subject,
            'document' => $document,
            'prefill' => $prefill,
            'roles' => self::SIGNER_ROLES,
            'entities' => Entity::query()->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function store(StoreSignatureRequestRequest $request)
    {
        [$class] = self::SUBJECT_TYPES[$request->validated('subject_type')];
        $subject = $class::query()->find($request->validated('subject_id'));

        if (! $subject) {
            return back()->with('danger', 'Der Vorgang wurde nicht gefunden.')->withInput();
        }

        $document = $this->subjectDocument($subject);
        if (! $document) {
            return back()
                ->with('danger', 'Zum Vorgang ist noch kein PDF hinterlegt. Bitte zuerst das Dokument erzeugen (z. B. Beschluss finalisieren).')
                ->withInput();
        }

        $participants = collect($request->validated('participants'))
            ->filter(fn (array $p) => ! empty($p['entity_id']))
            ->values()
            ->all();

        if ($participants === []) {
            return back()->with('danger', 'Bitte mindestens einen Unterzeichner angeben.')->withInput();
        }

        $signatureRequest = $this->signatures->create($subject, $document, $participants);

        if ($request->boolean('send_immediately')) {
            $this->signatures->send($signatureRequest);
        }

        return redirect()
            ->route('signatures.show', $signatureRequest)
            ->with('success', 'Signaturanfrage wurde erstellt.');
    }

    public function show(SignatureRequest $signatureRequest)
    {
        $signatureRequest->load(['participants.entity', 'subject', 'document', 'creator']);

        return view('signatures.show', [
            'request' => $signatureRequest,
            'subjectTypes' => self::SUBJECT_TYPES,
            'participantStatuses' => SignatureParticipantStatus::cases(),
        ]);
    }

    public function send(SignatureRequest $signatureRequest)
    {
        if ($signatureRequest->status === SignatureRequestStatus::Completed) {
            return redirect()
                ->route('signatures.show', $signatureRequest)
                ->with('warning', 'Die Anfrage ist bereits abgeschlossen.');
        }

        try {
            $this->signatures->send($signatureRequest);
        } catch (\Throwable $e) {
            // Fehler des Anbieters nicht verschlucken: der Bearbeiter muss
            // wissen, dass NICHT versendet wurde und warum.
            return redirect()
                ->route('signatures.show', $signatureRequest)
                ->with('danger', 'Der Versand ist nicht erfolgt: '.$e->getMessage());
        }

        $meldung = $signatureRequest->fresh()->provider === 'docusign'
            ? 'Die Anfrage wurde an DocuSign übergeben und versendet.'
            : 'Die Anfrage wurde als versendet markiert. Beim manuellen Prozess erfolgt der Versand außerhalb des Systems.';

        return redirect()
            ->route('signatures.show', $signatureRequest)
            ->with('success', $meldung);
    }

    /**
     * Status beim Anbieter abfragen (Abschnitt 102). Beim manuellen Weg wird
     * der Gesamtstatus aus den Teilnehmerstatus abgeleitet; bei DocuSign wird
     * der Umschlag abgefragt und bei Abschluss die unterschriebene Fassung
     * übernommen.
     */
    public function sync(SignatureRequest $signatureRequest)
    {
        try {
            $this->signatures->syncStatus($signatureRequest);
        } catch (\Throwable $e) {
            return redirect()
                ->route('signatures.show', $signatureRequest)
                ->with('danger', 'Der Status konnte nicht abgefragt werden: '.$e->getMessage());
        }

        $aktuell = $signatureRequest->fresh();

        return redirect()
            ->route('signatures.show', $signatureRequest)
            ->with('success', 'Status abgefragt. Stand: '.($aktuell->status?->label() ?? 'unbekannt').'.');
    }

    /** Teilnehmerstatus manuell pflegen (Abschnitt 102, manueller Adapter). */
    public function mark(MarkSignatureParticipantRequest $request, SignatureRequest $signatureRequest)
    {
        $participant = $signatureRequest->participants()
            ->whereKey($request->validated('participant_id'))
            ->first();

        if (! $participant) {
            return redirect()
                ->route('signatures.show', $signatureRequest)
                ->with('danger', 'Der Unterzeichner gehört nicht zu dieser Anfrage.');
        }

        $old = $participant->status?->value;
        $participant->update([
            'status' => $request->validated('status'),
            'status_changed_at' => Carbon::now(),
        ]);

        AuditService::log('signatures.participant-marked', $signatureRequest, [
            'participant_id' => $participant->id,
            'status' => $old,
        ], [
            'participant_id' => $participant->id,
            'status' => $request->validated('status'),
        ]);

        $this->signatures->syncStatus($signatureRequest);

        return redirect()
            ->route('signatures.show', $signatureRequest)
            ->with('success', 'Der Unterzeichnerstatus wurde aktualisiert.');
    }

    /** Signierte Fassung hochladen und Vorgang abschließen (Abschnitt 100). */
    public function attachSigned(
        AttachSignedDocumentRequest $request,
        SignatureRequest $signatureRequest,
        DocumentStorageInterface $storage,
    ) {
        if ($signatureRequest->status === SignatureRequestStatus::Completed) {
            return redirect()
                ->route('signatures.show', $signatureRequest)
                ->with('warning', 'Die Anfrage ist bereits abgeschlossen.');
        }

        $file = $request->file('signed_file');

        $document = $storage->store(
            $file,
            'gesellschaft/signaturen',
            $file->getClientOriginalName(),
            [
                'doc_type' => 'signature_protocol',
                'category' => 'Signierte Fassung',
                'description' => sprintf('Signierte Fassung zur Signaturanfrage %d', $signatureRequest->id),
                'uploaded_by' => $request->user()?->id,
            ],
        );

        $this->signatures->attachSignedDocument($signatureRequest, $document);

        return redirect()
            ->route('signatures.show', $signatureRequest)
            ->with('success', 'Die signierte Fassung wurde übernommen. Der Vorgang ist abgeschlossen.');
    }

    /**
     * Zu unterzeichnendes PDF des Vorgangs ermitteln: direktes Dokument
     * (Beschluss, Vertrag, Aktionärsliste) oder verknüpftes PDF
     * (z. B. Vertrag einer Aktienbewegung).
     */
    private function subjectDocument(object $subject): ?\App\Models\Document
    {
        if (method_exists($subject, 'document')) {
            $document = $subject->document()->first();
            if ($document) {
                return $document;
            }
        }

        if ($subject instanceof ShareTransaction) {
            $contractDocument = $subject->contract()->first()?->document()->first();
            if ($contractDocument) {
                return $contractDocument;
            }
        }

        if (method_exists($subject, 'documentLinks')) {
            return $subject->documentLinks()
                ->with('document')
                ->get()
                ->pluck('document')
                ->filter(fn ($d) => $d && $d->mime_type === 'application/pdf')
                ->first();
        }

        return null;
    }

    /** Unterzeichner je Vorgangsart sinnvoll vorbelegen (keine Pflichtvorgabe). */
    private function prefillParticipants(object $subject): array
    {
        if ($subject instanceof Resolution) {
            $subject->loadMissing('participants.entity');

            return $subject->participants
                ->map(fn ($p) => [
                    'entity_id' => $p->entity_id,
                    'name' => $p->entity?->display_name,
                    'role' => $p->role ?: 'Sonstiger Unterzeichner',
                ])
                ->values()
                ->all();
        }

        if ($subject instanceof ShareTransaction) {
            $subject->loadMissing(['buyer.entity', 'seller.entity']);
            $prefill = [];
            if ($subject->seller?->entity) {
                $prefill[] = ['entity_id' => $subject->seller->entity->id, 'name' => $subject->seller->entity->display_name, 'role' => 'Verkäufer'];
            }
            if ($subject->buyer?->entity) {
                $prefill[] = ['entity_id' => $subject->buyer->entity->id, 'name' => $subject->buyer->entity->display_name, 'role' => 'Käufer'];
            }

            return $prefill;
        }

        return [];
    }
}
