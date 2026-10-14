<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function makeTestDocument(DocumentStatus $status = DocumentStatus::Draft, bool $withPdf = false): DocumentRecord
{
    $attributes = ['status' => $status];

    if ($withPdf) {
        $attributes['draft_pdf_path'] = 'document-drafts/sample.pdf';
        $attributes['draft_pdf_original_name'] = 'sample.pdf';
    }

    return DocumentRecord::factory()->create($attributes);
}

test('viewAny ability allows captain and secretary but denies purok chairperson and guest', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();

    expect(Gate::forUser($captain)->allows('viewAny', DocumentRecord::class))->toBeTrue()
        ->and(Gate::forUser($secretary)->allows('viewAny', DocumentRecord::class))->toBeTrue()
        ->and(Gate::forUser($chairperson)->allows('viewAny', DocumentRecord::class))->toBeFalse()
        ->and(Gate::allows('viewAny', DocumentRecord::class))->toBeFalse();
});

test('view ability allows captain and secretary for any status but denies purok chairperson', function (DocumentStatus $status) {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();
    $document = makeTestDocument($status);

    expect(Gate::forUser($captain)->allows('view', $document))->toBeTrue()
        ->and(Gate::forUser($secretary)->allows('view', $document))->toBeTrue()
        ->and(Gate::forUser($chairperson)->allows('view', $document))->toBeFalse();
})->with(DocumentStatus::cases());

test('create ability allows only secretary', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();

    expect(Gate::forUser($captain)->allows('create', DocumentRecord::class))->toBeFalse()
        ->and(Gate::forUser($secretary)->allows('create', DocumentRecord::class))->toBeTrue()
        ->and(Gate::forUser($chairperson)->allows('create', DocumentRecord::class))->toBeFalse();
});

test('updateMetadata ability is allowed only on draft and needs revision states for authorized staff', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status);

    expect(Gate::forUser($user)->allows('updateMetadata', $document))->toBe($expected);
})->with([
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, false],
    'captain published' => ['captain', DocumentStatus::Published, false],
    'captain archived' => ['captain', DocumentStatus::Archived, false],
    'captain revoked' => ['captain', DocumentStatus::Revoked, false],
    'captain superseded' => ['captain', DocumentStatus::Superseded, false],

    'secretary draft' => ['secretary', DocumentStatus::Draft, true],
    'secretary needs revision' => ['secretary', DocumentStatus::NeedsRevision, true],
    'secretary pending approval' => ['secretary', DocumentStatus::PendingApproval, false],
    'secretary published' => ['secretary', DocumentStatus::Published, false],
    'secretary archived' => ['secretary', DocumentStatus::Archived, false],
    'secretary revoked' => ['secretary', DocumentStatus::Revoked, false],
    'secretary superseded' => ['secretary', DocumentStatus::Superseded, false],

    'purok draft' => ['purok', DocumentStatus::Draft, false],
    'purok needs revision' => ['purok', DocumentStatus::NeedsRevision, false],
]);

test('uploadDraftPdf ability is allowed only on draft and needs revision states for authorized staff', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status);

    expect(Gate::forUser($user)->allows('uploadDraftPdf', $document))->toBe($expected);
})->with([
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, false],
    'captain published' => ['captain', DocumentStatus::Published, false],

    'secretary draft' => ['secretary', DocumentStatus::Draft, true],
    'secretary needs revision' => ['secretary', DocumentStatus::NeedsRevision, true],
    'secretary pending approval' => ['secretary', DocumentStatus::PendingApproval, false],
    'secretary published' => ['secretary', DocumentStatus::Published, false],

    'purok draft' => ['purok', DocumentStatus::Draft, false],
]);

test('downloadDraftPdf ability allows captain and secretary but denies purok chairperson', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();
    $document = makeTestDocument(DocumentStatus::Draft, withPdf: true);

    expect(Gate::forUser($captain)->allows('downloadDraftPdf', $document))->toBeTrue()
        ->and(Gate::forUser($secretary)->allows('downloadDraftPdf', $document))->toBeTrue()
        ->and(Gate::forUser($chairperson)->allows('downloadDraftPdf', $document))->toBeFalse();
});

test('downloadQrCode ability allows only secretary', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();
    $document = makeTestDocument(DocumentStatus::Draft);

    expect(Gate::forUser($captain)->allows('downloadQrCode', $document))->toBeFalse()
        ->and(Gate::forUser($secretary)->allows('downloadQrCode', $document))->toBeTrue()
        ->and(Gate::forUser($chairperson)->allows('downloadQrCode', $document))->toBeFalse();
});

