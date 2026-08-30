<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\StorePersonRequest;
use App\Http\Requests\MasterData\UpdatePersonRequest;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\ResolutionParticipant;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Personenverwaltung als vollwertige Akte (Abschnitte 6 und 103 Masterprompt).
 */
class PersonController extends Controller
{
    /** Tabs der Personenakte (Abschnitt 103). */
    public const TABS = [
        'uebersicht' => ['label' => 'Übersicht', 'icon' => 'bi-grid'],
        'stammdaten' => ['label' => 'Stammdaten', 'icon' => 'bi-card-text'],
        'adressen' => ['label' => 'Adressen', 'icon' => 'bi-geo-alt'],
        'kontakt' => ['label' => 'Kontakt', 'icon' => 'bi-envelope'],
        'steuerdaten' => ['label' => 'Steuerdaten', 'icon' => 'bi-percent'],
        'bankkonten' => ['label' => 'Bankkonten', 'icon' => 'bi-bank'],
        'identitaet' => ['label' => 'Identität', 'icon' => 'bi-person-vcard'],
        'rollen' => ['label' => 'Rollen', 'icon' => 'bi-person-badge'],
        'unternehmen' => ['label' => 'Unternehmen', 'icon' => 'bi-building'],
        'darlehen' => ['label' => 'Darlehen', 'icon' => 'bi-cash-stack'],
        'aktien' => ['label' => 'Aktien', 'icon' => 'bi-graph-up-arrow'],
        'beschluesse' => ['label' => 'Beschlüsse', 'icon' => 'bi-journal-check'],
        'dokumente' => ['label' => 'Dokumente', 'icon' => 'bi-folder2-open'],
        'notizen' => ['label' => 'Notizen', 'icon' => 'bi-sticky'],
        'historie' => ['label' => 'Historie', 'icon' => 'bi-clock-history'],
    ];

    public function index(Request $request): View
    {
        $query = Entity::query()
            ->visibleTo($request->user())
            ->where('type', EntityType::Person)
            ->with(['person', 'addresses', 'contactDetails']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($sub) use ($q) {
                $like = '%'.$q.'%';
                $sub->where('display_name', 'like', $like)
                    ->orWhere('internal_number', 'like', $like)
                    ->orWhereHas('person', function ($p) use ($like) {
                        $p->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('birth_name', 'like', $like);
                    });
            });
        }

        $status = $request->query('status', 'active');
        if (in_array($status, ['active', 'archived'], true)) {
            $query->where('status', $status);
        }

