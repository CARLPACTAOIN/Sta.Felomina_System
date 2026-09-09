<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $document_type_id
 * @property int $document_category_id
 * @property int $created_by_id
 * @property string $document_number
 * @property int $document_year
 * @property string $title
 * @property Carbon $document_date
 * @property string $subject
 * @property string|null $keywords
 * @property DocumentStatus $status
 * @property string $verification_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DocumentType $type
 * @property-read DocumentCategory $category
 * @property-read User $creator
 */
#[Fillable([
    'document_type_id',
    'document_category_id',
    'created_by_id',
    'document_number',
    'document_year',
    'title',
    'document_date',
    'subject',
    'keywords',
    'status',
    'verification_token',
])]
class DocumentRecord extends Model
{
    /** @use HasFactory<DocumentRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function verificationUrl(): string
    {
        return route('verification.show', $this->verification_token);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentRecord $document): void {
            if (blank($document->status)) {
                $document->status = DocumentStatus::Draft;
            }

            if (blank($document->verification_token)) {
                $document->verification_token = self::newVerificationToken();
            }
        });
    }

    private static function newVerificationToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('verification_token', $token)->exists());

        return $token;
    }
}
