<?php

namespace App\Http\Controllers;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\Loan;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fälligkeiten (Abschnitt 72-nah): überfällige, heute fällige und
 * kommende Zahlungsplan-Positionen der sichtbaren Darlehen.
 */
class DueItemController extends Controller
{
    /** Standardhorizont für kommende Fälligkeiten (Tage). */
    public const DEFAULT_HORIZON_DAYS = 90;

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        $filters = [
            'item_type' => $request->query('item_type'),
            'loan_id' => $request->query('loan_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $base = fn () => RepaymentPlanItem::with('loan')
            ->whereHas('loan', fn ($q) => $q->visibleTo($user))
            ->when($filters['item_type'], fn ($q, $type) => $q->where('item_type', $type))
            ->when($filters['loan_id'], fn ($q, $id) => $q->where('loan_id', $id));

        $today = now()->toDateString();
        $horizon = $filters['to'] ?: now()->addDays(self::DEFAULT_HORIZON_DAYS)->toDateString();

        // Überfällig: nicht oder nur teilweise bezahlte Positionen der Vergangenheit
        $overdue = $base()
            ->whereDate('due_date', '<', $today)
            ->whereIn('status', [RepaymentItemStatus::Missed->value, RepaymentItemStatus::Partial->value])
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
            ->orderBy('due_date')
            ->get();

        // Heute fällig
        $dueToday = $base()
            ->whereDate('due_date', $today)
            ->whereIn('status', [
                RepaymentItemStatus::Planned->value,
                RepaymentItemStatus::Assumed->value,
                RepaymentItemStatus::Partial->value,
                RepaymentItemStatus::Missed->value,
            ])
            ->orderBy('due_date')
            ->get();

        // Kommend: geplante Positionen bis zum Horizont
        $upcoming = $base()
            ->whereDate('due_date', '>', $today)
            ->whereDate('due_date', '<=', $horizon)
            ->whereIn('status', [RepaymentItemStatus::Planned->value, RepaymentItemStatus::Assumed->value])
            ->orderBy('due_date')
            ->paginate(25)
            ->withQueryString();

        return view('due-items.index', [
            'overdue' => $overdue,
            'dueToday' => $dueToday,
            'upcoming' => $upcoming,
            'filters' => $filters,
            'horizon' => $horizon,
            'itemTypes' => RepaymentItemType::cases(),
            'loans' => Loan::visibleTo($user)->orderBy('loan_number')->get(['id', 'loan_number', 'title']),
        ]);
    }
}
