<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\InvitationStoreRequest;
use App\Mail\UserInvitationMail;
use App\Models\Entity;
use App\Models\UserInvitation;
use App\Services\AuditService;
use App\Services\MailDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Benutzereinladungen (Abschnitt 12 Masterprompt): zufälliger Einmal-Token,
 * nur SHA-256-Hash gespeichert, 7 Tage gültig, Versand per E-Mail.
 */
class InvitationController extends Controller
{
    public function index(Request $request): View
    {
        $invitations = UserInvitation::query()
            ->with(['entity:id,display_name', 'inviter:id,name', 'user:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.invitations.index', [
            'invitations' => $invitations,
            'roles' => Role::query()->orderBy('name')->get(),
            'entities' => Entity::query()->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function store(InvitationStoreRequest $request): RedirectResponse
    {
        $token = Str::random(64);

        $invitation = UserInvitation::create([
            'entity_id' => $request->input('entity_id'),
            'email' => $request->input('email'),
            'token_hash' => hash('sha256', $token),
            'roles' => $request->input('roles', []),
            'entity_ids' => $request->input('entity_ids', []),
            'expires_at' => now()->addDays(7),
            'invited_by' => $request->user()->id,
        ]);

        AuditService::log('admin.invitations.created', $invitation, [], [
            'email' => $invitation->email,
            'roles' => $invitation->roles,
            'entity_ids' => $invitation->entity_ids,
            'expires_at' => $invitation->expires_at->toDateTimeString(),
        ]);

        $result = MailDispatcher::send(
            $invitation->email,
            new UserInvitationMail($invitation, $token),
            'admin.invitations.mail_sent',
            $invitation,
        );

        return redirect()->route('admin.invitations.index')->with(
            $result['sent'] ? 'success' : 'warning',
            $result['sent']
                ? 'Die Einladung wurde erstellt und per E-Mail versendet.'
                : 'Die Einladung wurde erstellt, der Versand ist jedoch fehlgeschlagen: '.$result['error']
                    .' Die Einladung bleibt gültig, Sie können den Versand über "Erneut senden" wiederholen.',
        );
    }

    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 422, 'Diese Einladung wurde bereits angenommen.');
        abort_if($invitation->revoked_at !== null, 422, 'Diese Einladung wurde widerrufen.');

        // Neuer Token, alte Links werden ungültig
        $token = Str::random(64);
        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ])->save();

        AuditService::log('admin.invitations.resent', $invitation, [], ['email' => $invitation->email]);

        $result = MailDispatcher::send(
            $invitation->email,
            new UserInvitationMail($invitation, $token),
            'admin.invitations.mail_sent',
            $invitation,
        );

        return back()->with(
            $result['sent'] ? 'success' : 'warning',
            $result['sent']
                ? 'Die Einladung wurde mit neuem Link erneut versendet.'
                : 'Der Versand ist fehlgeschlagen: '.$result['error'].' Der neue Link ist trotzdem gültig.',
        );
    }

    public function revoke(Request $request, UserInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 422, 'Diese Einladung wurde bereits angenommen.');

        $invitation->forceFill(['revoked_at' => now()])->save();

        AuditService::log('admin.invitations.revoked', $invitation, [], ['email' => $invitation->email]);

        return back()->with('success', 'Die Einladung wurde widerrufen.');
    }
}
