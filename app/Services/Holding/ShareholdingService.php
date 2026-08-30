<?php

namespace App\Services\Holding;

use App\Enums\ShareTransactionStatus;
use App\Enums\ShareTransactionType;
use App\Models\DocumentLink;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareholderListSnapshot;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Services\Storage\DocumentStorageInterface;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aktienlogik der Müller Holding AG (Abschnitte 76 bis 83 Masterprompt).
 *
 * Grundprinzip: Der Aktienbestand wird NIE gespeichert, sondern immer aus den
 * wirksamen Aktienbewegungen berechnet (status=effective, wirtschaftlicher
 * Übergang <= Stichtag). Stornierungen erfolgen ausschließlich als
 * Gegenbuchung, nie durch Löschen (Abschnitt 121).
 */
class ShareholdingService
{
    public function __construct(
        private readonly DocumentStorageInterface $storage,
    ) {
    }

    /** Gesamtzahl der Aktien laut Grundkapitalstruktur (Abschnitt 76). */
    public function totalShares(): int
    {
        return (int) Setting::get('holding', 'total_shares', 0);
    }

    /**
     * Aktionärsstruktur zum Stichtag (Abschnitt 81).
     *
     * Nur Transaktionen mit status=effective und economic_transfer_date <= Stichtag
     * zählen. Rückgabe je Aktionär:
     * ['shareholder' => Shareholder, 'shares' => int, 'percentage' => string (6 NK)]
     * sortiert nach Bestand absteigend.
     */
    public function holdingsAsOf(?CarbonInterface $asOf = null): Collection
    {
        $asOf = $asOf ?? Carbon::now();

        $balances = [];
        ShareTransaction::query()
            ->where('status', ShareTransactionStatus::Effective->value)
            ->whereDate('economic_transfer_date', '<=', $asOf->toDateString())
            ->orderBy('economic_transfer_date')
            ->orderBy('id')
            ->get()
            ->each(function (ShareTransaction $t) use (&$balances) {
                $this->applyTransaction($t, $balances);
            });

        $shareholders = Shareholder::query()
            ->with('entity')
            ->whereIn('id', array_keys($balances))
            ->get()
            ->keyBy('id');

        $total = $this->totalShares();

        return collect($balances)
            ->map(function (int $shares, int $shareholderId) use ($shareholders, $total) {
                $shareholder = $shareholders->get($shareholderId);
                if (! $shareholder) {
                    return null;
                }

                return [
                    'shareholder' => $shareholder,
                    'shares' => $shares,
                    'percentage' => $this->percentage($shares, $total),
                ];
            })
            ->filter()
            ->sortByDesc('shares')
            ->values();
    }

    /** Bestand eines Aktionärs zum Stichtag, berechnet aus wirksamen Bewegungen. */
    public function sharesOf(Shareholder $shareholder, ?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::now();

        $balances = [];
        ShareTransaction::query()
            ->where('status', ShareTransactionStatus::Effective->value)
            ->whereDate('economic_transfer_date', '<=', $asOf->toDateString())
            ->where(function ($q) use ($shareholder) {
                $q->where('buyer_shareholder_id', $shareholder->id)
                    ->orWhere('seller_shareholder_id', $shareholder->id);
            })
            ->get()
            ->each(function (ShareTransaction $t) use (&$balances) {
                $this->applyTransaction($t, $balances);
            });

        return $balances[$shareholder->id] ?? 0;
    }

    /** Prozentualer Anteil mit 6 Nachkommastellen (Abschnitt 77). */
    public function percentage(int $shares, ?int $total = null): string
    {
        $total = $total ?? $this->totalShares();
        if ($total <= 0) {
            return '0.000000';
        }

        return Money::round(
            Money::div(bcmul((string) $shares, '100', 0), (string) $total, 8),
            6,
        );
    }

