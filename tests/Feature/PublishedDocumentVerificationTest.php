<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function createOfficialDocumentForVerification(User $captain, array $attributes = []): DocumentRecord
{
    $document = DocumentRecord::factory()
        ->published()
        ->create(array_merge([
            'approved_at' => now(),
            'approved_by_id' => $captain->id,
        ], $attributes));

    Storage::disk('local')->put('document-officials/'.$document->id.'/versions/1.pdf', '%PDF approved');

    DocumentFileVersion::factory()
        ->for($document, 'documentRecord')
        ->for($captain, 'approver')
        ->create([
            'file_path' => 'document-officials/'.$document->id.'/versions/1.pdf',
            'original_name' => 'current-official.pdf',
            'is_current' => true,
        ]);

    return $document;
}

test('published public verification page shows verified status and controlled pdf actions', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create(['name' => 'Captain Reyes']);
    $document = DocumentRecord::factory()
        ->published()
        ->create([
            'document_number' => 'RES-2026-099',
            'approved_at' => now(),
            'approved_by_id' => $captain->id,
        ]);
    Storage::disk('local')->put('document-officials/'.$document->id.'/versions/1.pdf', '%PDF approved');

    DocumentFileVersion::factory()
        ->for($document, 'documentRecord')
        ->for($captain, 'approver')
        ->create([
            'file_path' => 'document-officials/'.$document->id.'/versions/1.pdf',
            'original_name' => 'approved-resolution.pdf',
        ]);

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Verified Official Barangay Document')
        ->assertSee('Captain Reyes')
        ->assertSee('View Official PDF')
        ->assertSee('Download Official PDF')
        ->assertDontSee('document-officials/'.$document->id.'/versions/1.pdf');
});

test('archived public verification page shows history and still offers the official pdf', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create(['name' => 'Captain Reyes']);
    $document = createOfficialDocumentForVerification($captain, [
        'document_number' => 'RES-2026-120',
    ]);
    $document->forceFill([
        'status' => DocumentStatus::Archived,
        'archived_at' => now(),
        'archived_by_id' => $captain->id,
        'archive_reason' => 'Retained for historical reference after replacement.',
    ])->save();

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Archived Official Record')
        ->assertSee('historically valid')
        ->assertSee('Retained for historical reference after replacement.')
        ->assertSee('Captain Reyes')
        ->assertSee('View Official PDF')
        ->assertSee('Download Official PDF')
        ->assertDontSee('document-officials/'.$document->id.'/versions/1.pdf');

    $this->get(route('verification.document', $document->verification_token))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

test('revoked public verification page shows invalid history without pdf actions', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create(['name' => 'Captain Reyes']);
    $document = createOfficialDocumentForVerification($captain);
    $document->forceFill([
        'status' => DocumentStatus::Revoked,
        'revoked_at' => now(),
        'revoked_by_id' => $captain->id,
        'revoke_reason' => 'Barangay validation found the document was issued in error.',
    ])->save();

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Revoked Document')
        ->assertSee('not a current valid public document')
        ->assertSee('Barangay validation found the document was issued in error.')
        ->assertSee('Captain Reyes')
        ->assertDontSee('View Official PDF')
        ->assertDontSee('Download Official PDF');

    $this->get(route('verification.document', $document->verification_token))
        ->assertNotFound();
});

test('superseded public verification page shows replacement context without pdf actions', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create(['name' => 'Captain Reyes']);
    $replacement = createOfficialDocumentForVerification($captain, [
        'document_number' => 'RES-2026-220',
        'title' => 'Replacement Resolution',
    ]);
    $document = createOfficialDocumentForVerification($captain);
    $document->forceFill([
        'status' => DocumentStatus::Superseded,
        'superseded_at' => now(),
        'superseded_by_id' => $captain->id,
        'supersede_reason' => 'A newer official resolution now controls this matter.',
        'superseded_by_document_record_id' => $replacement->id,
        'supersede_reference' => 'Resolution No. 2026-220',
    ])->save();

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Superseded Document')
        ->assertSee('A newer official resolution now controls this matter.')
        ->assertSee('Replacement: RES-2026-220')
        ->assertSee($replacement->verificationUrl(), false)
        ->assertDontSee('View Official PDF')
        ->assertDontSee('Download Official PDF');

    $this->get(route('verification.document', $document->verification_token))
        ->assertNotFound();
});

test('public pdf route serves only the current approved published version', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->published()->create([
        'approved_at' => now(),
        'approved_by_id' => $captain->id,
    ]);
    Storage::disk('local')->put('document-officials/'.$document->id.'/versions/1.pdf', '%PDF approved');
    Storage::disk('local')->put('document-officials/'.$document->id.'/versions/0.pdf', '%PDF old');

    DocumentFileVersion::factory()
        ->for($document, 'documentRecord')
        ->for($captain, 'approver')
        ->create([
            'version_number' => 0,
            'file_path' => 'document-officials/'.$document->id.'/versions/0.pdf',
            'is_current' => false,
        ]);

    DocumentFileVersion::factory()
        ->for($document, 'documentRecord')
        ->for($captain, 'approver')
        ->create([
            'version_number' => 1,
            'file_path' => 'document-officials/'.$document->id.'/versions/1.pdf',
            'original_name' => 'current-official.pdf',
            'is_current' => true,
        ]);

    $this->get(route('verification.document', $document->verification_token))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('verification.document', ['token' => $document->verification_token, 'download' => 1]))
        ->assertSuccessful()
        ->assertDownload('current-official.pdf');
});

test('public pdf route serves archived official documents', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $document = createOfficialDocumentForVerification($captain);
    $document->forceFill([
        'status' => DocumentStatus::Archived,
        'archived_at' => now(),
        'archived_by_id' => $captain->id,
        'archive_reason' => 'No longer active but historically valid.',
    ])->save();

    $this->get(route('verification.document', $document->verification_token))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

test('unpublished records do not expose public pdf actions or document response', function (DocumentStatus $status) {
    Storage::fake('local');

    $document = DocumentRecord::factory()
        ->withDraftPdf('document-drafts/private.pdf')
        ->create(['status' => $status]);

    Storage::disk('local')->put('document-drafts/private.pdf', '%PDF draft');

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee($status->verificationHeading())
        ->assertDontSee('View Official PDF')
        ->assertDontSee('Download Official PDF')
        ->assertDontSee('document-drafts/private.pdf');

    $this->get(route('verification.document', $document->verification_token))
        ->assertNotFound();
})->with([
    'draft' => [DocumentStatus::Draft],
    'pending approval' => [DocumentStatus::PendingApproval],
    'needs revision' => [DocumentStatus::NeedsRevision],
]);
