<?php

namespace App\Http\Requests\Holding;

use App\Enums\ResolutionType;
use Illuminate\Validation\Rule;

/**
 * Beschlussdaten (Abschnitt 89). Tatsächliches Beschlussdatum (resolved_on)
 * und technisches Erfassungsdatum (recorded_at) werden strikt getrennt;
 * das Erfassungsdatum setzt der Controller auf den Erfassungszeitpunkt.
 */
class StoreResolutionRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ResolutionType::class)],
            'company_entity_id' => ['required', 'integer', 'exists:entities,id'],
            'applicant_entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'motion' => ['nullable', 'string', 'max:65000'],
            'reasoning' => ['nullable', 'string', 'max:65000'],
            'resolution_text' => ['nullable', 'string', 'max:65000'],
            'resolved_on' => ['nullable', 'date'],
            'conflict_of_interest' => ['nullable', 'boolean'],
            'conflict_notes' => ['nullable', 'string', 'max:65000', 'required_if:conflict_of_interest,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'type' => 'Beschlussart',
            'company_entity_id' => 'Gesellschaft',
            'applicant_entity_id' => 'Antragsteller',
            'motion' => 'Antrag',
            'reasoning' => 'Begründung',
            'resolution_text' => 'Beschlusstext',
            'resolved_on' => 'Tatsächliches Beschlussdatum',
            'conflict_of_interest' => 'Interessenkonflikt',
            'conflict_notes' => 'Beschreibung des Interessenkonflikts',
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'conflict_notes.required_if' => 'Bitte beschreiben Sie den Interessenkonflikt.',
        ]);
    }
}
