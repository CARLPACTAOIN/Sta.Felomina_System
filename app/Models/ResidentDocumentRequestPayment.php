<?php

namespace App\Models;

use Database\Factories\ResidentDocumentRequestPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ResidentDocumentRequestPayment extends Model
{
    /** @use HasFactory<ResidentDocumentRequestPaymentFactory> */
    use HasFactory;

    protected $fillable = ['resident_document_request_id', 'receipt_reference', 'amount', 'received_by_id', 'paid_at'];

    /** @return BelongsTo<ResidentDocumentRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ResidentDocumentRequest::class, 'resident_document_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Request payments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Request payments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }
}
