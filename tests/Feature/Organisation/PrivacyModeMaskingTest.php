<?php

namespace Tests\Feature\Organisation;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Services\Loans\LoanBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Datenschutzmodus (Abschnitt 126): die Maskierung ist zentral in
 * format_money() gelöst und gilt für JEDE Bildschirmausgabe, aber niemals
 * für PDF-Erzeugung, CSV-/XLSX-Exporte, Mails, Protokolle und Tests.
 */
class PrivacyModeMaskingTest extends OrganisationTestCase
{
    private const MASK = '•••••• €';

    private function privacyAdmin(): User
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['privacy_mode' => true])->save();

        return $admin->refresh();
    }

    private function seedSchedule(): void
    {
        $lender = $this->makeEntity('Geber AG', 'company');
        $borrower = $this->makeEntity('Nehmer GmbH', 'company');
        $loan = $this->makeLoan($lender, $borrower, ['loan_number' => 'DAR-2026-00777']);

        RepaymentPlanItem::create([
            'loan_id' => $loan->id,
            'item_type' => RepaymentItemType::Interest->value,
            'due_date' => today()->addDays(10)->toDateString(),
            'planned_amount' => '1234.56',
            'status' => RepaymentItemStatus::Planned->value,
            'origin' => 'assumed',
        ]);
    }

    // ------------------------------------------------------------------
    // Bildschirmausgabe
    // ------------------------------------------------------------------

    public function test_darlehensdetailseite_maskiert_alle_kennzahlen(): void
    {
        $balances = array_merge($this->zeroBalances(), [
            'disbursed' => '123456.78',
            'principal_outstanding' => '123456.78',
            'interest_open' => '123456.78',
            'total_receivable' => '123456.78',
        ]);
        $this->mock(LoanBalanceService::class, function ($mock) use ($balances) {
            $mock->shouldReceive('balances')->andReturn($balances);
            $mock->shouldReceive('statementRows')->andReturn(['rows' => [], 'total' => '0.00']);
        });

        $admin = $this->privacyAdmin();
        $loan = $this->makeLoan(
            $this->makeEntity('Geber AG', 'company'),
            $this->makeEntity('Nehmer GmbH', 'company'),
            ['principal_amount' => '123456.78'],
        );
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => $loan->effective_from]);

        $response = $this->actingAs($admin)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee(self::MASK, false);
        $response->assertDontSee('123.456,78');
    }

    public function test_reporttabelle_maskiert_betraege_auf_dem_bildschirm(): void
    {
        $this->seedSchedule();
        $admin = $this->privacyAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', 'faelligkeiten'));

        $response->assertOk();
        $response->assertSee(self::MASK, false);
        $response->assertDontSee('1.234,56');
    }

    public function test_ohne_datenschutzmodus_erscheinen_echte_betraege(): void
    {
        $this->seedSchedule();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', 'faelligkeiten'));

        $response->assertOk();
        $response->assertSee('1.234,56 EUR');
        $response->assertDontSee(self::MASK, false);
    }

    // ------------------------------------------------------------------
    // Exporte und PDF: nie maskieren
    // ------------------------------------------------------------------

    public function test_csv_export_enthaelt_echte_betraege_trotz_datenschutzmodus(): void
    {
        $this->seedSchedule();
        $admin = $this->privacyAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'csv']));

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringContainsString('1.234,56 EUR', $content);
        $this->assertStringNotContainsString('••••••', $content);
    }

    public function test_xlsx_export_enthaelt_echte_betraege_trotz_datenschutzmodus(): void
    {
        $this->seedSchedule();
        $admin = $this->privacyAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'xlsx']));

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringStartsWith('PK', $content);

        $file = tempnam(sys_get_temp_dir(), 'xlsxprivacy');
        file_put_contents($file, $content);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        @unlink($file);

        $this->assertStringContainsString('1.234,56 EUR', $xml.$shared);
        $this->assertStringNotContainsString('••••••', $xml.$shared);
    }

    public function test_pdf_export_wird_ausgeliefert_und_nicht_maskiert(): void
    {
        $this->seedSchedule();
        $admin = $this->privacyAdmin();

        $response = $this->actingAs($admin)->get(route('reports.show', ['key' => 'faelligkeiten', 'format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    /**
     * Kernfall der Trennung: Eine PDF-Erzeugung, die innerhalb einer normalen
     * Bildschirmanfrage läuft (z. B. Vertrags-PDF beim Finalisieren oder ein
     * Aktionärslisten-Snapshot), darf keine maskierten Beträge festschreiben.
     */
    public function test_pdf_erzeugung_im_bildschirmkontext_enthaelt_echte_betraege(): void
    {
        $admin = $this->privacyAdmin();
        $this->actingAs($admin);

        $loan = $this->makeLoan(
            $this->makeEntity('Geber AG', 'company'),
            $this->makeEntity('Nehmer GmbH', 'company'),
            ['principal_amount' => '123456.78'],
        );
        $loan->load(['lender', 'borrower']);

        // Bildschirmkontext einer gewöhnlichen Seite herstellen.
        $request = Request::create(route('loans.show', $loan), 'GET');
        $request->setUserResolver(fn () => $admin);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName('loans.show'));
        $this->app->instance('request', $request);

        // Bildschirmausgabe: maskiert.
        $this->assertTrue(money_masking_active());
        $this->assertSame(self::MASK, format_money('123456.78'));

        // PDF-Erzeugung im selben Kontext: echte Beträge.
        $pdf = Pdf::loadView('loans.statement-pdf', [
            'loan' => $loan,
            'rows' => [['label' => 'Ausgezahltes Kapital', 'amount' => '123456.78', 'sign' => '+']],
            'total' => '123456.78',
            'asOfDate' => today(),
            'documentNumber' => $loan->loan_number,
        ]);

        $text = (string) $pdf->getDomPDF()->getDom()->textContent;
        $this->assertStringContainsString('123.456,78', $text);
        $this->assertStringNotContainsString('••••••', $text);
    }

    // ------------------------------------------------------------------
    // Kontextschalter und Aufrufe außerhalb einer Web-Sitzung
    // ------------------------------------------------------------------

    public function test_aufruf_ohne_web_kontext_wird_nicht_maskiert(): void
    {
        $admin = $this->privacyAdmin();
        $this->actingAs($admin);

        // Keine aufgelöste Route: Konsolenbefehl, Scheduler, Queue, Unit-Test.
        $this->app->instance('request', Request::create('/', 'GET'));

        $this->assertFalse(money_masking_active());
        $this->assertSame('123.456,78 EUR', format_money('123456.78'));
    }

    public function test_expliziter_parameter_und_kontextschalter_erzwingen_echte_betraege(): void
    {
        $admin = $this->privacyAdmin();
        $this->actingAs($admin);

        $request = Request::create(route('reports.index'), 'GET');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName('reports.index'));
        $this->app->instance('request', $request);

        $this->assertSame(self::MASK, format_money('123456.78'));
        $this->assertSame('123.456,78 EUR', format_money('123456.78', 'EUR', false));
        $this->assertSame('123.456,78 EUR', without_money_masking(fn () => format_money('123456.78')));

        // Nach dem Kontextschalter gilt die Maskierung wieder.
        $this->assertSame(self::MASK, format_money('123456.78'));
    }
}
