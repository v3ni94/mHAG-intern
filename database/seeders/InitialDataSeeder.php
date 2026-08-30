<?php

namespace Database\Seeders;

use App\Enums\EntityType;
use App\Models\CorporateBody;
use App\Models\Entity;
use App\Models\LoanType;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Verbindliche Ausgangsdaten der Müller Holding AG (Abschnitte 75-77 Masterprompt,
 * Stammdaten aus der Corporate Identity). Idempotent.
 */
class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Gesellschaft: Müller Holding AG ----------
        $mhag = Entity::firstOrCreate(
            ['internal_number' => 'ENT-MHAG'],
            [
                'type' => EntityType::Company,
                'display_name' => 'Müller Holding AG',
                'status' => 'active',
            ],
        );

        $mhag->company()->firstOrCreate([], [
            'name' => 'Müller Holding AG',
            'short_name' => 'MHAG',
            'legal_form' => 'AG',
            'commercial_register' => 'HRB',
            'register_number' => 'HRB 104291',
            'register_court' => 'Amtsgericht Düsseldorf',
            'email' => 'kontakt@mueller-holding.ag',
            'website' => 'https://mueller-holding.ag',
        ]);

        $mhag->addresses()->firstOrCreate(
            ['type' => 'main'],
            [
                'street' => 'Rheinpromenade',
                'house_number' => '13',
                'postal_code' => '40789',
                'city' => 'Monheim am Rhein',
                'country' => 'Deutschland',
                'is_primary' => true,
            ],
        );

        // ---------- Personen: Vorstand und Aufsichtsrat ----------
        $timo = $this->person('ENT-P-TMUELLER', 'Timo', 'Müller');
        $walprecht = $this->person('ENT-P-JWALPRECHT', 'Jan', 'Walprecht');
        $schuhwirt = $this->person('ENT-P-FSCHUHWIRT', 'Frederik', 'Schuhwirt');
        $enns = $this->person('ENT-P-DENNS', 'David', 'Enns');

        // ---------- Organe ----------
        $board = CorporateBody::firstOrCreate(
            ['company_entity_id' => $mhag->id, 'type' => 'board'],
            ['name' => 'Vorstand'],
        );
        $board->members()->firstOrCreate(
            ['person_entity_id' => $timo->id],
            ['role' => 'Vorstand', 'is_chair' => true, 'status' => 'active'],
        );

        $supervisoryBoard = CorporateBody::firstOrCreate(
            ['company_entity_id' => $mhag->id, 'type' => 'supervisory_board'],
            ['name' => 'Aufsichtsrat'],
        );
        $supervisoryBoard->members()->firstOrCreate(
            ['person_entity_id' => $walprecht->id],
            ['role' => 'Aufsichtsratsvorsitzender', 'is_chair' => true, 'status' => 'active'],
        );
        $supervisoryBoard->members()->firstOrCreate(
            ['person_entity_id' => $schuhwirt->id],
            ['role' => 'Aufsichtsratsmitglied', 'is_chair' => false, 'status' => 'active'],
        );
        $supervisoryBoard->members()->firstOrCreate(
            ['person_entity_id' => $enns->id],
            ['role' => 'Aufsichtsratsmitglied', 'is_chair' => false, 'status' => 'active'],
        );

        // Organstellungen im Stammdatenmodell spiegeln
        $mhag->organizationRolesAsCompany()->firstOrCreate(
            ['person_entity_id' => $timo->id, 'role' => 'board_member'],
            ['is_active' => true, 'sole_representation' => true],
        );
        $mhag->organizationRolesAsCompany()->firstOrCreate(
            ['person_entity_id' => $walprecht->id, 'role' => 'supervisory_board_chair'],
            ['is_active' => true],
        );
        $mhag->organizationRolesAsCompany()->firstOrCreate(
            ['person_entity_id' => $schuhwirt->id, 'role' => 'supervisory_board_member'],
            ['is_active' => true],
        );
        $mhag->organizationRolesAsCompany()->firstOrCreate(
            ['person_entity_id' => $enns->id, 'role' => 'supervisory_board_member'],
            ['is_active' => true],
        );

        // ---------- Aktienstruktur (Abschnitt 76) ----------
        Setting::set('holding', 'company_entity_id', $mhag->id);
        Setting::set('holding', 'base_capital', '100000.00');
        Setting::set('holding', 'total_shares', 100000);

        $shareholderTimo = Shareholder::firstOrCreate(
            ['entity_id' => $timo->id],
            [
                'shareholder_number' => 'AKT-0001',
                'status' => 'active',
                'joined_on' => now()->toDateString(),
            ],
        );

        // Ausgangsbestand ausschließlich als wirksame Aktienbewegung, nie als
        // direkt gesetzter Saldo (Abschnitt 78).
        if (! ShareTransaction::query()->where('transaction_number', 'AB-INITIAL-0001')->exists()) {
            ShareTransaction::create([
                'transaction_number' => 'AB-INITIAL-0001',
                'type' => 'capital_increase',
                'buyer_shareholder_id' => $shareholderTimo->id,
                'share_count' => 100000,
                'economic_transfer_date' => now()->toDateString(),
                'booking_date' => now()->toDateString(),
                'status' => 'effective',
                'note' => 'Ausgangsbestand bei Systemeinführung: Timo Müller hält 100.000 Aktien (100 %). Datum entspricht dem Erfassungstag.',
            ]);
        }

        // ---------- Administrator-Konto ----------
        $admin = User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'timo@muellerhv.de')],
            [
                'name' => 'Timo Müller',
                'entity_id' => $timo->id,
                'password' => env('SEED_ADMIN_PASSWORD', 'Bitte-sofort-aendern-2026'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['Administrator', 'Vorstand']);
        if ($admin->entity_id) {
            $admin->entityAssignments()->firstOrCreate(
                ['entity_id' => $admin->entity_id, 'context' => 'self'],
                ['label' => 'Privat', 'is_default' => true],
            );
            $admin->entityAssignments()->firstOrCreate(
                ['entity_id' => $mhag->id, 'context' => 'company'],
                ['label' => 'Müller Holding AG'],
            );
        }

        // ---------- Darlehensarten (Abschnitt 22) ----------
        $loanTypes = [
            ['code' => 'endfaellig', 'name' => 'Endfälliges Darlehen'],
            ['code' => 'raten', 'name' => 'Ratendarlehen'],
            ['code' => 'annuitaet', 'name' => 'Annuitätendarlehen'],
            ['code' => 'individuell', 'name' => 'Individuelles Darlehen'],
            ['code' => 'unbefristet', 'name' => 'Unbefristetes Darlehen'],
            ['code' => 'rahmen', 'name' => 'Rahmendarlehen'],
            ['code' => 'kontokorrent', 'name' => 'Kontokorrentdarlehen'],
            ['code' => 'gesellschafter', 'name' => 'Gesellschafterdarlehen'],
            ['code' => 'privat', 'name' => 'Privatdarlehen'],
        ];
        foreach ($loanTypes as $type) {
            LoanType::firstOrCreate(['code' => $type['code']], $type);
        }

        // ---------- Grundeinstellungen ----------
        Setting::set('security', 'two_factor_required_roles', [
            'Administrator', 'Vorstand', 'Aufsichtsratsvorsitzender', 'Aufsichtsratsmitglied',
        ]);
        // Verrechnungsreihenfolge (Abschnitt 47), konfigurierbar
        Setting::set('loans', 'allocation_order', ['costs', 'fees', 'default_interest', 'interest', 'principal']);
    }

    private function person(string $internalNumber, string $firstName, string $lastName): Entity
    {
        $entity = Entity::firstOrCreate(
            ['internal_number' => $internalNumber],
            [
                'type' => EntityType::Person,
                'display_name' => $firstName.' '.$lastName,
                'status' => 'active',
            ],
        );

        $entity->person()->firstOrCreate([], [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return $entity;
    }
}
