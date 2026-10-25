<?php

namespace App\Enums;

enum ResidentDocumentRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case NeedsInformation = 'needs_information';
    case ReadyForPayment = 'ready_for_payment';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::NeedsInformation => 'Needs Information',
            self::ReadyForPayment => 'Ready for Payment',
            self::Released => 'Released',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::NeedsInformation, self::ReadyForPayment], true);
    }
}
