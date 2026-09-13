<?php

use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('secretary uploads a draft pdf for a document record', function () {
    Storage::fake('local');

    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->create(['created_by_id' => $secretary->id]);
    $file = UploadedFile::fake()->create('prepared-resolution.pdf', 256, 'application/pdf');

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('draftPdf', $file)
        ->call('uploadDraftPdf')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->draft_pdf_path)->toStartWith('document-drafts/')
        ->and($document->draft_pdf_original_name)->toBe('prepared-resolution.pdf')
        ->and($document->draft_pdf_mime_type)->toBe('application/pdf')
        ->and($document->draft_pdf_uploaded_by_id)->toBe($secretary->id)
        ->and($document->verification_token)->not->toBeNull();

    Storage::disk('local')->assertExists($document->draft_pdf_path);
});

test('replacing a draft pdf removes the previous working file', function () {
    Storage::fake('local');

    $secretary = User::factory()->create();
    Storage::put('document-drafts/old.pdf', 'old draft');

    $document = DocumentRecord::factory()->create([
        'created_by_id' => $secretary->id,
        'draft_pdf_path' => 'document-drafts/old.pdf',
        'draft_pdf_original_name' => 'old.pdf',
        'draft_pdf_mime_type' => 'application/pdf',
        'draft_pdf_size' => 9,
        'draft_pdf_uploaded_at' => now()->subDay(),
        'draft_pdf_uploaded_by_id' => $secretary->id,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('draftPdf', UploadedFile::fake()->create('new.pdf', 128, 'application/pdf'))
        ->call('uploadDraftPdf')
        ->assertHasNoErrors();

    $document->refresh();

    Storage::disk('local')->assertMissing('document-drafts/old.pdf');
    Storage::disk('local')->assertExists($document->draft_pdf_path);
    expect($document->draft_pdf_original_name)->toBe('new.pdf');
});

test('draft pdf upload rejects non pdf files', function () {
    Storage::fake('local');

    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->create(['created_by_id' => $secretary->id]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('draftPdf', UploadedFile::fake()->image('notes.png'))
        ->call('uploadDraftPdf')
        ->assertHasErrors(['draftPdf' => ['mimes']]);

    $this->assertDatabaseMissing('document_records', [
        'id' => $document->id,
        'draft_pdf_original_name' => 'notes.png',
    ]);
});

test('published documents cannot replace the draft pdf through the draft upload action', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->published()->create(['created_by_id' => $captain->id]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('draftPdf', UploadedFile::fake()->create('published.pdf', 128, 'application/pdf'))
        ->call('uploadDraftPdf')
        ->assertForbidden();

    $document->refresh();

    expect($document->draft_pdf_path)->toBeNull();
});

test('purok chairperson cannot render the document detail page', function () {
    $chairperson = User::factory()->purokChairperson()->create();
    $document = DocumentRecord::factory()->create();

    $this->actingAs($chairperson)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});
