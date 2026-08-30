<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Loan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentUploadTest extends DocumentsTestCase
{
    public function test_upload_happy_path_speichert_datei_mit_pruefsumme_und_version(): void
    {
        $user = $this->internalUser();
        $file = $this->fakePdf('darlehensvertrag.pdf');
        $expectedHash = hash('sha256', $file->getContent());

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'file' => $file,
            'doc_type' => 'contract',
            'category' => 'Vertragsunterlagen',
            'document_date' => '2026-02-01',
            'description' => 'Unterschriebener Darlehensvertrag',
            'tags' => 'Original, 2026',
        ]);

        $document = Document::firstOrFail();
        $response->assertRedirect(route('documents.show', $document));

        $this->assertSame('darlehensvertrag.pdf', $document->original_filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($expectedHash, $document->sha256);
        $this->assertSame(1, (int) $document->version);
        $this->assertSame(['Original', '2026'], $document->tags);
        $this->assertStringEndsWith('.pdf', $document->stored_filename);
        $this->assertSame($user->id, $document->uploaded_by);

        Storage::disk('documents')->assertExists($document->storage_path);
        $this->assertSame($expectedHash, hash('sha256', Storage::disk('documents')->get($document->storage_path)));

        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.uploaded', 'auditable_id' => $document->id]);
    }

    public function test_upload_mit_darlehensverknuepfung_legt_link_und_ordnerstruktur_an(): void
    {
        $user = $this->internalUser();
        $loan = $this->makeLoan(['loan_number' => 'DAR-2026-00042']);

        $this->actingAs($user)->post(route('documents.store'), [
            'file' => $this->fakePdf(),
            'doc_type' => 'contract',
            'link_type' => 'loan',
            'link_id' => $loan->id,
        ])->assertSessionHasNoErrors();

        $document = Document::firstOrFail();
        $this->assertStringStartsWith('darlehen/DAR-2026-00042/vertraege/', $document->storage_path);
        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => Loan::class,
            'linkable_id' => $loan->id,
        ]);
    }

    public function test_ablaufdatum_erzeugt_wiedervorlage(): void
    {
        $user = $this->internalUser();

        $this->actingAs($user)->post(route('documents.store'), [
            'file' => $this->fakePdf('ausweis.pdf'),
            'doc_type' => 'id_card',
            'expires_on' => '2031-05-01',
        ])->assertSessionHasNoErrors();

        $document = Document::firstOrFail();
        $this->assertDatabaseHas('reminders', [
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'assigned_to' => $user->id,
            'status' => 'open',
        ]);
    }

    public function test_php_datei_wird_wegen_mime_type_abgelehnt(): void
    {
        $user = $this->internalUser();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('schadcode.php', "<?php echo 'boese'; ?>"),
            'doc_type' => 'other',
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_endung_muss_zum_tatsaechlichen_mime_type_passen(): void
    {
        $user = $this->internalUser();

        // PDF-Inhalt, aber als .png deklariert: ablehnen.
        $response = $this->actingAs($user)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('bild.png', "%PDF-1.4\n%%EOF"),
            'doc_type' => 'other',
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_upload_erfordert_berechtigung(): void
    {
        $user = \App\Models\User::factory()->create(['is_active' => true]); // keine Rolle, keine Berechtigung

        $this->actingAs($user)->post(route('documents.store'), [
            'file' => $this->fakePdf(),
            'doc_type' => 'other',
        ])->assertForbidden();
    }
}
