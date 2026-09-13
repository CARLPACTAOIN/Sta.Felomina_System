<?php

namespace App\Models;

use Database\Factories\DocumentFileVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_record_id
 * @property int $version_number
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property bool $is_current
 * @property Carbon $approved_at
 * @property int $approved_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DocumentRecord $documentRecord
 * @property-read User $approver
 */
#[Fillable([
    'document_record_id',
    'version_number',
    'file_path',
    'original_name',
    'mime_type',
    'size',
    'is_current',
    'approved_at',
    'approved_by_id',
])]
class DocumentFileVersion extends Model
{
    /** @use HasFactory<DocumentFileVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DocumentRecord, $this>
     */
    public function documentRecord(): BelongsTo
    {
        return $this->belongsTo(DocumentRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}
