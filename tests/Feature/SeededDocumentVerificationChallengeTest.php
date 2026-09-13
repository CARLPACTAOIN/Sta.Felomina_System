<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Seeded Document Verification & Lifecycle Challenge Tests
|--------------------------------------------------------------------------
| Empirical verification of public verification endpoints and document
| lifecycle state machine on the seeded demo dataset.
*/

beforeEach(function () {
    $this->seed(DemoDataSeeder::class);
});

test('challenge 1: all 15 seeded published documents return 200 and stream valid PDF', function () {
    $publishedDocs = DocumentRecord::query()
        ->where('status', DocumentStatus::Published)
        ->get();

    expect($publishedDocs)->toHaveCount(15);

    foreach ($publishedDocs as $doc) {
        // 1. Verify show endpoint
        $showResponse = $this->get(route('verification.show', $doc->verification_token));
        $showResponse->assertSuccessful()
            ->assertSee('Verified Official Barangay Document')
            ->assertSee('Barangay Sta. Felomina')
            ->assertSee($doc->document_number)
            ->assertSee($doc->title)
            ->assertSee('View Official PDF')
            ->assertSee('Download Official PDF')
            ->assertDontSee('This page does not confirm authenticity')
            ->assertDontSee('not a current valid public document');

        // 2. Verify PDF stream endpoint
        $streamResponse = $this->get(route('verification.document', $doc->verification_token));
        $streamResponse->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');

        $content = $streamResponse->streamedContent();
        expect(str_starts_with($content, '%PDF-'))->toBeTrue(
            "Document {$doc->document_number} does not return a valid PDF header"
        );
        expect($content)->toContain($doc->document_number);

        // 3. Verify PDF download endpoint
        $downloadResponse = $this->get(route('verification.document', [
            'token' => $doc->verification_token,
            'download' => 1,
        ]));
        $downloadResponse->assertSuccessful()
            ->assertDownload("{$doc->document_number}.pdf");
    }
});

test('challenge 2: all 3 seeded archived documents display proper notices and stream valid PDF', function () {
    $archivedDocs = DocumentRecord::query()
        ->where('status', DocumentStatus::Archived)
        ->get();

    expect($archivedDocs)->toHaveCount(3);

    foreach ($archivedDocs as $doc) {
        expect($doc->archive_reason)->not->toBeEmpty();
        expect($doc->archived_at)->not->toBeNull();

        $showResponse = $this->get(route('verification.show', $doc->verification_token));
        $showResponse->assertSuccessful()
            ->assertSee('Archived Official Record')
            ->assertSee('This document remains historically valid, but it is no longer the current active record.')
            ->assertSee($doc->document_number)
            ->assertSee($doc->title)
            ->assertSee($doc->archive_reason)
            ->assertSee('View Official PDF')
            ->assertSee('Download Official PDF');

        // Archived documents must still allow access to official PDF
        $streamResponse = $this->get(route('verification.document', $doc->verification_token));
        $streamResponse->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');

        $content = $streamResponse->streamedContent();
        expect(str_starts_with($content, '%PDF-'))->toBeTrue();
    }
});

test('challenge 3: all 3 seeded revoked documents display revocation notices and block PDF access', function () {
    $revokedDocs = DocumentRecord::query()
        ->where('status', DocumentStatus::Revoked)
        ->get();

    expect($revokedDocs)->toHaveCount(3);

    foreach ($revokedDocs as $doc) {
        expect($doc->revoke_reason)->not->toBeEmpty();
        expect($doc->revoked_at)->not->toBeNull();

        $showResponse = $this->get(route('verification.show', $doc->verification_token));
        $showResponse->assertSuccessful()
            ->assertSee('Revoked Document')
            ->assertSee('This verification token is valid, but the document is not a current valid public document.')
            ->assertSee($doc->document_number)
            ->assertSee($doc->title)
            ->assertSee($doc->revoke_reason)
            ->assertDontSee('View Official PDF')
            ->assertDontSee('Download Official PDF');

        // Revoked documents must 404 on PDF access
        $this->get(route('verification.document', $doc->verification_token))
            ->assertNotFound();
    }
});

