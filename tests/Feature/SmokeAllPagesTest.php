<?php

namespace Tests\Feature;

use App\Enums\PaymentOrigin;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\Setting;
use App\Models\ShareTransaction;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rauchtest über alle Seiten: ruft als Administrator jede benannte GET-Route
 * mit realistischen Demodaten auf und stellt sicher, dass keine Seite mit
 * einem Serverfehler antwortet.
 */
class SmokeAllPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin = User::where('email', 'timo@muellerhv.de')->firstOrFail();
        // 2FA als eingerichtet markieren, damit die Pflicht-Middleware nicht umleitet
        $this->admin->forceFill([
            'two_factor_secret' => 'TESTSECRET234567',
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function test_alle_seiten_rendern_ohne_serverfehler(): void
    {
        $data = $this->createDemoData();

        $failures = [];
        $skipped = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            // Nicht sinnvoll im Rauchtest: Framework-/Token-Routen
            if (in_array($name, ['storage.local', 'invitations.show', 'password.reset', 'admin.backups.download'], true)) {
                continue;
            }

            if ($name === 'reports.show') {
                $urls = array_map(
                    fn ($key) => [$name, ['key' => $key]],
                    array_keys(\App\Http\Controllers\ReportController::REPORTS),
                );
            } elseif ($name === 'help.page') {
                $urls = array_map(
                    fn ($slug) => [$name, ['slug' => $slug]],
                    array_keys(\App\Http\Controllers\HelpController::PAGES),
                );
            } else {
                $params = [];
                $resolvable = true;
                foreach ($route->parameterNames() as $param) {
                    if ($param === 'entity' && str_starts_with($name, 'companies.')) {
                        $params[$param] = $data['company_entity'];
                    } elseif (array_key_exists($param, $data)) {
                        $params[$param] = $data[$param];
                    } else {
                        $resolvable = false;
                    }
                }
                if (! $resolvable) {
                    $skipped[] = $name;
                    continue;
                }
                $urls = [[$name, $params]];
            }

            foreach ($urls as [$routeName, $routeParams]) {
                $response = $this->actingAs($this->admin)->get(route($routeName, $routeParams));
                // Administrator mit allen Rechten: jede Seite muss rendern (200)
                // oder sauber umleiten (302, z. B. Gastrouten); 4xx/5xx sind Fehler.
                if (! in_array($response->getStatusCode(), [200, 302], true)) {
                    $failures[] = $routeName.' ('.json_encode($routeParams).') -> '.$response->getStatusCode();
                }
            }
        }

        $this->assertSame([], $failures, "Seiten mit Serverfehler:\n".implode("\n", $failures));
        $this->assertSame([], $skipped, "Routen ohne Parameter-Mapping im Rauchtest:\n".implode("\n", $skipped));
    }

    /**
     * Legt zusammenhängende Demodaten an und liefert die Routenparameter-Map.
     */
    private function createDemoData(): array
    {
        $mhag = Entity::where('internal_number', 'ENT-MHAG')->firstOrFail();
        $timo = Entity::where('internal_number', 'ENT-P-TMUELLER')->firstOrFail();

        $loan = Loan::create([
            'loan_number' => 'DAR-2026-90001',
            'title' => 'Smoke-Darlehen',
            'lender_entity_id' => $mhag->id,
            'borrower_entity_id' => $timo->id,
            'loan_type_id' => LoanType::first()->id,
            'effective_from' => now()->subMonths(3)->startOfMonth()->toDateString(),
            'contract_end' => now()->addYear()->toDateString(),
            'principal_amount' => '50000.00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => 'active',
        ]);
        $loan->interestTerms()->create([
            'rate' => '5.000000',
            'valid_from' => $loan->effective_from->toDateString(),
        ]);
        app(DisbursementService::class)->plan($loan, [
            'planned_amount' => '50000.00',
            'planned_date' => $loan->effective_from->toDateString(),
        ], $this->admin);
        app(LoanRecalculationService::class)->recalculate($loan, 'smoke.setup', $loan->effective_from, $this->admin);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payer_entity_id' => $timo->id,
            'payee_entity_id' => $mhag->id,
            'payment_date' => now()->subMonth()->toDateString(),
            'amount' => '200.00',
            'direction' => 'incoming',
            'origin' => PaymentOrigin::ManualEntered,
        ]);

        $security = Security::create([
            'loan_id' => $loan->id,
            'provider_entity_id' => $timo->id,
            'type' => 'guarantee',
            'nominal_value' => '10000.00',
            'status' => 'active',
        ]);

        $document = app(\App\Services\Storage\DocumentStorageInterface::class)->store(
            UploadedFile::fake()->createWithContent('smoke.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"),
            'darlehen/DAR-2026-90001/sonstiges',
            'smoke.pdf',
            ['doc_type' => 'other', 'uploaded_by' => $this->admin->id],
        );
        $document->links()->create(['linkable_type' => Loan::class, 'linkable_id' => $loan->id]);

        $templateVersion = \App\Models\ContractTemplateVersion::firstOrFail();
        $contract = Contract::create([
            'contract_number' => 'VER-2026-90001',
            'loan_id' => $loan->id,
            'contract_template_version_id' => $templateVersion->id,
            'title' => 'Smoke-Vertrag',
            'body_snapshot' => '<p>Test</p>',
            'status' => 'draft',
        ]);

        $resolution = Resolution::create([
            'resolution_number' => 'VOR-2026-901',
            'title' => 'Smoke-Beschluss',
            'company_entity_id' => $mhag->id,
            'type' => 'board',
            'motion' => 'Testantrag',
            'status' => 'draft',
            'recorded_at' => now(),
        ]);

        $signature = SignatureRequest::create([
            'subject_type' => Resolution::class,
            'subject_id' => $resolution->id,
            'provider' => 'manual',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $signature->participants()->create([
            'entity_id' => $timo->id,
            'role' => 'Vorstand',
            'status' => 'not_sent',
        ]);

        $investment = Investment::create([
            'company_entity_id' => $mhag->id,
            'share_percentage' => '100.000000',
            'status' => 'active',
        ]);

        $reminder = Reminder::create([
            'title' => 'Smoke-Wiedervorlage',
            'due_date' => now()->addDay()->toDateString(),
            'assigned_to' => $this->admin->id,
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($this->admin);
        AuditService::log('smoke.test', $loan);

        // Pflegeoberflächen für Changelog und "Wussten Sie?" (Abschnitte 118, 119)
        $changelog = \App\Models\ChangelogEntry::firstOrFail();
        $dailyFact = \App\Models\DailyFact::firstOrCreate(
            ['month_day' => '01-01'],
            [
                'title' => 'Rauchtest-Eintrag',
                'description' => 'Nur für den Rauchtest angelegt.',
                'source' => 'Interne Testdaten',
                'recurring' => true,
                'is_active' => false,
            ],
        );

        $shareTransaction = ShareTransaction::firstOrFail();
        $shareholder = \App\Models\Shareholder::firstOrFail();
        $corporateBody = \App\Models\CorporateBody::firstOrFail();
        $faq = \App\Models\FaqEntry::firstOrFail();
        $role = \Spatie\Permission\Models\Role::where('name', 'Sachbearbeiter')->firstOrFail();

        $snapshot = app(\App\Services\Holding\ShareholdingService::class)
            ->createListSnapshot(now(), $this->admin);

        return [
            'loan' => $loan->id,
            'payment' => $payment->id,
            'security' => $security->id,
            'document' => $document->id,
            'contract' => $contract->id,
            'contractTemplate' => $templateVersion->contract_template_id,
            'resolution' => $resolution->id,
            'signature_request' => $signature->id,
            'investment' => $investment->id,
            'reminder' => $reminder->id,
            'share_transaction' => $shareTransaction->id,
            'shareholder' => $shareholder->id,
            'corporate_body' => $corporateBody->id,
            'snapshot' => $snapshot->id,
            'entity' => $timo->id,
            'company_entity' => $mhag->id,
            'user' => $this->admin->id,
            'role' => $role->id,
            'faq' => $faq->id,
            'changelog' => $changelog->id,
            'daily_fact' => $dailyFact->id,
            'audit' => \App\Models\AuditLog::latest('id')->firstOrFail()->id,
            // 'key' und 'slug' werden separat expandiert
        ];
    }
}
