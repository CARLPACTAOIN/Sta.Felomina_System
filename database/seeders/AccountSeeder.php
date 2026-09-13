<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PurokSeeder::class);

        $accounts = [
            [
                'name' => 'Juan D. Cruz',
                'email' => 'captain@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Captain,
                'purok_code' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Maria L. Santos',
                'email' => 'secretary@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Secretary,
                'purok_code' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Roberto G. Perez',
                'email' => 'purok1@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-1',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Corazon V. Flores',
                'email' => 'purok2@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-2',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Danilo M. Ramos',
                'email' => 'purok3@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-3',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Lourdes B. Dela Cruz',
                'email' => 'purok4@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-4',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Antonio S. Morales',
                'email' => 'purok5@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-5',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Fe T. Bautista',
                'email' => 'purok6@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-6',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Eduardo K. Tan',
                'email' => 'purok7@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'purok_code' => 'purok-7',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Inactive Staff Member',
                'email' => 'inactive@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Secretary,
                'purok_code' => null,
                'is_active' => false,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($accounts as $account) {
            $purokId = is_string($account['purok_code'])
                ? Purok::query()->where('code', $account['purok_code'])->value('id')
                : null;

            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'purok_id' => $purokId,
                    'is_active' => $account['is_active'],
                ]
            );

            $user->forceFill([
                'email_verified_at' => $account['email_verified_at'],
            ])->save();
        }
    }
}
