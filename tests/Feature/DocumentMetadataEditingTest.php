<?php

use App\Enums\DocumentStatus;
use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function editableMetadataPayload(DocumentRecord $document, array $overrides = []): array
{
    return array_merge([
        'metadataDocumentTypeId' => (string) $document->document_type_id,
        'metadataDocumentCategoryId' => (string) $document->document_category_id,
        'metadataDocumentNumber' => $document->document_number,
        'metadataTitle' => $document->title,
        'metadataDocumentDate' => $document->document_date->toDateString(),
        'metadataSubject' => $document->subject,
        'metadataKeywords' => $document->keywords ?? '',
        'metadataIdentityChangeConfirmed' => false,
        'metadataIsProjectRelated' => false,
        'metadataProjectName' => '',
        'metadataProjectLocation' => '',
        'metadataApprovedBudget' => '',
        'metadataProjectStartDate' => '',
        'metadataTargetCompletionDate' => '',
        'metadataProjectRemarks' => '',
    ], $overrides);
}

function fillMetadataForm($component, array $payload): mixed
{
    foreach ($payload as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

test('secretary updates draft metadata with optional project metadata while preserving qr token', function () {
    $secretary = User::factory()->create();
    $legacyCaptainCreator = User::factory()->captain()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution']);
    $category = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $document = DocumentRecord::factory()->create([
        'created_by_id' => $legacyCaptainCreator->id,
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
    ]);
    $token = $document->verification_token;

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataTitle' => 'Drainage Canal Rehabilitation',
        'metadataSubject' => 'Approves rehabilitation of the barangay drainage canal.',
        'metadataKeywords' => 'drainage, rehabilitation',
        'metadataIsProjectRelated' => true,
        'metadataProjectName' => 'Drainage Canal Rehabilitation Project',
        'metadataProjectLocation' => 'Purok 3, Barangay Sta. Felomina',
        'metadataApprovedBudget' => '1250000.50',
        'metadataProjectStartDate' => '2026-10-01',
        'metadataTargetCompletionDate' => '2026-12-15',
        'metadataProjectRemarks' => 'Priority flood-control project.',
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors()
        ->assertSet('editingMetadata', false);

    $document->refresh();

    expect($document->title)->toBe('Drainage Canal Rehabilitation')
        ->and($document->subject)->toBe('Approves rehabilitation of the barangay drainage canal.')
        ->and($document->keywords)->toBe('drainage, rehabilitation')
        ->and($document->verification_token)->toBe($token)
        ->and($document->is_project_related)->toBeTrue()
        ->and($document->project_name)->toBe('Drainage Canal Rehabilitation Project')
        ->and($document->project_location)->toBe('Purok 3, Barangay Sta. Felomina')
        ->and($document->approved_budget)->toBe('1250000.50')
        ->and($document->project_start_date?->toDateString())->toBe('2026-10-01')
        ->and($document->target_completion_date?->toDateString())->toBe('2026-12-15');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_metadata_updated',
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $document->id,
    ]);
});

test('captain cannot edit needs revision metadata', function () {
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->needsRevision()->create();

    Livewire::actingAs($captain)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit')
        ->assertForbidden();

    expect($document->refresh()->title)->not->toBe('Corrected Metadata Title');
});

