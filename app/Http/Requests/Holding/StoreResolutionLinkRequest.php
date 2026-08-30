<?php

namespace App\Http\Requests\Holding;

use App\Http\Controllers\ResolutionController;
use Illuminate\Validation\Rule;

/**
 * Beschlussverknüpfung (Abschnitt 96) über eine Whitelist zulässiger Ziele.
 */
class StoreResolutionLinkRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'linkable_type' => ['required', Rule::in(array_keys(ResolutionController::LINKABLE_TYPES))],
            'linkable_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'linkable_type' => 'Verknüpfungsart',
            'linkable_id' => 'Verknüpfter Vorgang',
        ];
    }
}
