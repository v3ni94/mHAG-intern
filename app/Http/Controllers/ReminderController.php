<?php

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Http\Requests\Organisation\ReminderRequest;
use App\Models\Reminder;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Wiedervorlagen (Abschnitt 73 Masterprompt): CRUD, Zuweisung, Priorität,
 * Status, Bezug zu Fachobjekten. Überfällige Einträge werden rot markiert.
 */
class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $reminders = Reminder::query()
            ->with(['assignee:id,name', 'creator:id,name', 'remindable'])
            ->when(! $user->isInternal(), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('assigned_to', $user->id)->orWhere('created_by', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', ReminderStatus::Open->value))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->query('assigned_to')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->paginate(25)
            ->withQueryString();

        return view('reminders.index', [
            'reminders' => $reminders,
            'users' => $this->zuweisbareBenutzer($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('reminders.create', [
            'users' => $this->zuweisbareBenutzer($request->user()),
            'preset' => [
                'remindable_type' => $request->query('remindable_type'),
                'remindable_id' => $request->query('remindable_id'),
            ],
        ]);
    }

    /**
     * Benutzer, denen eine Wiedervorlage zugewiesen werden darf.
     *
     * Zuvor stand hier das vollständige Benutzerverzeichnis, für jede
     * angemeldete Rolle und ohne Berechtigungsprüfung; die Wiedervorlagen
     * liegen hinter keiner eigenen Berechtigung. Eine externe Rolle konnte so
     * die Namen aller Benutzer der Gruppe auslesen. Sie weist nur sich selbst
     * zu, mehr ist für den Zweck nicht erforderlich.
     */
    private function zuweisbareBenutzer(?User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        if (! $user->isInternal()) {
            return collect([$user->only(['id', 'name'])])
                ->map(fn (array $eintrag) => (object) $eintrag);
        }

        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function store(ReminderRequest $request): RedirectResponse
    {
        $reminder = Reminder::create([
            ...$request->safe()->only(['title', 'description', 'due_date', 'due_time', 'assigned_to', 'priority']),
            'status' => ReminderStatus::Open->value,
            'remindable_type' => $request->remindableClass(),
            'remindable_id' => $request->remindableClass() ? $request->integer('remindable_id') : null,
            'created_by' => $request->user()->id,
        ]);

        AuditService::log('reminders.created', $reminder, [], $reminder->only(['title', 'due_date', 'assigned_to', 'priority']));

        return redirect()->route('reminders.index')->with('success', 'Die Wiedervorlage wurde angelegt.');
    }

    public function edit(Request $request, Reminder $reminder): View
    {
        $this->authorizeAccess($reminder);

        return view('reminders.edit', [
            'reminder' => $reminder,
            'users' => $this->zuweisbareBenutzer($request->user()),
        ]);
    }

    public function update(ReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeAccess($reminder);

        $old = $reminder->only(['title', 'description', 'due_date', 'due_time', 'assigned_to', 'priority']);

        $reminder->update([
            ...$request->safe()->only(['title', 'description', 'due_date', 'due_time', 'assigned_to', 'priority']),
            'remindable_type' => $request->remindableClass(),
            'remindable_id' => $request->remindableClass() ? $request->integer('remindable_id') : null,
        ]);

        AuditService::log('reminders.updated', $reminder, $old, $reminder->only(['title', 'description', 'due_date', 'due_time', 'assigned_to', 'priority']));

        return redirect()->route('reminders.index')->with('success', 'Die Wiedervorlage wurde aktualisiert.');
    }

    public function done(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeAccess($reminder);

        $reminder->update(['status' => ReminderStatus::Done->value]);
        AuditService::log('reminders.done', $reminder);

        return back()->with('success', 'Die Wiedervorlage wurde als erledigt markiert.');
    }

    public function cancel(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeAccess($reminder);

        $reminder->update(['status' => ReminderStatus::Cancelled->value]);
        AuditService::log('reminders.cancelled', $reminder);

        return back()->with('success', 'Die Wiedervorlage wurde abgebrochen.');
    }

    /** Externe dürfen nur eigene bzw. ihnen zugewiesene Wiedervorlagen bearbeiten. */
    private function authorizeAccess(Reminder $reminder): void
    {
        $user = auth()->user();
        abort_unless(
            $user->isInternal() || $reminder->assigned_to === $user->id || $reminder->created_by === $user->id,
            403,
        );
    }
}
