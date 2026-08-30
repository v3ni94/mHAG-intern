<?php

namespace App\Http\Controllers;

use App\Enums\RepaymentItemStatus;
use App\Models\CorporateBodyMember;
use App\Models\Guarantee;
use App\Models\IdentityDocument;
use App\Models\Loan;
use App\Models\Reminder;
use App\Models\RepaymentPlanItem;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Serverseitiger Monatskalender (Abschnitt 72 Masterprompt).
 * Quellen: Zahlungsplan, Vertragsende/Fälligkeit, Sicherheiten, Bürgschaften,
 * Identitätsdokumente, Organmandate, Beschlüsse, Wiedervorlagen.
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $month = $this->parseMonth($request->query('month'));
        $selectedDay = $this->parseDay($request->query('day'), $month);

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = $this->events($user, $gridStart, $gridEnd);

        // Kalenderwochen für das Grid aufbauen
        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $month->month,
                    'isToday' => $cursor->isToday(),
                    'events' => $events[$key] ?? [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('calendar.index', [
            'month' => $month,
            'weeks' => $weeks,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'selectedDay' => $selectedDay,
            'dayEvents' => $selectedDay ? ($events[$selectedDay->toDateString()] ?? []) : [],
        ]);
    }

    /**
     * Alle Termine im Zeitraum, gruppiert nach Datum (Y-m-d).
     *
     * @return array<string, array<int, array{label: string, type: string, severity: string, url: ?string}>>
     */
    private function events(User $user, Carbon $from, Carbon $to): array
    {
        $events = [];
        $add = function (?\Carbon\CarbonInterface $date, string $type, string $label, string $severity, ?string $url = null) use (&$events, $from, $to) {
            if (! $date || $date->lt($from) || $date->gt($to)) {
                return;
            }
            $events[$date->toDateString()][] = ['type' => $type, 'label' => $label, 'severity' => $severity, 'url' => $url];
        };

        if ($user->can('loans.view')) {
            $loanIds = Loan::visibleTo($user)->pluck('id');

            // Zins-, Tilgungs- und Gebührenfälligkeiten
            RepaymentPlanItem::query()
                ->with('loan:id,loan_number')
                ->whereIn('loan_id', $loanIds)
                ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('status', [RepaymentItemStatus::Cancelled->value, RepaymentItemStatus::Waived->value])
                ->get()
                ->each(function (RepaymentPlanItem $item) use ($add) {
                    $add(
                        $item->due_date,
                        'Fälligkeit',
                        sprintf('%s %s: %s', $item->item_type->label(), $item->loan?->loan_number ?? '', format_money($item->planned_amount)),
                        $item->status->severity(),
                        $this->url('loans.show', $item->loan_id),
                    );
                });

            // Vertragsende und Endfälligkeit
            Loan::visibleTo($user)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('contract_end', [$from->toDateString(), $to->toDateString()])
                        ->orWhereBetween('due_date', [$from->toDateString(), $to->toDateString()]);
                })
                ->get()
                ->each(function (Loan $loan) use ($add) {
                    $add($loan->contract_end, 'Vertragsende', 'Vertragsende '.$loan->loan_number, 'warning', $this->url('loans.show', $loan->id));
                    $add($loan->due_date, 'Endfälligkeit', 'Endfälligkeit '.$loan->loan_number, 'info', $this->url('loans.show', $loan->id));
                });

            // Sicherheiten und Bürgschaften
            Security::query()->whereIn('loan_id', $loanIds)
                ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()])
                ->with('loan:id,loan_number')->get()
                ->each(fn (Security $s) => $add($s->valid_until, 'Sicherheit', sprintf('Sicherheit läuft aus (%s)', $s->loan?->loan_number ?? '-'), 'warning', $this->url('loans.show', $s->loan_id)));
            Guarantee::query()->whereIn('loan_id', $loanIds)
                ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()])
                ->with('loan:id,loan_number')->get()
                ->each(fn (Guarantee $g) => $add($g->valid_until, 'Bürgschaft', sprintf('Bürgschaft läuft aus (%s)', $g->loan?->loan_number ?? '-'), 'warning', $this->url('loans.show', $g->loan_id)));
        }

        if ($user->isInternal()) {
            // Identitätsdokumente
            IdentityDocument::query()->with('entity:id,display_name')
                ->whereBetween('expires_on', [$from->toDateString(), $to->toDateString()])
                ->get()
                ->each(fn (IdentityDocument $d) => $add($d->expires_on, 'Ausweis', sprintf('%s läuft ab: %s', $d->type?->label() ?? 'Dokument', $d->entity?->display_name ?? ''), 'danger', null));

            // Organmandate
            CorporateBodyMember::query()->with(['person:id,display_name', 'body:id,name'])
                ->whereBetween('ended_on', [$from->toDateString(), $to->toDateString()])
                ->get()
                ->each(fn (CorporateBodyMember $m) => $add($m->ended_on, 'Mandat', sprintf('Mandatsende %s (%s)', $m->person?->display_name ?? '', $m->body?->name ?? ''), 'warning', $this->url('corporate-bodies.index')));
        }

        if ($user->can('resolutions.view')) {
            Resolution::query()
                ->whereBetween('resolved_on', [$from->toDateString(), $to->toDateString()])
                ->get()
                ->each(fn (Resolution $r) => $add($r->resolved_on, 'Beschluss', sprintf('Beschluss %s: %s', $r->resolution_number, $r->title), 'info', $this->url('resolutions.show', $r->id)));
        }

        // Wiedervorlagen: intern alle, extern nur eigene
        Reminder::query()
            ->where('status', 'open')
            ->when(! $user->isInternal(), fn ($q) => $q->where('assigned_to', $user->id))
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with('assignee:id,name')
            ->get()
            ->each(fn (Reminder $r) => $add(
                $r->due_date,
                'Wiedervorlage',
                sprintf('%s%s (%s)', $r->title, $r->due_time ? ', '.substr($r->due_time, 0, 5).' Uhr' : '', $r->assignee?->name ?? 'nicht zugewiesen'),
                $r->due_date->isPast() && ! $r->due_date->isToday() ? 'danger' : 'info',
                route('reminders.index'),
            ));

        ksort($events);

        return $events;
    }

    private function parseMonth(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
            } catch (\Throwable) {
                // ungültiger Monat -> aktueller Monat
            }
        }

        return today()->startOfMonth();
    }

    private function parseDay(?string $value, Carbon $month): ?Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function url(string $name, mixed $param = null): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        return $param === null ? route($name) : route($name, $param);
    }
}
