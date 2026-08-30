<?php

namespace Tests\Feature\Holding;

use App\Enums\ResolutionStatus;
use App\Enums\SignatureRequestStatus;
use App\Models\Document;
use App\Models\Resolution;
use App\Models\SignatureRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Beschluss-Workflow (Abschnitt 93) bis zum Status "unterschrieben":
 * Erfassen -> Abstimmung -> Ergebnis -> PDF/Finalisieren -> Signatur.
 */
class ResolutionWorkflowTest extends HoldingTestCase
{
    public function test_kompletter_workflow_bis_signed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // 1./2. Antrag und Begründung erfassen (Vorstandsbeschluss)
        $response = $this->post(route('resolutions.store'), [
            'title' => 'Antrag Verkauf Firma XY',
            'type' => 'board',
            'company_entity_id' => $this->mhagEntityId(),
            'motion' => 'Die Beteiligung an der Firma XY wird veräußert.',
            'reasoning' => 'Strategische Neuausrichtung.',
            'resolution_text' => 'Der Vorstand beschließt den Verkauf der Firma XY.',
            'resolved_on' => now()->toDateString(),
            'conflict_of_interest' => '0',
        ]);

        $resolution = Resolution::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('resolutions.show', $resolution));

        // Nummer aus dem Nummernkreis der Beschlussart (VOR, 3-stellig)
        $this->assertMatchesRegularExpression('/^VOR-\d{4}-\d{3}$/', $resolution->resolution_number);
        $this->assertSame(ResolutionStatus::Draft, $resolution->status);
        $this->assertNotNull($resolution->recorded_at);

        // Teilnehmer aus dem Organ vorbelegt (Vorstand: Timo Müller)
        $this->assertCount(1, $resolution->participants);
        $participant = $resolution->participants->first();
        $this->assertSame('Timo Müller', $participant->entity->display_name);

        // 5. Abstimmung dokumentieren
        $this->post(route('resolutions.status', $resolution), ['status' => 'voting'])->assertRedirect();
        $this->post(route('resolutions.vote', $resolution), [
            'votes' => [$participant->id => 'yes'],
        ])->assertRedirect(route('resolutions.show', $resolution));

        $summary = app(\App\Services\Holding\ResolutionService::class)->voteSummary($resolution->fresh());
        $this->assertSame(['yes' => 1, 'no' => 0, 'abstain' => 0, 'absent' => 0], $summary);

        // 6. Ergebnis erfassen
        $this->post(route('resolutions.status', $resolution), ['status' => 'accepted'])->assertRedirect();
        $resolution->refresh();
        $this->assertSame(ResolutionStatus::Accepted, $resolution->status);
        $this->assertSame('accepted', $resolution->result);

        // 7. PDF erzeugen (Finalisieren) -> Ablage gesellschaft/beschluesse
        $this->post(route('resolutions.finalize', $resolution))->assertRedirect();
        $resolution->refresh();
        $this->assertSame(ResolutionStatus::ForSignature, $resolution->status);
        $this->assertNotNull($resolution->document_id);

        $pdf = Document::findOrFail($resolution->document_id);
        Storage::disk('documents')->assertExists($pdf->storage_path);
        $this->assertStringStartsWith('gesellschaft/beschluesse/', $pdf->storage_path);
        $this->assertSame($pdf->sha256, hash('sha256', Storage::disk('documents')->get($pdf->storage_path)));

        // 8. Signaturanfrage erstellen und versenden
        $this->post(route('signatures.store'), [
            'subject_type' => 'resolution',
            'subject_id' => $resolution->id,
            'participants' => [
                ['entity_id' => $participant->entity_id, 'role' => 'Vorstand', 'email' => 'timo@muellerhv.de'],
            ],
            'send_immediately' => '1',
        ])->assertRedirect();

        $signatureRequest = SignatureRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(SignatureRequestStatus::Sent, $signatureRequest->status);
        $this->assertSame('manual', $signatureRequest->provider);
        $signer = $signatureRequest->participants->first();
        $this->assertSame('sent', $signer->status->value);
        $this->assertNotNull($signer->status_changed_at);

        // 9. Teilnehmerstatus manuell pflegen: unterschrieben
        $this->post(route('signatures.mark', $signatureRequest), [
            'participant_id' => $signer->id,
            'status' => 'signed',
        ])->assertRedirect();

        $this->assertSame('signed', $signer->fresh()->status->value);
        $this->assertSame(SignatureRequestStatus::InProgress, $signatureRequest->fresh()->status);

        // 10. Signierte Fassung hochladen -> Anfrage completed, Beschluss signed
        $signedFile = UploadedFile::fake()->createWithContent('beschluss-signiert.pdf', $this->minimalPdfContent());

        $this->post(route('signatures.attach-signed', $signatureRequest), [
            'signed_file' => $signedFile,
        ])->assertRedirect();

        $signatureRequest->refresh();
        $this->assertSame(SignatureRequestStatus::Completed, $signatureRequest->status);
        $this->assertNotSame($pdf->id, $signatureRequest->document_id);

        $signedDocument = Document::findOrFail($signatureRequest->document_id);
        Storage::disk('documents')->assertExists($signedDocument->storage_path);

        // Vorgangsstatus fortgeschrieben (Abschnitt 100)
        $this->assertSame(ResolutionStatus::Signed, $resolution->fresh()->status);
    }

    public function test_finalisieren_ohne_ergebnis_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('resolutions.store'), [
            'title' => 'Beschluss ohne Ergebnis',
            'type' => 'board',
            'company_entity_id' => $this->mhagEntityId(),
        ]);
        $resolution = Resolution::query()->latest('id')->firstOrFail();

        $this->post(route('resolutions.finalize', $resolution))
            ->assertRedirect(route('resolutions.show', $resolution))
            ->assertSessionHas('danger');

        $this->assertNull($resolution->fresh()->document_id);
        $this->assertSame(ResolutionStatus::Draft, $resolution->fresh()->status);
    }

    public function test_aufsichtsratsbeschluss_belegt_teilnehmer_aus_aufsichtsrat_vor(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('resolutions.store'), [
            'title' => 'AR-Beschluss',
            'type' => 'supervisory_board',
            'company_entity_id' => $this->mhagEntityId(),
        ]);

        $resolution = Resolution::query()->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^AR-\d{4}-\d{3}$/', $resolution->resolution_number);

        // Aufsichtsrat der MHAG: Walprecht (Vorsitz), Schuhwirt, Enns
        $names = $resolution->participants->map(fn ($p) => $p->entity->display_name)->sort()->values()->all();
        $this->assertSame(['David Enns', 'Frederik Schuhwirt', 'Jan Walprecht'], $names);
    }

    public function test_historische_erfassung_trennt_beschlussdatum_und_erfassungsdatum(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('resolutions.store'), [
            'title' => 'Historischer Beschluss',
            'type' => 'other',
            'company_entity_id' => $this->mhagEntityId(),
            'resolved_on' => '2024-03-15',
        ]);

        $resolution = Resolution::query()->latest('id')->firstOrFail();
        $this->assertSame('2024-03-15', $resolution->resolved_on->toDateString());
        // Erfassungsdatum ist der technische Zeitpunkt, keine Rückdatierung
        $this->assertTrue($resolution->recorded_at->isSameDay(now()));
    }

    public function test_abstimmung_erfordert_berechtigung(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('resolutions.store'), [
            'title' => 'Beschluss',
            'type' => 'board',
            'company_entity_id' => $this->mhagEntityId(),
        ]);
        $resolution = Resolution::query()->latest('id')->firstOrFail();
        $participant = $resolution->participants->first();

        $this->actingAs($this->readOnlyUser());
        $this->post(route('resolutions.vote', $resolution), [
            'votes' => [$participant->id => 'yes'],
        ])->assertForbidden();
    }
}
