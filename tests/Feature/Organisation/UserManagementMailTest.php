<?php

namespace Tests\Feature\Organisation;

use App\Mail\SmtpTestMail;
use App\Mail\UserAccountChangedMail;
use App\Mail\UserCredentialsMail;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Benutzerverwaltung und Mailversand: Zugangsdaten zustellen, Passwort-Link,
 * Zurücksetzen der Zwei-Faktor-Authentifizierung, Archivieren und
 * Wiederherstellen, Prüfung der Mailkonfiguration.
 */
class UserManagementMailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Verwaltung',
            'is_active' => true,
            'two_factor_secret' => 'TESTSECRET234567',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('Administrator');
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'name' => 'Maria Muster',
            'email' => 'maria@example.test',
            'is_active' => true,
        ], $attributes));
        $user->assignRole('Sachbearbeiter');

        return $user;
    }

    public function test_zugangsdaten_werden_mit_passwortlink_versendet(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($this->admin)
            ->post(route('admin.users.send-credentials', $user), ['note' => 'Bitte zeitnah anmelden.'])
            ->assertRedirect();

        Mail::assertSent(UserCredentialsMail::class, function (UserCredentialsMail $mail) use ($user) {
            // Der Link zum Setzen des Passworts muss enthalten sein,
            // ein Passwort im Klartext darf NIE versendet werden.
            $this->assertNotNull($mail->passwordResetUrl);
            $this->assertStringContainsString('passwort-zuruecksetzen', $mail->passwordResetUrl);
            $this->assertStringContainsString(urlencode($user->email), $mail->passwordResetUrl);
            $this->assertSame('Bitte zeitnah anmelden.', $mail->note);

            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.users.credentials_sent']);
    }

    public function test_zugangsdatenmail_enthaelt_kein_passwort_im_klartext(): void
    {
        $user = $this->member();
        $mail = new UserCredentialsMail($user, 'https://example.test/reset/abc');

        $rendered = $mail->render();

        $this->assertStringContainsString($user->email, $rendered);
        $this->assertStringContainsString('Passwort jetzt festlegen', $rendered);
        // Der bekannte Testfaktor-Standardwert der Factory darf nicht auftauchen
        $this->assertStringNotContainsString('password', strtolower($rendered));
    }

    public function test_anlegen_mit_versand_der_zugangsdaten(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Neuer Benutzer',
            'email' => 'neu@example.test',
            'password' => 'EinLangesPasswort2026',
            'password_confirmation' => 'EinLangesPasswort2026',
            'roles' => ['Sachbearbeiter'],
            'is_active' => '1',
            'send_credentials' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
        Mail::assertSent(UserCredentialsMail::class);
    }

    public function test_scheiternder_mailversand_bricht_den_vorgang_nicht_ab(): void
    {
        $user = $this->member();

        // Mailserver nicht erreichbar: Versand schlägt fehl
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));

        $this->actingAs($this->admin)
            ->post(route('admin.users.send-credentials', $user))
            ->assertRedirect()
            ->assertSessionHas('warning');

        // Der Fehlschlag ist protokolliert, das Konto bleibt unverändert bestehen
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.users.credentials_sent_failed']);
        $this->assertDatabaseHas('users', ['email' => $user->email, 'is_active' => true]);
    }

    public function test_passwortlink_wird_versendet(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($this->admin)
            ->post(route('admin.users.send-password-reset', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_zwei_faktor_zurueck_setzen_informiert_den_benutzer(): void
    {
        Mail::fake();
        $user = $this->member([
            'two_factor_secret' => 'GEHEIMNIS12345678',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->assertTrue($user->hasTwoFactorEnabled());

        $this->actingAs($this->admin)
            ->post(route('admin.users.reset-two-factor', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);

        Mail::assertSent(UserAccountChangedMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.users.two_factor_reset']);
    }

    public function test_aenderung_der_rollen_benachrichtigt_auf_wunsch(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['Buchhaltung'],
            'is_active' => '1',
            'notify_user' => '1',
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->hasRole('Buchhaltung'));

        Mail::assertSent(UserAccountChangedMail::class, function (UserAccountChangedMail $mail) {
            $this->assertNotEmpty($mail->changes);
            $this->assertStringContainsString('Buchhaltung', implode(' ', $mail->changes));

            return true;
        });
    }

    public function test_ohne_haekchen_wird_nicht_benachrichtigt(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['Buchhaltung'],
            'is_active' => '1',
        ])->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_archivieren_und_wiederherstellen(): void
    {
        $user = $this->member();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.users.archived']);

        // Anmeldung eines archivierten Kontos ist nicht möglich
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)
            ->post(route('admin.users.restore', $user->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_eigenes_konto_kann_nicht_archiviert_werden(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }

    public function test_archivierte_konten_sind_getrennt_auffindbar(): void
    {
        $user = $this->member();
        $user->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee($user->email);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['archiviert' => 1]))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_testnachricht_prueft_die_mailkonfiguration(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.test-mail'), ['test_recipient' => 'pruefung@example.test'])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('success');

        Mail::assertSent(SmtpTestMail::class, fn ($mail) => $mail->hasTo('pruefung@example.test'));

        $lastTest = Setting::get('mail', 'last_test');
        $this->assertTrue($lastTest['successful']);
        $this->assertSame('pruefung@example.test', $lastTest['recipient']);
    }

    public function test_fehlgeschlagene_testnachricht_wird_verstaendlich_gemeldet(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Authentication failed: 535 incorrect'));

        $this->actingAs($this->admin)
            ->post(route('admin.settings.test-mail'), ['test_recipient' => 'pruefung@example.test'])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('danger');

        $lastTest = Setting::get('mail', 'last_test');
        $this->assertFalse($lastTest['successful']);
        $this->assertStringContainsString('Anmeldedaten', $lastTest['error']);
    }

    public function test_ohne_berechtigung_kein_zugriff_auf_benutzerverwaltung(): void
    {
        $sachbearbeiter = $this->member(['email' => 'ohne-rechte@example.test']);

        $this->actingAs($sachbearbeiter)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($sachbearbeiter)->post(route('admin.users.send-credentials', $this->admin))->assertForbidden();
        $this->actingAs($sachbearbeiter)->post(route('admin.settings.test-mail'), ['test_recipient' => 'x@example.test'])->assertForbidden();
    }

    public function test_mailversand_wird_im_audit_protokolliert(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($this->admin)->post(route('admin.users.send-credentials', $user));

        $entry = AuditLog::where('action', 'admin.users.credentials_sent')->firstOrFail();
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertSame($user->email, $entry->context['recipient']);
        $this->assertSame('versendet', $entry->context['result']);
    }
}
