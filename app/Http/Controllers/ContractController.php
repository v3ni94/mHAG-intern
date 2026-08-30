<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreContractRequest;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ContractGenerationService;
use App\Services\NumberSequenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Vertragserstellung mit Snapshot-Prinzip (Abschnitte 54/55 Masterprompt):
 * Ein Vertrag friert die verwendete Vorlagenversion als body_snapshot ein.
 * Bis zur Finalisierung ist der Vertrag deutlich als ENTWURF gekennzeichnet.
 */
class ContractController extends Controller
{
    public function __construct(
        private readonly ContractGenerationService $generator,
    ) {}

    public function index(Request $request)
    {
        $contracts = $this->scopedQuery($request->user())
            ->with(['loan', 'templateVersion.template', 'document'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('contract_number', 'like', $term)->orWhere('title', 'like', $term));
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('contracts.index', ['contracts' => $contracts]);
    }

    public function create(Request $request)
    {
        $templates = ContractTemplate::query()
            ->where('is_active', true)
            ->with('versions')
            ->orderBy('name')
            ->get();

        return view('contracts.create', [
            'templates' => $templates,
            'loans' => Loan::visibleTo($request->user())->orderBy('loan_number')->get(['id', 'loan_number', 'title']),
            'preselectedLoanId' => $request->query('loan_id'),
        ]);
    }

    public function store(StoreContractRequest $request)
    {
        $version = ContractTemplateVersion::with('template')->findOrFail($request->input('contract_template_version_id'));

        $loan = null;
        if ($request->filled('loan_id')) {
            $loan = Loan::visibleTo($request->user())->findOrFail($request->input('loan_id'));
        }

        // Snapshot bei Erstellung: Vorlagenversion + aktuelle Daten einfrieren.
        $data = $loan ? $this->generator->dataForLoan($loan) : [];
        $snapshot = $this->generator->render($version, $data);
        $missing = $this->generator->missingPlaceholders($version, $data);

        $contract = Contract::create([
            'contract_number' => 'ENTWURF-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'loan_id' => $loan?->id,
            'contract_template_version_id' => $version->id,
            'title' => $request->input('title'),
            'body_snapshot' => $snapshot,
            'status' => 'draft',
        ]);

        AuditService::log('contracts.created', $contract, [], [
            'title' => $contract->title,
            'template_version' => $version->version,
            'loan_id' => $loan?->id,
        ]);

        $message = 'Der Vertragsentwurf wurde angelegt.';
        if ($missing !== []) {
            $message .= ' Achtung: Es fehlen noch Werte für folgende Platzhalter: '.implode(', ', $missing).'.';
        }

        return redirect()->route('contracts.show', $contract)->with($missing !== [] ? 'warning' : 'success', $message);
    }

    public function show(Request $request, Contract $contract)
    {
        $contract = $this->scopedQuery($request->user())
            ->with(['loan', 'templateVersion.template', 'amendments', 'document'])
            ->findOrFail($contract->id);

        // Fehlende Platzhalter warnend anzeigen (Abschnitt 55): gegen den
        // aktuellen Datenstand geprüft, solange der Vertrag Entwurf ist.
        $missing = [];
        if ($contract->status === 'draft') {
            $missing = $this->missingForFinalize($contract);
        }

        return view('contracts.show', [
            'contract' => $contract,
            'missing' => $missing,
            'amendmentTypes' => \App\Http\Requests\Documents\StoreContractAmendmentRequest::AMENDMENT_TYPES,
        ]);
    }

    /**
     * Finalisieren (Abschnitt 55): nur ohne fehlende Platzhalter. Vergibt die
     * Vertragsnummer (Nummernkreis VER), friert den Snapshot final ein und
     * erzeugt das PDF über die Dokumentenablage.
     */
    public function finalize(Request $request, Contract $contract)
    {
        $contract = $this->scopedQuery($request->user())->with(['loan', 'templateVersion'])->findOrFail($contract->id);

        if ($contract->status !== 'draft') {
            return back()->withErrors(['contract' => 'Nur Entwürfe können finalisiert werden.']);
        }

        $missing = $this->missingForFinalize($contract);
        if ($missing !== []) {
            return back()->withErrors([
                'contract' => 'Finalisierung nicht möglich. Es fehlen Werte für folgende Platzhalter: '.implode(', ', $missing).'.',
            ]);
        }

        // Snapshot final rendern: Vorlagenversionen sind unveränderlich,
        // daher ist die Neubefüllung mit aktuellen Daten deterministisch.
        if ($contract->templateVersion && $contract->loan) {
            $contract->body_snapshot = $this->generator->render($contract->templateVersion, $this->generator->dataForLoan($contract->loan));
        }

        $number = NumberSequenceService::next('VER', 5);
        $oldNumber = $contract->contract_number;

        $contract->fill([
            'contract_number' => $number,
            'status' => 'final',
            'finalized_at' => now(),
        ])->save();

        AuditService::log('contracts.finalized', $contract, ['contract_number' => $oldNumber, 'status' => 'draft'], [
            'contract_number' => $number,
            'status' => 'final',
        ]);

        try {
            $this->generator->generatePdf($contract);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('contracts.show', $contract)->with(
                'warning',
                'Der Vertrag wurde finalisiert ('.$number.'), das PDF konnte jedoch nicht erzeugt werden: '.$e->getMessage(),
            );
        }

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Der Vertrag wurde finalisiert. Vertragsnummer: '.$number.'. Das PDF wurde in der Dokumentenablage gespeichert.');
    }

    /** PDF-Ausgabe: final aus der Dokumentenablage, Entwürfe als Vorschau mit ENTWURF-Kennzeichnung. */
    public function pdf(Request $request, Contract $contract, \App\Services\Storage\DocumentStorageInterface $storage)
    {
        $contract = $this->scopedQuery($request->user())->with(['loan', 'document'])->findOrFail($contract->id);

        if ($contract->document) {
            // Fehlende Datei ist ein Betriebszustand, kein Programmfehler: sie
            // wird benannt, nicht als Serverfehler ausgeliefert.
            try {
                $contents = $storage->retrieve($contract->document);
            } catch (\RuntimeException $e) {
                return back()->with('danger', 'Das Vertragsdokument ist in der Dokumentenablage '
                    .'nicht auffindbar. Bitte die Ablage prüfen oder das Dokument erneut hochladen.');
            }

            AuditService::log('documents.downloaded', $contract->document, [], ['context' => 'contracts.pdf']);

            return response()->streamDownload(
                fn () => print $contents,
                $contract->document->original_filename,
                ['Content-Type' => 'application/pdf'],
            );
        }

        // Entwurfsvorschau: nicht gespeichert, deutlich gekennzeichnet.
        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'documentNumber' => $contract->contract_number,
            'isDraft' => true,
        ])->setPaper('a4');

        return $pdf->download('ENTWURF-'.$contract->id.'.pdf');
    }

    // ------------------------------------------------------------------

    /** Externe sehen nur Verträge zu Darlehen ihrer zugeordneten Entities. */
    private function scopedQuery(User $user): Builder
    {
        return Contract::query()->when(
            ! $user->isInternal(),
            fn (Builder $q) => $q->whereHas('loan', fn (Builder $lq) => $lq->visibleTo($user)),
        );
    }

    /** Fehlende Platzhalter für die Finalisierung ermitteln. */
    private function missingForFinalize(Contract $contract): array
    {
        if ($contract->templateVersion) {
            $data = $contract->loan ? $this->generator->dataForLoan($contract->loan) : [];

            return $this->generator->missingPlaceholders($contract->templateVersion, $data);
        }

        // Ohne (gelöschte) Vorlagenversion: Snapshot auf offene Platzhalter prüfen.
        return $this->generator->placeholdersInBody((string) $contract->body_snapshot);
    }
}
