<?php

namespace App\Http\Controllers;

use App\Enums\DisbursementStatus;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\LoanDisbursement;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Liquiditätsplanung (Abschnitt 71 Masterprompt): erwartete Zinsen,
 * Tilgungen und Gebühren aus offenen Zahlungsplan-Positionen sowie
 * geplante Auszahlungen, je Monat für einen wählbaren Zeitraum.
 */
class LiquidityController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        $preset = (string) $request->query('preset', 'next12');
        [$from, $to] = $this->resolvePeriod($preset, $request->query('from'), $request->query('to'));

        // Erwartete Eingänge: offene SOLL-Positionen (geplant, teilweise, ausgefallen-offen)
        $items = RepaymentPlanItem::query()
            ->whereHas('loan', fn ($q) => $q->visibleTo($user)->inCurrentView($user))
            ->whereDate('due_date', '>=', $from->toDateString())
            ->whereDate('due_date', '<=', $to->toDateString())
            ->whereIn('status', [
                RepaymentItemStatus::Planned->value,
                RepaymentItemStatus::Partial->value,
                RepaymentItemStatus::Missed->value,
            ])
            ->orderBy('due_date')
            ->get();

        // Geplante Auszahlungen (Mittelabflüsse)
        $disbursements = LoanDisbursement::query()
            ->whereHas('loan', fn ($q) => $q->visibleTo($user)->inCurrentView($user))
            ->where('status', DisbursementStatus::Planned->value)
            ->whereDate('planned_date', '>=', $from->toDateString())
            ->whereDate('planned_date', '<=', $to->toDateString())
            ->orderBy('planned_date')
            ->get();

        // Monatsraster aufbauen
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor <= $end) {
            $months[$cursor->format('Y-m')] = [
                'label' => $cursor->translatedFormat('m/Y'),
                'interest' => '0.00',
                'principal' => '0.00',
                'fee' => '0.00',
                'disbursements' => '0.00',
                'net' => '0.00',
            ];
            $cursor->addMonth();
        }

        foreach ($items as $item) {
            $key = $item->due_date->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $bucket = match ($item->item_type) {
                RepaymentItemType::Interest => 'interest',
                RepaymentItemType::Principal => 'principal',
                RepaymentItemType::Fee => 'fee',
            };
            $months[$key][$bucket] = Money::add($months[$key][$bucket], $item->openAmount());
        }

        foreach ($disbursements as $disbursement) {
            $key = $disbursement->planned_date->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $months[$key]['disbursements'] = Money::add($months[$key]['disbursements'], $disbursement->planned_amount);
        }

        $totals = ['interest' => '0.00', 'principal' => '0.00', 'fee' => '0.00', 'disbursements' => '0.00', 'net' => '0.00'];
        foreach ($months as $key => $row) {
            $inflow = Money::add(Money::add($row['interest'], $row['principal']), $row['fee']);
            $months[$key]['net'] = Money::sub($inflow, $row['disbursements']);
            foreach (['interest', 'principal', 'fee', 'disbursements'] as $col) {
                $totals[$col] = Money::add($totals[$col], $row[$col]);
            }
            $totals['net'] = Money::add($totals['net'], $months[$key]['net']);
        }

        // Datenreihen für Chart.js (nur Darstellung; Berechnung bleibt serverseitig)
        $chart = [
            'labels' => array_values(array_map(fn ($m) => $m['label'], $months)),
            'interest' => array_values(array_map(fn ($m) => (float) $m['interest'], $months)),
            'principal' => array_values(array_map(fn ($m) => (float) $m['principal'], $months)),
            'fee' => array_values(array_map(fn ($m) => (float) $m['fee'], $months)),
            'disbursements' => array_values(array_map(fn ($m) => (float) Money::negate($m['disbursements']), $months)),
        ];

        return view('liquidity.index', [
            'months' => $months,
            'totals' => $totals,
            'chart' => $chart,
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'privacyMode' => (bool) $user->privacy_mode,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(string $preset, ?string $from, ?string $to): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [
                $from ? Carbon::parse($from) : $today->copy()->startOfMonth(),
                $to ? Carbon::parse($to) : $today->copy()->addMonths(12)->endOfMonth(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->addMonths(11)->endOfMonth()],
        };
    }
}
