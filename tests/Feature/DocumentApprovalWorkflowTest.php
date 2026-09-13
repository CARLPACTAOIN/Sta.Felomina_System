<?php

use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function storeWorkflowDraftPdf(string $path = 'document-drafts/prepared.pdf'): string
{
    Storage::disk('local')->put($path, '%PDF-1.4 prepared document');

    return $path;
}

test('secretary submits draft document with draft pdf for captain approval', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $path = storeWorkflowDraftPdf();
    $document = DocumentRecord::factory()
        ->withDraftPdf($path)
        ->create([
            'created_by_id' => $secretary->id,
            'draft_pdf_uploaded_by_id' => $secretary->id,
        ]);
    $token = $document->verification_token;

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('submitForApproval')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::PendingApproval)
        ->and($document->submitted_by_id)->toBe($secretary->id)
        ->and($document->submitted_at)->not->toBeNull()
        ->and($document->verification_token)->toBe($token)
        ->and($document->return_comment)->toBeNull();

    expect($captain->notifications()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_submitted',
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $document->id,
    ]);
});

test('submit for approval fails without a draft pdf', function () {
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->create(['created_by_id' => $secretary->id]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('submitForApproval')
        ->assertHasErrors(['workflow']);

    expect($document->refresh()->status)->toBe(DocumentStatus::Draft);
    expect(AuditLog::query()->count())->toBe(0);
});

test('submit for approval fails from invalid statuses', function () {
    Storage::fake('local');

    $secretary = User::factory()->create();
    $path = storeWorkflowDraftPdf();
    $document = DocumentRecord::factory()
        ->published()
        ->withDraftPdf($path)
        ->create(['created_by_id' => $secretary->id]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('submitForApproval')
        ->assertHasErrors(['workflow']);

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
});

test('purok chairperson cannot submit documents for approval', function () {
    Storage::fake('local');

    $chairperson = User::factory()->purokChairperson()->create();
    $document = DocumentRecord::factory()
        ->withDraftPdf(storeWorkflowDraftPdf())
        ->create();

    Livewire::actingAs($chairperson)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('submitForApproval')
        ->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::Draft);
});

test('captain returns pending approval document with comment', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf(storeWorkflowDraftPdf())
        ->create([
            'created_by_id' => $secretary->id,
            'submitted_by_id' => $secretary->id,
        ]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('returnComment', 'Please fix the signature page.')
        ->call('returnForRevision')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::NeedsRevision)
        ->and($document->return_comment)->toBe('Please fix the signature page.')
        ->and($document->returned_by_id)->toBe($captain->id)
        ->and($document->returned_at)->not->toBeNull();

    expect($secretary->notifications()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'document_returned',
        'auditable_id' => $document->id,
    ]);
});

test('return for revision requires a comment', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf(storeWorkflowDraftPdf())
        ->create();

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('returnForRevision')
        ->assertHasErrors(['returnComment' => ['required']]);

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingApproval);
});

test('secretary cannot return or approve a pending document', function () {
    Storage::fake('local');

    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf(storeWorkflowDraftPdf())
        ->create(['created_by_id' => $secretary->id]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('returnComment', 'Not allowed.')
        ->call('returnForRevision')
        ->assertForbidden();

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('approveForPublication')
        ->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingApproval);
    expect(DocumentFileVersion::query()->count())->toBe(0);
});

test('captain approves pending document and preserves official pdf version', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $path = storeWorkflowDraftPdf();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf($path)
        ->create([
            'created_by_id' => $secretary->id,
            'submitted_by_id' => $secretary->id,
            'draft_pdf_uploaded_by_id' => $secretary->id,
        ]);
    $token = $document->verification_token;

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('approveForPublication')
        ->assertHasNoErrors();

    $document->refresh();
    $fileVersion = $document->currentFileVersion();

    expect($document->status)->toBe(DocumentStatus::Published)
        ->and($document->approved_by_id)->toBe($captain->id)
        ->and($document->approved_at)->not->toBeNull()
        ->and($document->verification_token)->toBe($token)
        ->and($fileVersion)->not->toBeNull()
        ->and($fileVersion?->version_number)->toBe(1)
        ->and($fileVersion?->file_path)->toBe('document-officials/'.$document->id.'/versions/1.pdf')
        ->and($fileVersion?->file_path)->not->toBe($path);

    Storage::disk('local')->assertExists($path);
    Storage::disk('local')->assertExists($fileVersion->file_path);

    expect($secretary->notifications()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'document_approved',
        'auditable_id' => $document->id,
    ]);
});

test('needs revision document can replace draft pdf and resubmit', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    Storage::disk('local')->put('document-drafts/old.pdf', '%PDF old');

    $document = DocumentRecord::factory()
        ->needsRevision()
        ->withDraftPdf('document-drafts/old.pdf')
        ->create([
            'created_by_id' => $secretary->id,
            'draft_pdf_uploaded_by_id' => $secretary->id,
        ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('draftPdf', UploadedFile::fake()->create('corrected.pdf', 128, 'application/pdf'))
        ->call('uploadDraftPdf')
        ->call('submitForApproval')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::PendingApproval)
        ->and($document->return_comment)->toBeNull()
        ->and($document->draft_pdf_original_name)->toBe('corrected.pdf');

    expect($captain->notifications()->count())->toBe(1);
});