test('challenge 4: all 3 seeded superseded documents display notices, block PDF, and identify replacement', function () {
    $supersededDocs = DocumentRecord::query()
        ->where('status', DocumentStatus::Superseded)
        ->with('supersededByDocumentRecord')
        ->get();

    expect($supersededDocs)->toHaveCount(3);

    $expectedPairs = [
        'ORD-2024-001' => 'ORD-2026-001',
        'RES-2024-002' => 'RES-2026-001',
        'EO-2025-002' => 'EO-2026-001',
    ];

    foreach ($supersededDocs as $doc) {
        expect($doc->supersede_reason)->not->toBeEmpty();
        expect($doc->superseded_at)->not->toBeNull();
        expect($doc->superseded_by_document_record_id)->not->toBeNull();
        expect($doc->supersededByDocumentRecord)->not->toBeNull();

        $expectedReplacementNumber = $expectedPairs[$doc->document_number] ?? null;
        expect($doc->supersededByDocumentRecord->document_number)->toBe($expectedReplacementNumber);

        $showResponse = $this->get(route('verification.show', $doc->verification_token));
        $showResponse->assertSuccessful()
            ->assertSee('Superseded Document')
            ->assertSee('This verification token is valid, but the document is not a current valid public document.')
            ->assertSee($doc->document_number)
            ->assertSee($doc->title)
            ->assertSee($doc->supersede_reason)
            ->assertSee("Replacement: {$doc->supersededByDocumentRecord->document_number}")
            ->assertSee($doc->supersededByDocumentRecord->verificationUrl(), false)
            ->assertDontSee('View Official PDF')
            ->assertDontSee('Download Official PDF');

        // Superseded documents must 404 on PDF access
        $this->get(route('verification.document', $doc->verification_token))
            ->assertNotFound();
    }
});

test('challenge 5: all unapproved seeded records (draft, pending, needs revision) warn and block PDF', function () {
    $unapprovedDocs = DocumentRecord::query()
        ->whereIn('status', [
            DocumentStatus::Draft,
            DocumentStatus::PendingApproval,
            DocumentStatus::NeedsRevision,
        ])
        ->get();

    expect($unapprovedDocs)->toHaveCount(8); // 3 draft + 3 pending + 2 revision

    foreach ($unapprovedDocs as $doc) {
        $showResponse = $this->get(route('verification.show', $doc->verification_token));
        $showResponse->assertSuccessful()
            ->assertSee($doc->status->verificationHeading())
            ->assertSee('This page does not confirm authenticity. No official PDF is available for public viewing or download in this state.')
            ->assertSee('Verification token found, but this record is not yet an approved public document.')
            ->assertSee('Not approved')
            ->assertDontSee('View Official PDF')
            ->assertDontSee('Download Official PDF');

        // Document endpoint must 404
        $this->get(route('verification.document', $doc->verification_token))
            ->assertNotFound();
    }
});

test('challenge 6: adversarial edge cases on verification endpoints', function () {
    // 1. Non-existent token returns 404
    $this->get('/verify/nonexistenttoken1234567890abcdef1234567890')->assertNotFound();
    $this->get('/verify/nonexistenttoken1234567890abcdef1234567890/document')->assertNotFound();

    // 2. Malformed token characters return 404 (route constraint)
    $this->get('/verify/invalid-token-with-dashes')->assertNotFound();
    $this->get('/verify/invalid$token!')->assertNotFound();

    // 3. Document sequential ID cannot be used instead of token
    $published = DocumentRecord::query()->where('status', DocumentStatus::Published)->firstOrFail();
    $this->get('/verify/'.$published->id)->assertNotFound();
    $this->get('/verify/'.$published->id.'/document')->assertNotFound();

    // 4. If physical storage file is deleted/missing, document endpoint returns 404 gracefully (no 500 error)
    $version = $published->currentFileVersion();
    expect($version)->not->toBeNull();
    $originalPath = $version->file_path;

    Storage::disk('local')->delete($originalPath);
    $this->get(route('verification.document', $published->verification_token))
        ->assertNotFound();
});
