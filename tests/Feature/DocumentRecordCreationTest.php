<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Livewire\Livewire;

test('secretary creates a draft document record without a pdf', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution', 'number_prefix' => 'RES']);
    $category = DocumentCategory::factory()->create(['name' => 'Governance']);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentCategoryId', (string) $category->id)
        ->set('documentNumber', 'RES-2026-001')
        ->set('title', 'Rehabilitation of Barangay Health Center')
        ->set('documentDate', '2026-09-09')
        ->set('subject', 'Approves the rehabilitation of the barangay health center.')
        ->set('keywords', 'health, rehabilitation')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('documents.index'));

    $document = DocumentRecord::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->created_by_id)->toBe($secretary->id)
        ->and($document->verification_token)->toHaveLength(48)
        ->and($document->verification_token)->not->toBe((string) $document->id);

    $this->assertDatabaseHas('document_records', [
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-001',
        'document_year' => 2026,
        'title' => 'Rehabilitation of Barangay Health Center',
        'keywords' => 'health, rehabilitation',
    ]);
});

test('document number is unique per document type and year', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['number_prefix' => 'RES']);
    $category = DocumentCategory::factory()->create();

    DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-001',
        'document_year' => 2026,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentCategoryId', (string) $category->id)
        ->set('documentNumber', 'RES-2026-001')
        ->set('title', 'Duplicate resolution')
        ->set('documentDate', '2026-09-09')
        ->set('subject', 'Attempts to reuse an existing document number.')
        ->call('save')
        ->assertHasErrors(['documentNumber']);

    $this->assertDatabaseCount('document_records', 1);
});

test('suggested numbering uses the next sequence for the selected type and year', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['number_prefix' => 'RES']);
    $category = DocumentCategory::factory()->create();

    DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-014',
        'document_year' => 2026,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentDate', '2026-09-09')
        ->set('documentTypeId', (string) $type->id)
        ->assertSet('documentNumber', 'RES-2026-015');
});

test('purok chairperson cannot create document records through the livewire action', function () {
    $chairperson = User::factory()->purokChairperson()->create();
    $type = DocumentType::factory()->create();
    $category = DocumentCategory::factory()->create();

    Livewire::actingAs($chairperson)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentCategoryId', (string) $category->id)
        ->set('documentNumber', 'DOC-2026-001')
        ->set('title', 'Unauthorized document')
        ->set('documentDate', '2026-09-09')
        ->set('subject', 'This role must not create document records.')
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseCount('document_records', 0);
});
