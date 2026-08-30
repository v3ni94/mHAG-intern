<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\EndOrganizationRoleRequest;
use App\Http\Requests\MasterData\OrganizationRoleRequest;
use App\Http\Requests\MasterData\UpdateOrganizationRoleRequest;
use App\Models\Entity;
use App\Models\OrganizationRole;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;

/**
 * Organstellungen (Abschnitt 7 Masterprompt): Verknüpfung Person/Unternehmen
 * mit Rolle, Vertretungsregel und Historie. Historische Organstellungen
 * werden NIE überschrieben oder gelöscht; Beenden setzt ended_on und
 * is_active=false.
 */
class OrganizationRoleController extends Controller
{
    public function store(OrganizationRoleRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->validated();

        $role = OrganizationRole::create([
            'company_entity_id' => $data['company_entity_id'],
            'person_entity_id' => $data['person_entity_id'],
            'role' => $data['role'],
            'started_on' => $data['started_on'] ?? null,
            'is_active' => true,
            'sole_representation' => $data['sole_representation'] ?? false,
            'representation_rule' => $data['representation_rule'] ?? null,
            'exemption_181' => $data['exemption_181'] ?? false,
            'note' => $data['note'] ?? null,
        ]);

        AuditService::log('entities.organization_role_created', $role, [], [
            'role' => $role->role->value,
            'started_on' => $role->started_on?->toDateString(),
        ], [
            'entity_id' => $entity->id,
            'company_entity_id' => $role->company_entity_id,
            'person_entity_id' => $role->person_entity_id,
        ]);

        return $this->redirectToTab($entity)
            ->with('success', 'Die Organstellung wurde angelegt.');
    }

    public function update(UpdateOrganizationRoleRequest $request, Entity $entity, OrganizationRole $organizationRole): RedirectResponse
    {
        $this->ensureBelongsTo($entity, $organizationRole);

        $data = $request->validated();
        $old = $organizationRole->only(array_keys($data));

        $organizationRole->update($data);

        AuditService::log('entities.organization_role_updated', $organizationRole, $old, $data, [
            'entity_id' => $entity->id,
            'company_entity_id' => $organizationRole->company_entity_id,
            'person_entity_id' => $organizationRole->person_entity_id,
        ]);

        return $this->redirectToTab($entity)
            ->with('success', 'Die Organstellung wurde aktualisiert.');
    }

    /**
     * Organstellung beenden: ended_on setzen, is_active=false.
     * Der Datensatz bleibt vollständig erhalten (Historie).
     */
    public function end(EndOrganizationRoleRequest $request, Entity $entity, OrganizationRole $organizationRole): RedirectResponse
    {
        $this->ensureBelongsTo($entity, $organizationRole);

        if (! $organizationRole->is_active) {
            return $this->redirectToTab($entity)
                ->with('warning', 'Diese Organstellung ist bereits beendet.');
        }

        $old = $organizationRole->only(['ended_on', 'is_active', 'note']);

        $endedOn = $request->validated('ended_on');
        $note = $request->validated('note');

        $organizationRole->update([
            'ended_on' => $endedOn,
            'is_active' => false,
            'note' => $note !== null
                ? trim(($organizationRole->note ? $organizationRole->note."\n" : '').$note)
                : $organizationRole->note,
        ]);

        AuditService::log('entities.organization_role_ended', $organizationRole, $old, [
            'ended_on' => $endedOn,
            'is_active' => false,
        ], [
            'entity_id' => $entity->id,
            'company_entity_id' => $organizationRole->company_entity_id,
            'person_entity_id' => $organizationRole->person_entity_id,
        ]);

        return $this->redirectToTab($entity)
            ->with('success', 'Die Organstellung wurde zum '.format_date($endedOn).' beendet. Die Historie bleibt erhalten.');
    }

    private function ensureBelongsTo(Entity $entity, OrganizationRole $organizationRole): void
    {
        abort_unless(
            (int) $organizationRole->company_entity_id === (int) $entity->id
            || (int) $organizationRole->person_entity_id === (int) $entity->id,
            404,
        );
    }

    private function redirectToTab(Entity $entity): RedirectResponse
    {
        return $entity->type === EntityType::Person
            ? redirect()->route('persons.show', [$entity, 'tab' => 'rollen'])
            : redirect()->route('companies.show', [$entity, 'tab' => 'organe']);
    }
}
