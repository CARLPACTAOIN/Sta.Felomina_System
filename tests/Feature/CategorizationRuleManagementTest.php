<?php

use App\Models\AuditLog;
use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\User;
use Livewire\Livewire;

test('captain creates categorization rule with normalized keywords and audit event', function () {
    $captain = User::factory()->captain()->create();
    $category = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $type = DocumentType::factory()->create(['name' => 'Resolution']);

    Livewire::actingAs($captain)
        ->test('pages::categorization-rules.index')
        ->set('name', 'Infrastructure Works')
        ->set('documentCategoryId', (string) $category->id)
        ->set('documentTypeId', (string) $type->id)
        ->set('keywordText', "Road, Drainage\nConstruction")
        ->set('priority', '25')
        ->call('createRule')
        ->assertHasNoErrors()
        ->assertSee('Infrastructure Works');

    $rule = CategorizationRule::where('name', 'Infrastructure Works')->firstOrFail();

    expect($rule->document_category_id)->toBe($category->id)
        ->and($rule->document_type_id)->toBe($type->id)
        ->and($rule->keywords)->toBe(['road', 'drainage', 'construction'])
        ->and($rule->priority)->toBe(25)
        ->and($rule->is_active)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'categorization_rule_created',
        'auditable_type' => CategorizationRule::class,
        'auditable_id' => $rule->id,
    ]);
});

test('secretary updates and deactivates categorization rule with audit events', function () {
    $secretary = User::factory()->create();
    $health = DocumentCategory::factory()->create(['name' => 'Health']);
    $education = DocumentCategory::factory()->create(['name' => 'Education']);
    $rule = CategorizationRule::factory()->create([
        'document_category_id' => $health->id,
        'name' => 'Health Terms',
        'keywords' => ['clinic'],
        'priority' => 40,
        'is_active' => true,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::categorization-rules.index')
        ->call('beginEdit', $rule->id)
        ->set('editName', 'Education Terms')
        ->set('editDocumentCategoryId', (string) $education->id)
        ->set('editKeywordText', 'School; Scholarship')
        ->set('editPriority', '10')
        ->set('editIsActive', false)
        ->call('updateRule')
        ->assertHasNoErrors();

    $rule->refresh();

    expect($rule->name)->toBe('Education Terms')
        ->and($rule->document_category_id)->toBe($education->id)
        ->and($rule->keywords)->toBe(['school', 'scholarship'])
        ->and($rule->priority)->toBe(10)
        ->and($rule->is_active)->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'categorization_rule_deactivated',
        'auditable_type' => CategorizationRule::class,
        'auditable_id' => $rule->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $secretary->id,
        'action' => 'categorization_rule_updated',
        'auditable_type' => CategorizationRule::class,
        'auditable_id' => $rule->id,
    ]);
});

test('purok chairperson cannot manage categorization rules through livewire actions', function () {
    $chairperson = User::factory()->purokChairperson()->create();
    $category = DocumentCategory::factory()->create();

    Livewire::actingAs($chairperson)
        ->test('pages::categorization-rules.index')
        ->set('name', 'Blocked Rule')
        ->set('documentCategoryId', (string) $category->id)
        ->set('keywordText', 'blocked')
        ->call('createRule')
        ->assertForbidden();

    expect(CategorizationRule::where('name', 'Blocked Rule')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'categorization_rule_created')->exists())->toBeFalse();
});

test('categorization rule creation validates required fields', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::categorization-rules.index')
        ->set('priority', '')
        ->call('createRule')
        ->assertHasErrors([
            'name' => ['required'],
            'documentCategoryId' => ['required'],
            'keywordText' => ['required'],
            'priority' => ['required'],
        ]);
});