    /**
     * Transaktion wirksam setzen (Abschnitt 80: nur wirksame Bewegungen
     * verändern den Bestand). Prüft die Verkäuferdeckung und bei
     * Kapitalerhöhungen die Kapitalgrenze (total_shares).
     */
    public function makeEffective(ShareTransaction $transaction, ?User $user = null): void
    {
        if ($transaction->status === ShareTransactionStatus::Effective) {
            throw ValidationException::withMessages([
                'status' => 'Die Transaktion ist bereits wirksam.',
            ]);
        }
        if ($transaction->status === ShareTransactionStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Eine stornierte Transaktion kann nicht wirksam gesetzt werden.',
            ]);
        }
        if ((int) $transaction->share_count <= 0) {
            throw ValidationException::withMessages([
                'share_count' => 'Die Anzahl der Aktien muss größer als 0 sein.',
            ]);
        }

        $type = $transaction->type;
        $transferDate = $transaction->economic_transfer_date ?? Carbon::now();

        // Käufer- und Verkäuferpflicht je Transaktionsart
        if ($type === ShareTransactionType::CapitalIncrease && ! $transaction->buyer_shareholder_id) {
            throw ValidationException::withMessages([
                'buyer_shareholder_id' => 'Bei einer Kapitalerhöhung muss ein empfangender Aktionär angegeben sein.',
            ]);
        }
        if (in_array($type, [ShareTransactionType::Redemption, ShareTransactionType::CapitalDecrease], true)
            && ! $transaction->seller_shareholder_id) {
            throw ValidationException::withMessages([
                'seller_shareholder_id' => 'Bei Einziehung oder Kapitalherabsetzung muss ein abgebender Aktionär angegeben sein.',
            ]);
        }

        // Kapitalgrenze bei Kapitalerhöhung: Summe aller Bestände darf die
        // Gesamtzahl der Aktien (Setting holding.total_shares) nicht überschreiten.
        if ($type === ShareTransactionType::CapitalIncrease) {
            $outstanding = $this->holdingsAsOf($transferDate)->sum('shares');
            $total = $this->totalShares();
            if ($total > 0 && $outstanding + (int) $transaction->share_count > $total) {
                throw ValidationException::withMessages([
                    'share_count' => sprintf(
                        'Die Kapitalerhöhung überschreitet die hinterlegte Gesamtzahl der Aktien (%s). Bitte zuerst die Grundkapitaldaten anpassen.',
                        number_format($total, 0, ',', '.'),
                    ),
                ]);
            }
        }

        // Verkäuferdeckung: Der abgebende Aktionär muss zum wirtschaftlichen
        // Übergang UND an jedem späteren Stichtag über genügend Aktien verfügen
        // (bereits wirksame, zukünftig datierte Bewegungen werden berücksichtigt).
        if ($transaction->seller_shareholder_id) {
            $seller = $transaction->seller()->firstOrFail();
            $available = $this->minimumBalanceFrom($seller, $transferDate);
            if ($available < (int) $transaction->share_count) {
                throw ValidationException::withMessages([
                    'seller_shareholder_id' => sprintf(
                        'Der abgebende Aktionär verfügt zum wirtschaftlichen Übergang (%s) nicht über genügend Aktien. Verfügbar: %s, benötigt: %s.',
                        $transferDate->format('d.m.Y'),
                        number_format($available, 0, ',', '.'),
                        number_format((int) $transaction->share_count, 0, ',', '.'),
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($transaction, $user) {
            $oldStatus = $transaction->status?->value;
            $transaction->status = ShareTransactionStatus::Effective;
            if (! $transaction->booking_date) {
                $transaction->booking_date = Carbon::now()->toDateString();
            }
            $transaction->save();

            AuditService::log(
                'share-transactions.made-effective',
                $transaction,
                ['status' => $oldStatus],
                ['status' => ShareTransactionStatus::Effective->value],
                ['user_id' => $user?->id, 'transaction_number' => $transaction->transaction_number],
            );
        });
    }

    /**
     * Storno (Abschnitt 121): Wirksame Transaktionen werden NIE gelöscht,
     * sondern durch eine Gegenbuchung (type=correction, reversal_of)
     * neutralisiert. Noch nicht wirksame Transaktionen erhalten den Status
     * "storniert". Rückgabe: die Gegenbuchung bzw. die stornierte Transaktion.
     */
    public function cancel(ShareTransaction $transaction, ?User $user = null): ShareTransaction
    {
        if ($transaction->status === ShareTransactionStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Die Transaktion ist bereits storniert.',
            ]);
        }

        if (ShareTransaction::query()->where('reversal_of', $transaction->id)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Zu dieser Transaktion existiert bereits eine Gegenbuchung.',
            ]);
        }

        if ($transaction->status !== ShareTransactionStatus::Effective) {
            return DB::transaction(function () use ($transaction, $user) {
                $oldStatus = $transaction->status?->value;
                $transaction->status = ShareTransactionStatus::Cancelled;
                $transaction->save();

                AuditService::log(
                    'share-transactions.cancelled',
                    $transaction,
                    ['status' => $oldStatus],
                    ['status' => ShareTransactionStatus::Cancelled->value],
                    ['user_id' => $user?->id],
                );

                return $transaction;
            });
        }

        // Gegenbuchung: Käufer und Verkäufer getauscht, gleiche Stückzahl.
        // Wirkungsdatum: heute, bei zukünftigem Übergang das Übergangsdatum,
        // damit sich beide Bewegungen ab dem gleichen Stichtag aufheben.
        $originalDate = $transaction->economic_transfer_date;
        $reversalDate = ($originalDate && $originalDate->isFuture())
            ? $originalDate->toDateString()
            : Carbon::now()->toDateString();

        return DB::transaction(function () use ($transaction, $user, $reversalDate) {
            $reversal = ShareTransaction::create([
                'transaction_number' => NumberSequenceService::next('AB', 5),
                'type' => ShareTransactionType::Correction->value,
                'seller_shareholder_id' => $transaction->buyer_shareholder_id,
                'buyer_shareholder_id' => $transaction->seller_shareholder_id,
                'share_count' => $transaction->share_count,
                'price_per_share' => $transaction->price_per_share,
                'total_price' => $transaction->total_price,
                'economic_transfer_date' => $reversalDate,
                'booking_date' => Carbon::now()->toDateString(),
                'status' => ShareTransactionStatus::Effective->value,
                'reversal_of' => $transaction->id,
                'created_by' => $user?->id,
                'note' => sprintf(
                    'Gegenbuchung (Storno) zu %s vom %s.',
                    $transaction->transaction_number,
                    $transaction->economic_transfer_date?->format('d.m.Y'),
                ),
            ]);

            AuditService::log(
                'share-transactions.cancelled',
                $transaction,
                ['status' => ShareTransactionStatus::Effective->value],
                [],
                [
                    'user_id' => $user?->id,
                    'reversal_transaction_id' => $reversal->id,
                    'reversal_transaction_number' => $reversal->transaction_number,
                ],
            );

            return $reversal;
        });
    }

    /**
     * Offizielle Aktionärsliste (Abschnitte 82/83): unveränderlicher
     * Daten-Snapshot als JSON, PDF im CI mit Unterschriftsbereichen für
     * Vorstand und Aufsichtsratsvorsitzenden, Ablage über die
     * Dokumentenablage, SHA-256 zur Integritätsprüfung.
     */
    public function createListSnapshot(CarbonInterface $asOf, User $user): ShareholderListSnapshot
    {
        $holdings = $this->holdingsAsOf($asOf)->filter(fn (array $row) => $row['shares'] > 0)->values();

        $companyEntity = Entity::query()
            ->with(['company', 'addresses'])
            ->find(Setting::get('holding', 'company_entity_id'));

        $address = $companyEntity?->primaryAddress();

        $rows = $holdings->map(function (array $row) {
            /** @var Shareholder $shareholder */
            $shareholder = $row['shareholder'];
            $entityAddress = $shareholder->entity?->addresses?->count()
                ? $shareholder->entity->primaryAddress()
                : $shareholder->entity?->addresses()->orderByDesc('is_primary')->first();

            return [
                'shareholder_number' => $shareholder->shareholder_number,
                'name' => $shareholder->entity?->display_name,
                'address' => $entityAddress
                    ? trim(sprintf(
                        '%s %s, %s %s',
                        $entityAddress->street,
                        $entityAddress->house_number,
                        $entityAddress->postal_code,
                        $entityAddress->city,
                    ), ' ,')
                    : null,
                'shares' => $row['shares'],
                'percentage' => $row['percentage'],
            ];
        })->all();

        $data = [
            'company' => [
                'name' => $companyEntity?->display_name ?? 'Müller Holding AG',
                'register' => $companyEntity?->company?->register_number,
                'register_court' => $companyEntity?->company?->register_court,
                'address' => $address
                    ? trim(sprintf('%s %s, %s %s', $address->street, $address->house_number, $address->postal_code, $address->city), ' ,')
                    : null,
            ],
            'base_capital' => (string) Setting::get('holding', 'base_capital', '0'),
            'total_shares' => $this->totalShares(),
            'as_of_date' => $asOf->toDateString(),
            'generated_at' => Carbon::now()->toDateTimeString(),
            'shareholders' => $rows,
        ];

        $documentNumber = NumberSequenceService::next('AL', 3);

        $pdfContent = Pdf::loadView('shareholders.pdf.list', [
            'data' => $data,
            'documentNumber' => $documentNumber,
        ])->output();

        $document = $this->storage->store(
            $pdfContent,
            'gesellschaft/aktionaere',
            $documentNumber.'-aktionaersliste.pdf',
            [
                'doc_type' => 'shareholder_list',
                'category' => 'Aktionärsliste',
                'document_date' => $asOf->toDateString(),
                'description' => sprintf('Aktionärsliste %s zum Stichtag %s', $documentNumber, $asOf->format('d.m.Y')),
                'uploaded_by' => $user->id,
            ],
        );

        $sha256 = $document->sha256 ?: hash('sha256', $pdfContent);

        return DB::transaction(function () use ($documentNumber, $asOf, $data, $document, $sha256, $user) {
            $snapshot = ShareholderListSnapshot::create([
                'document_number' => $documentNumber,
                'as_of_date' => $asOf->toDateString(),
                'data' => $data,
                'document_id' => $document->id,
                'sha256' => $sha256,
                'signature_status' => 'unsigned',
                'created_by' => $user->id,
            ]);

            DocumentLink::firstOrCreate([
                'document_id' => $document->id,
                'linkable_type' => $snapshot->getMorphClass(),
                'linkable_id' => $snapshot->id,
            ]);

            AuditService::log(
                'shareholders.list-created',
                $snapshot,
                [],
                ['document_number' => $documentNumber, 'as_of_date' => $asOf->toDateString(), 'sha256' => $sha256],
                ['user_id' => $user->id],
            );

            return $snapshot;
        });
    }

    /**
     * Wirkung einer wirksamen Transaktion auf die Bestandssalden:
     * Käufer plus, Verkäufer minus; Kapitalerhöhung nur Käufer,
     * Einziehung und Kapitalherabsetzung nur Verkäufer.
     */
    private function applyTransaction(ShareTransaction $t, array &$balances): void
    {
        $count = (int) $t->share_count;
        $type = $t->type instanceof ShareTransactionType ? $t->type : ShareTransactionType::from((string) $t->type);

        if ($type === ShareTransactionType::CapitalIncrease) {
            if ($t->buyer_shareholder_id) {
                $balances[$t->buyer_shareholder_id] = ($balances[$t->buyer_shareholder_id] ?? 0) + $count;
            }

            return;
        }

        if (in_array($type, [ShareTransactionType::Redemption, ShareTransactionType::CapitalDecrease], true)) {
            if ($t->seller_shareholder_id) {
                $balances[$t->seller_shareholder_id] = ($balances[$t->seller_shareholder_id] ?? 0) - $count;
            }

            return;
        }

        if ($t->buyer_shareholder_id) {
            $balances[$t->buyer_shareholder_id] = ($balances[$t->buyer_shareholder_id] ?? 0) + $count;
        }
        if ($t->seller_shareholder_id) {
            $balances[$t->seller_shareholder_id] = ($balances[$t->seller_shareholder_id] ?? 0) - $count;
        }
    }

    /**
     * Kleinster Bestand des Aktionärs ab dem Stichtag unter Berücksichtigung
     * aller bereits wirksamen, auch zukünftig datierten Bewegungen.
     */
    private function minimumBalanceFrom(Shareholder $shareholder, CarbonInterface $from): int
    {
        $balance = $this->sharesOf($shareholder, $from);
        $minimum = $balance;

        ShareTransaction::query()
            ->where('status', ShareTransactionStatus::Effective->value)
            ->whereDate('economic_transfer_date', '>', $from->toDateString())
            ->where(function ($q) use ($shareholder) {
                $q->where('buyer_shareholder_id', $shareholder->id)
                    ->orWhere('seller_shareholder_id', $shareholder->id);
            })
            ->orderBy('economic_transfer_date')
            ->orderBy('id')
            ->get()
            ->each(function (ShareTransaction $t) use (&$balance, &$minimum, $shareholder) {
                $balances = [$shareholder->id => $balance];
                $this->applyTransaction($t, $balances);
                $balance = $balances[$shareholder->id] ?? $balance;
                $minimum = min($minimum, $balance);
            });

        return $minimum;
    }
}
