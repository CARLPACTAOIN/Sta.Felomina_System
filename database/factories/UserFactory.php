<?php

namespace Database\Factories;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\Purok;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'purok_id' => null,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->roles()->doesntExist()) {
                $user->assignRole($this->roleName(UserRole::Secretary));
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function captain(): static
    {
        return $this->afterCreating(fn (User $user): User => $user->syncRoles($this->roleName(UserRole::Captain)));
    }

    public function secretary(): static
    {
        return $this->afterCreating(fn (User $user): User => $user->syncRoles($this->roleName(UserRole::Secretary)));
    }

    public function purokChairperson(): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'purok_id' => Purok::factory(),
            ])
            ->afterCreating(fn (User $user): User => $user->syncRoles($this->roleName(UserRole::PurokChairperson)));
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    private function roleName(UserRole $role): string
    {
        if (Permission::query()->count() < count(StaffPermission::cases())) {
            app(RolePermissionSeeder::class)->run();
        }

        Role::findOrCreate($role->value);

        return $role->value;
    }
}
