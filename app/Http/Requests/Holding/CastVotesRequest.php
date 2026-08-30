<?php

namespace App\Http\Requests\Holding;

use App\Enums\VoteChoice;
use Illuminate\Validation\Rule;

/**
 * Abstimmung dokumentieren (Abschnitt 94): je Teilnehmer optional
 * Ja, Nein, Enthaltung oder nicht teilgenommen. Das System bewertet
 * daraus keine gesetzlichen Mehrheiten.
 */
class CastVotesRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'votes' => ['required', 'array'],
            'votes.*' => ['nullable', Rule::enum(VoteChoice::class)],
            'excluded_from_deliberation' => ['nullable', 'array'],
            'excluded_from_deliberation.*' => ['nullable', 'boolean'],
            'excluded_from_vote' => ['nullable', 'array'],
            'excluded_from_vote.*' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'votes' => 'Abstimmung',
            'votes.*' => 'Stimme',
        ];
    }
}
