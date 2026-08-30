<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractTemplateVersion;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Loan;
use App\Services\Storage\DocumentStorageInterface;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Vertragserzeugung (Abschnitte 52-55 Masterprompt): Platzhalter-Ersetzung,
 * Snapshot-Prinzip (Vorlagenänderungen ändern alte Verträge nie) und
 * PDF-Erzeugung im CI der Müller Holding AG.
 */
class ContractGenerationService
{
    public const PLACEHOLDER_PATTERN = '/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/u';

    public function __construct(
        private readonly DocumentStorageInterface $storage,
    ) {}

    /**
     * {{platzhalter}} im Vorlagen-HTML ersetzen. Werte werden mit e()
     * escaped; unbekannte Platzhalter bleiben sichtbar stehen und können
     * über missingPlaceholders() gemeldet werden.
     */
    public function render(ContractTemplateVersion $version, array $data): string
    {
        return $this->renderBody((string) $version->body, $data);
    }

    /** Platzhalter, für die keine (nicht leeren) Daten vorliegen. */
    public function missingPlaceholders(ContractTemplateVersion $version, array $data): array
    {
        $missing = [];
        foreach ($this->placeholdersInBody((string) $version->body) as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || trim((string) $data[$key]) === '') {
                $missing[] = $key;
            }
        }

        return array_values(array_unique($missing));
    }

    /** Alle im HTML enthaltenen {{platzhalter}}. */
    public function placeholdersInBody(string $body): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Standardplatzhalter aus Darlehen und Vertragsparteien befüllen
     * (Abschnitt 53). Fehlende Angaben werden nicht erfunden; die
     * betroffenen Schlüssel fehlen dann und werden warnend gemeldet.
     */
    public function dataForLoan(Loan $loan): array
    {
        $loan->loadMissing(['lender.addresses', 'lender.person', 'lender.company', 'borrower.addresses', 'borrower.person', 'borrower.company', 'interestTerms', 'securities']);

        $data = [
            'darlehensnummer' => $loan->loan_number,
        ];

        if ($loan->lender) {
            $data['darlehensgeber.name'] = $loan->lender->display_name;
            $data['darlehensgeber.anschrift'] = $loan->lender->primaryAddress()?->oneLine();
        }
        if ($loan->borrower) {
            $data['darlehensnehmer.name'] = $loan->borrower->display_name;
            $data['darlehensnehmer.anschrift'] = $loan->borrower->primaryAddress()?->oneLine();
        }

        if ($loan->principal_amount !== null) {
            $data['darlehensbetrag'] = format_money($loan->principal_amount, $loan->currency ?: 'EUR');
        }

        // Aktueller Zinssatz: heute gültiger Staffelzins-Term, sonst der letzte.
        $today = now()->startOfDay();
        $currentTerm = $loan->interestTerms
            ->first(fn ($t) => $t->valid_from <= $today && ($t->valid_until === null || $t->valid_until >= $today))
            ?? $loan->interestTerms->sortBy('valid_from')->last();
        if ($currentTerm) {
            $data['zinssatz'] = format_percent($currentTerm->rate);
        }

        foreach ([
            'vertragsdatum' => $loan->contract_date,
            'beginn' => $loan->effective_from,
            'ende' => $loan->contract_end,
            'faelligkeit' => $loan->due_date,
            'auszahlungstag' => $loan->disbursement_date,
        ] as $key => $date) {
            if ($date !== null) {
                $data[$key] = format_date($date);
            }
        }

        if ($loan->interest_frequency) {
            $data['zinsfaelligkeit'] = $loan->interest_frequency->label();
        }
        if ($loan->interest_method) {
            $data['zinsmethode'] = $loan->interest_method->label();
        }
        if ($loan->repayment_model) {
            $data['tilgungsregelung'] = $loan->repayment_model->label();
        }

        // Sicherheiten: Typ-Labels (keine rechtliche Bewertung).
        if ($loan->securities->isNotEmpty()) {
            $data['sicherheit'] = $loan->securities
                ->map(fn ($s) => $s->type?->label().($s->nominal_value !== null ? ' über '.format_money($s->nominal_value) : ''))
                ->filter()
                ->implode('; ');
        } else {
            $data['sicherheit'] = 'Keine Sicherheiten erfasst.';
        }

        return array_filter($data, fn ($v) => $v !== null && trim((string) $v) !== '');
    }

    /**
     * PDF im CI erzeugen (Abschnitt 132), über die Dokumentenablage speichern
     * und mit dem Vertrag (und ggf. Darlehen) verknüpfen.
     */
    public function generatePdf(Contract $contract): Document
    {
        $contract->loadMissing('loan');

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'documentNumber' => $contract->contract_number,
        ])->setPaper('a4');

        $directory = $contract->loan
            ? config('documents.folders.loans', 'darlehen').'/'.$contract->loan->loan_number.'/vertraege'
            : config('documents.folders.exports', 'exports');

        $document = $this->storage->store(
            $pdf->output(),
            $directory,
            $contract->contract_number.'.pdf',
            [
                'doc_type' => 'contract',
                'category' => 'Vertrag',
                'document_date' => now()->toDateString(),
                'description' => 'Vertrag '.$contract->contract_number.': '.$contract->title,
            ],
        );

        DocumentLink::firstOrCreate([
            'document_id' => $document->id,
            'linkable_type' => Contract::class,
            'linkable_id' => $contract->id,
        ]);
        if ($contract->loan) {
            DocumentLink::firstOrCreate([
                'document_id' => $document->id,
                'linkable_type' => Loan::class,
                'linkable_id' => $contract->loan->id,
            ]);
        }

        $contract->update(['document_id' => $document->id]);

        return $document;
    }

    /** Snapshot-HTML mit Daten befüllen (interner Renderer). */
    public function renderBody(string $body, array $data): string
    {
        return (string) preg_replace_callback(self::PLACEHOLDER_PATTERN, function (array $m) use ($data) {
            $key = $m[1];
            if (array_key_exists($key, $data) && $data[$key] !== null && trim((string) $data[$key]) !== '') {
                return nl2br(e((string) $data[$key]));
            }

            return $m[0]; // unbekannt: sichtbar stehen lassen (Warnhinweis über missingPlaceholders)
        }, $body);
    }
}
