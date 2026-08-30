<?php

namespace App\Http\Controllers;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\CorporateBodyMember;
use App\Models\Guarantee;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\RepaymentPlanItem;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Support\Money;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Reports mit Export (Abschnitte 107 und 108 Masterprompt).
 * Filter werden in alle Exportformate übernommen; Datenscope über visibleTo.
 */
class ReportController extends Controller
{
    /** Verfügbare Reports: key => [Titel, Beschreibung, Icon, Zusatz-Permission|null]. */
    public const REPORTS = [
        'darlehensbestand' => ['Darlehensbestandsliste', 'Alle sichtbaren Darlehen mit offenem Kapital und Gesamtforderung.', 'bi-cash-stack', 'loans.view'],
        'offene-posten' => ['Offene Posten', 'Offene und überfällige Positionen aus dem Zahlungsplan.', 'bi-exclamation-circle', 'loans.view'],
        'zinsen-soll-ist' => ['Zinsen Soll/Ist', 'Sollzinsen, bestätigte und systemseitig angenommene Zahlungen je Darlehen und Jahr.', 'bi-percent', 'loans.view'],
        'faelligkeiten' => ['Fälligkeiten', 'Kommende Zins-, Tilgungs- und Gebührenfälligkeiten.', 'bi-calendar-check', 'loans.view'],
        'ueberfaellige-darlehen' => ['Überfällige Darlehen', 'Darlehen mit erfassten Zahlungsausfällen oder Teilzahlungen.', 'bi-exclamation-octagon', 'loans.view'],
        'ertrag-rendite' => ['Ertrag und Rendite', 'Belegter Ertrag, durchschnittlich gebundenes Kapital, Rendite und Effektivrendite je Darlehen.', 'bi-graph-up-arrow', 'loans.view'],
        'sicherheiten' => ['Sicherheiten und Bürgschaften', 'Bestellte Sicherheiten und Bürgschaften mit Laufzeiten.', 'bi-shield-check', 'loans.view'],
        'darlehen-je-kreditgeber' => ['Darlehen je Kreditgeber', 'Anzahl und Volumen gruppiert nach Darlehensgeber.', 'bi-people', 'loans.view'],
        'darlehen-je-kreditnehmer' => ['Darlehen je Kreditnehmer', 'Anzahl und Volumen gruppiert nach Darlehensnehmer.', 'bi-person-lines-fill', 'loans.view'],
        'aktionaersliste' => ['Aktionärsliste', 'Aktienbestand und Quote je Aktionär, stichtagsfähig.', 'bi-pie-chart', 'shares.view'],
        'aktienbewegungen' => ['Aktienbewegungen', 'Alle Aktienbewegungen mit Status und wirtschaftlichem Übergang.', 'bi-arrow-repeat', 'shares.view'],
        'beteiligungen' => ['Beteiligungsübersicht', 'Beteiligungen der Müller Holding AG.', 'bi-diagram-3', 'shares.view'],
        'beschlussregister' => ['Beschlussregister', 'Beschlüsse mit Art, Datum, Ergebnis und Status.', 'bi-journal-check', 'resolutions.view'],
        'organhistorie' => ['Organhistorie', 'Vorstands- und Aufsichtsratsmandate inklusive beendeter Mandate.', 'bi-person-badge', 'shares.view'],
    ];

    public function __construct(
        private readonly \App\Services\Loans\LoanBalanceService $balanceService,
        private readonly \App\Services\Loans\LoanYieldService $yieldService,
    ) {
    }

    public function index(Request $request): \Illuminate\View\View
    {
        $user = $request->user();
        $reports = collect(self::REPORTS)
            ->filter(fn (array $def) => $def[3] === null || $user->can($def[3]));

        return view('reports.index', ['reports' => $reports]);
    }

    public function show(Request $request, string $key): mixed
    {
        abort_unless(array_key_exists($key, self::REPORTS), 404);

        $user = $request->user();
        $definition = self::REPORTS[$key];
        if ($definition[3] !== null) {
            abort_unless($user->can($definition[3]), 403);
        }

        $report = $this->build($key, $request, $user);

        $format = $request->query('format');
        if (in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            return $this->export($key, $report, $format);
        }

        return view('reports.show', [
            'key' => $key,
            'title' => $definition[0],
            'description' => $definition[1],
            'report' => $report,
        ]);
    }

