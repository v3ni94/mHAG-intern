<?php

namespace App\Http\Controllers;

use App\Models\DocumentLink;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Storage\DocumentStorageInterface;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Forderungsaufstellung (Abschnitte 39/51 Masterprompt): Stichtag wählen,
 * Ausgabe als PDF im CI (gemeinsames Layout pdf.layout).
 *
 * Jede erzeugte Aufstellung wird als unveränderlicher Snapshot im
 * Dokumentenmodul abgelegt (Abschnitt 39): Ablage über die
 * DocumentStorageInterface-Pipeline unter darlehen/{Darlehensnummer}/sonstiges,
 * Verknüpfung mit dem Darlehen, SHA-256 der Datei. Bestehende Snapshots
 * werden nie überschrieben; eine spätere Korrektur der Daten verändert eine
 * früher erzeugte Aufstellung deshalb nicht.
 */
class LoanStatementController extends Controller
{
    /** Kategorie der Snapshots im Dokumentenmodul. */
    public const SNAPSHOT_CATEGORY = 'Forderungsaufstellung';

    public function show(
        Request $request,
        int $loan,
        LoanBalanceService $balanceService,
        DocumentStorageInterface $storage,
    ) {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        $validated = $request->validate(
            ['date' => ['nullable', 'date']],
            ['date.date' => 'Der Stichtag muss ein gültiges Datum sein.'],
        );

        $model = Loan::visibleTo($user)
            ->with(['lender', 'borrower'])
            ->findOrFail($loan);

        $asOf = isset($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today();

        $result = $balanceService->statementRows($model, $asOf);
        [$rows, $total] = $this->normalizeStatement($result);

        $pdfContent = Pdf::loadView('loans.statement-pdf', [
            'loan' => $model,
            'rows' => $rows,
            'total' => $total,
            'asOfDate' => $asOf,
            'documentNumber' => $model->loan_number,
        ])->output();

        $filename = 'Forderungsaufstellung-'.$model->loan_number.'-'.$asOf->format('Y-m-d').'.pdf';

        // Snapshot ablegen (Abschnitt 39). Schlägt die Ablage fehl, wird die
        // Aufstellung trotzdem ausgeliefert; der Fehler wird protokolliert.
        $document = null;
        try {
            $document = $storage->store($pdfContent, $this->directoryFor($model), $filename, [
                'doc_type' => 'other',
                'category' => self::SNAPSHOT_CATEGORY,
                'document_date' => $asOf->toDateString(),
                'description' => sprintf(
                    'Forderungsaufstellung zum %s, Gesamtforderung %s',
                    $asOf->format('d.m.Y'),
                    Money::format($total, $model->currency ?: 'EUR'),
                ),
                'uploaded_by' => $user->id,
            ]);

            DocumentLink::firstOrCreate([
                'document_id' => $document->id,
                'linkable_type' => $model->getMorphClass(),
                'linkable_id' => $model->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Forderungsaufstellung konnte nicht abgelegt werden.', [
                'loan_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }

        AuditService::log('loans.statement_generated', $model, [], [], [
            'as_of' => $asOf->toDateString(),
            'total' => $total,
            'document_id' => $document?->id,
            'sha256' => $document?->sha256,
        ]);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdfContent),
        ]);
    }

    /** Ablagestruktur gem. Abschnitt 61: darlehen/{Nummer}/sonstiges */
    private function directoryFor(Loan $loan): string
    {
        $folders = config('documents.folders');

        return ($folders['loans'] ?? 'darlehen').'/'.$loan->loan_number.'/sonstiges';
    }

    /**
     * Ergebnis des LoanBalanceService in eine einheitliche Struktur bringen:
     * Zeilen [label, amount, sign] und Gesamtsumme.
     *
     * @return array{0: array<int, array{label: string, amount: string, sign: string}>, 1: string}
     */
    private function normalizeStatement(array $result): array
    {
        $rawRows = $result['rows'] ?? $result;
        $total = $result['total'] ?? $result['sum'] ?? null;

        $rows = [];
        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = [
                'label' => (string) ($row['label'] ?? $row[0] ?? ''),
                'amount' => Money::normalize($row['amount'] ?? $row[1] ?? '0'),
                'sign' => (string) ($row['sign'] ?? $row[2] ?? '+'),
            ];
        }

        if ($total === null) {
            $total = '0.00';
            foreach ($rows as $row) {
                if ($row['sign'] === '-') {
                    $total = Money::sub($total, $row['amount']);
                } elseif ($row['sign'] !== '=') {
                    $total = Money::add($total, $row['amount']);
                }
            }
        }

        return [$rows, Money::normalize($total)];
    }
}
