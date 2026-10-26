<?php

use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;

test('public verification route shows draft records as not yet verified without a pdf action', function () {
    $type = DocumentType::factory()->create(['name' => 'Resolution']);
    $category = DocumentCategory::factory()->create(['name' => 'Governance']);
    $document = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-014',
        'document_year' => 2026,
        'title' => 'Rehabilitation of Barangay Health Center',
    ]);

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Draft / Not Yet Verified')
        ->assertSee('This page does not confirm authenticity')
        ->assertSee('RES-2026-014')
        ->assertSee('Resolution')
        ->assertSee('Rehabilitation of Barangay Health Center')
        ->assertSee('wrap-anywhere font-mono text-xs leading-5', false)
        ->assertDontSee('Download PDF');
});

test('public verification route does not expose sequential document ids', function () {
    $document = DocumentRecord::factory()->create();

    $this->get('/verify/'.$document->id)->assertNotFound();
});

test('public verification route escapes document metadata', function () {
    $document = DocumentRecord::factory()->create([
        'title' => '<script>alert("unsafe")</script>',
    ]);

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee(e('<script>alert("unsafe")</script>'), false)
        ->assertDontSee('<script>alert("unsafe")</script>', false);
});
