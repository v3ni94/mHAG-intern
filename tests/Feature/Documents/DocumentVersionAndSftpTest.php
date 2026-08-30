<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Setting;
use App\Services\SftpStatusService;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Support\Facades\Storage;

class DocumentVersionAndSftpTest extends DocumentsTestCase
{
    public function test_neue_dokumentversion_erhoeht_versionszaehler_und_behaelt_alte_datei(): void
    {
        $user = $this->internalUser();

        $document = app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\n%Version eins\n%%EOF",
            'sonstiges',
            'bericht.pdf',
            ['doc_type' => 'other', 'uploaded_by' => $user->id],
        );
        $firstPath = $document->storage_path;

        $this->actingAs($user)->post(route('documents.versions.store', $document), [
            'file' => $this->fakePdf('bericht-v2.pdf'),
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(2, (int) $document->version);
        $this->assertSame('bericht-v2.pdf', $document->original_filename);
        $this->assertNotSame($firstPath, $document->storage_path);

        // Beide Versionsdateien existieren; document_versions vollständig.
        Storage::disk('documents')->assertExists($firstPath);
        Storage::disk('documents')->assertExists($document->storage_path);
        $this->assertSame(2, $document->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.version_uploaded', 'auditable_id' => $document->id]);
    }

    public function test_nachtraegliche_verknuepfung_nur_ueber_whitelist(): void
    {
        $user = $this->internalUser();
        $document = app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\n%Inhalt\n%%EOF",
            'sonstiges',
            'verknuepfung.pdf',
            ['doc_type' => 'other', 'uploaded_by' => $user->id],
        );
        $loan = $this->makeLoan();

        // Zulässig: Whitelist-Typ 'loan'
        $this->actingAs($user)->post(route('documents.link', $document), [
            'link_type' => 'loan',
            'link_id' => $loan->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => \App\Models\Loan::class,
            'linkable_id' => $loan->id,
        ]);

        // Unzulässig: freie Klassennamen werden abgelehnt.
        $this->actingAs($user)->post(route('documents.link', $document), [
            'link_type' => 'App\\Models\\User',
            'link_id' => $user->id,
        ])->assertSessionHasErrors('link_type');

        $this->assertDatabaseMissing('document_links', [
            'linkable_type' => \App\Models\User::class,
        ]);
    }

    public function test_sftp_status_ohne_konfiguration_ist_neutral_nicht_konfiguriert(): void
    {
        config(['filesystems.disks.sftp.host' => null]);

        $result = app(SftpStatusService::class)->test();

        $this->assertFalse($result['configured']);
        $this->assertFalse($result['online']);
        $this->assertNull($result['error']);
        $this->assertNotEmpty($result['tested_at']);

        // Ergebnis wird als Setting abgelegt (Statuskarte Admin-Bereich).
        $stored = Setting::get('sftp', 'last_test');
        $this->assertIsArray($stored);
        $this->assertFalse($stored['configured']);
    }

    public function test_admin_sftp_seite_zeigt_status_ohne_geheimnisse(): void
    {
        config([
            'filesystems.disks.sftp.host' => null,
            'filesystems.disks.sftp.password' => 'streng-geheimes-passwort',
        ]);

        $user = $this->internalUser();

        $response = $this->actingAs($user)->get(route('admin.sftp.index'));
        $response->assertOk();
        $response->assertSee('SFTP-Verbindung testen');
        $response->assertDontSee('streng-geheimes-passwort');

        $this->actingAs($user)->post(route('admin.sftp.test'))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.sftp_tested']);
    }

    public function test_sftp_seite_erfordert_admin_berechtigung(): void
    {
        $user = $this->internalUser('Buchhaltung');

        $this->actingAs($user)->get(route('admin.sftp.index'))->assertForbidden();
    }
}
