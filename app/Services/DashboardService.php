<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Enums\ResolutionStatus;
use App\Models\Guarantee;
use App\Models\IdentityDocument;
use App\Models\Loan;
use App\Models\LoanRecalculation;
use App\Models\LoginAttempt;
use App\Models\Reminder;
use App\Models\RepaymentPlanItem;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Dashboard und Controlling (Abschnitte 68, 69, 74, 136 Masterprompt).
 * Externe Benutzer sehen ausschließlich Kennzahlen ihrer eigenen Darlehen
 * (Datenscope über Loan::visibleTo).
 */
class DashboardService
{
    public function __construct(
        private readonly \App\Services\Loans\LoanBalanceService $balanceService,
    ) {
    }

    /**
     * "Heute relevant" (Abschnitt 74): Liste aus severity, icon (Emoji),
     * text und url.
     *
     * @return array<int, array{severity: string, icon: string, text: string, url: ?string}>
     */
    public function todayRelevant(User $user): array
    {
        $today = today();
        $items = [];
        $loanIds = Loan::visibleTo($user)->inCurrentView($user)->pluck('id');

        // Überfällige Zahlungen: nur tatsächlich erfasste Ausfälle/Teilzahlungen
        $overdueCount = RepaymentPlanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('due_date', '<', $today)
            ->whereIn('status', [RepaymentItemStatus::Missed->value, RepaymentItemStatus::Partial->value])
            ->count();
        if ($overdueCount > 0) {
            $items[] = $this->entry('danger', sprintf('%d %s überfällig', $overdueCount, $overdueCount === 1 ? 'Zahlung' : 'Zahlungen'), $this->url('due-items.index'));
        }

        // Heute fällige Zahlungen (SOLL, noch nicht bestätigt)
        $dueTodayCount = RepaymentPlanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('due_date', $today)
            ->whereIn('status', [RepaymentItemStatus::Planned->value, RepaymentItemStatus::Assumed->value])
            ->count();
        if ($dueTodayCount > 0) {
            $items[] = $this->entry('warning', sprintf('%d %s heute fällig', $dueTodayCount, $dueTodayCount === 1 ? 'Zahlung' : 'Zahlungen'), $this->url('due-items.index'));
        }

        // Verträge enden innerhalb von 14 Tagen
        $endingContracts = Loan::visibleTo($user)->inCurrentView($user)
            ->whereNotNull('contract_end')
            ->whereDate('contract_end', '>=', $today)
            ->whereDate('contract_end', '<=', $today->copy()->addDays(14))
            ->count();
        if ($endingContracts > 0) {
            $items[] = $this->entry('warning', sprintf('%d %s in den nächsten 14 Tagen', $endingContracts, $endingContracts === 1 ? 'Vertrag endet' : 'Verträge enden'), $this->url('loans.index'));
        }

        // Beschlüsse warten auf Unterschrift (nur mit Berechtigung)
        if ($user->can('resolutions.view')) {
            $forSignature = Resolution::query()->where('status', ResolutionStatus::ForSignature->value)->count();
            if ($forSignature > 0) {
                $items[] = $this->entry('warning', sprintf('%d %s auf Unterschrift', $forSignature, $forSignature === 1 ? 'Beschluss wartet' : 'Beschlüsse warten'), $this->url('resolutions.index'));
            }
        }

        // Identitätsdokumente (nur intern)
        if ($user->isInternal()) {
            $expired = IdentityDocument::query()->whereNotNull('expires_on')->whereDate('expires_on', '<', $today)->count();
            if ($expired > 0) {
                $items[] = $this->entry('danger', sprintf('%d %s abgelaufen', $expired, $expired === 1 ? 'Identitätsdokument ist' : 'Identitätsdokumente sind'), $this->url('persons.index'));
            }
            $expiring = IdentityDocument::query()
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '>=', $today)
                ->whereDate('expires_on', '<=', $today->copy()->addDays(30))
                ->count();
            if ($expiring > 0) {
                $items[] = $this->entry('warning', sprintf('%d %s innerhalb von 30 Tagen ab', $expiring, $expiring === 1 ? 'Identitätsdokument läuft' : 'Identitätsdokumente laufen'), $this->url('persons.index'));
            }
        }

        // Offene Wiedervorlagen des Benutzers (fällig oder überfällig)
        $openReminders = Reminder::query()
            ->where('status', 'open')
            ->where('assigned_to', $user->id)
            ->whereDate('due_date', '<=', $today)
            ->count();
        if ($openReminders > 0) {
            $items[] = $this->entry('warning', sprintf('%d %s offen', $openReminders, $openReminders === 1 ? 'Wiedervorlage' : 'Wiedervorlagen'), $this->url('reminders.index'));
        }

