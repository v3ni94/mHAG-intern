<?php

namespace Tests\Feature\Organisation;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\Reminder;
use App\Models\RepaymentPlanItem;

class ScanDueItemsTest extends OrganisationTestCase
{
    public function test_scan_erzeugt_benachrichtigungen_und_ist_idempotent(): void
    {
        $admin = $this->makeAdmin();
        $buchhaltung = $this->makeUserWithRole('Buchhaltung');
        // Externer Benutzer darf keine Fachbenachrichtigungen erhalten
        $external = $this->makeUserWithRole('Darlehensgeber');

        $lender = $this->makeEntity('Geber AG', 'company');
        $borrower = $this->makeEntity('Nehmer GmbH', 'company');
        $loan = $this->makeLoan($lender, $borrower);

        // Heute fällige Zinsrate (SOLL)
        RepaymentPlanItem::create([
            'loan_id' => $loan->id,
            'item_type' => RepaymentItemType::Interest->value,
            'due_date' => today()->toDateString(),
            'planned_amount' => '500.00',
            'status' => RepaymentItemStatus::Planned->value,
            'origin' => 'assumed',
        ]);

        // Fällige Wiedervorlage für die Buchhaltung
        Reminder::create([
            'title' => 'Unterlagen nachfordern',
            'due_date' => today()->toDateString(),
            'assigned_to' => $buchhaltung->id,
            'priority' => 'normal',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $this->artisan('app:scan-due-items')->assertSuccessful();

        $adminCount = $admin->notifications()->count();
        $this->assertGreaterThanOrEqual(1, $adminCount);
        $this->assertTrue(
            $admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'] ?? '', 'heute fällig')),
            'Administrator muss über die heute fällige Zahlung informiert werden.',
        );

        // Wiedervorlage: nur der zugewiesene Benutzer
        $this->assertTrue(
            $buchhaltung->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'] ?? '', 'Wiedervorlage')),
        );
        $this->assertFalse(
            $admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'] ?? '', 'Wiedervorlage')),
            'Wiedervorlage-Benachrichtigung geht nur an den zugewiesenen Benutzer.',
        );

        // Externe Rolle erhält keine Fachbenachrichtigungen
        $this->assertSame(0, $external->notifications()->count());

        // Idempotenz: zweiter Lauf am selben Tag erzeugt nichts Neues
        $buchhaltungCount = $buchhaltung->notifications()->count();
        $this->artisan('app:scan-due-items')->assertSuccessful();

        $this->assertSame($adminCount, $admin->notifications()->count(), 'Zweiter Lauf darf keine Duplikate erzeugen.');
        $this->assertSame($buchhaltungCount, $buchhaltung->notifications()->count());
    }

    public function test_notify_erzeugt_datenbank_benachrichtigung_mit_message_und_url(): void
    {
        $user = $this->makeAdmin();

        app(\App\Services\NotificationService::class)->notify($user, 'Testnachricht', '/dashboard', 'warning');

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Testnachricht', $notification->data['message']);
        $this->assertSame('/dashboard', $notification->data['url']);
        $this->assertSame('warning', $notification->data['severity']);
    }
}
