<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentTaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['Resolution', 'resolution', 'RES', 'Barangay council resolutions and formal decisions.'],
            ['Ordinance', 'ordinance', 'ORD', 'Local legislative ordinances approved by the barangay.'],
            ['Certificate', 'certificate', 'CERT', 'Official certifications issued by the barangay.'],
            ['Memorandum', 'memorandum', 'MEM', 'Internal and official memoranda.'],
            ['Correspondence Record', 'correspondence_record', 'COR', 'Incoming and outgoing official correspondence.'],
            ['Meeting Minutes', 'meeting_minutes', 'MIN', 'Minutes of barangay meetings and sessions.'],
        ];

        foreach ($types as [$name, $code, $prefix, $description]) {
            DocumentType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'number_prefix' => $prefix,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }

        $categories = [
            ['Governance', 'Policies, council actions, and administrative governance.'],
            ['Health', 'Health center, sanitation, medical, and public health records.'],
            ['Infrastructure', 'Roads, facilities, utilities, and public works records.'],
            ['Social Services', 'Assistance, welfare, and community support records.'],
            ['Public Safety', 'Peace and order, disaster response, and safety records.'],
            ['Education', 'School, youth, scholarship, and learning-related records.'],
        ];

        foreach ($categories as [$name, $description]) {
            DocumentCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }
    }
}
