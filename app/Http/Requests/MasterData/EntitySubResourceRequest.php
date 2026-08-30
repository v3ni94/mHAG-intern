<?php

namespace App\Http\Requests\MasterData;

use App\Models\Entity;

/**
 * Basis für Unterressourcen einer Akte (Adressen, Kontaktdaten, Bankkonten,
 * Steuerdaten, Identitätsdokumente, Beziehungen, Organstellungen).
 *
 * Autorisierung: Bearbeitungsrecht je nach Aktentyp (persons.update bzw.
 * companies.update) plus Entity-Sichtbarkeit für externe Benutzer.
 */
abstract class EntitySubResourceRequest extends MasterDataFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $entity = $this->route('entity');

        if (! $user || ! $entity instanceof Entity) {
            return false;
        }

        $permission = str_starts_with((string) $this->route()?->getName(), 'companies.')
            ? 'companies.update'
            : 'persons.update';

        if (! $user->can($permission)) {
            return false;
        }

        return $user->isInternal() || $user->accessibleEntityIds()->contains($entity->id);
    }

    protected function entity(): Entity
    {
        return $this->route('entity');
    }
}
