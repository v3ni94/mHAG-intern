<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanBalanceService;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Forderungsaufstellung (Abschnitt 51 Masterprompt): Stichtag wählen,
 * Ausgabe als PDF im CI (gemeinsames Layout pdf.layout).
 */
class LoanStatementController extends Controller
{
    public function show(Request $request, int $loan, LoanBalanceService $balanceService)
    {
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

        AuditService::log('loans.statement_generated', $model, [], [], [
            'as_of' => $asOf->toDateString(),
        ]);

        $pdf = Pdf::loadView('loans.statement-pdf', [
            'loan' => $model,
            'rows' => $rows,
            'total' => $total,
            'asOfDate' => $asOf,
            'documentNumber' => $model->loan_number,
        ]);

        return $pdf->download('Forderungsaufstellung-'.$model->loan_number.'-'.$asOf->format('Y-m-d').'.pdf');
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