test('purok chairperson cannot access document detail page or edit document metadata', function () {
    $chairperson = User::factory()->purokChairperson()->create();
    $document = DocumentRecord::factory()->create();

    Livewire::actingAs($chairperson)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->assertForbidden();

    $this->actingAs($chairperson)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

test('captain and secretary cannot edit metadata outside draft or needs revision', function (string $role, DocumentStatus $status) {
    $user = $role === 'captain'
        ? User::factory()->captain()->create()
        : User::factory()->create();
    $document = DocumentRecord::factory()->create(['status' => $status]);

    Livewire::actingAs($user)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit')
        ->assertForbidden();
})->with([
    'secretary pending approval' => ['secretary', DocumentStatus::PendingApproval],
    'secretary published' => ['secretary', DocumentStatus::Published],
    'secretary archived' => ['secretary', DocumentStatus::Archived],
    'secretary revoked' => ['secretary', DocumentStatus::Revoked],
    'secretary superseded' => ['secretary', DocumentStatus::Superseded],
    'captain pending approval' => ['captain', DocumentStatus::PendingApproval],
    'captain published' => ['captain', DocumentStatus::Published],
    'captain archived' => ['captain', DocumentStatus::Archived],
    'captain revoked' => ['captain', DocumentStatus::Revoked],
    'captain superseded' => ['captain', DocumentStatus::Superseded],
]);

test('duplicate edited document numbers are rejected within type and year', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['number_prefix' => 'RES']);
    $category = DocumentCategory::factory()->create();
    $existing = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-001',
        'document_year' => 2026,
    ]);
    $document = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'document_number' => 'RES-2026-002',
        'document_year' => 2026,
    ]);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataDocumentNumber' => $existing->document_number,
        'metadataIdentityChangeConfirmed' => true,
    ]))
        ->call('updateMetadata')
        ->assertHasErrors(['metadataDocumentNumber']);

    expect($document->refresh()->document_number)->toBe('RES-2026-002');
});

test('document identity changes require confirmation and preserve verification token', function () {
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->create([
        'document_number' => 'RES-2026-010',
        'document_year' => 2026,
        'document_date' => '2026-09-22',
    ]);
    $token = $document->verification_token;

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataDocumentNumber' => 'RES-2026-011',
    ]))
        ->call('updateMetadata')
        ->assertHasErrors(['metadataIdentityChangeConfirmed']);

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataDocumentNumber' => 'RES-2026-011',
        'metadataIdentityChangeConfirmed' => true,
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors();

    expect($document->refresh()->document_number)->toBe('RES-2026-011')
        ->and($document->verification_token)->toBe($token);
});

test('metadata edits refresh category suggestions and preserve manual override audit behavior', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    $rule = CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Drainage Rule',
        'keywords' => ['drainage'],
        'priority' => 10,
    ]);
    $document = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $governance->id,
        'title' => 'Original Governance Title',
        'subject' => 'Original subject.',
    ]);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataTitle' => 'Drainage Repair Resolution',
        'metadataSubject' => 'Approves drainage repair.',
    ]));

    $component
        ->call('beginMetadataManualCategoryOverride')
        ->set('metadataDocumentCategoryId', (string) $governance->id)
        ->call('updateMetadata')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->suggested_document_category_id)->toBe($infrastructure->id)
        ->and($document->category_suggestion_rule_id)->toBe($rule->id)
        ->and($document->document_category_id)->toBe($governance->id)
        ->and($document->category_overridden_by_id)->toBe($secretary->id)
        ->and($document->category_overridden_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_category_suggested',
        'auditable_id' => $document->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_category_overridden',
        'auditable_id' => $document->id,
    ]);
});

test('project metadata appears on internal and public-safe surfaces and is searchable', function () {
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->projectRelated()->create([
        'title' => 'Covered Court Funding Resolution',
        'project_name' => 'Covered Court Rehabilitation',
        'project_location' => 'Barangay Plaza',
    ]);

    $this->actingAs($secretary)
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertSee('Covered Court Rehabilitation')
        ->assertSee('Barangay Plaza');

    $this->get(route('verification.show', $document->verification_token))
        ->assertSuccessful()
        ->assertSee('Covered Court Rehabilitation')
        ->assertSee('Barangay Plaza');

    $this->actingAs($secretary)
        ->get(route('documents.index', ['q' => 'covered court']))
        ->assertSuccessful()
        ->assertSee('Covered Court Funding Resolution');
});

test('turning off project metadata clears project fields', function () {
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()->projectRelated()->create();

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataIsProjectRelated' => false,
        'metadataProjectName' => 'Should be cleared',
        'metadataProjectLocation' => 'Should be cleared',
        'metadataApprovedBudget' => '100.00',
        'metadataProjectStartDate' => '2026-10-01',
        'metadataTargetCompletionDate' => '2026-10-02',
        'metadataProjectRemarks' => 'Should be cleared',
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->is_project_related)->toBeFalse()
        ->and($document->project_name)->toBeNull()
        ->and($document->project_location)->toBeNull()
        ->and($document->approved_budget)->toBeNull()
        ->and($document->project_start_date)->toBeNull()
        ->and($document->target_completion_date)->toBeNull()
        ->and($document->project_remarks)->toBeNull();
});

