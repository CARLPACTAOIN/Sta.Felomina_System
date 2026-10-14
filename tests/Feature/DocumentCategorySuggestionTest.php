<?php

use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Livewire\Livewire;

test('document creation applies deterministic category suggestion and records audit evidence', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution', 'number_prefix' => 'RES']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $health = DocumentCategory::factory()->create(['name' => 'Health']);
    $lowerPriorityRule = CategorizationRule::factory()->create([
        'document_category_id' => $health->id,
        'name' => 'Health Rehabilitation',
        'keywords' => ['rehabilitation'],
        'priority' => 50,
    ]);
    $winningRule = CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Infrastructure Drainage',
        'keywords' => ['drainage', 'road'],
        'priority' => 10,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentNumber', 'RES-2026-001')
        ->set('title', 'Drainage Rehabilitation')
        ->set('documentDate', '2026-09-12')
        ->set('subject', 'Approves road drainage repair near the health center.')
        ->set('keywords', 'public works')
        ->assertSet('documentCategoryId', (string) $infrastructure->id)
        ->assertSet('suggestedDocumentCategoryId', $infrastructure->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $document = DocumentRecord::query()->firstOrFail();

    expect($document->document_category_id)->toBe($infrastructure->id)
        ->and($document->suggested_document_category_id)->toBe($infrastructure->id)
        ->and($document->category_suggestion_rule_id)->toBe($winningRule->id)
        ->and($document->category_suggestion_terms)->toBe(['drainage', 'road'])
        ->and($document->category_suggestion_reason)->toBe('Matched rule "Infrastructure Drainage" using: drainage, road.')
        ->and($document->category_overridden_at)->toBeNull()
        ->and($lowerPriorityRule->id)->not->toBe($winningRule->id);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_category_suggested',
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $document->id,
    ]);

    expect($document->auditLogs()->where('action', 'document_category_overridden')->exists())->toBeFalse();
});

test('manual category override preserves suggestion metadata and writes override audit event', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution', 'number_prefix' => 'RES']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    $rule = CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Infrastructure Rule',
        'keywords' => ['road'],
        'priority' => 10,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentNumber', 'RES-2026-002')
        ->set('title', 'Road Clearing Resolution')
        ->set('documentDate', '2026-09-12')
        ->set('subject', 'Road clearing schedule for administrative compliance.')
        ->call('beginManualCategoryOverride')
        ->set('documentCategoryId', (string) $governance->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $document = DocumentRecord::query()->firstOrFail();

    expect($document->document_category_id)->toBe($governance->id)
        ->and($document->suggested_document_category_id)->toBe($infrastructure->id)
        ->and($document->category_suggestion_rule_id)->toBe($rule->id)
        ->and($document->category_overridden_by_id)->toBe($secretary->id)
        ->and($document->category_overridden_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'document_category_overridden',
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $document->id,
    ]);
});

test('automatic category cannot be changed without entering manual correction mode', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Project Record', 'number_prefix' => 'PRJ']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    CategorizationRule::factory()->create([
        'document_category_id' => $infrastructure->id,
        'name' => 'Road Projects',
        'keywords' => ['road'],
        'priority' => 10,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentNumber', 'PRJ-2026-001')
        ->set('title', 'Road concreting project')
        ->set('documentDate', '2026-09-12')
        ->set('subject', 'Concrete the main barangay road.')
        ->set('documentCategoryId', (string) $governance->id)
        ->call('save')
        ->assertHasNoErrors();

    $document = DocumentRecord::query()->firstOrFail();

    expect($document->document_category_id)->toBe($infrastructure->id)
        ->and($document->category_overridden_at)->toBeNull();
});

test('no rule match requires a manual category and records the exceptional assignment', function () {
    $secretary = User::factory()->create();
    $type = DocumentType::factory()->create(['name' => 'Special Record', 'number_prefix' => 'SPC']);
    $category = DocumentCategory::factory()->create(['name' => 'Governance']);

    $component = Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentNumber', 'SPC-2026-001')
        ->set('title', 'Annual recognition')
        ->set('documentDate', '2026-09-12')
        ->set('subject', 'Recognition of community volunteers.')
        ->call('save')
        ->assertHasErrors(['documentCategoryId']);

    $component
        ->set('documentCategoryId', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $document = DocumentRecord::query()->firstOrFail();
    $audit = $document->auditLogs()->where('action', 'document_category_overridden')->firstOrFail();

    expect($document->suggested_document_category_id)->toBeNull()
        ->and($document->document_category_id)->toBe($category->id)
        ->and($document->category_overridden_by_id)->toBe($secretary->id)
        ->and($audit->context['reason'])->toBe('no_rule_matched')
        ->and($audit->context['selected_category_id'])->toBe($category->id);
});

test('document type specific rules only categorize their configured document type', function () {
    $secretary = User::factory()->create();
    $resolution = DocumentType::factory()->create(['name' => 'Resolution', 'number_prefix' => 'RES']);
    $ordinance = DocumentType::factory()->create(['name' => 'Ordinance', 'number_prefix' => 'ORD']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $governance = DocumentCategory::factory()->create(['name' => 'Governance']);
    CategorizationRule::factory()->create([
        'document_type_id' => $resolution->id,
        'document_category_id' => $infrastructure->id,
        'name' => 'Resolution Road Rule',
        'keywords' => ['road'],
        'priority' => 1,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::documents.create')
        ->set('documentTypeId', (string) $ordinance->id)
        ->set('documentNumber', 'ORD-2026-001')
        ->set('title', 'Road use ordinance')
        ->set('documentDate', '2026-09-12')
        ->set('subject', 'Regulates use of the barangay road.')
        ->assertSet('suggestedDocumentCategoryId', null)
        ->set('documentCategoryId', (string) $governance->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(DocumentRecord::query()->firstOrFail()->document_category_id)->toBe($governance->id);
});
