<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Enums\OrganizationRoleType;
use App\Http\Requests\MasterData\StoreCompanyRequest;
use App\Http\Requests\MasterData\UpdateCompanyRequest;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Resolution;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Unternehmensverwaltung als vollwertige Akte (Abschnitte 7 und 104 Masterprompt).
 */
class CompanyController extends Controller
{
    /** Tabs der Unternehmensakte (Abschnitt 104). */
    public const TABS = [
        'uebersicht' => ['label' => 'Übersicht', 'icon' => 'bi-grid'],
        'stammdaten' => ['label' => 'Stammdaten', 'icon' => 'bi-card-text'],
        'adressen' => ['label' => 'Adressen', 'icon' => 'bi-geo-alt'],
        'ansprechpartner' => ['label' => 'Ansprechpartner', 'icon' => 'bi-person-lines-fill'],
        'organe' => ['label' => 'Organe', 'icon' => 'bi-person-badge'],
        'bankkonten' => ['label' => 'Bankkonten', 'icon' => 'bi-bank'],
        'darlehen' => ['label' => 'Darlehen', 'icon' => 'bi-cash-stack'],
        'beteiligungen' => ['label' => 'Beteiligungen', 'icon' => 'bi-pie-chart'],
        'aktien' => ['label' => 'Aktien', 'icon' => 'bi-graph-up-arrow'],
        'vertraege' => ['label' => 'Verträge', 'icon' => 'bi-file-earmark-text'],
        'beschluesse' => ['label' => 'Beschlüsse', 'icon' => 'bi-journal-check'],
        'dokumente' => ['label' => 'Dokumente', 'icon' => 'bi-folder2-open'],
        'historie' => ['label' => 'Historie', 'icon' => 'bi-clock-history'],
    ];

    public function index(Request $request): View
    {
        $query = Entity::query()
            ->visibleTo($request->user())
            ->whereIn('type', [EntityType::Company, EntityType::Organization])
            ->with(['company', 'addresses']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($sub) use ($q) {
                $like = '%'.$q.'%';
                $sub->where('display_name', 'like', $like)
                    ->orWhere('internal_number', 'like', $like)
                    ->orWhereHas('company', function ($c) use ($like) {
                        $c->where('name', 'like', $like)
                            ->orWhere('short_name', 'like', $like)
                            ->orWhere('register_number', 'like', $like)
                            ->orWhere('vat_id', 'like', $like);
                    });
            });
        }

        $status = $request->query('status', 'active');
        if (in_array($status, ['active', 'archived'], true)) {
            $query->where('status', $status);
        }

