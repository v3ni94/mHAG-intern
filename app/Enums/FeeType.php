<?php

namespace App\Enums;

enum FeeType: string
{
    case Processing = 'processing';
    case Commitment = 'commitment';
    case Contract = 'contract';
    case Administration = 'administration';
    case Extension = 'extension';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Bearbeitungsgebühr',
            self::Commitment => 'Bereitstellungsgebühr',
            self::Contract => 'Vertragsgebühr',
            self::Administration => 'Verwaltungsgebühr',
            self::Extension => 'Verlängerungsgebühr',
            self::Other => 'Sonstige Gebühr',
        };
    }
}
