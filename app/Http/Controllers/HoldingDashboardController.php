<?php

namespace App\Http\Controllers;

use App\Enums\ResolutionStatus;
use App\Enums\SignatureRequestStatus;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Resolution;
use App\Models\Setting;
use App\Models\ShareTransaction;
use App\Models\SignatureRequest;
use App\Services\Holding\ShareholdingService;
use App\Support\Money;

/**
 * Holding-Dashboard (Abschnitt 106): KPI-Karten und Widgets.
 */
class HoldingDashboardController extends Controller
{
    /** Beschlussstatus, die als "offen" gelten (noch nicht abgeschlossen). */
    private const OPEN_RESOLUTION_STATUSES = [
        ResolutionStatus::Draft,
        ResolutionStatus::Submitted,
        ResolutionStatus::Review,
        ResolutionStatus::Voting,
        ResolutionStatus::ForSignature,
    ];

    private const OPEN_SIGNATURE_STATUSES = [
        SignatureRequestStatus::Draft,
        SignatureRequestStatus::Sent,
        SignatureRequestStatus::InProgress,
    ];

    public function index(ShareholdingService $shareholding)
    {
        $holdings = $shareholding->holdingsAsOf();
        $activeHoldings = $holdings->filter(fn (array $row) => $row['shares'] > 0)->values();

        $kpis = [
            'base_capital' => (string) Setting::get('holding', 'base_capital', '0'),
            'total_shares' => $shareholding->totalShares(),
            'shareholder_count' => $activeHoldings->count(),
            'investment_count' => Investment::query()->where('status', 'active')->count(),
            'open_resolutions' => Resolution::query()
                ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_RESOLUTION_STATUSES))
                ->count(),
            'open_signatures' => SignatureRequest::query()
                ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_SIGNATURE_STATUSES))
                ->count(),
        ];

        // Darlehens-KPIs über die Darlehens-Engine (Modul von Agent B).
        // Absicherung für den Parallelaufbau: Ist die Engine noch nicht
        // ausgeliefert, werden die Kennzahlen als "nicht verfügbar" angezeigt,
        // es werden keine Werte erfunden (Abschnitt 140).
        $loanKpis = null;
        if (class_exists(\App\Services\Loans\LoanBalanceService::class)) {
            $mhagEntityId = Setting::get('holding', 'company_entity_id');
            $loans = Loan::query()
                ->where('lender_entity_id', $mhagEntityId)
                ->whereNotIn('status', ['archived'])
                ->get();

            $balanceService = app(\App\Services\Loans\LoanBalanceService::class);
            $totalReceivable = '0.00';
            $overdue = '0.00';
            $interestOpen = '0.00';
            foreach ($loans as $loan) {
                $balances = $balanceService->balances($loan);
                $totalReceivable = Money::add($totalReceivable, $balances['total_receivable'] ?? '0');
                $overdue = Money::add($overdue, $balances['overdue_amount'] ?? '0');
                $interestOpen = Money::add($interestOpen, $balances['interest_open'] ?? '0');
            }
            $loanKpis = [
                'loan_count' => $loans->count(),
                'total_receivable' => $totalReceivable,
                'overdue_amount' => $overdue,
                'interest_open' => $interestOpen,
            ];
        }

        // Widgets
        $recentTransactions = ShareTransaction::query()
            ->with(['buyer.entity', 'seller.entity'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentResolutions = Resolution::query()
            ->with('company')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $openSignatureRequests = SignatureRequest::query()
            ->with(['participants.entity', 'subject'])
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_SIGNATURE_STATUSES))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Aktionärsstruktur für Chart.js (Donut, Abschnitt 106)
        $chart = [
            'labels' => $activeHoldings->map(fn (array $row) => $row['shareholder']->entity?->display_name)->all(),
            'values' => $activeHoldings->map(fn (array $row) => $row['shares'])->all(),
        ];

        return view('holding.dashboard', [
            'kpis' => $kpis,
            'loanKpis' => $loanKpis,
            'holdings' => $activeHoldings,
            'chart' => $chart,
            'recentTransactions' => $recentTransactions,
            'recentResolutions' => $recentResolutions,
            'openSignatureRequests' => $openSignatureRequests,
        ]);
    }
}
