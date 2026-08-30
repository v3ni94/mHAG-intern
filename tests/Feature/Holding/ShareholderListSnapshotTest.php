<?php

namespace Tests\Feature\Holding;

use App\Models\Document;
use App\Models\ShareholderListSnapshot;
use App\Services\Holding\ShareholdingService;
use Illuminate\Support\Facades\Storage;

/**
 * Aktionärslisten-Snapshot (Abschnitte 82/83): Nummer AL-..., JSON-Snapshot,
 * PDF-Ablage als Document mit SHA-256, unveränderlich.
 */
class ShareholderListSnapshotTest extends HoldingTestCase
{
    public function test_snapshot_erzeugt_document_mit_sha256_und_datensnapshot(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = app(ShareholdingService::class);
        $snapshot = $service->createListSnapshot(now(), $admin);

        // Nummernkreis AL, 3-stellig
        $this->assertMatchesRegularExpression('/^AL-\d{4}-\d{3}$/', $snapshot->document_number);

        // Dokument wurde über die Dokumentenablage gespeichert
        $document = Document::findOrFail($snapshot->document_id);
        Storage::disk('documents')->assertExists($document->storage_path);
        $this->assertStringStartsWith('gesellschaft/aktionaere/', $document->storage_path);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame('shareholder_list', $document->doc_type);

        // SHA-256 stimmt mit der gespeicherten Datei überein
        $storedContent = Storage::disk('documents')->get($document->storage_path);
        $this->assertSame(hash('sha256', $storedContent), $snapshot->sha256);
        $this->assertSame($document->sha256, $snapshot->sha256);

        // Daten-Snapshot (JSON): Gesellschaft, Grundkapital, Aktien, Aktionäre
        $data = $snapshot->data;
        $this->assertSame('Müller Holding AG', $data['company']['name']);
        $this->assertSame('100000.00', $data['base_capital']);
        $this->assertSame(100000, $data['total_shares']);
        $this->assertSame(now()->toDateString(), $data['as_of_date']);
        $this->assertCount(1, $data['shareholders']);
        $this->assertSame('Timo Müller', $data['shareholders'][0]['name']);
        $this->assertSame(100000, $data['shareholders'][0]['shares']);
        $this->assertSame('100.000000', $data['shareholders'][0]['percentage']);

        $this->assertSame($admin->id, $snapshot->created_by);
    }

    public function test_snapshot_ueber_http_erzeugen_und_herunterladen(): void
    {
        $this->actingAs($this->admin());

        $response = $this->post(route('shareholders.list.create'), [
            'as_of' => now()->toDateString(),
        ]);
        $response->assertRedirect(route('shareholders.index'));
        $response->assertSessionHas('success');

        $snapshot = ShareholderListSnapshot::firstOrFail();

        $download = $this->get(route('shareholders.list.download', $snapshot));
        $download->assertOk();
        $download->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $download->getContent());
    }

    public function test_snapshot_erzeugen_erfordert_berechtigung_shares_list(): void
    {
        $this->actingAs($this->readOnlyUser());

        $this->post(route('shareholders.list.create'), ['as_of' => now()->toDateString()])
            ->assertForbidden();
    }
}
