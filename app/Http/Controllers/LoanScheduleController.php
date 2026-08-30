<?php

namespace App\Http\Controllers;

use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Http\Requests\Loans\UpdateScheduleItemRequest;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\ScheduleActualService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Soll/Ist-Erfassung je Zahlungsplan-Position (Abschnitte 26-28 Masterprompt).
 * Setzt IST-Betrag, Status und Herkunft (manuell bestätigt), bucht die
 * Wirkung im Darlehenskonto (Abschnitte 29/48: Tilgung senkt das Kapital,
 * Zins- und Gebührenzahlungen mindern die Forderung) und stößt anschließend
 * die Neuberechnung ab dem Fälligkeitsdatum an.
 * manually_adjusted wird NUR bei einer SOLL-Änderung gesetzt.
 */
class LoanScheduleController extends Controller
{
    public function update(
        UpdateScheduleItemRequest $request,
        int $item,
        LoanRecalculationService $recalculationService,
        ScheduleActualService $scheduleActualService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        $planItem = RepaymentPlanItem::with('loan')
            ->whereHas('loan', fn ($q) => $q->visibleTo($user))
            ->findOrFail($item);
        $loan = $planItem->loan;

        $data = $request->validated();
        $status = RepaymentItemStatus::from($data['status']);

        $old = [
            'planned_amount' => (string) $planItem->planned_amount,
            'actual_amount' => $planItem->actual_amount !== null ? (string) $planItem->actual_amount : null,
            'status' => $planItem->status->value,
            'origin' => $planItem->origin?->value,
        ];

        // IST-Betrag: bei "nicht bezahlt"/"erlassen" 0, sonst erfasster Betrag
        $actualAmount = in_array($status, [RepaymentItemStatus::Missed, RepaymentItemStatus::Waived], true)
            ? '0.00'
            : Money::normalize($data['actual_amount']);

        $attributes = [
            'actual_amount' => $actualAmount,
            'status' => $status,
            'origin' => PaymentOrigin::ManualConfirmed,
            'actual_date' => $data['actual_date'] ?? null,
            'value_date' => $data['value_date'] ?? null,
            'comment' => $data['comment'] ?? $planItem->comment,
        ];

        // SOLL-Änderung: nur dann manually_adjusted setzen (Vorgabe Bauplan)
        if (array_key_exists('planned_amount', $data) && $data['planned_amount'] !== null
            && Money::cmp($data['planned_amount'], $planItem->planned_amount) !== 0) {
            $attributes['planned_amount'] = Money::normalize($data['planned_amount']);
            $attributes['manually_adjusted'] = true;
        }

        $planItem->update($attributes);

        // Wirkung im Darlehenskonto herstellen (Abschnitt 29): erfüllte
        // Tilgung senkt das Kapital, Zins-/Gebührenzahlung mindert die
        // Forderung. Rücknahme oder Korrektur wirkt per Gegenbuchung;
        // bereits über eine Zahlung verrechnete Beträge werden nicht
        // doppelt gebucht.
        $booking = $scheduleActualService->reconcile(
            $planItem,
            $user,
            'Korrektur der Zahlungsplan-Position vom '.format_date($planItem->due_date),
        );

        AuditService::log('loans.schedule_item_updated', $planItem, $old, [
            'planned_amount' => (string) $planItem->planned_amount,
            'actual_amount' => (string) $planItem->actual_amount,
            'status' => $planItem->status->value,
            'origin' => $planItem->origin?->value,
        ], [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'due_date' => $planItem->due_date?->toDateString(),
            'booking_id' => $booking?->id,
            'booking_amount' => $booking ? (string) $booking->amount : null,
        ]);

        // Neuberechnung ab dem frühesten betroffenen Datum (Abschnitt 35):
        // Wirkungsdatum der Zahlung oder Fälligkeit, je nachdem was früher liegt.
        $earliest = collect([
            $planItem->due_date?->toDateString(),
            $planItem->actual_date?->toDateString(),
        ])->filter()->min();

        $recalculationService->recalculate(
            $loan,
            'schedule.actual_recorded',
            $earliest ? Carbon::parse($earliest) : null,
            $user,
        );

        return redirect()
            ->route('loans.show', [$loan, 'tab' => $request->input('return_tab', 'soll-ist')])
            ->with('success', 'Position vom '.format_date($planItem->due_date).' wurde aktualisiert.');
    }
}
