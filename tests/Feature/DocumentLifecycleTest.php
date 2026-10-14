<?php

use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function createLifecyclePublishedDocument(array $attributes = []): DocumentRecord
{
    return DocumentRecord::factory()
        ->published()
        ->create(array_merge([
            'approved_at' => now(),
            'approved_by_id' => User::factory()->captain(),
        ], $attributes));
}

test('captain archives a published document with a reason', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);
    $token = $document->verification_token;

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('archiveReason', 'Retention period completed; historical reference only.')
        ->call('archiveDocument')
        ->assertHasNoErrors();

    $document->refresh();
    $auditLog = AuditLog::query()->where('action', 'document_archived')->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Archived)
        ->and($document->archived_by_id)->toBe($captain->id)
        ->and($document->archived_at)->not->toBeNull()
        ->and($document->archive_reason)->toBe('Retention period completed; historical reference only.')
        ->and($document->verification_token)->toBe($token)
        ->and($auditLog->actor_id)->toBe($captain->id)
        ->and($auditLog->auditable_id)->toBe($document->id)
        ->and($auditLog->context)->toMatchArray([
            'reason' => 'Retention period completed; historical reference only.',
        ]);
});

test('captain revokes a published document with a reason', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);
    $token = $document->verification_token;

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('revokeReason', 'Issued in error after validation review.')
        ->call('revokeDocument')
        ->assertHasNoErrors();

    $document->refresh();
    $auditLog = AuditLog::query()->where('action', 'document_revoked')->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Revoked)
        ->and($document->revoked_by_id)->toBe($captain->id)
        ->and($document->revoked_at)->not->toBeNull()
        ->and($document->revoke_reason)->toBe('Issued in error after validation review.')
        ->and($document->verification_token)->toBe($token)
        ->and($auditLog->actor_id)->toBe($captain->id)
        ->and($auditLog->auditable_id)->toBe($document->id)
        ->and($auditLog->context)->toMatchArray([
            'reason' => 'Issued in error after validation review.',
        ]);
});

test('captain supersedes a published document with a published replacement document', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);
    $replacement = createLifecyclePublishedDocument([
        'approved_by_id' => $captain->id,
        'document_number' => 'RES-2026-200',
        'title' => 'Replacement Resolution',
    ]);
    $token = $document->verification_token;

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('supersedeReason', 'Corrected by a newer approved resolution.')
        ->set('supersedeReplacementDocumentId', (string) $replacement->id)
        ->call('supersedeDocument')
        ->assertHasNoErrors();

    $document->refresh();
    $auditLog = AuditLog::query()->where('action', 'document_superseded')->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Superseded)
        ->and($document->superseded_by_id)->toBe($captain->id)
        ->and($document->superseded_at)->not->toBeNull()
        ->and($document->supersede_reason)->toBe('Corrected by a newer approved resolution.')
        ->and($document->superseded_by_document_record_id)->toBe($replacement->id)
        ->and($document->verification_token)->toBe($token)
        ->and($auditLog->context)->toMatchArray([
            'reason' => 'Corrected by a newer approved resolution.',
            'replacement_document_record_id' => $replacement->id,
        ]);
});

test('captain supersedes a published document with a replacement reference', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('supersedeReason', 'External replacement was approved by council.')
        ->set('supersedeReference', 'Resolution No. 2026-211')
        ->call('supersedeDocument')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Superseded)
        ->and($document->supersede_reason)->toBe('External replacement was approved by council.')
        ->and($document->superseded_by_document_record_id)->toBeNull()
        ->and($document->supersede_reference)->toBe('Resolution No. 2026-211');
});

test('secretary cannot change published document lifecycle', function () {
    $secretary = User::factory()->create();
    $document = createLifecyclePublishedDocument();

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('archiveReason', 'Not allowed.')
        ->call('archiveDocument')
        ->assertForbidden();

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('revokeReason', 'Not allowed.')
        ->call('revokeDocument')
        ->assertForbidden();

    Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('supersedeReason', 'Not allowed.')
        ->set('supersedeReference', 'Replacement reference')
        ->call('supersedeDocument')
        ->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
    expect(AuditLog::query()->count())->toBe(0);
});

test('purok chairperson cannot access document detail page for lifecycle operations', function () {
    $chairperson = User::factory()->purokChairperson()->create();
    $document = createLifecyclePublishedDocument();

    Livewire::actingAs($chairperson)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->assertForbidden();

    $this->actingAs($chairperson)
        ->get(route('documents.show', $document))
        ->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
    expect(AuditLog::query()->count())->toBe(0);
});

