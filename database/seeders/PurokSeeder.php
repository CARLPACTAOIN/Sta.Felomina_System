<?php

namespace Database\Seeders;

use App\Models\Purok;
use Illuminate\Database\Seeder;

class PurokSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (range(1, 7) as $number) {
            Purok::updateOrCreate(
                ['code' => 'purok-'.$number],
                [
                    'name' => 'Purok '.$number,
                    'sort_order' => $number,
                    'is_active' => true,
                ]
            );
        }
    }
}
