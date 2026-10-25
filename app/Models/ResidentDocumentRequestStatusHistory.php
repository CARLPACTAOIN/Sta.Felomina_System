<?php

namespace App\Models;

use App\Enums\ResidentDocumentRequestStatus;
use Database\Factories\ResidentDocumentRequestStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ResidentDocumentRequestStatusHistory extends Model
{
    /** @use HasFactory<ResidentDocumentRequestStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = ['resident_document_request_id', 'from_status', 'to_status', 'actor_id', 'reason'];

    /** @return BelongsTo<ResidentDocumentRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ResidentDocumentRequest::class, 'resident_document_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Request status history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Request status history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['from_status' => ResidentDocumentRequestStatus::class, 'to_status' => ResidentDocumentRequestStatus::class];
    }
}
