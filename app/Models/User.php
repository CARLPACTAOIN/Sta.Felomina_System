<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StaffPermission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property int|null $purok_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Purok|null $purok
 * @property-read Collection<int, Household> $createdHouseholds
 * @property-read Collection<int, Resident> $createdResidents
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'purok_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function createdHouseholds(): HasMany
    {
        return $this->hasMany(Household::class, 'created_by_id');
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function createdResidents(): HasMany
    {
        return $this->hasMany(Resident::class, 'created_by_id');
    }

    public function staffRole(): ?UserRole
    {
        foreach (UserRole::cases() as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public function staffRoleLabel(): string
    {
        return $this->staffRole()?->label() ?? 'Staff';
    }

    public function hasStaffRole(UserRole $role): bool
    {
        return $this->hasRole($role->value);
    }

    public function canManageDocuments(): bool
    {
        return $this->can(StaffPermission::ViewDocuments->value);
    }

    public function canViewBarangayResidents(): bool
    {
        return $this->can(StaffPermission::ViewResidentsBarangay->value);
    }

    public function canManageBarangayResidents(): bool
    {
        return $this->can(StaffPermission::ManageResidentsBarangay->value);
    }

    public function canViewBarangayHouseholds(): bool
    {
        return $this->can(StaffPermission::ViewHouseholdsBarangay->value);
    }

    public function canManageBarangayHouseholds(): bool
    {
        return $this->can(StaffPermission::ManageHouseholdsBarangay->value);
    }

    public function isPurokChairperson(): bool
    {
        return $this->hasStaffRole(UserRole::PurokChairperson);
    }

    public function canAccessPurok(?int $purokId): bool
    {
        if ($this->canViewBarangayResidents() || $this->canViewBarangayHouseholds()) {
            return true;
        }

        return $purokId !== null
            && $this->isPurokChairperson()
            && $this->purok_id === $purokId;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'purok_id' => 'integer',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