    /**
     * Report aufbauen: title, columns (deutsche Header), rows (formatiert),
     * filters (aktive Filterwerte für die View).
     *
     * @return array{columns: array<int, string>, rows: array<int, array<int, string>>, filters: array<string, mixed>, hint: ?string}
     */
    private function build(string $key, Request $request, User $user): array
    {
        return match ($key) {
            'darlehensbestand' => $this->loanPortfolio($request, $user),
            'offene-posten' => $this->openItems($request, $user),
            'zinsen-soll-ist' => $this->interestTargetActual($request, $user),
            'faelligkeiten' => $this->dueDates($request, $user),
            'ueberfaellige-darlehen' => $this->overdueLoans($request, $user),
            'ertrag-rendite' => $this->yieldReport($request, $user),
            'sicherheiten' => $this->securities($request, $user),
            'darlehen-je-kreditgeber' => $this->loansByParty($request, $user, 'lender'),
            'darlehen-je-kreditnehmer' => $this->loansByParty($request, $user, 'borrower'),
            'aktionaersliste' => $this->shareholderList($request),
            'aktienbewegungen' => $this->shareTransactions($request),
            'beteiligungen' => $this->investments($request),
            'beschlussregister' => $this->resolutionRegister($request),
            'organhistorie' => $this->bodyHistory($request),
        };
    }

    // ------------------------------------------------------------------
    // Darlehen
    // ------------------------------------------------------------------

    /**
     * Ertrag und Rendite je Darlehen (Anforderung vom 30.08.2026).
     *
     * Belegter Ertrag und Ertrag einschliesslich systemseitiger Annahmen
     * werden getrennt ausgewiesen (Abschnitt 24). Nicht ermittelbare
     * Effektivrenditen werden als "nicht berechenbar" gekennzeichnet, es wird
     * keine Zahl erfunden.
     */
    private function yieldReport(Request $request, User $user): array
    {
        $status = $request->query('status');
        // Der abgesicherte Helfer, wie in den anderen Reports: ein
        // unpruefbarer Stichtag aus der Adresszeile darf den Report nicht
        // mit einem Serverfehler beenden.
        $asOf = $this->date($request->query('as_of')) ?? today();

        $loans = Loan::visibleTo($user)->inCurrentView($user)
            ->with(['lender:id,display_name', 'borrower:id,display_name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('loan_number')
            ->get();

        $prozent = fn (?string $wert) => $wert === null
            ? 'nicht berechenbar'
            : number_format((float) $wert, 4, ',', '.').' %';

        $rows = $loans->map(function (Loan $loan) use ($asOf, $prozent) {
            $yield = $this->yieldService->analyse($loan, $asOf);

            return [
                $loan->loan_number,
                (string) $loan->title,
                $loan->borrower?->display_name ?? '',
                $loan->status?->label() ?? '',
                format_money($yield['interest_confirmed']),
                format_money($yield['interest_capitalized']),
                format_money($yield['fees_confirmed']),
                format_money($yield['yield_confirmed']),
                format_money($yield['yield_assumed']),
                format_money($yield['average_capital']),
                $prozent($yield['return_pa']),
                $prozent($yield['irr']),
            ];
        })->all();

        return [
            'columns' => [
                'Darlehensnummer', 'Titel', 'Darlehensnehmer', 'Status',
                'Vereinnahmte Zinsen', 'Kapitalisierte Zinsen', 'Vereinnahmte Gebühren',
                'Ertrag belegt', 'davon nur angenommen', 'Durchschnittlich gebundenes Kapital',
                'Rendite p. a.', 'Effektivrendite p. a.',
            ],
            'rows' => $rows,
            'filters' => ['status' => $status, 'as_of' => $asOf->toDateString()],
            'hint' => 'Belegter Ertrag umfasst bestätigte Zahlungen und dem Kapital zugeschriebene Zinsen. '
                .'Systemseitig angenommene Zahlungen sind gesondert ausgewiesen und nicht im belegten Ertrag enthalten. '
                .'Die Effektivrendite ist eine rechnerische Kennzahl aus den erfassten Zahlungsströmen, keine Bewertung.',
        ];
    }

    private function loanPortfolio(Request $request, User $user): array
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $loans = Loan::visibleTo($user)->inCurrentView($user)
            ->with(['lender:id,display_name', 'borrower:id,display_name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('loan_number', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')))
            ->orderBy('loan_number')
            ->get();

        $rows = $loans->map(function (Loan $loan) {
            $balances = $this->balanceService->balances($loan);

            return [
                $loan->loan_number,
                (string) $loan->title,
                $loan->lender?->display_name ?? '',
                $loan->borrower?->display_name ?? '',
                $loan->status?->label() ?? '',
                format_date($loan->effective_from),
                format_money($loan->principal_amount),
                format_money($balances['principal_outstanding'] ?? '0.00'),
                format_money($balances['interest_open'] ?? '0.00'),
                format_money($balances['total_receivable'] ?? '0.00'),
            ];
        })->all();

        return [
            'columns' => ['Darlehensnummer', 'Titel', 'Darlehensgeber', 'Darlehensnehmer', 'Status', 'Wirkungsbeginn', 'Darlehensbetrag', 'Offenes Kapital', 'Offene Zinsen', 'Gesamtforderung'],
            'rows' => $rows,
            'filters' => ['status' => $status, 'search' => $search],
            'hint' => null,
        ];
    }

