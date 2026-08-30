<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Entity;
use App\Models\User;
use App\Services\Storage\DocumentStorageInterface;

class DocumentDownloadTest extends DocumentsTestCase
{
    private function storedDocument(string $name = 'unterlage.pdf'): Document
    {
        return app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\n%Inhalt\n%%EOF",
            'sonstiges',
            $name,
            ['doc_type' => 'other', 'uploaded_by' => null],
        );
    }

    public function test_interner_benutzer_kann_herunterladen_und_wird_auditiert(): void
    {
        $user = $this->internalUser();
        $document = $this->storedDocument();

        $response = $this->actingAs($user)->get(route('documents.download', $document));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('unterlage.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame("%PDF-1.4\n%Inhalt\n%%EOF", $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.downloaded', 'auditable_id' => $document->id]);
    }

    public function test_externer_ohne_verknuepfung_erhaelt_404(): void
    {
        [$external] = $this->externalLender();
        $document = $this->storedDocument();

        $this->actingAs($external)->get(route('documents.download', $document))->assertNotFound();
        $this->actingAs($external)->get(route('documents.show', $document))->assertNotFound();
    }

    public function test_externer_mit_verknuepfung_zur_eigenen_entity_darf_zugreifen(): void
    {
        [$external, $entity] = $this->externalLender();
        $document = $this->storedDocument();

        DocumentLink::create([
            'document_id' => $document->id,
            'linkable_type' => Entity::class,
            'linkable_id' => $entity->id,
        ]);

        $this->actingAs($external)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($external)->get(route('documents.download', $document))->assertOk();
    }

    public function test_download_erfordert_berechtigung(): void
    {
        $user = User::factory()->create(['is_active' => true]); // keine Rolle
        $document = $this->storedDocument();

        $this->actingAs($user)->get(route('documents.download', $document))->assertForbidden();
    }

    public function test_externer_sieht_in_der_liste_nur_verknuepfte_dokumente(): void
    {
        [$external, $entity] = $this->externalLender();
        $visible = $this->storedDocument('sichtbare-unterlage.pdf');
        $hidden = $this->storedDocument('geheime-unterlage.pdf');

        DocumentLink::create([
            'document_id' => $visible->id,
            'linkable_type' => Entity::class,
            'linkable_id' => $entity->id,
        ]);

        $response = $this->actingAs($external)->get(route('documents.index'));
        $response->assertOk();
        $response->assertSee('sichtbare-unterlage.pdf');
        $response->assertDontSee('geheime-unterlage.pdf');
    }
}
