<?php

namespace App\Http\Requests\MasterData;

use App\Models\Entity;

class UpdatePersonRequest extends StorePersonRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $entity = $this->route('entity');

        if (! $user || ! $entity instanceof Entity || ! $user->can('persons.update')) {
            return false;
        }

        return $user->isInternal() || $user->accessibleEntityIds()->contains($entity->id);
    }
}
