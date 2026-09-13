<?php

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

test('captain can create and update households across puroks with audit events', function () {
    $captain = User::factory()->captain()->create();
    $purokOne = Purok::factory()->create(['name' => 'Purok 1']);
    $purokTwo = Purok::factory()->create(['name' => 'Purok 2']);

    Livewire::actingAs($captain)
        ->test('pages::households.index')
        ->set('purokId', (string) $purokOne->id)
        ->set('address', 'Zone 1, Sta. Felomina')
        ->set('householdHeadName', 'Maria Santos')
        ->call('createHousehold')
        ->assertHasNoErrors();

    $household = Household::where('household_head_name', 'Maria Santos')->firstOrFail();

    expect($household->household_code)->toStartWith('HH-')
        ->and($household->purok_id)->toBe($purokOne->id)
        ->and($household->created_by_id)->toBe($captain->id);

    Livewire::actingAs($captain)
        ->test('pages::households.index')
        ->call('beginEdit', $household->id)
        ->set('editPurokId', (string) $purokTwo->id)
        ->set('editAddress', 'Zone 2, Sta. Felomina')
        ->set('editHouseholdHeadName', 'Maria Updated')
        ->call('updateHousehold')
        ->assertHasNoErrors();

    $household->refresh();

    expect($household->household_head_name)->toBe('Maria Updated')
        ->and($household->address)->toBe('Zone 2, Sta. Felomina')
        ->and($household->purok_id)->toBe($purokTwo->id)
        ->and($household->updated_by_id)->toBe($captain->id);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'household_created',
        'auditable_type' => Household::class,
        'auditable_id' => $household->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'household_updated',
        'auditable_type' => Household::class,
        'auditable_id' => $household->id,
    ]);
});

test('secretary can manage barangay wide households', function () {
    $secretary = User::factory()->create();
    $purok = Purok::factory()->create(['name' => 'Purok 3']);

    Livewire::actingAs($secretary)
        ->test('pages::households.index')
        ->set('purokId', (string) $purok->id)
        ->set('address', 'Barangay Road')
        ->set('householdHeadName', 'Juan Dela Cruz')
        ->call('createHousehold')
        ->assertHasNoErrors()
        ->assertSee('Juan Dela Cruz');

    $this->assertDatabaseHas('households', [
        'purok_id' => $purok->id,
        'household_head_name' => 'Juan Dela Cruz',
    ]);
});

test('purok chairperson only sees and manages assigned purok households', function () {
    $assignedPurok = Purok::factory()->create(['name' => 'Assigned Purok']);
    $otherPurok = Purok::factory()->create(['name' => 'Other Purok']);
    $chairperson = User::factory()->purokChairperson()->create(['purok_id' => $assignedPurok->id]);
    $assignedHousehold = Household::factory()->create([
        'purok_id' => $assignedPurok->id,
        'household_head_name' => 'Assigned Head',
    ]);
    $otherHousehold = Household::factory()->create([
        'purok_id' => $otherPurok->id,
        'household_head_name' => 'Other Head',
    ]);

    Livewire::actingAs($chairperson)
        ->test('pages::households.index')
        ->assertSee('Assigned Head')
        ->assertDontSee('Other Head')
        ->set('purokId', (string) $otherPurok->id)
        ->set('address', 'Forced assigned address')
        ->set('householdHeadName', 'Forced Assigned')
        ->call('createHousehold')
        ->assertHasNoErrors()
        ->call('beginEdit', $otherHousehold->id)
        ->assertNotFound();

    $created = Household::where('household_head_name', 'Forced Assigned')->firstOrFail();

    expect($created->purok_id)->toBe($assignedPurok->id)
        ->and($assignedHousehold->purok_id)->toBe($assignedPurok->id);
});

test('household creation validates required fields', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::households.index')
        ->call('createHousehold')
        ->assertHasErrors([
            'purokId' => ['required'],
            'address' => ['required'],
            'householdHeadName' => ['required'],
        ]);
});

test('households with residents cannot be moved to another purok', function () {
    $captain = User::factory()->captain()->create();
    $purokOne = Purok::factory()->create();
    $purokTwo = Purok::factory()->create();
    $household = Household::factory()->create(['purok_id' => $purokOne->id]);
    Resident::factory()->create([
        'purok_id' => $purokOne->id,
        'household_id' => $household->id,
    ]);

    Livewire::actingAs($captain)
        ->test('pages::households.index')
        ->call('beginEdit', $household->id)
        ->set('editPurokId', (string) $purokTwo->id)
        ->call('updateHousehold')
        ->assertHasErrors(['editPurokId']);

    expect($household->refresh()->purok_id)->toBe($purokOne->id)
        ->and(AuditLog::where('action', 'household_updated')->where('auditable_id', $household->id)->exists())->toBeFalse();
});
