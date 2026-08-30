<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Support\Facades\Storage;

class DocumentArchiveDeleteTest extends DocumentsTestCase
{
    private function storedDocument(): Document
    {
        return app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\n%Inhalt\n%%EOF",
            'sonstiges',
            'ablage.pdf',
            ['doc_type' => 'other', 'uploaded_by' => null],
        );
    }

    public function test_archivieren_setzt_status_und_schreibt_audit(): void
    {
        $user = $this->internalUser();
        $document = $this->storedDocument();

        $this->actingAs($user)->post(route('documents.archive', $document))->assertRedirect();

        $this->assertSame(DocumentStatus::Archived, $document->fresh()->status);
        Storage::disk('documents')->assertExists($document->storage_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.archived', 'auditable_id' => $document->id]);
    }

    public function test_endgueltiges_loeschen_entfernt_datei_und_auditiert(): void
    {
        $user = $this->internalUser(); // Administrator hat documents.delete
        $document = $this->storedDocument();
        $path = $document->storage_path;

        $this->actingAs($user)->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'));

        Storage::disk('documents')->assertMissing($path);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.deleted', 'auditable_id' => $document->id]);
    }

    public function test_loeschen_ohne_berechtigung_ist_verboten(): void
    {
        $user = $this->internalUser('Buchhaltung'); // hat kein documents.delete
        $document = $this->storedDocument();

        $this->actingAs($user)->delete(route('documents.destroy', $document))->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_archivieren_ohne_berechtigung_ist_verboten(): void
    {
        $user = $this->internalUser('Sachbearbeiter'); // hat kein documents.archive
        $document = $this->storedDocument();

        $this->actingAs($user)->post(route('documents.archive', $document))->assertForbidden();
    }
}
