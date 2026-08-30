<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\ContactDetailRequest;
use App\Models\ContactDetail;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kontaktdaten (E-Mail, Telefon, Mobil, Fax, ...) einer Akte.
 */
class ContactDetailController extends Controller
{
    public function store(ContactDetailRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['is_primary'])) {
            $entity->contactDetails()->where('type', $data['type'])->update(['is_primary' => false]);
        }

        $contact = $entity->contactDetails()->create($data);

        AuditService::log('entities.contact_created', $contact, [], $data, ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Der Kontakt wurde gespeichert.');
    }

    public function update(ContactDetailRequest $request, Entity $entity, ContactDetail $contactDetail): RedirectResponse
    {
        $data = $request->validated();
        $old = $contactDetail->only(array_keys($data));

        if (! empty($data['is_primary'])) {
            $entity->contactDetails()->where('type', $data['type'])
                ->whereKeyNot($contactDetail->id)->update(['is_primary' => false]);
        }

        $contactDetail->update($data);

        AuditService::log('entities.contact_updated', $contactDetail, $old, $data, ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Der Kontakt wurde aktualisiert.');
    }

    public function destroy(Request $request, Entity $entity, ContactDetail $contactDetail): RedirectResponse
    {
        $this->ensureVisible($request, $entity);

        $old = $contactDetail->toArray();
        $contactDetail->delete();

        AuditService::log('entities.contact_deleted', null, $old, [], ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Der Kontakt wurde gelöscht.');
    }

    private function ensureVisible(Request $request, Entity $entity): void
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    private function redirectToTab(Entity $entity): RedirectResponse
    {
        // Personenakte: Tab "Kontakt"; Unternehmensakte: Tab "Ansprechpartner".
        return $entity->type === EntityType::Person
            ? redirect()->route('persons.show', [$entity, 'tab' => 'kontakt'])
            : redirect()->route('companies.show', [$entity, 'tab' => 'ansprechpartner']);
    }
}