test('lifecycle actions require reasons', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('archiveDocument')
        ->assertHasErrors(['archiveReason' => ['required']]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('revokeDocument')
        ->assertHasErrors(['revokeReason' => ['required']]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('supersedeDocument')
        ->assertHasErrors([
            'supersedeReason' => ['required'],
            'supersedeReference' => ['required_without'],
        ]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
    expect(AuditLog::query()->count())->toBe(0);
});

test('supersede requires a replacement document or reference', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('supersedeReason', 'Replacement context is required.')
        ->call('supersedeDocument')
        ->assertHasErrors(['supersedeReference' => ['required_without']]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
});

test('supersede replacement must be another published document', function () {
    $captain = User::factory()->captain()->create();
    $document = createLifecyclePublishedDocument(['approved_by_id' => $captain->id]);
    $draftReplacement = DocumentRecord::factory()->create();

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set('supersedeReason', 'Replacement must already be official.')
        ->set('supersedeReplacementDocumentId', (string) $draftReplacement->id)
        ->call('supersedeDocument')
        ->assertHasErrors(['supersedeReplacementDocumentId' => ['exists']]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
});

test('non published records cannot transition through lifecycle actions', function (DocumentStatus $status, string $method, string $field) {
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->create(['status' => $status]);

    $component = Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->set($field, 'Lifecycle reason.');

    if ($method === 'supersedeDocument') {
        $component->set('supersedeReference', 'Replacement reference.');
    }

    $component
        ->call($method)
        ->assertForbidden();

    expect($document->refresh()->status)->toBe($status);
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'draft archive' => [DocumentStatus::Draft, 'archiveDocument', 'archiveReason'],
    'pending archive' => [DocumentStatus::PendingApproval, 'archiveDocument', 'archiveReason'],
    'needs revision archive' => [DocumentStatus::NeedsRevision, 'archiveDocument', 'archiveReason'],
    'archived archive' => [DocumentStatus::Archived, 'archiveDocument', 'archiveReason'],
    'revoked archive' => [DocumentStatus::Revoked, 'archiveDocument', 'archiveReason'],
    'superseded archive' => [DocumentStatus::Superseded, 'archiveDocument', 'archiveReason'],
    'draft revoke' => [DocumentStatus::Draft, 'revokeDocument', 'revokeReason'],
    'pending revoke' => [DocumentStatus::PendingApproval, 'revokeDocument', 'revokeReason'],
    'needs revision revoke' => [DocumentStatus::NeedsRevision, 'revokeDocument', 'revokeReason'],
    'archived revoke' => [DocumentStatus::Archived, 'revokeDocument', 'revokeReason'],
    'revoked revoke' => [DocumentStatus::Revoked, 'revokeDocument', 'revokeReason'],
    'superseded revoke' => [DocumentStatus::Superseded, 'revokeDocument', 'revokeReason'],
    'draft supersede' => [DocumentStatus::Draft, 'supersedeDocument', 'supersedeReason'],
    'pending supersede' => [DocumentStatus::PendingApproval, 'supersedeDocument', 'supersedeReason'],
    'needs revision supersede' => [DocumentStatus::NeedsRevision, 'supersedeDocument', 'supersedeReason'],
    'archived supersede' => [DocumentStatus::Archived, 'supersedeDocument', 'supersedeReason'],
    'revoked supersede' => [DocumentStatus::Revoked, 'supersedeDocument', 'supersedeReason'],
    'superseded supersede' => [DocumentStatus::Superseded, 'supersedeDocument', 'supersedeReason'],
]);

test('document record model enforces published status for lifecycle transitions', function (DocumentStatus $status) {
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->create(['status' => $status]);

    expect(fn () => $document->archive($captain, 'Reason'))
        ->toThrow(ValidationException::class);
    expect(fn () => $document->revoke($captain, 'Reason'))
        ->toThrow(ValidationException::class);
    expect(fn () => $document->supersede($captain, 'Reason', null, 'REF-1'))
        ->toThrow(ValidationException::class);
})->with([
    'draft' => [DocumentStatus::Draft],
    'pending' => [DocumentStatus::PendingApproval],
    'needs revision' => [DocumentStatus::NeedsRevision],
    'archived' => [DocumentStatus::Archived],
    'revoked' => [DocumentStatus::Revoked],
    'superseded' => [DocumentStatus::Superseded],
]);
