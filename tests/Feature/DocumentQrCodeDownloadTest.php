<?php

use App\Models\DocumentRecord;
use App\Models\User;

test('secretary downloads a qr svg for the document permanent verification url', function () {
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->create([
        'document_number' => 'RES-2026-014',
        'verification_token' => 'PermanentTokenForDraftDocument1234567890ABCDE',
    ]);

    $response = $this->actingAs($secretary)->get(route('documents.qr-code', $document));

    $response
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'attachment; filename="res-2026-014-qr.svg"')
        ->assertHeader('X-Verification-Route', $document->verificationUrl());

    expect($response->getContent())
        ->toContain('<svg')
        ->toContain('xmlns="http://www.w3.org/2000/svg"');
});

test('qr download keeps the same document verification identity across requests', function () {
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->create([
        'verification_token' => 'StableTokenForRepeatedDownload1234567890ABCDE',
    ]);

    $firstResponse = $this->actingAs($captain)->get(route('documents.qr-code', $document));
    $secondResponse = $this->actingAs($captain)->get(route('documents.qr-code', $document));

    $document->refresh();

    expect($document->verification_token)->toBe('StableTokenForRepeatedDownload1234567890ABCDE')
        ->and($secondResponse->getContent())->toBe($firstResponse->getContent())
        ->and($secondResponse->headers->get('X-Verification-Route'))->toBe($document->verificationUrl());
});

test('qr download requires an authenticated document manager', function () {
    $document = DocumentRecord::factory()->create();

    $this->get(route('documents.qr-code', $document))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->purokChairperson()->create())
        ->get(route('documents.qr-code', $document))
        ->assertForbidden();
});
