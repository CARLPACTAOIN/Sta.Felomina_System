<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;

test('document archive searches metadata and related taxonomy fields', function () {
    $secretary = User::factory()->create();
    $resolution = DocumentType::factory()->create(['name' => 'Resolution', 'code' => 'resolution']);
    $certificate = DocumentType::factory()->create(['name' => 'Certificate', 'code' => 'certificate']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $health = DocumentCategory::factory()->create(['name' => 'Health']);

    DocumentRecord::factory()->create([
        'document_type_id' => $resolution->id,
        'document_category_id' => $infrastructure->id,
        'document_number' => 'RES-2026-001',
        'document_year' => 2026,
        'title' => 'Drainage Canal Repair',
        'subject' => 'Approves barangay drainage construction.',
        'keywords' => 'road, flood control',
    ]);
    DocumentRecord::factory()->create([
        'document_type_id' => $certificate->id,
        'document_category_id' => $health->id,
        'document_number' => 'CERT-2026-001',
        'document_year' => 2026,
        'title' => 'Medical Mission Certification',
        'subject' => 'Certifies attendance.',
        'keywords' => 'clinic',
    ]);

    $this->actingAs($secretary)
        ->get(route('documents.index', ['q' => 'drainage']))
        ->assertSuccessful()
        ->assertSee('Drainage Canal Repair')
        ->assertDontSee('Medical Mission Certification');

    $this->actingAs($secretary)
        ->get(route('documents.index', ['q' => 'certificate']))
        ->assertSuccessful()
        ->assertSee('Medical Mission Certification')
        ->assertDontSee('Drainage Canal Repair');
});

test('document archive combines type year category and status filters', function () {
    $captain = User::factory()->captain()->create();
    $resolution = DocumentType::factory()->create(['name' => 'Resolution']);
    $certificate = DocumentType::factory()->create(['name' => 'Certificate']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $health = DocumentCategory::factory()->create(['name' => 'Health']);

    DocumentRecord::factory()->published()->create([
        'document_type_id' => $resolution->id,
        'document_category_id' => $infrastructure->id,
        'document_number' => 'RES-2026-010',
        'document_year' => 2026,
        'title' => 'Published Infrastructure Record',
        'document_date' => '2026-03-01',
    ]);
    DocumentRecord::factory()->create([
        'document_type_id' => $resolution->id,
        'document_category_id' => $infrastructure->id,
        'document_number' => 'RES-2025-001',
        'document_year' => 2025,
        'title' => 'Wrong Year Record',
        'document_date' => '2025-03-01',
    ]);
    DocumentRecord::factory()->published()->create([
        'document_type_id' => $certificate->id,
        'document_category_id' => $health->id,
        'document_number' => 'CERT-2026-001',
        'document_year' => 2026,
        'title' => 'Wrong Taxonomy Record',
        'document_date' => '2026-03-01',
    ]);

    $this->actingAs($captain)
        ->get(route('documents.index', [
            'type' => $resolution->id,
            'category' => $infrastructure->id,
            'status' => DocumentStatus::Published->value,
            'year' => 2026,
        ]))
        ->assertSuccessful()
        ->assertSee('Published Infrastructure Record')
        ->assertDontSee('Wrong Year Record')
        ->assertDontSee('Wrong Taxonomy Record');
});
