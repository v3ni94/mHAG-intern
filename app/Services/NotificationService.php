<?php

namespace App\Services;

use App\Enums\RepaymentItemStatus;
use App\Enums\ResolutionStatus;
use App\Models\CorporateBodyMember;
use App\Models\Guarantee;
use App\Models\IdentityDocument;
use App\Models\Loan;
use App\Models\Reminder;
use App\Models\RepaymentPlanItem;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Systemweite Benachrichtigungen (Abschnitt 127 Masterprompt).
 *
 * Erzeugt In-App-Benachrichtigungen (database channel). Der tägliche Scan
 * (app:scan-due-items) ist idempotent: pro Objekt, Ereignis und Tag wird
 * höchstens eine Benachrichtigung je Empfänger erzeugt.
 */
class NotificationService
{
    /** Interne Rollen, die Fachbenachrichtigungen erhalten. */
    public const RECIPIENT_ROLES = ['Administrator', 'Vorstand', 'Buchhaltung', 'Sachbearbeiter'];

    /**
     * Einzelne Benachrichtigung erzeugen (data: message, url, severity).
     */
    public function notify(User $user, string $message, ?string $url = null, string $severity = 'info', ?string $dedupeKey = null): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'system',
            'data' => array_filter([
                'message' => $message,
                'url' => $url,
                'severity' => $severity,
                'key' => $dedupeKey,
            ], fn ($v) => $v !== null),
        ]);
    }

    /**
     * Täglicher Scan über Fälligkeiten, Abläufe und Wiedervorlagen.
     * Rückgabe: Anzahl neu erzeugter Benachrichtigungen.
     */
    public function scanDueItems(): int
    {
        $today = today();
        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::RECIPIENT_ROLES))
            ->get();

        $created = 0;
        $existing = $this->existingKeysToday();

        $send = function (User $user, string $message, ?string $url, string $severity, string $key) use (&$created, &$existing) {
            $lookup = $user->id.'|'.$key;
            if (isset($existing[$lookup])) {
                return;
            }
            $this->notify($user, $message, $url, $severity, $key);
            $existing[$lookup] = true;
            $created++;
        };

        $broadcast = function (string $message, ?string $url, string $severity, string $key) use ($recipients, $send) {
            foreach ($recipients as $user) {
                $send($user, $message, $url, $severity, $key);
            }
        };

        // 1) Zahlungen heute fällig (SOLL, planned/assumed)
        $dueToday = RepaymentPlanItem::query()
            ->with('loan')
            ->whereDate('due_date', $today)
            ->whereIn('status', [RepaymentItemStatus::Planned->value, RepaymentItemStatus::Assumed->value])
            ->get();
        foreach ($dueToday as $item) {
            if (! $item->loan) {
                continue;
            }
            $broadcast(
                sprintf('Zahlung heute fällig: %s, %s über %s.', $item->loan->loan_number, $item->item_type->label(), format_money($item->planned_amount)),
                $this->url('loans.show', $item->loan_id),
                'warning',
                'repayment_due:'.$item->id,
            );
        }

        // 2) Überfällige Zahlungen (erfasste Ausfälle/Teilzahlungen sowie offene SOLL-Zeilen der Vergangenheit)
        $overdue = RepaymentPlanItem::query()
            ->with('loan')
            ->whereDate('due_date', '<', $today)
            ->whereIn('status', [
                RepaymentItemStatus::Missed->value,
                RepaymentItemStatus::Partial->value,
                RepaymentItemStatus::Planned->value,
            ])
            ->get();
        foreach ($overdue as $item) {
            if (! $item->loan) {
                continue;
            }
            $broadcast(
                sprintf(
                    'Zahlung überfällig seit %s: %s, %s (noch zu zahlen %s).',
                    format_date($item->due_date),
                    $item->loan->loan_number,
                    $item->item_type->label(),
                    // Erwarteter Betrag, nicht der Buchwert: bei einer nur
                    // angenommenen Erfuellung waere der Buchwert 0,00 und die
                    // Meldung damit sinnlos.
                    format_money($item->expectedAmount()),
                ),
                $this->url('loans.show', $item->loan_id),
                'danger',
                'repayment_overdue:'.$item->id,
            );
        }

        // 3) Verträge laufen aus (contract_end innerhalb 14 Tagen)
        $endingLoans = Loan::query()
            ->whereNotNull('contract_end')
            ->whereDate('contract_end', '>=', $today)
            ->whereDate('contract_end', '<=', $today->copy()->addDays(14))
            ->get();
        foreach ($endingLoans as $loan) {
            $broadcast(
                sprintf('Vertrag %s endet am %s.', $loan->loan_number, format_date($loan->contract_end)),
                $this->url('loans.show', $loan->id),
                'warning',
                'contract_end:'.$loan->id,
            );
        }

        // 4) Identitätsdokumente abgelaufen oder laufen innerhalb 30 Tagen ab
        $documents = IdentityDocument::query()
            ->with('entity')
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', $today->copy()->addDays(30))
            ->get();
        foreach ($documents as $doc) {
            $expired = $doc->expires_on->lt($today);
            $broadcast(
                sprintf(
                    '%s von %s %s am %s %s.',
                    $doc->type?->label() ?? 'Identitätsdokument',
                    $doc->entity?->display_name ?? 'unbekannt',
                    $expired ? 'ist abgelaufen' : 'läuft ab',
                    format_date($doc->expires_on),
                    $expired ? '(bitte erneuern)' : '',
                ),
                $doc->entity_id ? $this->url('persons.show', $doc->entity_id) : null,
                $expired ? 'danger' : 'warning',
                'identity_document:'.$doc->id,
            );
        }

        // 5) Sicherheiten und Bürgschaften laufen aus (30 Tage)
        foreach (Security::query()->with('loan')->where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $today->copy()->addDays(30))->get() as $security) {
            $broadcast(
                sprintf('Sicherheit "%s" (Darlehen %s) läuft am %s aus.', $security->type?->label() ?? 'Sicherheit', $security->loan?->loan_number ?? '-', format_date($security->valid_until)),
                $security->loan_id ? $this->url('loans.show', $security->loan_id) : null,
                'warning',
                'security_expiry:'.$security->id,
            );
        }
        foreach (Guarantee::query()->with('loan')->where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $today->copy()->addDays(30))->get() as $guarantee) {
            $broadcast(
                sprintf('Bürgschaft zu Darlehen %s läuft am %s aus.', $guarantee->loan?->loan_number ?? '-', format_date($guarantee->valid_until)),
                $guarantee->loan_id ? $this->url('loans.show', $guarantee->loan_id) : null,
                'warning',
                'guarantee_expiry:'.$guarantee->id,
            );
        }

        // 6) Organmandate enden innerhalb 30 Tagen
        $endingMandates = CorporateBodyMember::query()
            ->with(['person', 'body'])
            ->whereNotNull('ended_on')
            ->whereDate('ended_on', '>=', $today)
            ->whereDate('ended_on', '<=', $today->copy()->addDays(30))
            ->get();
        foreach ($endingMandates as $member) {
            $broadcast(
                sprintf('Mandat von %s (%s) endet am %s.', $member->person?->display_name ?? 'unbekannt', $member->body?->name ?? 'Organ', format_date($member->ended_on)),
                $this->url('corporate-bodies.index'),
                'warning',
                'mandate_end:'.$member->id,
            );
        }

        // 7) Beschlüsse warten auf Unterschrift
        foreach (Resolution::query()->where('status', ResolutionStatus::ForSignature->value)->get() as $resolution) {
            $broadcast(
                sprintf('Beschluss %s wartet auf Unterschrift: %s.', $resolution->resolution_number, $resolution->title),
                $this->url('resolutions.show', $resolution->id),
                'warning',
                'resolution_signature:'.$resolution->id,
            );
        }

        // 8) Wiedervorlagen fällig (heute oder überfällig) -> zugewiesener Benutzer
        $reminders = Reminder::query()
            ->with('assignee')
            ->where('status', 'open')
            ->whereDate('due_date', '<=', $today)
            ->get();
        foreach ($reminders as $reminder) {
            $assignee = $reminder->assignee;
            if (! $assignee || ! $assignee->is_active) {
                continue;
            }
            $overdueReminder = $reminder->due_date->lt($today);
            $send(
                $assignee,
                sprintf('Wiedervorlage %s: %s.', $overdueReminder ? 'überfällig seit '.format_date($reminder->due_date) : 'heute fällig', $reminder->title),
                $this->url('reminders.index'),
                $overdueReminder ? 'danger' : 'warning',
                'reminder_due:'.$reminder->id,
            );
        }

        return $created;
    }

    /**
     * Bereits heute erzeugte Benachrichtigungs-Schlüssel je Benutzer
     * (Basis der Idempotenz: Objekt + Ereignis + Tag).
     *
     * @return array<string, true>
     */
    private function existingKeysToday(): array
    {
        $keys = [];
        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereDate('created_at', today())
            ->get(['notifiable_id', 'data'])
            ->each(function (DatabaseNotification $notification) use (&$keys) {
                $key = $notification->data['key'] ?? null;
                if ($key !== null) {
                    $keys[$notification->notifiable_id.'|'.$key] = true;
                }
            });

        return $keys;
    }

    /** Routen anderer Module defensiv verlinken (Integration erfolgt modulweise). */
    private function url(string $name, mixed $param = null): ?string
    {
        if (! \Illuminate\Support\Facades\Route::has($name)) {
            return null;
        }

        return $param === null ? route($name) : route($name, $param);
    }
}
