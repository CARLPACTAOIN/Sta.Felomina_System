<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case NeedsRevision = 'needs_revision';
    case Published = 'published';
    case Archived = 'archived';
    case Revoked = 'revoked';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::NeedsRevision => 'Needs Revision',
            self::Published => 'Published',
            self::Archived => 'Archived',
            self::Revoked => 'Revoked',
            self::Superseded => 'Superseded',
        };
    }

    public function verificationHeading(): string
    {
        return match ($this) {
            self::Draft => 'Draft / Not Yet Verified',
            self::PendingApproval => 'Pending / Not Yet Verified',
            self::NeedsRevision => 'Needs Revision / Not Yet Verified',
            self::Published => 'Verified Official Barangay Document',
            self::Archived => 'Archived Official Record',
            self::Revoked => 'Revoked Document',
            self::Superseded => 'Superseded Document',
        };
    }

    public function verificationDescription(): string
    {
        return match ($this) {
            self::Draft => 'This QR belongs to a Draft record that has not been approved or published.',
            self::PendingApproval => 'This record is awaiting Captain review and must not be treated as authentic.',
            self::NeedsRevision => 'This record was returned for correction and is not an official published document.',
            self::Published => 'This record has been approved and published by Barangay Sta. Felomina.',
            self::Archived => 'This record remains historically valid but is no longer current or active.',
            self::Revoked => 'This document is no longer considered valid. Its verification history remains available.',
            self::Superseded => 'A newer official version replaces this document. The original history remains accessible.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingApproval => 'amber',
            self::Published => 'emerald',
            self::Archived => 'sky',
            self::Revoked => 'red',
            self::Superseded => 'violet',
            default => 'slate',
        };
    }

    public function isPubliclyVerified(): bool
    {
        return $this === self::Published;
    }
}