        // Ablaufende Sicherheiten und Bürgschaften (30 Tage)
        $expiringSecurities = Security::query()
            ->whereIn('loan_id', $loanIds)
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $today->copy()->addDays(30))
            ->count();
        $expiringSecurities += Guarantee::query()
            ->whereIn('loan_id', $loanIds)
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $today->copy()->addDays(30))
            ->count();
        if ($expiringSecurities > 0) {
            $items[] = $this->entry('warning', sprintf('%d %s aus', $expiringSecurities, $expiringSecurities === 1 ? 'Sicherheit läuft' : 'Sicherheiten laufen'), $this->url('securities.index'));
        }

        if ($items === []) {
            $items[] = $this->entry('info', 'Keine dringenden Vorgänge. Alles im Plan.', null);
        }

        return $items;
    }

    /**
     * KPI-Karten (Abschnitt 68), aggregiert über sichtbare Darlehen.
     *
     * @return array<string, array{label: string, value: string, severity: ?string, hint: ?string, money: bool}>
     */
    public function loanKpis(User $user): array
    {
        $today = today();
        $loans = Loan::visibleTo($user)->inCurrentView($user)
            ->whereNotIn('status', [LoanStatus::Draft->value, LoanStatus::Archived->value])
            ->get();
        $loanIds = $loans->pluck('id');

        $sum = [
            'disbursed' => '0.00', 'principal_outstanding' => '0.00', 'interest_confirmed' => '0.00',
            'interest_open' => '0.00', 'fees_open' => '0.00', 'overdue_amount' => '0.00', 'total_receivable' => '0.00',
        ];
        foreach ($loans as $loan) {
            $balances = $this->balanceService->balances($loan);
            foreach ($sum as $key => $value) {
                $sum[$key] = Money::add($value, $balances[$key] ?? '0.00');
            }
        }

        $planned = fn (RepaymentItemType $type, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to) => (string) RepaymentPlanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->where('item_type', $type->value)
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->sum('planned_amount');

        $interestCurrentYear = $planned(RepaymentItemType::Interest, $today->copy()->startOfYear(), $today->copy()->endOfYear());
        $interestNext12 = $planned(RepaymentItemType::Interest, $today, $today->copy()->addMonths(12));
        $principalNext12 = $planned(RepaymentItemType::Principal, $today, $today->copy()->addMonths(12));

        $activeStatuses = [LoanStatus::Active->value, LoanStatus::PartiallyRepaid->value];
        $activeCount = $loans->whereIn('status.value', $activeStatuses)->count();

        $overdueLoanCount = RepaymentPlanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('due_date', '<', $today)
            ->whereIn('status', [RepaymentItemStatus::Missed->value, RepaymentItemStatus::Partial->value])
            ->distinct()
            ->count('loan_id');

        return [
            'total_portfolio' => ['label' => 'Gesamtportfolio (Forderung)', 'value' => $sum['total_receivable'], 'severity' => null, 'hint' => 'Gesamtforderung aller sichtbaren Darlehen', 'money' => true],
            'disbursed' => ['label' => 'Ursprünglich verliehen', 'value' => $sum['disbursed'], 'severity' => null, 'hint' => 'Summe der Auszahlungen', 'money' => true],
            'outstanding' => ['label' => 'Aktuell offen (Kapital)', 'value' => $sum['principal_outstanding'], 'severity' => 'info', 'hint' => null, 'money' => true],
            'interest_year' => ['label' => 'Zinsen laufendes Jahr (Soll)', 'value' => $interestCurrentYear, 'severity' => null, 'hint' => 'Sollzinsen '.$today->year, 'money' => true],
            'interest_received' => ['label' => 'Erhaltene Zinsen (bestätigt)', 'value' => $sum['interest_confirmed'], 'severity' => 'success', 'hint' => 'Nur bestätigte Zahlungen', 'money' => true],
            'interest_open' => ['label' => 'Offene Zinsen', 'value' => $sum['interest_open'], 'severity' => Money::isPositive($sum['interest_open']) ? 'warning' : null, 'hint' => null, 'money' => true],
            'fees_open' => ['label' => 'Offene Gebühren', 'value' => $sum['fees_open'], 'severity' => null, 'hint' => null, 'money' => true],
            'interest_next12' => ['label' => 'Erwartete Zinsen 12 Monate', 'value' => $interestNext12, 'severity' => null, 'hint' => 'Soll der nächsten 12 Monate', 'money' => true],
            'principal_next12' => ['label' => 'Tilgungen 12 Monate', 'value' => $principalNext12, 'severity' => null, 'hint' => 'Soll der nächsten 12 Monate', 'money' => true],
            'overdue_amount' => ['label' => 'Überfälliges Kapital', 'value' => $sum['overdue_amount'], 'severity' => Money::isPositive($sum['overdue_amount']) ? 'danger' : 'success', 'hint' => null, 'money' => true],
            'active_loans' => ['label' => 'Aktive Darlehen', 'value' => (string) $activeCount, 'severity' => null, 'hint' => null, 'money' => false],
            'overdue_loans' => ['label' => 'Überfällige Darlehen', 'value' => (string) $overdueLoanCount, 'severity' => $overdueLoanCount > 0 ? 'danger' : 'success', 'hint' => 'Mit erfassten Zahlungsausfällen', 'money' => false],
        ];
    }

    /**
     * Datenreihen für Chart.js (Abschnitt 69).
     *
     * @return array<string, mixed>
     */
    public function charts(User $user): array
    {
        $today = today();
        $loans = Loan::visibleTo($user)->inCurrentView($user)
            ->whereNotIn('status', [LoanStatus::Draft->value, LoanStatus::Archived->value])
            ->with(['lender', 'borrower'])
            ->get();
        $loanIds = $loans->pluck('id');

        $byLender = $loans->groupBy(fn (Loan $l) => $l->lender?->display_name ?? 'Unbekannt')
            ->map(fn ($group) => round((float) $group->sum(fn (Loan $l) => (float) $l->principal_amount), 2))
            ->sortDesc()
            ->take(10);

        $byBorrower = $loans->groupBy(fn (Loan $l) => $l->borrower?->display_name ?? 'Unbekannt')
            ->map(fn ($group) => round((float) $group->sum(fn (Loan $l) => (float) $l->principal_amount), 2))
            ->sortDesc()
            ->take(10);

        $byStatus = $loans->groupBy(fn (Loan $l) => $l->status?->label() ?? 'Unbekannt')
            ->map->count();

        // Soll-Cashflows der nächsten 12 Monate aus dem Zahlungsplan
        $months = [];
        $cursor = $today->copy()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $months[$cursor->format('Y-m')] = $cursor->locale('de')->isoFormat('MMM YY');
            $cursor->addMonth();
        }

        $cashflowRows = RepaymentPlanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('due_date', '>=', $today->copy()->startOfMonth())
            ->whereDate('due_date', '<', $today->copy()->startOfMonth()->addMonths(12))
            ->whereNotIn('status', [RepaymentItemStatus::Cancelled->value, RepaymentItemStatus::Waived->value])
            ->get(['due_date', 'item_type', 'planned_amount']);

        $interestSeries = array_fill_keys(array_keys($months), 0.0);
        $principalSeries = array_fill_keys(array_keys($months), 0.0);
        foreach ($cashflowRows as $row) {
            $key = $row->due_date->format('Y-m');
            if (! array_key_exists($key, $interestSeries)) {
                continue;
            }
            if ($row->item_type === RepaymentItemType::Interest) {
                $interestSeries[$key] += (float) $row->planned_amount;
            } elseif ($row->item_type === RepaymentItemType::Principal) {
                $principalSeries[$key] += (float) $row->planned_amount;
            }
        }

        return [
            'volume_by_lender' => ['labels' => $byLender->keys()->values(), 'values' => $byLender->values()],
            'volume_by_borrower' => ['labels' => $byBorrower->keys()->values(), 'values' => $byBorrower->values()],
            'loans_by_status' => ['labels' => $byStatus->keys()->values(), 'values' => $byStatus->values()],
            'cashflow_12m' => [
                'labels' => array_values($months),
                'interest' => array_values(array_map(fn ($v) => round($v, 2), $interestSeries)),
                'principal' => array_values(array_map(fn ($v) => round($v, 2), $principalSeries)),
            ],
        ];
    }

    /**
     * Administrator-Zusatzblock (Abschnitt 136): Systemzustand kompakt.
     *
     * @return array<string, mixed>
     */
    public function adminOverview(): array
    {
        $backup = Setting::get('backup', 'last_run');

        return [
            'open_invitations' => UserInvitation::query()
                ->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->count(),
            'failed_logins_24h' => LoginAttempt::query()
                ->where('successful', false)
                ->where('created_at', '>=', now()->subDay())->count(),
            'open_jobs' => (int) DB::table('jobs')->count(),
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            'last_backup' => is_array($backup) ? $backup : null,
            'recalculation_errors' => LoanRecalculation::query()->where('status', 'error')
                ->latest('created_at')->limit(5)->get(),
            'sftp_status' => Setting::get('sftp', 'last_test'),
            'disk_free' => @disk_free_space(storage_path()) ?: null,
        ];
    }

    private function entry(string $severity, string $text, ?string $url): array
    {
        return [
            'severity' => $severity,
            'icon' => match ($severity) {
                'danger' => '🔴',
                'warning' => '🟠',
                default => '🔵',
            },
            'text' => $text,
            'url' => $url,
        ];
    }

    /** Routen anderer Module defensiv verlinken. */
    private function url(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }
}
