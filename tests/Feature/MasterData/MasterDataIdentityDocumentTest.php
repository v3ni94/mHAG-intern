<?php

namespace Tests\Feature\MasterData;

use App\Models\IdentityDocument;
use App\Models\Reminder;

class MasterDataIdentityDocumentTest extends MasterDataTestCase
{
    public function test_ablaufdatum_erzeugt_wiedervorlage(): void
    {
        $entity = $this->createPersonEntity();
        $admin = $this->admin();
        $expires = now()->addMonths(6)->toDateString();

        $this->actingAs($admin)->post(route('persons.identity-documents.store', $entity), [
            'type' => 'passport',
            'document_number' => 'C01X00T47',
            'issued_on' => '2020-01-10',
            'expires_on' => $expires,
            'authority' => 'Stadt Monheim am Rhein',
            'country' => 'Deutschland',
        ])->assertRedirect(route('persons.show', [$entity, 'tab' => 'identitaet']));

        $document = IdentityDocument::first();
        $this->assertNotNull($document);

        $reminder = Reminder::where('remindable_type', IdentityDocument::class)
            ->where('remindable_id', $document->id)
            ->first();

        $this->assertNotNull($reminder, 'Ablaufdatum muss automatisch eine Wiedervorlage anlegen.');
        $this->assertSame('open', $reminder->status->value);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($expires)->subWeeks(6)->toDateString(),
            $reminder->due_date->toDateString(),
        );
        $this->assertSame($admin->id, $reminder->assigned_to);
        $this->assertStringContainsString('Reisepass', $reminder->title);
    }

    public function test_ohne_ablaufdatum_keine_wiedervorlage(): void
    {
        $entity = $this->createPersonEntity();

        $this->actingAs($this->admin())->post(route('persons.identity-documents.store', $entity), [
            'type' => 'id_card',
            'document_number' => 'T22000129',
        ]);

        $this->assertDatabaseCount('identity_documents', 1);
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_aenderung_des_ablaufdatums_aktualisiert_wiedervorlage(): void
    {
        $entity = $this->createPersonEntity();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('persons.identity-documents.store', $entity), [
            'type' => 'passport',
            'expires_on' => now()->addMonths(6)->toDateString(),
        ]);

        $document = IdentityDocument::first();
        $newExpires = now()->addYears(2)->toDateString();

        $this->actingAs($admin)->put(route('persons.identity-documents.update', [$entity, $document]), [
            'type' => 'passport',
            'expires_on' => $newExpires,
        ]);

        $reminders = Reminder::where('remindable_type', IdentityDocument::class)
            ->where('remindable_id', $document->id)
            ->get();

        $this->assertCount(1, $reminders, 'Es darf nur eine offene Wiedervorlage je Dokument geben.');
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($newExpires)->subWeeks(6)->toDateString(),
            $reminders->first()->due_date->toDateString(),
        );
    }

    public function test_loeschen_bricht_wiedervorlage_ab(): void
    {
        $entity = $this->createPersonEntity();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('persons.identity-documents.store', $entity), [
            'type' => 'passport',
            'expires_on' => now()->addMonths(3)->toDateString(),
        ]);

        $document = IdentityDocument::first();

        $this->actingAs($admin)->delete(route('persons.identity-documents.destroy', [$entity, $document]));

        $this->assertDatabaseCount('identity_documents', 0);
        $this->assertSame('cancelled', Reminder::first()->status->value);
    }

    public function test_pruefvermerk_setzt_pruefer_und_datum(): void
    {
        $entity = $this->createPersonEntity();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('persons.identity-documents.store', $entity), [
            'type' => 'id_card',
            'verified' => '1',
        ]);

        $document = IdentityDocument::first();
        $this->assertTrue($document->verified);
        $this->assertNotNull($document->verified_at);
        $this->assertSame($admin->id, $document->verified_by);
    }
}
