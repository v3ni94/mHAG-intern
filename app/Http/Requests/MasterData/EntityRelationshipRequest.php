<?php

namespace App\Http\Requests\MasterData;

use App\Enums\RelationshipType;
use App\Http\Requests\Concerns\ParstDeutscheBetraege;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EntityRelationshipRequest extends EntitySubResourceRequest
{
    use ParstDeutscheBetraege;

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'entity_b_id', 'share_percentage', 'share_count', 'valid_from', 'valid_until', 'note',
        ]);

        // Deutsche Dezimaleingabe (z. B. "25,5") in Punktnotation wandeln.
        // Sechs Nachkommastellen, wie die Spalte (DECIMAL(9,6)). Mit der
        // Vorgabe von zwei Stellen wurde 33,333333 zu 33,33.
        $this->parstProzent('share_percentage', 6);
    }

    public function rules(): array
    {
        return [
            'entity_b_id' => [
                'required', 'integer',
                Rule::exists('entities', 'id')->whereNull('deleted_at'),
            ],
            'relationship_type' => ['required', Rule::enum(RelationshipType::class)],
            'share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'share_count' => ['nullable', 'integer', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->betragsfehlerMelden($v);

            if ((int) $this->input('entity_b_id') === (int) $this->entity()->id) {
                $v->errors()->add('entity_b_id', 'Ein Unternehmen kann nicht mit sich selbst verbunden werden.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'entity_b_id' => 'Verbundenes Unternehmen',
            'relationship_type' => 'Beziehungsart',
            'share_percentage' => 'Beteiligungsquote',
            'share_count' => 'Anzahl Anteile',
            'valid_from' => 'Beginn',
            'valid_until' => 'Ende',
            'note' => 'Bemerkung',
        ];
    }
}
