<?php

namespace App\Http\Requests\MasterData;

use App\Models\Entity;

class UpdateCompanyRequest extends StoreCompanyRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $entity = $this->route('entity');

        if (! $user || ! $entity instanceof Entity || ! $user->can('companies.update')) {
            return false;
        }

        return $user->isInternal() || $user->accessibleEntityIds()->contains($entity->id);
    }
}
