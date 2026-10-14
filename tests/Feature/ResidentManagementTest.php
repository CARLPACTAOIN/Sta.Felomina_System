<?php

use App\Enums\ResidentStatus;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

test('captain and secretary can view residents across all puroks', function (User $user) {
    $purokOne = Purok::factory()->create(['name' => 'Purok 1']);
    $purokTwo = Purok::factory()->create(['name' => 'Purok 2']);
    $householdOne = Household::factory()->create(['purok_id' => $purokOne->id]);
    $householdTwo = Household::factory()->create(['purok_id' => $purokTwo->id]);
    Resident::factory()->create([
        'first_name' => 'Across',
        'middle_name' => null,
        'last_name' => 'One',
        'purok_id' => $purokOne->id,
        'household_id' => $householdOne->id,
    ]);
    Resident::factory()->create([
        'first_name' => 'Across',
        'middle_name' => null,
        'last_name' => 'Two',
        'purok_id' => $purokTwo->id,
        'household_id' => $householdTwo->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::residents.index')
        ->assertSee('Across One')
        ->assertSee('Across Two');
})->with([
    'captain' => fn () => User::factory()->captain()->create(),
    'secretary' => fn () => User::factory()->create(),
]);

test('captain creates resident with household grouping and audit event', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create(['name' => 'Purok 4']);
    $household = Household::factory()->create([
        'purok_id' => $purok->id,
        'household_head_name' => 'Household Head',
    ]);

    Livewire::actingAs($captain)
        ->test('pages::residents.index')
        ->set('firstName', 'Ana')
        ->set('lastName', 'Reyes')
        ->set('sex', 'female')
        ->set('birthdate', '1995-04-12')
        ->set('civilStatus', 'single')
        ->set('address', 'Purok 4 Road')
        ->set('purokId', (string) $purok->id)
        ->set('householdId', (string) $household->id)
        ->set('isRegisteredVoter', true)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertHasNoErrors()
        ->assertSee('Ana Reyes')
        ->assertSee($household->household_code);

    $resident = Resident::where('first_name', 'Ana')->where('last_name', 'Reyes')->firstOrFail();

    expect($resident->resident_code)->toStartWith('RES-')
        ->and($resident->purok_id)->toBe($purok->id)
        ->and($resident->household_id)->toBe($household->id)
        ->and($resident->is_registered_voter)->toBeTrue()
        ->and($resident->created_by_id)->toBe($captain->id)
        ->and($resident->normalized_full_name)->toBe('ana reyes');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'resident_created',
        'auditable_type' => Resident::class,
        'auditable_id' => $resident->id,
    ]);
});

test('resident creation warns about likely duplicates before save and can be overridden', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    Resident::factory()->create([
        'first_name' => 'Pedro',
        'middle_name' => 'A',
        'last_name' => 'Santos',
        'suffix' => null,
        'birthdate' => '1988-01-20',
        'purok_id' => $purok->id,
        'household_id' => $household->id,
    ]);

    $component = Livewire::actingAs($captain)
        ->test('pages::residents.index')
        ->set('firstName', 'Pedro')
        ->set('middleName', 'A')
        ->set('lastName', 'Santos')
        ->set('sex', 'male')
        ->set('birthdate', '1988-01-20')
        ->set('civilStatus', 'married')
        ->set('address', 'Same Purok')
        ->set('purokId', (string) $purok->id)
        ->set('householdId', (string) $household->id)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertHasNoErrors()
        ->assertSee('Possible duplicate resident records were found.');

    expect(Resident::where('first_name', 'Pedro')->where('last_name', 'Santos')->count())->toBe(1)
        ->and($component->get('duplicateResidentIds'))->not->toBeEmpty();

    $component
        ->call('createResident', true)
        ->assertHasNoErrors();

    expect(Resident::where('first_name', 'Pedro')->where('last_name', 'Santos')->count())->toBe(2);
});

test('purok chairperson only sees and manages assigned purok residents', function () {
    $assignedPurok = Purok::factory()->create(['name' => 'Assigned Purok']);
    $otherPurok = Purok::factory()->create(['name' => 'Other Purok']);
    $chairperson = User::factory()->purokChairperson()->create(['purok_id' => $assignedPurok->id]);
    $assignedHousehold = Household::factory()->create(['purok_id' => $assignedPurok->id]);
    $otherHousehold = Household::factory()->create(['purok_id' => $otherPurok->id]);
    Resident::factory()->create([
        'first_name' => 'Visible',
        'middle_name' => null,
        'last_name' => 'Resident',
        'purok_id' => $assignedPurok->id,
        'household_id' => $assignedHousehold->id,
    ]);
    $otherResident = Resident::factory()->create([
        'first_name' => 'Hidden',
        'middle_name' => null,
        'last_name' => 'Resident',
        'purok_id' => $otherPurok->id,
        'household_id' => $otherHousehold->id,
    ]);

    Livewire::actingAs($chairperson)
        ->test('pages::residents.index')
        ->assertSee('Visible Resident')
        ->assertDontSee('Hidden Resident')
        ->set('firstName', 'Forced')
        ->set('lastName', 'Assigned')
        ->set('sex', 'female')
        ->set('birthdate', '2000-02-02')
        ->set('civilStatus', 'single')
        ->set('address', 'Assigned Address')
        ->set('purokId', (string) $otherPurok->id)
        ->set('householdId', (string) $assignedHousehold->id)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertHasNoErrors()
        ->call('beginEdit', $otherResident->id)
        ->assertNotFound();

    $created = Resident::where('first_name', 'Forced')->where('last_name', 'Assigned')->firstOrFail();

    expect($created->purok_id)->toBe($assignedPurok->id)
        ->and($created->household_id)->toBe($assignedHousehold->id);
});

