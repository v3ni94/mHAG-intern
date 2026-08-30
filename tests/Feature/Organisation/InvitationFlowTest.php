<?php

namespace Tests\Feature\Organisation;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

class InvitationFlowTest extends OrganisationTestCase
{
    public function test_einladungs_roundtrip_store_show_accept(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $entity = $this->makeEntity('Eingeladene Person');
        $scopeEntity = $this->makeEntity('Datenbereich GmbH', 'company');

        // 1) Einladung erstellen
        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'neu@example.com',
            'entity_id' => $entity->id,
            'roles' => ['Darlehensgeber'],
            'entity_ids' => [$scopeEntity->id],
        ]);
        $response->assertRedirect(route('admin.invitations.index'));

        $invitation = UserInvitation::firstOrFail();
        $this->assertSame('neu@example.com', $invitation->email);
        $this->assertSame(64, strlen($invitation->token_hash)); // sha256-Hex, nie Klartext
        $this->assertTrue($invitation->expires_at->gt(now()->addDays(6)));

        // 2) Mail wurde mit Klartext-Token versendet; Hash muss passen
        $token = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$token, $invitation) {
            $token = $mail->token;

            return $mail->invitation->is($invitation) && $mail->hasTo('neu@example.com');
        });
        $this->assertNotNull($token);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        // 3) Einladungsseite über den Link aufrufbar
        $this->post(route('logout'));
        $this->get(route('invitations.show', $token))->assertOk();

        // 4) Annahme legt Benutzer mit Rollen und Datenbereich an
        $accept = $this->post(route('invitations.accept', $token), [
            'name' => 'Neuer Benutzer',
            'password' => 'SicheresPasswort123',
            'password_confirmation' => 'SicheresPasswort123',
        ]);
        $accept->assertRedirect(route('two-factor.setup'));

        $user = User::where('email', 'neu@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Darlehensgeber'));
        $this->assertSame($entity->id, $user->entity_id);
        $this->assertTrue($user->entityAssignments()->where('entity_id', $scopeEntity->id)->exists());
        $this->assertNotNull($invitation->fresh()->accepted_at);

        // 5) Einmal-Link: erneuter Aufruf ist ungültig
        $this->post(route('logout'));
        $this->get(route('invitations.show', $token))->assertNotFound();
    }

    public function test_widerrufene_einladung_ist_unbrauchbar(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'widerruf@example.com',
            'roles' => ['Mitarbeiter'],
        ]);

        $invitation = UserInvitation::firstOrFail();
        $token = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        $this->actingAs($admin)->post(route('admin.invitations.revoke', $invitation))->assertRedirect();
        $this->assertNotNull($invitation->fresh()->revoked_at);

        $this->post(route('logout'));
        $this->get(route('invitations.show', $token))->assertNotFound();
    }
}
