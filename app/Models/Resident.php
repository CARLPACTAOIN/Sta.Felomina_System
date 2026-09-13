<?php

namespace App\Models;

use App\Enums\ResidentStatus;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $resident_code
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string $normalized_full_name
 * @property string $sex
 * @property Carbon $birthdate
 * @property string $civil_status
 * @property string|null $contact_number
 * @property string $address
 * @property int $purok_id
 * @property int $household_id
 * @property bool $is_registered_voter
 * @property ResidentStatus $status
 * @property string|null $birthplace
 * @property string|null $occupation
 * @property string|null $remarks
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purok $purok
 * @property-read Household $household
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
#[Fillable([
    'resident_code',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'normalized_full_name',
    'sex',
    'birthdate',
    'civil_status',
    'contact_number',
    'address',
    'purok_id',
    'household_id',
    'is_registered_voter',
    'status',
    'birthplace',
    'occupation',
    'remarks',
    'created_by_id',
    'updated_by_id',
])]
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ResidentStatus::Active,
        'is_registered_voter' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Resident $resident): void {
            if (blank($resident->resident_code)) {
                $resident->resident_code = self::newResidentCode();
            }
        });

        static::saving(function (Resident $resident): void {
            $resident->normalized_full_name = self::normalizeFullName(
                $resident->first_name,
                $resident->middle_name,
                $resident->last_name,
                $resident->suffix,
            );
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
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
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
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter(fn (?string $value): bool => filled($value))
            ->join(' ');
    }

    public static function newResidentCode(): string
    {
        do {
            $code = 'RES-'.Str::upper(Str::random(10));
        } while (self::query()->where('resident_code', $code)->exists());

        return $code;
    }

    public static function normalizeFullName(?string $firstName, ?string $middleName, ?string $lastName, ?string $suffix): string
    {
        return Str::of(collect([$firstName, $middleName, $lastName, $suffix])
            ->filter(fn (?string $value): bool => filled($value))
            ->join(' '))
            ->squish()
            ->lower()
            ->toString();
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeLikelyDuplicate(Builder $query, string $normalizedFullName, string $birthdate, int $purokId): Builder
    {
        return $query
            ->where('normalized_full_name', $normalizedFullName)
            ->whereDate('birthdate', $birthdate)
            ->where('purok_id', $purokId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_registered_voter' => 'boolean',
            'status' => ResidentStatus::class,
        ];
    }
}