test('purok chairperson cannot create resident using another purok household', function () {
    $assignedPurok = Purok::factory()->create();
    $otherPurok = Purok::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create(['purok_id' => $assignedPurok->id]);
    $otherHousehold = Household::factory()->create(['purok_id' => $otherPurok->id]);

    Livewire::actingAs($chairperson)
        ->test('pages::residents.index')
        ->set('firstName', 'Blocked')
        ->set('lastName', 'Resident')
        ->set('sex', 'male')
        ->set('birthdate', '1990-01-01')
        ->set('civilStatus', 'single')
        ->set('address', 'Blocked Address')
        ->set('householdId', (string) $otherHousehold->id)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertHasErrors(['householdId']);

    expect(Resident::where('first_name', 'Blocked')->exists())->toBeFalse();
});

test('resident update changes status and writes audit events', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    $resident = Resident::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'Resident',
        'status' => ResidentStatus::Active,
        'purok_id' => $purok->id,
        'household_id' => $household->id,
    ]);

    Livewire::actingAs($captain)
        ->test('pages::residents.index')
        ->call('beginEdit', $resident->id)
        ->set('editFirstName', 'Updated')
        ->set('editStatus', ResidentStatus::Transferred->value)
        ->call('updateResident')
        ->assertHasNoErrors();

    $resident->refresh();

    expect($resident->first_name)->toBe('Updated')
        ->and($resident->status)->toBe(ResidentStatus::Transferred)
        ->and($resident->updated_by_id)->toBe($captain->id);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'resident_status_changed',
        'auditable_type' => Resident::class,
        'auditable_id' => $resident->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'resident_updated',
        'auditable_type' => Resident::class,
        'auditable_id' => $resident->id,
    ]);
});

test('resident creation validates required fields', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::residents.index')
        ->call('createResident')
        ->assertHasErrors([
            'firstName' => ['required'],
            'lastName' => ['required'],
            'sex' => ['required'],
            'birthdate' => ['required'],
            'civilStatus' => ['required'],
            'address' => ['required'],
            'purokId' => ['required'],
            'householdId' => ['required'],
        ]);
});

test('secretary can view but cannot create or update residents', function () {
    $secretary = User::factory()->create();
    $purok = Purok::factory()->create();
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    $resident = Resident::factory()->create([
        'first_name' => 'Readonly',
        'middle_name' => null,
        'last_name' => 'Resident',
        'purok_id' => $purok->id,
        'household_id' => $household->id,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::residents.index')
        ->assertSee('Readonly Resident')
        ->set('firstName', 'Blocked')
        ->set('lastName', 'Resident')
        ->set('sex', 'male')
        ->set('birthdate', '1990-01-01')
        ->set('civilStatus', 'single')
        ->set('address', 'Blocked Address')
        ->set('purokId', (string) $purok->id)
        ->set('householdId', (string) $household->id)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertForbidden();

    Livewire::actingAs($secretary)
        ->test('pages::residents.index')
        ->call('beginEdit', $resident->id)
        ->set('editFirstName', 'Blocked Update')
        ->call('updateResident')
        ->assertForbidden();

    expect(Resident::where('first_name', 'Blocked')->exists())->toBeFalse()
        ->and($resident->refresh()->first_name)->toBe('Readonly');
});

test('captain cannot attach resident to household from another purok', function () {
    $captain = User::factory()->captain()->create();
    $purokOne = Purok::factory()->create();
    $purokTwo = Purok::factory()->create();
    $otherHousehold = Household::factory()->create(['purok_id' => $purokTwo->id]);

    Livewire::actingAs($captain)
        ->test('pages::residents.index')
        ->set('firstName', 'Mismatch')
        ->set('lastName', 'Resident')
        ->set('sex', 'female')
        ->set('birthdate', '1999-09-09')
        ->set('civilStatus', 'single')
        ->set('address', 'Mismatch Address')
        ->set('purokId', (string) $purokOne->id)
        ->set('householdId', (string) $otherHousehold->id)
        ->set('status', ResidentStatus::Active->value)
        ->call('createResident')
        ->assertHasErrors(['householdId']);

    expect(AuditLog::where('action', 'resident_created')->exists())->toBeFalse();
});

test('resident search matches household code contact and voter filter', function () {
    $secretary = User::factory()->create();
    $purok = Purok::factory()->create();
    $matchingHousehold = Household::factory()->create([
        'purok_id' => $purok->id,
        'household_code' => 'HH-SPECIAL',
    ]);
    $otherHousehold = Household::factory()->create(['purok_id' => $purok->id]);

    Resident::factory()->create([
        'first_name' => 'Visible',
        'middle_name' => null,
        'last_name' => 'Resident',
        'contact_number' => '0917-111-2222',
        'purok_id' => $purok->id,
        'household_id' => $matchingHousehold->id,
        'is_registered_voter' => true,
    ]);
    Resident::factory()->create([
        'first_name' => 'Hidden',
        'middle_name' => null,
        'last_name' => 'Resident',
        'contact_number' => '0917-333-4444',
        'purok_id' => $purok->id,
        'household_id' => $otherHousehold->id,
        'is_registered_voter' => false,
    ]);

    Livewire::actingAs($secretary)
        ->test('pages::residents.index')
        ->set('search', 'hh-special')
        ->set('voterFilter', 'registered')
        ->assertSee('Visible Resident')
        ->assertDontSee('Hidden Resident');
});