test('review ability allows captain but denies secretary and purok chairperson', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create();

    expect(Gate::forUser($captain)->allows('review', DocumentRecord::class))->toBeTrue()
        ->and(Gate::forUser($secretary)->allows('review', DocumentRecord::class))->toBeFalse()
        ->and(Gate::forUser($chairperson)->allows('review', DocumentRecord::class))->toBeFalse();
});

test('submit ability requires draft or needs revision status with an uploaded draft pdf', function (string $role, DocumentStatus $status, bool $withPdf, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status, withPdf: $withPdf);

    expect(Gate::forUser($user)->allows('submit', $document))->toBe($expected);
})->with([
    'secretary draft with pdf' => ['secretary', DocumentStatus::Draft, true, true],
    'secretary needs revision with pdf' => ['secretary', DocumentStatus::NeedsRevision, true, true],
    'secretary draft without pdf' => ['secretary', DocumentStatus::Draft, false, false],
    'secretary needs revision without pdf' => ['secretary', DocumentStatus::NeedsRevision, false, false],
    'secretary pending approval with pdf' => ['secretary', DocumentStatus::PendingApproval, true, false],
    'secretary published with pdf' => ['secretary', DocumentStatus::Published, true, false],

    'captain draft with pdf' => ['captain', DocumentStatus::Draft, true, false],
    'captain draft without pdf' => ['captain', DocumentStatus::Draft, false, false],

    'purok draft with pdf' => ['purok', DocumentStatus::Draft, true, false],
]);

test('returnForRevision ability allows only captain on pending approval documents', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status, withPdf: true);

    expect(Gate::forUser($user)->allows('returnForRevision', $document))->toBe($expected);
})->with([
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, true],
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain published' => ['captain', DocumentStatus::Published, false],

    'secretary pending approval' => ['secretary', DocumentStatus::PendingApproval, false],
    'purok pending approval' => ['purok', DocumentStatus::PendingApproval, false],
]);

test('approve ability allows only captain on pending approval documents with draft pdf', function (string $role, DocumentStatus $status, bool $withPdf, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status, withPdf: $withPdf);

    expect(Gate::forUser($user)->allows('approve', $document))->toBe($expected);
})->with([
    'captain pending approval with pdf' => ['captain', DocumentStatus::PendingApproval, true, true],
    'captain pending approval without pdf' => ['captain', DocumentStatus::PendingApproval, false, false],
    'captain draft with pdf' => ['captain', DocumentStatus::Draft, true, false],
    'captain published with pdf' => ['captain', DocumentStatus::Published, true, false],

    'secretary pending approval with pdf' => ['secretary', DocumentStatus::PendingApproval, true, false],
    'purok pending approval with pdf' => ['purok', DocumentStatus::PendingApproval, true, false],
]);

test('archive ability allows only captain on published documents', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status);

    expect(Gate::forUser($user)->allows('archive', $document))->toBe($expected);
})->with([
    'captain published' => ['captain', DocumentStatus::Published, true],
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain archived' => ['captain', DocumentStatus::Archived, false],
    'captain revoked' => ['captain', DocumentStatus::Revoked, false],
    'captain superseded' => ['captain', DocumentStatus::Superseded, false],

    'secretary published' => ['secretary', DocumentStatus::Published, false],
    'purok published' => ['purok', DocumentStatus::Published, false],
]);

test('revoke ability allows only captain on published documents', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status);

    expect(Gate::forUser($user)->allows('revoke', $document))->toBe($expected);
})->with([
    'captain published' => ['captain', DocumentStatus::Published, true],
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain archived' => ['captain', DocumentStatus::Archived, false],
    'captain revoked' => ['captain', DocumentStatus::Revoked, false],
    'captain superseded' => ['captain', DocumentStatus::Superseded, false],

    'secretary published' => ['secretary', DocumentStatus::Published, false],
    'purok published' => ['purok', DocumentStatus::Published, false],
]);

test('supersede ability allows only captain on published documents', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'secretary' => User::factory()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
    };
    $document = makeTestDocument($status);

    expect(Gate::forUser($user)->allows('supersede', $document))->toBe($expected);
})->with([
    'captain published' => ['captain', DocumentStatus::Published, true],
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain archived' => ['captain', DocumentStatus::Archived, false],
    'captain revoked' => ['captain', DocumentStatus::Revoked, false],
    'captain superseded' => ['captain', DocumentStatus::Superseded, false],

    'secretary published' => ['secretary', DocumentStatus::Published, false],
    'purok published' => ['purok', DocumentStatus::Published, false],
]);
