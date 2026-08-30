<?php

namespace App\Http\Controllers;

use App\Http\Requests\MasterData\EntityRelationshipRequest;
use App\Models\Entity;
use App\Models\EntityRelationship;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Unternehmensbeziehungen (Abschnitt 8 Masterprompt): Mutter, Tochter,
 * Schwester, Beteiligung, Joint Venture, verbundenes Unternehmen.
 */
class EntityRelationshipController extends Controller
{
    public function store(EntityRelationshipRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->validated();

        $relationship = EntityRelationship::create([
            'entity_a_id' => $entity->id,
            'entity_b_id' => $data['entity_b_id'],
            'relationship_type' => $data['relationship_type'],
            'share_percentage' => $data['share_percentage'] ?? null,
            'share_count' => $data['share_count'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        AuditService::log('entities.relationship_created', $relationship, [], $data, [
            'entity_id' => $entity->id,
            'company_entity_id' => $entity->id,
        ]);

        return redirect()->route('companies.show', [$entity, 'tab' => 'beteiligungen'])
            ->with('success', 'Die Unternehmensbeziehung wurde gespeichert.');
    }

    public function update(EntityRelationshipRequest $request, Entity $entity, EntityRelationship $relationship): RedirectResponse
    {
        $this->ensureBelongsTo($entity, $relationship);

        // Die beteiligten Unternehmen bleiben unverändert; angepasst werden
        // Beziehungsart, Quote, Anteile, Zeitraum und Bemerkung.
        $data = collect($request->validated())
            ->only(['relationship_type', 'share_percentage', 'share_count', 'valid_from', 'valid_until', 'note'])
            ->all();
        $old = $relationship->only(array_keys($data));

        $relationship->update($data);

        AuditService::log('entities.relationship_updated', $relationship, $old, $data, [
            'entity_id' => $entity->id,
            'company_entity_id' => $entity->id,
        ]);

        return redirect()->route('companies.show', [$entity, 'tab' => 'beteiligungen'])
            ->with('success', 'Die Unternehmensbeziehung wurde aktualisiert.');
    }

    public function destroy(Request $request, Entity $entity, EntityRelationship $relationship): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
        $this->ensureBelongsTo($entity, $relationship);

        $old = $relationship->toArray();
        $relationship->delete();

        AuditService::log('entities.relationship_deleted', null, $old, [], [
            'entity_id' => $entity->id,
            'company_entity_id' => $entity->id,
        ]);

        return redirect()->route('companies.show', [$entity, 'tab' => 'beteiligungen'])
            ->with('success', 'Die Unternehmensbeziehung wurde gelöscht.');
    }

    private function ensureBelongsTo(Entity $entity, EntityRelationship $relationship): void
    {
        abort_unless(
            (int) $relationship->entity_a_id === (int) $entity->id
            || (int) $relationship->entity_b_id === (int) $entity->id,
            404,
        );
    }
}
