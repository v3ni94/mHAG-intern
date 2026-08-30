<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\AddressRequest;
use App\Models\Address;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Adressen einer Personen- oder Unternehmensakte (Abschnitt 6 Masterprompt).
 */
class AddressController extends Controller
{
    public function store(AddressRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->addressData();

        if (! empty($data['is_primary'])) {
            $entity->addresses()->update(['is_primary' => false]);
        }

        $address = $entity->addresses()->create($data);

        AuditService::log('entities.address_created', $address, [], $data, ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity, 'adressen')
            ->with('success', 'Die Adresse wurde gespeichert.');
    }

    public function update(AddressRequest $request, Entity $entity, Address $address): RedirectResponse
    {
        $data = $request->addressData();
        $old = $address->only(array_keys($data));

        if (! empty($data['is_primary'])) {
            $entity->addresses()->whereKeyNot($address->id)->update(['is_primary' => false]);
        }

        $address->update($data);

        AuditService::log('entities.address_updated', $address, $old, $data, ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity, 'adressen')
            ->with('success', 'Die Adresse wurde aktualisiert.');
    }

    public function destroy(Request $request, Entity $entity, Address $address): RedirectResponse
    {
        $this->ensureVisible($request, $entity);

        $old = $address->toArray();
        $address->delete();

        AuditService::log('entities.address_deleted', null, $old, [], ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity, 'adressen')
            ->with('success', 'Die Adresse wurde gelöscht.');
    }

    private function ensureVisible(Request $request, Entity $entity): void
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    private function redirectToTab(Entity $entity, string $tab): RedirectResponse
    {
        $route = $entity->type === EntityType::Person ? 'persons.show' : 'companies.show';

        return redirect()->route($route, [$entity, 'tab' => $tab]);
    }
}
