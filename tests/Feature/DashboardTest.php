<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Models\AuditLog;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('captain sees barangay-wide document metrics and resident distributions', function () {
    $captain = User::factory()->captain()->create();
    $type = DocumentType::factory()->create(['name' => 'Resolution', 'code' => 'RES']);
    $catInfra = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $catHealth = DocumentCategory::factory()->create(['name' => 'Health']);

    // Create document records in various states
    DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $catInfra->id,
        'status' => DocumentStatus::Published,
        'created_by_id' => $captain->id,
    ]);

    DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $catInfra->id,
        'status' => DocumentStatus::PendingApproval,
        'created_by_id' => $captain->id,
    ]);

    DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $catHealth->id,
        'status' => DocumentStatus::Archived,
        'created_by_id' => $captain->id,
    ]);

    $purok = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    Resident::factory()->count(3)->create([
        'purok_id' => $purok->id,
        'household_id' => $household->id,
        'status' => ResidentStatus::Active,
    ]);

    AuditLog::record($captain, 'document_approved', DocumentRecord::first(), 'Resolution approved by Captain.');

    $response = $this->actingAs($captain)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Barangay-wide workspace')
        ->assertSee('Document records')
        ->assertSee('Pending approval')
        ->assertSee('Published')
        ->assertSee('Infrastructure')
        ->assertSee('Resolution approved by Captain.')
        ->assertDontSee('Counts remain empty until');
});

test('purok chairperson sees only assigned purok metrics and zero document metrics', function () {
    $purok1 = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $purok2 = Purok::query()->skip(1)->first() ?? Purok::factory()->create(['name' => 'Purok 2']);

    $chairperson = User::factory()->purokChairperson()->create([
        'purok_id' => $purok1->id,
    ]);

    $household1 = Household::factory()->create(['purok_id' => $purok1->id]);
    $household2 = Household::factory()->create(['purok_id' => $purok2->id]);

    // Purok 1: 2 active, 1 inactive
    Resident::factory()->count(2)->create([
        'purok_id' => $purok1->id,
        'household_id' => $household1->id,
        'status' => ResidentStatus::Active,
    ]);
    Resident::factory()->create([
        'purok_id' => $purok1->id,
        'household_id' => $household1->id,
        'status' => ResidentStatus::Inactive,
    ]);

    // Purok 2: 5 active
    Resident::factory()->count(5)->create([
        'purok_id' => $purok2->id,
        'household_id' => $household2->id,
        'status' => ResidentStatus::Active,
    ]);

    // Documents exist in system
    DocumentRecord::factory()->count(4)->create([
        'status' => DocumentStatus::Published,
        'created_by_id' => $chairperson->id,
    ]);

    $response = $this->actingAs($chairperson)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Assigned purok workspace')
        ->assertSee($purok1->name)
        ->assertSee('Active residents')
        ->assertSee('Households')
        ->assertDontSee('Document records')
        ->assertDontSee('Pending approval')
        ->assertDontSee('Create document record');
});
