<?php

namespace App\Http\Requests\Holding;

use App\Enums\VoteChoice;
use App\Models\Resolution;
use Illuminate\Validation\Rule;

/**
 * Abstimmung dokumentieren (Abschnitt 94): je Teilnehmer optional
 * Ja, Nein, Enthaltung oder nicht teilgenommen. Das System bewertet
 * daraus keine gesetzlichen Mehrheiten.
 */
class CastVotesRequest extends HoldingFormRequest
{
    /**
     * Sichtbarkeit vor der Validierung prüfen.
     *
     * Die Prüfung gehört hierher und nicht erst in den Controller: Der
     * FormRequest wird vor dem Controller aufgelöst, eine fehlgeschlagene
     * Validierung hätte sonst eine Weiterleitung erzeugt, bevor die Schranke
     * überhaupt greift. Der Controller prüft zusätzlich, das ist Absicht.
     */
    public function authorize(): bool
    {
        $resolution = $this->route('resolution');
        $user = $this->user();

        if (! $resolution instanceof Resolution || $user === null) {
            return false;
        }

        return Resolution::query()->visibleTo($user)->whereKey($resolution->getKey())->exists();
    }

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
