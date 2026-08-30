<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Support\Facades\Storage;

class DocumentIntegrityTest extends DocumentsTestCase
{
    private function storedDocument(): Document
    {
        return app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\n%Originalinhalt\n%%EOF",
            'sonstiges',
            'original.pdf',
            ['doc_type' => 'other', 'uploaded_by' => null],
        );
    }

    public function test_unveraenderte_datei_besteht_die_integritaetspruefung(): void
    {
        $user = $this->internalUser();
        $document = $this->storedDocument();

        $response = $this->actingAs($user)->get(route('documents.show', [$document, 'pruefen' => 1]));

        $response->assertOk();
        $response->assertSee('Datei unverändert');
        $response->assertDontSee('Integritätsprüfung fehlgeschlagen');
    }

    public function test_manipulierte_datei_faellt_bei_der_integritaetspruefung_auf(): void
    {
        $user = $this->internalUser();
        $document = $this->storedDocument();

        // Datei nachträglich manipulieren: Prüfsumme muss abweichen (rot).
        Storage::disk('documents')->put($document->storage_path, "%PDF-1.4\n%MANIPULIERT\n%%EOF");

        $response = $this->actingAs($user)->get(route('documents.show', [$document, 'pruefen' => 1]));

        $response->assertOk();
        $response->assertSee('Integritätsprüfung fehlgeschlagen');
    }

    public function test_checksum_liefert_sha256_der_gespeicherten_datei(): void
    {
        $document = $this->storedDocument();
        $storage = app(DocumentStorageInterface::class);

        $this->assertSame($document->sha256, $storage->checksum($document));
        $this->assertTrue($storage->exists($document));
    }
}
