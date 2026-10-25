<?php

namespace App\Models;

use Database\Factories\PurokFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $current_resolution_file_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Household> $households
 * @property-read Collection<int, Resident> $residents
 * @property-read PurokResolutionFile|null $currentResolutionFile
 * @property-read Collection<int, PurokResolutionFile> $resolutionFiles
 */
#[Fillable(['name', 'code', 'sort_order', 'is_active'])]
class Purok extends Model
{
    /** @use HasFactory<PurokFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    /**
     * @return BelongsTo<PurokResolutionFile, $this>
     */
    public function currentResolutionFile(): BelongsTo
    {
        return $this->belongsTo(PurokResolutionFile::class, 'current_resolution_file_id');
    }

    /**
     * @return HasMany<PurokResolutionFile, $this>
     */
    public function resolutionFiles(): HasMany
    {
        return $this->hasMany(PurokResolutionFile::class)->latest('uploaded_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'current_resolution_file_id' => 'integer',
        ];
    }
}
