<?php

namespace App\Enums;

enum UserRole: string
{
    case Captain = 'captain';
    case Secretary = 'secretary';
    case PurokChairperson = 'purok_chairperson';

    public function label(): string
    {
        return match ($this) {
            self::Captain => 'Barangay Captain',
            self::Secretary => 'Barangay Secretary',
            self::PurokChairperson => 'Purok Chairperson',
        };
    }

    public function canManageDocuments(): bool
    {
        return in_array($this, [self::Captain, self::Secretary], true);
    }

    public function canViewBarangayResidents(): bool
    {
        return in_array($this, [self::Captain, self::Secretary], true);
    }
}
