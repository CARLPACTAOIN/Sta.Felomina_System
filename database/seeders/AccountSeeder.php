<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Juan D. Cruz',
                'email' => 'captain@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Captain,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Maria L. Santos',
                'email' => 'secretary@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Secretary,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Roberto G. Perez',
                'email' => 'purok1@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Corazon V. Flores',
                'email' => 'purok2@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Danilo M. Ramos',
                'email' => 'purok3@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Lourdes B. Dela Cruz',
                'email' => 'purok4@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Antonio S. Morales',
                'email' => 'purok5@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Fe T. Bautista',
                'email' => 'purok6@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Eduardo K. Tan',
                'email' => 'purok7@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::PurokChairperson,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Inactive Staff Member',
                'email' => 'inactive@stafelomina.local',
                'password' => 'password',
                'role' => UserRole::Secretary,
                'is_active' => false,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_active' => $account['is_active'],
                ]
            );

            $user->forceFill([
                'email_verified_at' => $account['email_verified_at'],
            ])->save();
        }
    }
}
