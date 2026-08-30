<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Absicherung der SFTP-Konfiguration.
 *
 * Hintergrund: Ein in der .env vorhandener, aber leerer SFTP_PRIVATE_KEY ist
 * ein leerer String und damit nicht "nicht gesetzt". Der SFTP-Adapter versucht
 * dann, diesen leeren Wert als Schlüssel zu laden, und bricht mit
 * "Unable to load private key" ab, obwohl die Anmeldung per Passwort
 * vorgesehen ist. Genau dieser Fall ist im Betrieb aufgetreten.
 */
class SftpConfigurationTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $vorher = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['SFTP_HOST', 'SFTP_PORT', 'SFTP_USERNAME', 'SFTP_PASSWORD', 'SFTP_PRIVATE_KEY', 'SFTP_PASSPHRASE', 'SFTP_HOST_FINGERPRINT', 'SFTP_TIMEOUT'] as $key) {
            $this->vorher[$key] = $_SERVER[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->vorher as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        parent::tearDown();
    }

    /**
     * Konfigurationsdatei mit den gesetzten Umgebungswerten neu auswerten.
     *
     * @return array<string, mixed>
     */
    private function sftpConfig(array $env): array
    {
        foreach ($env as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $config = require config_path('filesystems.php');

        return $config['disks']['sftp'];
    }

    public function test_leerer_schluessel_wird_nicht_uebergeben(): void
    {
        $sftp = $this->sftpConfig([
            'SFTP_HOST' => 'sftp.example.test',
            'SFTP_USERNAME' => 'benutzer',
            'SFTP_PASSWORD' => 'geheim',
            'SFTP_PRIVATE_KEY' => '',
            'SFTP_PASSPHRASE' => '',
        ]);

        $this->assertArrayNotHasKey('privateKey', $sftp, 'Ein leerer Schlüssel darf nicht als Schlüssel gelten.');
        $this->assertArrayNotHasKey('passphrase', $sftp);
        $this->assertSame('geheim', $sftp['password']);
        $this->assertSame('benutzer', $sftp['username']);
    }

    public function test_gesetzter_schluessel_wird_uebergeben(): void
    {
        $sftp = $this->sftpConfig([
            'SFTP_HOST' => 'sftp.example.test',
            'SFTP_USERNAME' => 'benutzer',
            'SFTP_PRIVATE_KEY' => '/pfad/zum/schluessel',
            'SFTP_PASSPHRASE' => 'passphrase',
        ]);

        $this->assertSame('/pfad/zum/schluessel', $sftp['privateKey']);
        $this->assertSame('passphrase', $sftp['passphrase']);
    }

    public function test_leerer_port_ergibt_nicht_port_null(): void
    {
        $sftp = $this->sftpConfig([
            'SFTP_HOST' => 'sftp.example.test',
            'SFTP_PORT' => '',
            'SFTP_TIMEOUT' => '',
        ]);

        $this->assertSame(22, $sftp['port']);
        $this->assertSame(15, $sftp['timeout']);
    }

    public function test_fehlender_fingerprint_wird_nicht_uebergeben(): void
    {
        $sftp = $this->sftpConfig([
            'SFTP_HOST' => 'sftp.example.test',
            'SFTP_HOST_FINGERPRINT' => '',
        ]);

        $this->assertArrayNotHasKey('hostFingerprint', $sftp);
        // Auch bei gefilterter Konfiguration muessen die Schalter erhalten bleiben
        $this->assertTrue($sftp['throw']);
        $this->assertTrue($sftp['report']);
    }
}