test('document record policy gates metadata edits by permission and status', function (string $role, DocumentStatus $status, bool $expected) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };
    $document = DocumentRecord::factory()->create(['status' => $status]);

    expect(Gate::forUser($user)->allows('updateMetadata', $document))->toBe($expected);
})->with([
    'secretary draft' => ['secretary', DocumentStatus::Draft, true],
    'secretary needs revision' => ['secretary', DocumentStatus::NeedsRevision, true],
    'secretary pending approval' => ['secretary', DocumentStatus::PendingApproval, false],
    'captain draft' => ['captain', DocumentStatus::Draft, false],
    'captain needs revision' => ['captain', DocumentStatus::NeedsRevision, false],
    'captain published' => ['captain', DocumentStatus::Published, false],
    'purok draft' => ['purok', DocumentStatus::Draft, false],
]);

test('metadata edit automatically replaces the category when a higher priority rule matches', function () {
    $secretary = User::factory()->create();
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Road Rule',
        'keywords' => ['road'],
        'priority' => 10,
    ]);
    $document = DocumentRecord::factory()->create([
        'created_by_id' => $secretary->id,
        'document_category_id' => $governance->id,
        'title' => 'Original record',
        'subject' => 'Original subject',
    ]);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataTitle' => 'Barangay road rehabilitation',
        'metadataSubject' => 'Concrete the main road.',
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors();

    expect($document->refresh()->document_category_id)->toBe($infrastructure->id)
        ->and($document->suggested_document_category_id)->toBe($infrastructure->id)
        ->and($document->category_overridden_at)->toBeNull();
});

test('metadata edit retains and audits a manual category when no rule matches', function () {
    $secretary = User::factory()->create();
    $category = DocumentCategory::factory()->create(['name' => 'Governance']);
    $document = DocumentRecord::factory()->create([
        'created_by_id' => $secretary->id,
        'document_category_id' => $category->id,
        'title' => 'Original record',
        'subject' => 'Original subject',
    ]);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataTitle' => 'Community recognition',
        'metadataSubject' => 'Recognizes local volunteers.',
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors();

    $document->refresh();
    $audit = $document->auditLogs()->where('action', 'document_category_overridden')->latest()->firstOrFail();

    expect($document->document_category_id)->toBe($category->id)
        ->and($document->suggested_document_category_id)->toBeNull()
        ->and($document->category_overridden_by_id)->toBe($secretary->id)
        ->and($audit->context['reason'])->toBe('no_rule_matched');
});

test('secretary can reset a prior manual category correction to the automatic result', function () {
    $secretary = User::factory()->create();
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    $rule = CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Road Rule',
        'keywords' => ['road'],
        'priority' => 10,
    ]);
    $document = DocumentRecord::factory()->create([
        'created_by_id' => $secretary->id,
        'document_category_id' => $governance->id,
        'suggested_document_category_id' => $infrastructure->id,
        'category_suggestion_rule_id' => $rule->id,
        'category_suggestion_terms' => ['road'],
        'category_suggestion_reason' => 'Matched rule "Road Rule" using: road.',
        'category_suggested_at' => now(),
        'category_overridden_by_id' => $secretary->id,
        'category_overridden_at' => now(),
        'title' => 'Road rehabilitation',
        'subject' => 'Repairs the barangay road.',
    ]);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit')
        ->call('useMetadataSuggestedCategory');

    fillMetadataForm($component, editableMetadataPayload($document, [
        'metadataDocumentCategoryId' => (string) $infrastructure->id,
    ]))
        ->call('updateMetadata')
        ->assertHasNoErrors();

    expect($document->refresh()->document_category_id)->toBe($infrastructure->id)
        ->and($document->category_overridden_by_id)->toBeNull()
        ->and($document->category_overridden_at)->toBeNull();
});
