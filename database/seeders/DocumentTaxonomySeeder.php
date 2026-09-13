<?php

namespace Database\Seeders;

use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

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
            ['Executive Order', 'executive_order', 'EO', 'Official executive orders and administrative directives issued by the Punong Barangay.'],
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
            ['Governance', 'governance', 'Policies, council actions, and administrative governance.'],
            ['Health', 'health', 'Health center, sanitation, medical, and public health records.'],
            ['Infrastructure', 'infrastructure', 'Roads, facilities, utilities, and public works records.'],
            ['Social Services', 'social-services', 'Assistance, welfare, and community support records.'],
            ['Public Safety', 'public-safety', 'Peace and order, disaster response, and safety records.'],
            ['Education', 'education', 'School, youth, scholarship, and learning-related records.'],
            ['Peace & Order', 'peace-and-order', 'Barangay peacekeeping, tanod operations, curfew enforcement, and community dispute resolution.'],
            ['Disaster Risk Reduction', 'disaster-risk-reduction', 'Disaster preparedness, calamity fund management, climate response, and emergency rescue.'],
        ];

        foreach ($categories as [$name, $slug, $description]) {
            DocumentCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }

        $rules = [
            ['Infrastructure Keywords', 'Infrastructure', null, ['road', 'drainage', 'construction', 'rehabilitation', 'building', 'facility', 'bridge', 'water system'], 10],
            ['Health Keywords', 'Health', null, ['health', 'clinic', 'medical', 'sanitation', 'vaccination', 'dengue', 'medicine'], 20],
            ['Public Safety Keywords', 'Public Safety', null, ['peace and order', 'safety', 'disaster', 'emergency', 'rescue', 'fire', 'incident'], 30],
            ['Social Services Keywords', 'Social Services', null, ['assistance', 'welfare', 'indigent', 'senior citizen', 'pwd', 'relief'], 40],
            ['Education Keywords', 'Education', null, ['school', 'student', 'youth', 'scholarship', 'learning', 'daycare'], 50],
            ['Governance Keywords', 'Governance', null, ['council', 'policy', 'administration', 'ordinance', 'resolution', 'session', 'meeting'], 60],
            ['Meeting Minutes Governance', 'Governance', 'meeting_minutes', ['minutes', 'session', 'assembly', 'agenda'], 5],
            ['Peace & Order Keywords', 'Peace & Order', null, ['peace and order', 'tanod', 'curfew', 'checkpoint', 'patrol', 'dispute', 'badac', 'lupon'], 25],
            ['Disaster Risk Reduction Keywords', 'Disaster Risk Reduction', null, ['disaster', 'calamity', 'evacuation', 'flood', 'typhoon', 'relief', 'bdrrmc', 'rescue', 'hazard', 'preparedness'], 15],
        ];

        foreach ($rules as [$name, $categoryName, $typeCode, $keywords, $priority]) {
            $category = DocumentCategory::where('name', $categoryName)->firstOrFail();
            $type = $typeCode === null ? null : DocumentType::where('code', $typeCode)->firstOrFail();

            CategorizationRule::updateOrCreate(
                ['name' => $name],
                [
                    'document_category_id' => $category->id,
                    'document_type_id' => $type?->id,
                    'keywords' => $keywords,
                    'priority' => $priority,
                    'is_active' => true,
                ]
            );
        }
    }
}
