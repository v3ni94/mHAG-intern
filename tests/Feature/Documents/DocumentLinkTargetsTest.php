<?php

namespace Tests\Feature\Documents;

use App\Http\Controllers\DocumentController;
use App\Models\CorporateBody;
use App\Models\CorporateBodyMember;
use App\Models\Entity;
use App\Models\Guarantee;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Verknüpfungsziele für Dokumente (Masterprompt 57 und 61).
 *
 * Geprüft werden die zuvor fehlenden Ziele Zahlung, Bürgschaft, Beteiligung
 * und Organmitglied sowie die Zugriffsprüfung: Ein externer Benutzer darf
 * kein Dokument an einen fremden Vorgang hängen.
 */
class DocumentLinkTargetsTest extends DocumentsTestCase
{
    private User $intern;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intern = $this->internalUser();
    }

    private function person(string $name): Entity
    {
        $entity = Entity::create([
            'type' => 'person',
            'display_name' => $name,
            'status' => 'active',
        ]);
        $entity->person()->create([
            'first_name' => explode(' ', $name)[0],
            'last_name' => explode(' ', $name)[1] ?? 'Muster',
        ]);

        return $entity;
    }

    private function darlehen(Entity $geber, Entity $nehmer, string $nummer = 'DAR-2026-00001'): Loan
    {
        return Loan::create([
            'loan_number' => $nummer,
            'title' => 'Testdarlehen',
            'lender_entity_id' => $geber->id,
            'borrower_entity_id' => $nehmer->id,
            'loan_type_id' => LoanType::create(['name' => 'Endfällig', 'code' => 'endf-'.$nummer])->id,
            'effective_from' => '2026-01-01',
            'principal_amount' => '100000.00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => 'active',
        ]);
    }

    private function datei(): UploadedFile
    {
        return $this->fakePdf('nachweis.pdf');
    }

    public function test_alle_geforderten_verknuepfungsziele_sind_vorhanden(): void
    {
        // Masterprompt 57 nennt diese Ziele ausdrücklich
        foreach ([
            'entity', 'loan', 'contract', 'payment', 'security', 'guarantee',
            'share_transaction', 'resolution', 'corporate_body_member', 'investment',
            'identity_document',
        ] as $typ) {
            $this->assertArrayHasKey($typ, DocumentController::LINKABLE_TYPES, "Verknüpfungsziel {$typ} fehlt");
            $this->assertArrayHasKey($typ, DocumentController::LINKABLE_LABELS, "Bezeichnung für {$typ} fehlt");
        }
    }

    public function test_zahlungsbeleg_wird_am_vorgang_abgelegt(): void
    {
        $geber = $this->person('Maria Geberin');
        $nehmer = $this->person('Paul Nehmer');
        $loan = $this->darlehen($geber, $nehmer);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payer_entity_id' => $nehmer->id,
            'payee_entity_id' => $geber->id,
            'payment_date' => '2026-02-01',
            'amount' => '500.00',
            'direction' => 'incoming',
            'origin' => 'bank_import',
        ]);

        $this->actingAs($this->intern)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'payment_receipt',
            'link_type' => 'payment',
            'link_id' => $payment->id,
        ])->assertRedirect();

        $document = \App\Models\Document::latest('id')->firstOrFail();

        // Ablage laut Masterprompt 61 unter darlehen/{nummer}/zahlungen
        $this->assertStringContainsString('darlehen/DAR-2026-00001/zahlungen', $document->storage_path);
        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => Payment::class,
            'linkable_id' => $payment->id,
        ]);
    }

    public function test_buergschaftsurkunde_wird_bei_den_sicherheiten_abgelegt(): void
    {
        $geber = $this->person('Maria Geberin');
        $nehmer = $this->person('Paul Nehmer');
        $loan = $this->darlehen($geber, $nehmer);

        $guarantee = Guarantee::create([
            'loan_id' => $loan->id,
            'guarantor_entity_id' => $geber->id,
            'guarantee_type' => 'Höchstbetragsbürgschaft',
            'max_amount' => '50000.00',
            'status' => 'active',
        ]);

        $this->actingAs($this->intern)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'guarantee',
            'link_type' => 'guarantee',
            'link_id' => $guarantee->id,
        ])->assertRedirect();

        $document = \App\Models\Document::latest('id')->firstOrFail();
        $this->assertStringContainsString('darlehen/DAR-2026-00001/sicherheiten', $document->storage_path);
        $this->assertDatabaseHas('document_links', [
            'linkable_type' => Guarantee::class,
            'linkable_id' => $guarantee->id,
        ]);
    }

    public function test_beteiligung_und_organmitglied_landen_im_gesellschaftsordner(): void
    {
        $gesellschaft = Entity::create(['type' => 'company', 'display_name' => 'Beispiel GmbH', 'status' => 'active']);
        $gesellschaft->company()->create(['name' => 'Beispiel GmbH', 'legal_form' => 'GmbH']);

        $investment = Investment::create([
            'company_entity_id' => $gesellschaft->id,
            'share_percentage' => '100.000000',
            'status' => 'active',
        ]);

        $this->actingAs($this->intern)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'other',
            'link_type' => 'investment',
            'link_id' => $investment->id,
        ])->assertRedirect();

        $this->assertStringContainsString(
            'gesellschaft/beteiligungen',
            \App\Models\Document::latest('id')->firstOrFail()->storage_path,
        );

        $person = $this->person('Jan Walprecht');
        $gremium = CorporateBody::create([
            'company_entity_id' => $gesellschaft->id,
            'type' => 'supervisory_board',
            'name' => 'Aufsichtsrat',
        ]);
        $mitglied = CorporateBodyMember::create([
            'corporate_body_id' => $gremium->id,
            'person_entity_id' => $person->id,
            'role' => 'Aufsichtsratsvorsitzender',
            'is_chair' => true,
            'status' => 'active',
        ]);

        $this->actingAs($this->intern)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'other',
            'link_type' => 'corporate_body_member',
            'link_id' => $mitglied->id,
        ])->assertRedirect();

        // Getrennte Ablage je Gremium
        $this->assertStringContainsString(
            'gesellschaft/aufsichtsrat',
            \App\Models\Document::latest('id')->firstOrFail()->storage_path,
        );
    }

    public function test_externer_benutzer_kann_kein_dokument_an_fremden_vorgang_haengen(): void
    {
        $geber = $this->person('Maria Geberin');
        $eigen = $this->person('Paul Nehmer');
        $fremd = $this->person('Fremde Person');

        $eigenesDarlehen = $this->darlehen($geber, $eigen, 'DAR-2026-00001');
        $fremdesDarlehen = $this->darlehen($geber, $fremd, 'DAR-2026-00002');

        $fremdeZahlung = Payment::create([
            'loan_id' => $fremdesDarlehen->id,
            'payer_entity_id' => $fremd->id,
            'payee_entity_id' => $geber->id,
            'payment_date' => '2026-02-01',
            'amount' => '500.00',
            'direction' => 'incoming',
            'origin' => 'bank_import',
        ]);

        $externer = User::factory()->create(['entity_id' => $eigen->id, 'is_active' => true]);
        $externer->assignRole('Darlehensnehmer');
        $externer->givePermissionTo('documents.upload');
        $externer->entityAssignments()->create(['entity_id' => $eigen->id, 'context' => 'self']);

        // Verknüpfung mit der fremden Zahlung muss abgelehnt werden
        $this->actingAs($externer)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'payment_receipt',
            'link_type' => 'payment',
            'link_id' => $fremdeZahlung->id,
        ])->assertSessionHasErrors('link_id');

        $this->assertSame(0, \App\Models\Document::count());

        // Am eigenen Darlehen ist die Ablage erlaubt
        $this->actingAs($externer)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'payment_receipt',
            'link_type' => 'loan',
            'link_id' => $eigenesDarlehen->id,
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\Document::count());
    }

    public function test_externer_benutzer_erreicht_keine_gesellschaftsobjekte(): void
    {
        $gesellschaft = Entity::create(['type' => 'company', 'display_name' => 'Beispiel GmbH', 'status' => 'active']);
        $investment = Investment::create([
            'company_entity_id' => $gesellschaft->id,
            'share_percentage' => '100.000000',
            'status' => 'active',
        ]);

        $eigen = $this->person('Paul Nehmer');
        $externer = User::factory()->create(['entity_id' => $eigen->id, 'is_active' => true]);
        $externer->assignRole('Darlehensnehmer');
        $externer->givePermissionTo('documents.upload');
        $externer->entityAssignments()->create(['entity_id' => $eigen->id, 'context' => 'self']);

        $this->actingAs($externer)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'other',
            'link_type' => 'investment',
            'link_id' => $investment->id,
        ])->assertSessionHasErrors('link_id');

        $this->assertSame(0, \App\Models\Document::count());
    }

    public function test_unbekannter_verknuepfungstyp_wird_abgelehnt(): void
    {
        // Freie Klassennamen dürfen nicht auflösbar sein
        $this->actingAs($this->intern)->post(route('documents.store'), [
            'file' => $this->datei(),
            'doc_type' => 'other',
            'link_type' => 'App\Models\User',
            'link_id' => $this->intern->id,
        ])->assertSessionHasErrors();

        $this->assertSame(0, \App\Models\Document::count());
    }
}
