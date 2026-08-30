<?php

namespace Tests\Feature\Documents;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Loan;
use Illuminate\Support\Facades\Storage;

class DocumentContractWorkflowTest extends DocumentsTestCase
{
    private function makeTemplate(): ContractTemplate
    {
        $template = ContractTemplate::create([
            'name' => 'Testvorlage Darlehen',
            'category' => 'Privatdarlehen',
            'is_active' => true,
        ]);

        $template->versions()->create([
            'version' => '1.0',
            'body' => '<h1>Darlehensvertrag {{darlehensnummer}}</h1>'
                .'<p>Geber: {{darlehensgeber.name}}, {{darlehensgeber.anschrift}}</p>'
                .'<p>Nehmer: {{darlehensnehmer.name}}</p>'
                .'<p>Betrag: {{darlehensbetrag}} zu {{zinssatz}}</p>'
                .'<p>Laufzeit: {{beginn}} bis {{ende}}</p>',
            'placeholders' => [
                'darlehensnummer', 'darlehensgeber.name', 'darlehensgeber.anschrift',
                'darlehensnehmer.name', 'darlehensbetrag', 'zinssatz', 'beginn', 'ende',
            ],
        ]);

        return $template;
    }

    private function makeCompleteLoan(): Loan
    {
        $loan = $this->makeLoan(['loan_number' => 'DAR-2026-00077']);
        $loan->interestTerms()->create([
            'rate' => '4.500000',
            'valid_from' => '2026-01-01',
        ]);

        return $loan;
    }

    public function test_vertragsentwurf_wird_mit_befuellten_platzhaltern_angelegt(): void
    {
        $user = $this->internalUser();
        $template = $this->makeTemplate();
        $loan = $this->makeCompleteLoan();

        $this->actingAs($user)->post(route('contracts.store'), [
            'title' => 'Darlehensvertrag Test',
            'contract_template_version_id' => $template->versions()->first()->id,
            'loan_id' => $loan->id,
        ])->assertSessionHasNoErrors();

        $contract = Contract::firstOrFail();
        $this->assertSame('draft', $contract->status);
        $this->assertStringStartsWith('ENTWURF-', $contract->contract_number);
        $this->assertStringContainsString('DAR-2026-00077', $contract->body_snapshot);
        $this->assertStringContainsString('Geber GmbH', $contract->body_snapshot);
        $this->assertStringContainsString('50.000,00 EUR', $contract->body_snapshot);
        $this->assertStringContainsString('4,50 %', $contract->body_snapshot);

        $response = $this->actingAs($user)->get(route('contracts.show', $contract));
        $response->assertOk();
        $response->assertSee('ENTWURF');
    }

    public function test_vorlagenaenderung_veraendert_bestehenden_vertrag_nicht(): void
    {
        $user = $this->internalUser();
        $template = $this->makeTemplate();
        $version1 = $template->versions()->first();
        $loan = $this->makeCompleteLoan();

        $this->actingAs($user)->post(route('contracts.store'), [
            'title' => 'Snapshot-Test',
            'contract_template_version_id' => $version1->id,
            'loan_id' => $loan->id,
        ]);

        $contract = Contract::firstOrFail();
        $snapshotBefore = $contract->body_snapshot;
        $bodyV1Before = $version1->body;

        // Vorlage "ändern" = neue Version anlegen (Abschnitt 54).
        $this->actingAs($user)->post(route('contract-templates.versions.store', $template), [
            'version' => '2.0',
            'body' => '<h1>KOMPLETT NEUER TEXT {{darlehensnummer}}</h1>',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $template->versions()->count());
        $this->assertSame($bodyV1Before, $version1->fresh()->body, 'Bestehende Vorlagenversionen dürfen nie verändert werden.');
        $this->assertSame($snapshotBefore, $contract->fresh()->body_snapshot, 'Der Vertrags-Snapshot darf sich durch Vorlagenänderungen nicht ändern.');
        $this->assertStringNotContainsString('KOMPLETT NEUER TEXT', $contract->fresh()->body_snapshot);
    }

    public function test_fehlende_platzhalter_blockieren_die_finalisierung(): void
    {
        $user = $this->internalUser();
        $template = $this->makeTemplate();
        // Darlehen ohne Vertragsende und ohne Zinssatz: {{ende}} und {{zinssatz}} fehlen.
        $loan = $this->makeLoan(['contract_end' => null]);

        $this->actingAs($user)->post(route('contracts.store'), [
            'title' => 'Unvollständiger Vertrag',
            'contract_template_version_id' => $template->versions()->first()->id,
            'loan_id' => $loan->id,
        ]);

        $contract = Contract::firstOrFail();

        $response = $this->actingAs($user)->post(route('contracts.finalize', $contract));
        $response->assertSessionHasErrors('contract');

        $contract->refresh();
        $this->assertSame('draft', $contract->status);
        $this->assertStringStartsWith('ENTWURF-', $contract->contract_number);
        $this->assertNull($contract->finalized_at);
        $this->assertNull($contract->document_id);
    }

    public function test_finalisierung_vergibt_nummer_und_erzeugt_pdf_in_der_dokumentenablage(): void
    {
        $user = $this->internalUser();
        $template = $this->makeTemplate();
        $loan = $this->makeCompleteLoan();

        $this->actingAs($user)->post(route('contracts.store'), [
            'title' => 'Finaler Vertrag',
            'contract_template_version_id' => $template->versions()->first()->id,
            'loan_id' => $loan->id,
        ]);

        $contract = Contract::firstOrFail();

        $this->actingAs($user)->post(route('contracts.finalize', $contract))->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('final', $contract->status);
        $this->assertMatchesRegularExpression('/^VER-\d{4}-\d{5}$/', $contract->contract_number);
        $this->assertNotNull($contract->finalized_at);
        $this->assertNotNull($contract->document_id);

        $document = $contract->document;
        $this->assertSame('contract', $document->doc_type);
        $this->assertStringStartsWith('darlehen/DAR-2026-00077/vertraege/', $document->storage_path);
        Storage::disk('documents')->assertExists($document->storage_path);
        $this->assertSame($document->sha256, hash('sha256', Storage::disk('documents')->get($document->storage_path)));

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => Contract::class,
            'linkable_id' => $contract->id,
        ]);
        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => Loan::class,
            'linkable_id' => $loan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contracts.finalized', 'auditable_id' => $contract->id]);
    }

    public function test_nachtrag_wird_erfasst_und_angezeigt(): void
    {
        $user = $this->internalUser();
        $template = $this->makeTemplate();
        $loan = $this->makeCompleteLoan();

        $this->actingAs($user)->post(route('contracts.store'), [
            'title' => 'Vertrag mit Nachtrag',
            'contract_template_version_id' => $template->versions()->first()->id,
            'loan_id' => $loan->id,
        ]);
        $contract = Contract::firstOrFail();

        $this->actingAs($user)->post(route('contracts.amendments.store', $contract), [
            'amendment_type' => 'term_extension',
            'description' => 'Laufzeit um zwölf Monate verlängert.',
            'effective_date' => '2027-01-01',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contract_amendments', [
            'contract_id' => $contract->id,
            'amendment_type' => 'term_extension',
        ]);

        $response = $this->actingAs($user)->get(route('contracts.show', $contract));
        $response->assertSee('Laufzeitverlängerung');
        $response->assertSee('Laufzeit um zwölf Monate verlängert.');
    }
}
