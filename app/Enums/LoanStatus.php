<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case ContractPrepared = 'contract_prepared';
    case ForSignature = 'for_signature';
    case Signed = 'signed';
    case DisbursementPlanned = 'disbursement_planned';
    case Active = 'active';
    case PartiallyRepaid = 'partially_repaid';
    case Repaid = 'repaid';
    case Deferred = 'deferred';
    case Terminated = 'terminated';
    case Overdue = 'overdue';
    case Dunning = 'dunning';
    case Legal = 'legal';
    case Defaulted = 'defaulted';
    case WrittenOff = 'written_off';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::ContractPrepared => 'Vertrag vorbereitet',
            self::ForSignature => 'Zur Unterschrift',
            self::Signed => 'Unterschrieben',
            self::DisbursementPlanned => 'Auszahlung geplant',
            self::Active => 'Aktiv',
            self::PartiallyRepaid => 'Teilweise getilgt',
            self::Repaid => 'Vollständig getilgt',
            self::Deferred => 'Gestundet',
            self::Terminated => 'Gekündigt',
            self::Overdue => 'Überfällig',
            self::Dunning => 'Mahnung',
            self::Legal => 'Rechtliche Bearbeitung',
            self::Defaulted => 'Ausgefallen',
            self::WrittenOff => 'Abgeschrieben',
            self::Archived => 'Archiviert',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::ContractPrepared => 'neutral',
            self::ForSignature => 'warning',
            self::Signed => 'success',
            self::DisbursementPlanned => 'info',
            self::Active => 'success',
            self::PartiallyRepaid => 'info',
            self::Repaid => 'success',
            self::Deferred => 'warning',
            self::Terminated => 'warning',
            self::Overdue => 'danger',
            self::Dunning => 'danger',
            self::Legal => 'danger',
            self::Defaulted => 'danger',
            self::WrittenOff => 'danger',
            self::Archived => 'neutral',
        };
    }
}
