<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Annahme einer Benutzereinladung (Abschnitt 12): Link ist zufällig,
 * zeitlich begrenzt, einmalig verwendbar und serverseitig nur gehasht gespeichert.
 */
class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findUsable($token);

        return view('auth.invitation', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findUsable($token);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($request, $invitation) {
            $user = $invitation->user_id
                ? User::findOrFail($invitation->user_id)
                : User::create([
                    'name' => $request->input('name'),
                    'email' => $invitation->email,
                    'entity_id' => $invitation->entity_id,
                    'is_active' => true,
                ]);

            $user->forceFill([
                'password' => $request->input('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();

            if ($invitation->roles) {
                $user->syncRoles($invitation->roles);
            }

            foreach ($invitation->entity_ids ?? [] as $entityId) {
                $user->entityAssignments()->firstOrCreate(
                    ['entity_id' => $entityId, 'context' => 'self'],
                );
            }

            $invitation->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();

            return $user;
        });

        AuditService::log('auth.invitation_accepted', $user, [], [], ['invitation_id' => $invitation->id]);

        Auth::login($user);
        $request->session()->regenerate();

        // Danach 2FA-Einrichtung (Pflichtrollen werden durch Middleware umgeleitet).
        return redirect()->route('two-factor.setup')
            ->with('success', 'Ihr Konto ist aktiviert. Richten Sie jetzt die Zwei-Faktor-Authentifizierung ein.');
    }

    private function findUsable(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        abort_if(! $invitation || ! $invitation->isUsable(), 404, 'Diese Einladung ist nicht mehr gültig.');

        return $invitation;
    }
}
