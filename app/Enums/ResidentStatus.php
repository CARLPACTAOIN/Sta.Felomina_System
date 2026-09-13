<?php

namespace App\Enums;

enum ResidentStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Deceased = 'deceased';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Transferred => 'Transferred',
            self::Deceased => 'Deceased',
            self::Inactive => 'Inactive',
        };
    }
}
