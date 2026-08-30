<?php

namespace Tests\Feature\Organisation;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\RepaymentPlanItem;
use App\Services\Loans\LoanBalanceService;

class ReportExportTest extends OrganisationTestCase
{
    private function seedSchedule(): void
    {
        $lender = $this->makeEntity('Geber AG', 'company');
        $borrower = $this->makeEntity('Nehmer GmbH', 'company');
        $loan = $this->makeLoan($lender, $borrower, ['loan_number' => 'DAR-2026-00042']);

        RepaymentPlanItem::create([
            'loan_id' => $loan->id,
            'item_type' => RepaymentItemType::Interest->value,
            'due_date' => today()->addDays(10)->toDateString(),
            'planned_amount' => '1234.56',
            'status' => RepaymentItemStatus::Planned->value,
            'origin' => 'assumed',
        ]);
        RepaymentPlanItem::create([
            'loan_id' => $loan->id,
            'item_type' => RepaymentItemType::Principal->value,
            'due_date' => today()->addDays(20)->toDateString(),
            'planned_amount' => '50000.00',
            'status' => RepaymentItemStatus::Planned->value,
            'origin' => 'assumed',
        ]);
    }

    public function test_csv_export_faelligkeiten_mit_bom_semikolon_und_deutschen_headern(): void
    {
        $this->seedSchedule();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV muss mit UTF-8-BOM beginnen.');
        // fputcsv setzt Felder mit Leerzeichen in Anführungszeichen (gültiges CSV)
        $this->assertStringContainsString('"Fällig am";Darlehensnummer;Art;Sollbetrag;Status', $content);
        $this->assertStringContainsString('DAR-2026-00042', $content);
        $this->assertStringContainsString('1.234,56 EUR', $content);
        $this->assertStringContainsString('50.000,00 EUR', $content);
    }

    public function test_filter_werden_in_den_export_uebernommen(): void
    {
        $this->seedSchedule();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', [
            'key' => 'faelligkeiten',
            'format' => 'csv',
            'typ' => 'interest',
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('1.234,56 EUR', $content);
        $this->assertStringNotContainsString('50.000,00 EUR', $content, 'Tilgungszeile muss durch den Filter entfallen.');
    }

    public function test_xlsx_export_liefert_gueltiges_zip_paket(): void
    {
        $this->seedSchedule();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->getContent();
        $this->assertStringStartsWith('PK', $content, 'XLSX muss ein ZIP-Paket sein.');

        $file = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($file, $content);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($file);

        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('DAR-2026-00042', $sheet);
    }

    public function test_externe_sehen_nur_eigene_darlehen_im_report(): void
    {
        $this->seedSchedule(); // fremdes Darlehen

        $ownEntity = $this->makeEntity('Eigene Entität');
        $external = $this->makeUserWithRole('Darlehensgeber', $ownEntity->id);

        $response = $this->actingAs($external)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'csv']));

        $response->assertOk();
        $this->assertStringNotContainsString('DAR-2026-00042', $response->getContent());
    }

    public function test_darlehensbestand_nutzt_balance_service(): void
    {
        $this->seedSchedule();

        $this->mock(LoanBalanceService::class, function ($mock) {
            $mock->shouldReceive('balances')->andReturn(array_merge($this->zeroBalances(), [
                'principal_outstanding' => '99999.99',
                'total_receivable' => '101000.00',
            ]));
        });

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('reports.show', ['key' => 'darlehensbestand', 'format' => 'csv']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Offenes Kapital', $content);
        $this->assertStringContainsString('99.999,99 EUR', $content);
    }
}
