<?php

namespace App\Http\Controllers;

use App\Enums\ResolutionStatus;
use App\Http\Requests\Holding\CastVotesRequest;
use App\Models\Resolution;
use App\Models\ResolutionVote;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Abstimmung dokumentieren (Abschnitt 94): je Teilnehmer Ja, Nein,
 * Enthaltung oder nicht teilgenommen. Das System zählt nur und bewertet
 * keine gesetzlichen Mehrheiten.
 */
class ResolutionVoteController extends Controller
{
    public function store(CastVotesRequest $request, Resolution $resolution)
    {
        // Ohne diese Pruefung liess sich zu jedem beliebigen Beschluss der
        // Gruppe abstimmen, schreibend, allein ueber die Adresszeile.
        abort_unless(
            Resolution::query()->visibleTo($request->user())->whereKey($resolution->getKey())->exists(),
            404,
        );

        if (in_array($resolution->status, [ResolutionStatus::Signed, ResolutionStatus::Completed, ResolutionStatus::Archived], true)) {
            return redirect()
                ->route('resolutions.show', $resolution)
                ->with('danger', 'Die Abstimmung eines unterschriebenen oder abgeschlossenen Beschlusses kann nicht mehr geändert werden.');
        }

        $votes = $request->validated('votes');
        $excludedDeliberation = $request->validated('excluded_from_deliberation') ?? [];
        $excludedVote = $request->validated('excluded_from_vote') ?? [];

        $participants = $resolution->participants()->get()->keyBy('id');

        DB::transaction(function () use ($resolution, $votes, $excludedDeliberation, $excludedVote, $participants) {
            foreach ($votes as $participantId => $choice) {
                $participant = $participants->get((int) $participantId);
                if (! $participant) {
                    continue;
                }

                // Interessenkonflikt (Abschnitt 95): Ausschlüsse dokumentieren
                $participant->update([
                    'attended' => $choice === null || $choice === ''
                        ? $participant->attended
                        : $choice !== 'absent',
                    'excluded_from_deliberation' => (bool) ($excludedDeliberation[$participantId] ?? false),
                    'excluded_from_vote' => (bool) ($excludedVote[$participantId] ?? false),
                ]);

                if ($choice === null || $choice === '') {
                    // Keine Angabe: bestehende Stimme entfernen, nichts erfinden
                    ResolutionVote::query()
                        ->where('resolution_id', $resolution->id)
                        ->where('resolution_participant_id', $participant->id)
                        ->delete();

                    continue;
                }

                ResolutionVote::updateOrCreate(
                    [
                        'resolution_id' => $resolution->id,
                        'resolution_participant_id' => $participant->id,
                    ],
                    ['vote' => $choice],
                );
            }

            AuditService::log('resolutions.votes-recorded', $resolution, [], [
                'votes' => $votes,
            ]);
        });

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'Die Abstimmung wurde dokumentiert.');
    }
}