    private function openItems(Request $request, User $user): array
    {
        $until = $this->date($request->query('bis')) ?? today();
        $type = $request->query('typ');

        $items = RepaymentPlanItem::query()
            ->with('loan:id,loan_number,title')
            ->whereIn('loan_id', Loan::visibleTo($user)->inCurrentView($user)->pluck('id'))
            ->whereDate('due_date', '<=', $until)
            ->whereIn('status', [
                RepaymentItemStatus::Planned->value,
                RepaymentItemStatus::Missed->value,
                RepaymentItemStatus::Partial->value,
            ])
            ->when($type, fn ($q) => $q->where('item_type', $type))
            ->orderBy('due_date')
            ->get();

        $rows = $items->map(fn (RepaymentPlanItem $item) => [
            $item->loan?->loan_number ?? '',
            $item->item_type->label(),
            format_date($item->due_date),
            format_money($item->planned_amount),
            $item->actual_amount !== null ? format_money($item->actual_amount) : '',
            format_money($item->expectedAmount()),
            $item->status->label(),
        ])->all();

        return [
            'columns' => ['Darlehensnummer', 'Art', 'Fällig am', 'Sollbetrag', 'Istbetrag', 'Noch zu zahlen', 'Status'],
            'rows' => $rows,
            'filters' => ['bis' => $until->toDateString(), 'typ' => $type],
            'hint' => 'Berücksichtigt Positionen mit Status Geplant, Nicht bezahlt und Teilweise bezahlt bis zum gewählten Datum.',
        ];
    }

    private function interestTargetActual(Request $request, User $user): array
    {
        $year = (int) ($request->query('jahr') ?: today()->year);
        $from = Carbon::create($year)->startOfYear();
        $to = Carbon::create($year)->endOfYear();

        $loans = Loan::visibleTo($user)->inCurrentView($user)->with(['lender:id,display_name', 'borrower:id,display_name'])->orderBy('loan_number')->get();
        $items = RepaymentPlanItem::query()
            ->whereIn('loan_id', $loans->pluck('id'))
            ->where('item_type', RepaymentItemType::Interest->value)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('loan_id');

        $rows = [];
        foreach ($loans as $loan) {
            $loanItems = $items->get($loan->id, collect());
            if ($loanItems->isEmpty()) {
                continue;
            }
            $target = '0.00';
            $confirmed = '0.00';
            $assumed = '0.00';
            foreach ($loanItems as $item) {
                if (in_array($item->status, [RepaymentItemStatus::Cancelled, RepaymentItemStatus::Waived], true)) {
                    continue;
                }
                $target = Money::add($target, $item->planned_amount);
                if ($item->status === RepaymentItemStatus::Assumed) {
                    $assumed = Money::add($assumed, $item->planned_amount);
                } elseif (in_array($item->status, [RepaymentItemStatus::Confirmed, RepaymentItemStatus::Late, RepaymentItemStatus::Partial], true)) {
                    $confirmed = Money::add($confirmed, $item->actual_amount ?? '0.00');
                }
            }
            $open = Money::sub(Money::sub($target, $confirmed), $assumed);
            if (Money::isNegative($open)) {
                $open = '0.00';
            }
            $rows[] = [
                $loan->loan_number,
                $loan->lender?->display_name ?? '',
                $loan->borrower?->display_name ?? '',
                format_money($target),
                format_money($confirmed),
                format_money($assumed),
                format_money($open),
            ];
        }

        return [
            'columns' => ['Darlehensnummer', 'Darlehensgeber', 'Darlehensnehmer', 'Zinsen Soll '.$year, 'Ist bestätigt', 'Systemseitig angenommen', 'Offen'],
            'rows' => $rows,
            'filters' => ['jahr' => $year],
            'hint' => 'Systemseitig angenommene Zahlungen sind keine bestätigten Zahlungen und werden getrennt ausgewiesen.',
        ];
    }

