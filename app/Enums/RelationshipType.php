<?php

namespace App\Enums;

enum RelationshipType: string
{
    case Parent = 'parent';
    case Subsidiary = 'subsidiary';
    case Sister = 'sister';
    case Investment = 'investment';
    case JointVenture = 'joint_venture';
    case Affiliated = 'affiliated';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Parent => 'Muttergesellschaft',
            self::Subsidiary => 'Tochtergesellschaft',
            self::Sister => 'Schwesterunternehmen',
            self::Investment => 'Beteiligung',
            self::JointVenture => 'Joint Venture',
            self::Affiliated => 'Verbundenes Unternehmen',
            self::Other => 'Sonstige Beziehung',
        };
    }
}