        return view('persons.index', [
            'entities' => $query->orderBy('display_name')->paginate(25)->withQueryString(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('persons.create');
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        $entity = DB::transaction(function () use ($request) {
            $entity = Entity::create([
                'type' => EntityType::Person,
                'display_name' => trim($request->validated('first_name').' '.$request->validated('last_name')),
                'status' => 'active',
                'internal_number' => NumberSequenceService::next('PER'),
                'tags' => $request->tagsArray(),
                'notes' => $request->validated('notes'),
            ]);
            $entity->person()->create($request->personData());

            // Anschrift aus dem Anlegen-Formular übernehmen (Masterprompt 6)
            if ($adresse = $request->initialAddressData('main')) {
                $entity->addresses()->create($adresse);
            }
            $entity->load('person');
            $entity->refreshDisplayName();

            return $entity;
        });

        AuditService::log('persons.created', $entity, [], [
            'display_name' => $entity->display_name,
            'internal_number' => $entity->internal_number,
        ]);

        return redirect()
            ->route('persons.show', $entity)
            ->with('success', 'Die Personenakte '.$entity->display_name.' wurde angelegt.');
    }

    public function show(Request $request, Entity $entity): View
    {
        $this->ensureAccessible($request, $entity);
        $user = $request->user();

        $tabs = self::TABS;
        if (! $user->isInternal()) {
            unset($tabs['notizen']);
        }

        $tab = (string) $request->query('tab', 'uebersicht');
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'uebersicht';
        }

        $entity->load('person');
        $data = $this->loadTabData($entity, $tab, $request);

        return view('persons.show', array_merge($data, [
            'entity' => $entity,
            'tabs' => $tabs,
            'tab' => $tab,
            'routePrefix' => 'persons',
        ]));
    }

    public function edit(Request $request, Entity $entity): View
    {
        $this->ensureAccessible($request, $entity);
        $entity->load('person');

        return view('persons.edit', ['entity' => $entity]);
    }

    public function update(UpdatePersonRequest $request, Entity $entity): RedirectResponse
    {
        $this->ensureAccessible($request, $entity);
        $entity->load('person');

        // Ohne Personensatz ist die Akte unvollstaendig. Frueher brach der
        // Speichervorgang hier mit einem Serverfehler ab.
        abort_if($entity->person === null, 404,
            'Zu diesem Eintrag ist kein Personensatz hinterlegt. Bitte die Administration verständigen.');

        $oldPerson = $entity->person->only(array_keys($request->personData()));
        $oldEntity = $entity->only(['tags', 'notes']);

        DB::transaction(function () use ($request, $entity) {
            $entity->person->update($request->personData());
            $entity->update([
                'tags' => $request->tagsArray(),
                'notes' => $request->user()->isInternal() ? $request->validated('notes') : $entity->notes,
            ]);
            $entity->refreshDisplayName();
        });

        AuditService::log('persons.updated', $entity,
            ['person' => $oldPerson, 'entity' => $oldEntity],
            ['person' => $request->personData(), 'entity' => $entity->only(['tags', 'notes'])],
        );

        return redirect()
            ->route('persons.show', $entity)
            ->with('success', 'Die Personenakte wurde gespeichert.');
    }

    /** Archivieren bzw. Reaktivieren (kein Löschen, Abschnitt 103). */
    public function archive(Request $request, Entity $entity): RedirectResponse
    {
        $this->ensureAccessible($request, $entity);

        if ($entity->status === 'archived') {
            $entity->update(['status' => 'active']);
            AuditService::log('persons.reactivated', $entity, ['status' => 'archived'], ['status' => 'active']);

            return redirect()->route('persons.show', $entity)
                ->with('success', 'Die Personenakte wurde reaktiviert.');
        }

        $entity->update(['status' => 'archived']);
        AuditService::log('persons.archived', $entity, ['status' => 'active'], ['status' => 'archived']);

        return redirect()->route('persons.index')
            ->with('success', 'Die Personenakte '.$entity->display_name.' wurde archiviert.');
    }

    /** Sichtbarkeit und Aktentyp prüfen (externe Benutzer: nur zugeordnete Entities). */
    private function ensureAccessible(Request $request, Entity $entity): void
    {
        abort_unless($entity->type === EntityType::Person, 404);
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    /** Daten je aktivem Tab nachladen (verhindert unnötige Abfragen und Lazy Loading). */
    private function loadTabData(Entity $entity, string $tab, Request $request): array
    {
        $data = [];

        switch ($tab) {
            case 'uebersicht':
                $entity->load(['addresses', 'contactDetails', 'taxDetail', 'bankAccounts', 'identityDocuments'])
                    ->loadCount(['loansAsLender', 'loansAsBorrower', 'documentLinks', 'organizationRolesAsPerson']);
                break;
            case 'adressen':
                $entity->load('addresses');
                break;
            case 'kontakt':
                $entity->load('contactDetails');
                break;
            case 'steuerdaten':
                $entity->load('taxDetail');
                break;
            case 'bankkonten':
                $entity->load('bankAccounts');
                break;
            case 'identitaet':
                $entity->load(['identityDocuments' => fn ($q) => $q->with('verifier')->orderByDesc('expires_on')]);
                break;
            case 'rollen':
            case 'unternehmen':
                $entity->load(['organizationRolesAsPerson' => fn ($q) => $q->with('company')->orderByDesc('is_active')->orderBy('started_on')]);
                // Auswahlliste nach Sichtbarkeit: ohne visibleTo enthielt sie
                // auch ausgeschlossene Gesellschaften und legte damit deren
                // Namen offen.
                $data['companyOptions'] = Entity::query()
                    ->visibleTo($request->user())
                    ->whereIn('type', [EntityType::Company, EntityType::Organization])
                    ->where('status', 'active')
                    ->orderBy('display_name')
                    ->get(['id', 'display_name']);
                break;
            case 'darlehen':
                $entity->load([
                    'loansAsLender' => fn ($q) => $q->with(['borrower', 'loanType']),
                    'loansAsBorrower' => fn ($q) => $q->with(['lender', 'loanType']),
                ]);
                break;
            case 'aktien':
                $entity->load([
                    'shareholder' => fn ($q) => $q->with([
                        'purchases' => fn ($p) => $p->with(['seller.entity', 'buyer.entity'])->orderByDesc('economic_transfer_date'),
                        'sales' => fn ($s) => $s->with(['seller.entity', 'buyer.entity'])->orderByDesc('economic_transfer_date'),
                    ]),
                ]);
                break;
            case 'beschluesse':
                $data['resolutionParticipations'] = ResolutionParticipant::query()
                    ->where('entity_id', $entity->id)
                    ->with(['resolution.company'])
                    ->latest('id')
                    ->limit(100)
                    ->get();
                break;
            case 'dokumente':
                $entity->load(['documentLinks' => fn ($q) => $q->with('document')->latest('id')]);
                break;
            case 'historie':
                $data['auditLogs'] = $this->auditLogsFor($entity);
                break;
        }

        return $data;
    }

    private function auditLogsFor(Entity $entity): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuditLog::query()
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
    }
}
