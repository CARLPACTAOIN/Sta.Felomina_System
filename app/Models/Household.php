<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $household_code
 * @property int $purok_id
 * @property string $address
 * @property string $household_head_name
 * @property string|null $notes
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purok $purok
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, Resident> $residents
 */
#[Fillable([
    'household_code',
    'purok_id',
    'address',
    'household_head_name',
    'notes',
    'created_by_id',
    'updated_by_id',
])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Household $household): void {
            if (blank($household->household_code)) {
                $household->household_code = self::newHouseholdCode();
            }
        });
    }

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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public static function newHouseholdCode(): string
    {
        do {
            $code = 'HH-'.Str::upper(Str::random(10));
        } while (self::query()->where('household_code', $code)->exists());

        return $code;
    }
}