    private function dueDates(Request $request, User $user): array
    {
        $from = $this->date($request->query('von')) ?? today();
        $to = $this->date($request->query('bis')) ?? today()->copy()->addMonths(3);
        $type = $request->query('typ');

        $items = RepaymentPlanItem::query()
            ->with('loan:id,loan_number')
            ->whereIn('loan_id', Loan::visibleTo($user)->inCurrentView($user)->pluck('id'))
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [RepaymentItemStatus::Cancelled->value, RepaymentItemStatus::Waived->value])
            ->when($type, fn ($q) => $q->where('item_type', $type))
            ->orderBy('due_date')
            ->get();

        $rows = $items->map(fn (RepaymentPlanItem $item) => [
            format_date($item->due_date),
            $item->loan?->loan_number ?? '',
            $item->item_type->label(),
            format_money($item->planned_amount),
            $item->status->label(),
        ])->all();

        return [
            'columns' => ['Fällig am', 'Darlehensnummer', 'Art', 'Sollbetrag', 'Status'],
            'rows' => $rows,
            'filters' => ['von' => $from->toDateString(), 'bis' => $to->toDateString(), 'typ' => $type],
            'hint' => null,
        ];
    }

    private function overdueLoans(Request $request, User $user): array
    {
        $overdueItems = RepaymentPlanItem::query()
            ->with('loan.lender:id,display_name', 'loan.borrower:id,display_name')
            ->whereIn('loan_id', Loan::visibleTo($user)->inCurrentView($user)->pluck('id'))
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [RepaymentItemStatus::Missed->value, RepaymentItemStatus::Partial->value])
            ->get()
            ->groupBy('loan_id');

        $rows = [];
        foreach ($overdueItems as $items) {
            /** @var Collection<int, RepaymentPlanItem> $items */
            $loan = $items->first()->loan;
            if (! $loan) {
                continue;
            }
            $open = '0.00';
            foreach ($items as $item) {
                $open = Money::add($open, $item->expectedAmount());
            }
            $rows[] = [
                $loan->loan_number,
                $loan->lender?->display_name ?? '',
                $loan->borrower?->display_name ?? '',
                $loan->status?->label() ?? '',
                (string) $items->count(),
                format_date($items->min('due_date')),
                format_money($open),
            ];
        }

        return [
            'columns' => ['Darlehensnummer', 'Darlehensgeber', 'Darlehensnehmer', 'Status', 'Überfällige Positionen', 'Älteste Fälligkeit', 'Offener Betrag'],
            'rows' => $rows,
            'filters' => [],
            'hint' => 'Nur tatsächlich erfasste Ausfälle und Teilzahlungen (keine systemseitigen Annahmen).',
        ];
    }

    private function securities(Request $request, User $user): array
    {
        $status = $request->query('status');
        $loanIds = Loan::visibleTo($user)->inCurrentView($user)->pluck('id');

        $rows = [];
        Security::query()->with(['loan:id,loan_number', 'provider:id,display_name'])
            ->whereIn('loan_id', $loanIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('valid_until')
            ->get()
            ->each(function (Security $s) use (&$rows) {
                $rows[] = [
                    'Sicherheit',
                    $s->type?->label() ?? '',
                    $s->loan?->loan_number ?? '',
                    $s->provider?->display_name ?? '',
                    format_money($s->nominal_value),
                    format_date($s->valid_from),
                    format_date($s->valid_until),
                    ucfirst((string) $s->status),
                ];
            });
        Guarantee::query()->with(['loan:id,loan_number', 'guarantor:id,display_name'])
            ->whereIn('loan_id', $loanIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('valid_until')
            ->get()
            ->each(function (Guarantee $g) use (&$rows) {
                $rows[] = [
                    'Bürgschaft',
                    (string) ($g->guarantee_type ?: 'Bürgschaft'),
                    $g->loan?->loan_number ?? '',
                    $g->guarantor?->display_name ?? '',
                    format_money($g->max_amount),
                    format_date($g->valid_from),
                    format_date($g->valid_until),
                    ucfirst((string) $g->status),
                ];
            });

        return [
            'columns' => ['Kategorie', 'Art', 'Darlehensnummer', 'Sicherungsgeber', 'Nominal/Höchstbetrag', 'Gültig ab', 'Gültig bis', 'Status'],
            'rows' => $rows,
            'filters' => ['status' => $status],
            'hint' => null,
        ];
    }

    private function loansByParty(Request $request, User $user, string $side): array
    {
        $relation = $side === 'lender' ? 'lender' : 'borrower';

        $loans = Loan::visibleTo($user)->inCurrentView($user)->with([$relation.':id,display_name'])->get();

        $rows = $loans->groupBy(fn (Loan $l) => $l->{$relation}?->display_name ?? 'Unbekannt')
            ->map(function (Collection $group, string $name) {
                $volume = '0.00';
                $outstanding = '0.00';
                foreach ($group as $loan) {
                    $volume = Money::add($volume, $loan->principal_amount);
                    $balances = $this->balanceService->balances($loan);
                    $outstanding = Money::add($outstanding, $balances['principal_outstanding'] ?? '0.00');
                }

                return [$name, (string) $group->count(), format_money($volume), format_money($outstanding)];
            })
            ->sortBy(fn (array $row) => $row[0])
            ->values()
            ->all();

        return [
            'columns' => [$side === 'lender' ? 'Darlehensgeber' : 'Darlehensnehmer', 'Anzahl Darlehen', 'Darlehensvolumen', 'Offenes Kapital'],
            'rows' => $rows,
            'filters' => [],
            'hint' => null,
        ];
    }

    // ------------------------------------------------------------------
    // Holding
    // ------------------------------------------------------------------

    private function shareholderList(Request $request): array
    {
        $asOf = $this->date($request->query('stichtag')) ?? today();

        /** @var \App\Services\Holding\ShareholdingService $shareholding */
        $shareholding = app(\App\Services\Holding\ShareholdingService::class);
        $holdings = $shareholding->holdingsAsOf($asOf);

        $rows = collect($holdings)->map(function (array $entry) {
            /** @var \App\Models\Shareholder $shareholder */
            $shareholder = $entry['shareholder'];

            return [
                (string) $shareholder->shareholder_number,
                $shareholder->entity?->display_name ?? '',
                number_format((int) $entry['shares'], 0, ',', '.'),
                format_percent($entry['percentage'], 4),
            ];
        })->all();

        return [
            'columns' => ['Aktionärsnummer', 'Aktionär', 'Aktien', 'Quote'],
            'rows' => $rows,
            'filters' => ['stichtag' => $asOf->toDateString()],
            'hint' => 'Bestand aus wirksamen Aktienbewegungen (wirtschaftlicher Übergang bis zum Stichtag).',
        ];
    }

    private function shareTransactions(Request $request): array
    {
        $from = $this->date($request->query('von'));
        $to = $this->date($request->query('bis'));
        $status = $request->query('status');

        $transactions = ShareTransaction::query()
            ->with(['seller.entity:id,display_name', 'buyer.entity:id,display_name'])
            ->when($from, fn ($q) => $q->whereDate('economic_transfer_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('economic_transfer_date', '<=', $to))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('economic_transfer_date')
            ->get();

        $rows = $transactions->map(fn (ShareTransaction $t) => [
            (string) $t->transaction_number,
            $t->type?->label() ?? '',
            $t->seller?->entity?->display_name ?? '',
            $t->buyer?->entity?->display_name ?? '',
            number_format((int) $t->share_count, 0, ',', '.'),
            $t->total_price !== null ? format_money($t->total_price) : '',
            format_date($t->economic_transfer_date),
            $t->status?->label() ?? '',
        ])->all();

        return [
            'columns' => ['Nummer', 'Art', 'Verkäufer', 'Käufer', 'Stückzahl', 'Gesamtpreis', 'Wirtschaftlicher Übergang', 'Status'],
            'rows' => $rows,
            'filters' => ['von' => $from?->toDateString(), 'bis' => $to?->toDateString(), 'status' => $status],
            'hint' => null,
        ];
    }

    private function investments(Request $request): array
    {
        $status = $request->query('status');

        $investments = Investment::query()
            ->with('company:id,display_name')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('company_entity_id')
            ->get();

        $rows = $investments->map(fn (Investment $i) => [
            $i->company?->display_name ?? '',
            format_percent($i->share_percentage, 2),
            $i->share_count !== null ? number_format((int) $i->share_count, 0, ',', '.') : '',
            format_date($i->acquired_on),
            format_money($i->acquisition_cost),
            $i->current_value !== null ? format_money($i->current_value) : '',
            ucfirst((string) $i->status),
        ])->all();

        return [
            'columns' => ['Unternehmen', 'Quote', 'Anteile', 'Erworben am', 'Anschaffungskosten', 'Aktueller Wert', 'Status'],
            'rows' => $rows,
            'filters' => ['status' => $status],
            'hint' => null,
        ];
    }

    private function resolutionRegister(Request $request): array
    {
        $year = $request->query('jahr');
        $type = $request->query('typ');
        $status = $request->query('status');

        $resolutions = Resolution::query()
            ->with('company:id,display_name')
            ->when($year, fn ($q) => $q->whereYear('resolved_on', $year))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('resolved_on')
            ->orderByDesc('id')
            ->get();

        $rows = $resolutions->map(fn (Resolution $r) => [
            (string) $r->resolution_number,
            (string) $r->title,
            $r->type?->label() ?? '',
            $r->company?->display_name ?? '',
            format_date($r->resolved_on),
            $r->result ? ucfirst((string) $r->result) : '',
            $r->status?->label() ?? '',
        ])->all();

        return [
            'columns' => ['Beschlussnummer', 'Titel', 'Art', 'Gesellschaft', 'Beschlossen am', 'Ergebnis', 'Status'],
            'rows' => $rows,
            'filters' => ['jahr' => $year, 'typ' => $type, 'status' => $status],
            'hint' => null,
        ];
    }

    private function bodyHistory(Request $request): array
    {
        $bodyType = $request->query('gremium');
        $onlyActive = $request->boolean('nur_aktive');

        $members = CorporateBodyMember::query()
            ->with(['body:id,name,type', 'person:id,display_name'])
            ->when($bodyType, fn ($q) => $q->whereHas('body', fn ($sub) => $sub->where('type', $bodyType)))
            ->when($onlyActive, fn ($q) => $q->where('status', 'active'))
            ->orderBy('corporate_body_id')
            ->orderBy('started_on')
            ->get();

        $rows = $members->map(fn (CorporateBodyMember $m) => [
            $m->body?->name ?? '',
            $m->person?->display_name ?? '',
            (string) $m->role,
            $m->is_chair ? 'Ja' : 'Nein',
            format_date($m->started_on),
            $m->ended_on ? format_date($m->ended_on) : 'laufend',
            $m->status === 'active' ? 'Aktiv' : 'Beendet',
        ])->all();

        return [
            'columns' => ['Gremium', 'Person', 'Funktion', 'Vorsitz', 'Beginn', 'Ende', 'Status'],
            'rows' => $rows,
            'filters' => ['gremium' => $bodyType, 'nur_aktive' => $onlyActive],
            'hint' => 'Mandate werden nie gelöscht; beendete Mandate bleiben mit Enddatum erhalten.',
        ];
    }

    // ------------------------------------------------------------------
    // Export (Abschnitt 108): CSV, XLSX, PDF; Filter werden übernommen.
    // ------------------------------------------------------------------

    private function export(string $key, array $report, string $format): mixed
    {
        $filename = 'report-'.$key.'-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return $this->csv($filename.'.csv', $report['columns'], $report['rows']);
        }

        if ($format === 'xlsx') {
            return SimpleXlsxWriter::download($filename.'.xlsx', $report['columns'], $report['rows'], self::REPORTS[$key][0]);
        }

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => self::REPORTS[$key][0],
            'report' => $report,
            'generatedAt' => now(),
        ])->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait');

        return $pdf->download($filename.'.pdf');
    }

    /** CSV: Semikolon-getrennt, UTF-8 mit BOM, deutsche Header. */
    private function csv(string $filename, array $columns, array $rows): \Illuminate\Http\Response
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
