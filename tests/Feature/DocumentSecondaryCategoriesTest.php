<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\ReportEngine;
use Livewire\Livewire;

function secondaryCreateForm(User $user, DocumentCategory $primary): mixed
{
    $type = DocumentType::factory()->create();

    return Livewire::actingAs($user)->test('pages::documents.create')
        ->set('documentTypeId', (string) $type->id)
        ->set('documentCategoryId', (string) $primary->id)
        ->set('documentNumber', 'MULTI-2026-1')
        ->set('title', 'Community project')
        ->set('documentDate', '2026-01-01')
        ->set('subject', 'Community project details');
}

test('secretary creates a document with secondary categories and excludes the primary', function () {
    $user = User::factory()->create();
    $primary = DocumentCategory::factory()->create();
    $secondary = DocumentCategory::factory()->count(2)->create();

    secondaryCreateForm($user, $primary)
        ->set('secondaryCategoryIds', [(string) $primary->id, (string) $secondary[0]->id, (string) $secondary[1]->id])
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $document = DocumentRecord::firstOrFail();
    expect($document->document_category_id)->toBe($primary->id)
        ->and($document->secondaryCategories->modelKeys())->toEqualCanonicalizing($secondary->modelKeys());
    $this->assertDatabaseHas('audit_logs', ['action' => 'document_secondary_categories_updated', 'auditable_id' => $document->id]);
});

test('secondary categories reject duplicates, missing and inactive selections', function () {
    $user = User::factory()->create();
    $primary = DocumentCategory::factory()->create();
    $inactive = DocumentCategory::factory()->create(['is_active' => false]);
    $secondary = DocumentCategory::factory()->create();

    secondaryCreateForm($user, $primary)->set('secondaryCategoryIds', [(string) $secondary->id, (string) $secondary->id])->call('save')->assertHasErrors(['secondaryCategoryIds.0']);
    secondaryCreateForm($user, $primary)->set('secondaryCategoryIds', [(string) $inactive->id, '999999'])->call('save')->assertHasErrors(['secondaryCategoryIds.0', 'secondaryCategoryIds.1']);
    expect(DocumentRecord::count())->toBe(0);
});

test('metadata edits preserve inactive secondary categories and remove a new primary from them', function () {
    $user = User::factory()->create();
    $primary = DocumentCategory::factory()->create();
    $secondary = DocumentCategory::factory()->create();
    $inactive = DocumentCategory::factory()->create(['is_active' => false]);
    $document = DocumentRecord::factory()->create(['document_category_id' => $primary->id]);
    $document->secondaryCategories()->attach([$secondary->id, $inactive->id]);

    Livewire::actingAs($user)->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit')->call('beginMetadataManualCategoryOverride')
        ->set('metadataDocumentCategoryId', (string) $secondary->id)
        ->call('updateMetadata')->assertHasNoErrors();

    expect($document->refresh()->document_category_id)->toBe($secondary->id)
        ->and($document->secondaryCategories->modelKeys())->toEqual([$inactive->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'document_secondary_categories_updated', 'auditable_id' => $document->id]);
});

test('secondary category changes follow existing document metadata authorization', function () {
    $user = User::factory()->create();
    $document = DocumentRecord::factory()->create(['status' => DocumentStatus::Published]);
    Livewire::actingAs($user)->test('pages::documents.show', ['documentRecord' => $document])
        ->call('startMetadataEdit')->assertForbidden();
});

test('secondary categories are searchable filterable displayed and counted without duplicating documents', function () {
    $user = User::factory()->captain()->create();
    $primary = DocumentCategory::factory()->create(['name' => 'Governance']);
    $secondary = DocumentCategory::factory()->create(['name' => 'Unique Secondary']);
    $document = DocumentRecord::factory()->create(['document_category_id' => $primary->id, 'title' => 'Unique multisubject document']);
    $document->secondaryCategories()->attach($secondary);
    $other = DocumentRecord::factory()->create(['document_category_id' => $primary->id]);

    $this->actingAs($user)->get(route('documents.index', ['category' => $secondary->id]))->assertOk()->assertSee($document->title)->assertDontSee($other->title)->assertSee('Unique Secondary');
    $this->actingAs($user)->get(route('documents.index', ['q' => 'Unique Secondary']))->assertOk()->assertSee($document->title)->assertDontSee($other->title);
    $this->get(route('verification.show', $document->verification_token))->assertOk()->assertSee('Unique Secondary');
    $report = app(ReportEngine::class)->documentReport($user, ['category_id' => $secondary->id], null);
    expect($report['total_count'])->toBe(1)->and($report['records']->modelKeys())->toBe([$document->id]);
    $counts = app(DashboardMetricsService::class)->forCaptainOrSecretary($user)['category_distribution']->pluck('count', 'name');
    expect($counts['Governance'])->toBe(2)->and($counts['Unique Secondary'])->toBe(1);
});

test('approval queue and printable report include and filter secondary categories', function () {
    $captain = User::factory()->captain()->create();
    $secondary = DocumentCategory::factory()->create(['name' => 'Community Health']);
    $document = DocumentRecord::factory()->pendingApproval()->create(['title' => 'Health project']);
    $document->secondaryCategories()->attach($secondary);
    $other = DocumentRecord::factory()->pendingApproval()->create(['title' => 'Other project']);

    Livewire::actingAs($captain)->test('pages::approvals.index')
        ->set('categoryId', (string) $secondary->id)
        ->assertSee($document->title)->assertSee('Community Health')->assertDontSee($other->title);
    $this->actingAs($captain)->get(route('reports.print', ['type' => 'document', 'category_id' => $secondary->id]))
        ->assertOk()->assertSee($document->title)->assertSee('Community Health')->assertDontSee($other->title);
});
