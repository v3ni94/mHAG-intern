<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\Holding\EndCorporateBodyMemberRequest;
use App\Http\Requests\Holding\StoreCorporateBodyMemberRequest;
use App\Models\CorporateBody;
use App\Models\CorporateBodyMember;
use App\Models\Entity;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Vorstand und Aufsichtsrat (Abschnitte 85 bis 87): aktive Mitglieder,
 * Vorsitz-Kennzeichnung, Mandatsende-Warnung und stichtagsfähige
 * Organhistorie. Mitglieder werden NIE gelöscht, nur beendet.
 */
class CorporateBodyController extends Controller
{
    /** Vorwarnzeit für auslaufende Mandate in Tagen. */
    private const MANDATE_WARNING_DAYS = 90;

    public function index(Request $request)
    {
        $request->validate(
            ['as_of' => ['nullable', 'date']],
            ['as_of.date' => 'Der Stichtag muss ein gültiges Datum sein.'],
        );

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : null;

        $companyEntityId = Setting::get('holding', 'company_entity_id');

        $bodies = CorporateBody::query()
            ->visibleTo($request->user())
            ->with('company')
            ->where('company_entity_id', $companyEntityId)
            ->orderBy('type')
            ->get()
            ->map(function (CorporateBody $body) use ($asOf) {
                $members = $this->membersAsOf($body, $asOf);

                return ['body' => $body, 'members' => $members];
            });

        return view('corporate-bodies.index', [
            'bodies' => $bodies,
            'asOf' => $asOf,
            'warningDate' => Carbon::now()->addDays(self::MANDATE_WARNING_DAYS),
        ]);
    }

    public function show(Request $request, CorporateBody $corporateBody)
    {
        abort_unless(
            CorporateBody::query()->visibleTo($request->user())->whereKey($corporateBody->getKey())->exists(),
            404,
        );

        $request->validate(
            ['as_of' => ['nullable', 'date']],
            ['as_of.date' => 'Der Stichtag muss ein gültiges Datum sein.'],
        );

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : null;

        $corporateBody->load(['company', 'members.person']);

        $persons = Entity::query()
            ->where('type', EntityType::Person)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return view('corporate-bodies.show', [
            'body' => $corporateBody,
            'asOf' => $asOf,
            'membersAsOf' => $this->membersAsOf($corporateBody, $asOf),
            'allMembers' => $corporateBody->members->sortByDesc('started_on')->values(),
            'persons' => $persons,
            'warningDate' => Carbon::now()->addDays(self::MANDATE_WARNING_DAYS),
        ]);
    }

    public function storeMember(StoreCorporateBodyMemberRequest $request, CorporateBody $corporateBody)
    {
        $data = $request->validated();
        $data['is_chair'] = (bool) ($data['is_chair'] ?? false);
        $data['status'] = 'active';

        $member = $corporateBody->members()->create($data);

        AuditService::log('corporate-bodies.member-added', $member, [], $data, [
            'corporate_body' => $corporateBody->name,
        ]);

        return redirect()
            ->route('corporate-bodies.show', $corporateBody)
            ->with('success', 'Mitglied wurde aufgenommen.');
    }

    /**
     * Mandat beenden (Abschnitt 87): ended_on setzen, Status "ended",
     * Historie bleibt vollständig erhalten.
     */
    public function endMember(EndCorporateBodyMemberRequest $request, CorporateBody $corporateBody, CorporateBodyMember $member)
    {
        abort_unless($member->corporate_body_id === $corporateBody->id, 404);

        if ($member->status === 'ended') {
            return redirect()
                ->route('corporate-bodies.show', $corporateBody)
                ->with('warning', 'Das Mandat ist bereits beendet.');
        }

        $old = $member->only(['status', 'ended_on', 'note']);

        $member->update([
            'ended_on' => $request->validated('ended_on'),
            'status' => 'ended',
            'note' => trim(($member->note ? $member->note."\n" : '').(string) $request->validated('note')) ?: $member->note,
        ]);

        AuditService::log('corporate-bodies.member-ended', $member, $old, [
            'status' => 'ended',
            'ended_on' => $request->validated('ended_on'),
        ]);

        return redirect()
            ->route('corporate-bodies.show', $corporateBody)
            ->with('success', 'Das Mandat wurde beendet. Die Historie bleibt erhalten.');
    }

    /**
     * Mitglieder eines Organs, optional zum Stichtag ("Wer war am X im Amt?").
     * Ohne Stichtag: aktive Mitglieder.
     */
    private function membersAsOf(CorporateBody $body, ?Carbon $asOf)
    {
        $query = $body->members()->with('person');

        if ($asOf) {
            $query
                ->where(function ($q) use ($asOf) {
                    $q->whereNull('started_on')->orWhereDate('started_on', '<=', $asOf->toDateString());
                })
                ->where(function ($q) use ($asOf) {
                    $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $asOf->toDateString());
                });
        } else {
            $query->where('status', 'active');
        }

        return $query
            ->orderByDesc('is_chair')
            ->orderBy('started_on')
            ->get();
    }
}
