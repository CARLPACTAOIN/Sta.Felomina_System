<?php

namespace App\Models;

use App\Enums\ResidentDocumentRequestStatus;
use Database\Factories\ResidentDocumentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $resident_id
 * @property int $document_type_id
 * @property int $purok_id
 * @property int $created_by_id
 * @property int|null $submitted_by_id
 * @property int|null $document_record_id
 * @property string $reference
 * @property string $expected_fee
 * @property ResidentDocumentRequestStatus $status
 * @property bool|null $open_guard
 * @property Carbon|null $submitted_at
 * @property Carbon|null $released_at
 * @property Resident $resident
 * @property DocumentType $documentType
 * @property Purok $purok
 * @property ResidentDocumentRequestPayment|null $payment
 */
class ResidentDocumentRequest extends Model
{
    /** @use HasFactory<ResidentDocumentRequestFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ResidentDocumentRequestStatus::Draft,
        'open_guard' => true,
    ];

    protected $fillable = [
        'reference', 'resident_id', 'document_type_id', 'purok_id', 'created_by_id', 'submitted_by_id',
        'document_record_id', 'resident_name_snapshot', 'address_snapshot', 'household_code_snapshot',
        'household_head_snapshot', 'purok_name_snapshot', 'purpose', 'intake_notes', 'expected_fee',
        'status', 'open_guard', 'latest_reason', 'submitted_at', 'released_at', 'released_by_id', 'released_to_name',
    ];

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return BelongsTo<Purok, $this> */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /** @return BelongsTo<DocumentRecord, $this> */
    public function documentRecord(): BelongsTo
    {
        return $this->belongsTo(DocumentRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    /** @return HasOne<ResidentDocumentRequestPayment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(ResidentDocumentRequestPayment::class);
    }

    /** @return HasMany<ResidentDocumentRequestStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ResidentDocumentRequestStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    /** @return MorphMany<AuditLog, $this> */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function isEditableByChairperson(): bool
    {
        return in_array($this->status, [ResidentDocumentRequestStatus::Draft, ResidentDocumentRequestStatus::NeedsInformation], true)
            && $this->document_record_id === null;
    }

    public function canSubmit(): bool
    {
        return in_array($this->status, [ResidentDocumentRequestStatus::Draft, ResidentDocumentRequestStatus::NeedsInformation], true)
            && $this->document_record_id === null;
    }

    public function canCancelByChairperson(): bool
    {
        return in_array($this->status, [ResidentDocumentRequestStatus::Draft, ResidentDocumentRequestStatus::Submitted, ResidentDocumentRequestStatus::NeedsInformation], true)
            && $this->document_record_id === null;
    }

    public function canBeProcessed(): bool
    {
        return $this->status === ResidentDocumentRequestStatus::Submitted && $this->document_record_id === null;
    }

    public function canRecordPayment(): bool
    {
        return $this->status === ResidentDocumentRequestStatus::ReadyForPayment && (float) $this->expected_fee > 0 && $this->payment === null;
    }

    public function canRelease(): bool
    {
        if ($this->status !== ResidentDocumentRequestStatus::ReadyForPayment) {
            return false;
        }

        return number_format((float) $this->expected_fee, 2, '.', '') === '0.00'
            || $this->payment()->exists();
    }

    protected function casts(): array
    {
        return ['expected_fee' => 'decimal:2', 'status' => ResidentDocumentRequestStatus::class, 'open_guard' => 'boolean', 'submitted_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