        return view('companies.index', [
            'entities' => $query->orderBy('display_name')->paginate(25)->withQueryString(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $entity = DB::transaction(function () use ($request) {
            $entity = Entity::create([
                'type' => EntityType::Company,
                'display_name' => $request->validated('name'),
                'status' => 'active',
                'internal_number' => NumberSequenceService::next('UNT'),
                'tags' => $request->tagsArray(),
                'notes' => $request->validated('notes'),
            ]);
            $entity->company()->create($request->companyData());

            // Anschrift aus dem Anlegen-Formular übernehmen (Masterprompt 7)
            if ($adresse = $request->initialAddressData('business')) {
                $entity->addresses()->create($adresse);
            }

            $entity->load('company');

            return $entity;
        });

        AuditService::log('companies.created', $entity, [], [
            'display_name' => $entity->display_name,
            'internal_number' => $entity->internal_number,
        ]);

        return redirect()
            ->route('companies.show', $entity)
            ->with('success', 'Die Unternehmensakte '.$entity->display_name.' wurde angelegt.');
    }

    public function show(Request $request, Entity $entity): View
    {
        $this->ensureAccessible($request, $entity);
        $user = $request->user();

        $tab = (string) $request->query('tab', 'uebersicht');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'uebersicht';
        }

        $entity->load('company');
        $data = $this->loadTabData($entity, $tab, $request);

        return view('companies.show', array_merge($data, [
            'entity' => $entity,
            'tabs' => self::TABS,
            'tab' => $tab,
            'routePrefix' => 'companies',
        ]));
    }

    public function edit(Request $request, Entity $entity): View
    {
        $this->ensureAccessible($request, $entity);
        $entity->load('company');

        return view('companies.edit', ['entity' => $entity]);
    }

    public function update(UpdateCompanyRequest $request, Entity $entity): RedirectResponse
    {
        $this->ensureAccessible($request, $entity);
        $entity->load('company');

        // Ohne Unternehmenssatz ist die Akte unvollstaendig. Frueher brach der
        // Speichervorgang hier mit einem Serverfehler ab.
        abort_if($entity->company === null, 404,
            'Zu diesem Eintrag ist kein Unternehmenssatz hinterlegt. Bitte die Administration verständigen.');

        $oldCompany = $entity->company->only(array_keys($request->companyData()));
        $oldEntity = $entity->only(['tags', 'notes']);

        DB::transaction(function () use ($request, $entity) {
            $entity->company->update($request->companyData());
            $entity->update([
                'tags' => $request->tagsArray(),
                'notes' => $request->user()->isInternal() ? $request->validated('notes') : $entity->notes,
            ]);
            $entity->refreshDisplayName();
        });

        AuditService::log('companies.updated', $entity,
            ['company' => $oldCompany, 'entity' => $oldEntity],
            ['company' => $request->companyData(), 'entity' => $entity->only(['tags', 'notes'])],
        );

        return redirect()
            ->route('companies.show', $entity)
            ->with('success', 'Die Unternehmensakte wurde gespeichert.');
    }

    /** Archivieren bzw. Reaktivieren (kein Löschen). */
    public function archive(Request $request, Entity $entity): RedirectResponse
    {
        $this->ensureAccessible($request, $entity);

        if ($entity->status === 'archived') {
            $entity->update(['status' => 'active']);
            AuditService::log('companies.reactivated', $entity, ['status' => 'archived'], ['status' => 'active']);

            return redirect()->route('companies.show', $entity)
                ->with('success', 'Die Unternehmensakte wurde reaktiviert.');
        }

        $entity->update(['status' => 'archived']);
        AuditService::log('companies.archived', $entity, ['status' => 'active'], ['status' => 'archived']);

        return redirect()->route('companies.index')
            ->with('success', 'Die Unternehmensakte '.$entity->display_name.' wurde archiviert.');
    }

    private function ensureAccessible(Request $request, Entity $entity): void
    {
        abort_unless(in_array($entity->type, [EntityType::Company, EntityType::Organization], true), 404);
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    private function loadTabData(Entity $entity, string $tab, Request $request): array
    {
        $data = [];

        switch ($tab) {
            case 'uebersicht':
                $entity->load(['addresses', 'contactDetails', 'taxDetail', 'bankAccounts'])
                    ->loadCount(['loansAsLender', 'loansAsBorrower', 'documentLinks', 'organizationRolesAsCompany']);
                break;
            case 'stammdaten':
                $entity->load('taxDetail');
                break;
            case 'adressen':
                $entity->load('addresses');
                break;
            case 'ansprechpartner':
                $entity->load(['contactDetails', 'organizationRolesAsCompany' => fn ($q) => $q
                    ->where('role', OrganizationRoleType::ContactPerson)
                    ->with('person')
                    ->orderByDesc('is_active')]);
                $data['personOptions'] = $this->personOptions();
                break;
            case 'organe':
                $entity->load(['organizationRolesAsCompany' => fn ($q) => $q
                    ->with('person')
                    ->orderByDesc('is_active')
                    ->orderBy('started_on')]);
                $data['personOptions'] = $this->personOptions();
                break;
            case 'bankkonten':
                $entity->load('bankAccounts');
                break;
            case 'darlehen':
                $entity->load([
                    'loansAsLender' => fn ($q) => $q->with(['borrower', 'loanType']),
                    'loansAsBorrower' => fn ($q) => $q->with(['lender', 'loanType']),
                ]);
                break;
            case 'beteiligungen':
                $entity->load([
                    'relationshipsAsA' => fn ($q) => $q->with('entityB'),
                    'relationshipsAsB' => fn ($q) => $q->with('entityA'),
                ]);
                $data['investments'] = Investment::query()
                    ->where('company_entity_id', $entity->id)
                    ->latest('acquired_on')
                    ->get();
                $data['companyOptions'] = Entity::query()
                    ->whereIn('type', [EntityType::Company, EntityType::Organization])
                    ->where('status', 'active')
                    ->where('id', '!=', $entity->id)
                    ->orderBy('display_name')
                    ->get(['id', 'display_name']);
                break;
            case 'aktien':
                $entity->load([
                    'shareholder' => fn ($q) => $q->with([
                        'purchases' => fn ($p) => $p->with(['seller.entity', 'buyer.entity'])->orderByDesc('economic_transfer_date'),
                        'sales' => fn ($s) => $s->with(['seller.entity', 'buyer.entity'])->orderByDesc('economic_transfer_date'),
                    ]),
                ]);
                break;
            case 'vertraege':
                $data['contracts'] = Contract::query()
                    ->whereIn('loan_id', Loan::query()
                        ->where('lender_entity_id', $entity->id)
                        ->orWhere('borrower_entity_id', $entity->id)
                        ->select('id'))
                    ->with('loan')
                    ->latest('id')
                    ->limit(100)
                    ->get();
                break;
            case 'beschluesse':
                // Klammerung beachten: ohne die innere Gruppierung wuerde die
                // Sichtbarkeitspruefung nur fuer den zweiten Zweig gelten.
                // Ueber den Antragsteller kann hier ein Beschluss EINER
                // ANDEREN Gesellschaft auftauchen, deshalb ist die Pruefung
                // hier noetig.
                $data['resolutions'] = Resolution::query()
                    ->visibleTo($request->user())
                    ->where(function ($q) use ($entity) {
                        $q->where('company_entity_id', $entity->id)
                            ->orWhere('applicant_entity_id', $entity->id);
                    })
                    ->with('company')
                    ->latest('id')
                    ->limit(100)
                    ->get();
                break;
            case 'dokumente':
                $entity->load(['documentLinks' => fn ($q) => $q->with('document')->latest('id')]);
                break;
            case 'historie':
                $data['auditLogs'] = AuditLog::query()
                    ->with('user')
                    ->where(function ($q) use ($entity) {
                        $q->where(function ($sub) use ($entity) {
                            $sub->where('auditable_type', $entity->getMorphClass())
                                ->where('auditable_id', $entity->id);
                        })
                            ->orWhere('context->entity_id', $entity->id)
                            ->orWhere('context->person_entity_id', $entity->id)
                            ->orWhere('context->company_entity_id', $entity->id);
                    })
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(25)
                    ->withQueryString();
                break;
        }

        return $data;
    }

    private function personOptions(): Collection
    {
        return Entity::query()
            ->where('type', EntityType::Person)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
    }
}
