<?php

namespace App\Models;

use Database\Factories\PurokResolutionFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purok_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by_id
 * @property Carbon $uploaded_at
 * @property-read Purok $purok
 * @property-read User|null $uploader
 */
#[Fillable(['path', 'original_name', 'mime_type', 'size', 'uploaded_by_id', 'uploaded_at'])]
class PurokResolutionFile extends Model
{
    /** @use HasFactory<PurokResolutionFileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
