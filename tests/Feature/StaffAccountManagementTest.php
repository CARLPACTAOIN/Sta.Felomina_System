<?php

use App\Enums\UserRole;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('captain can view real staff account list', function () {
    $captain = User::factory()->captain()->create(['name' => 'Captain Admin']);
    $secretary = User::factory()->create(['name' => 'Secretary User']);

    $this->actingAs($captain)
        ->get(route('staff-accounts.index'))
        ->assertSuccessful()
        ->assertSee('Captain Admin')
        ->assertSee('Secretary User')
        ->assertSee('Create staff account');
});

test('non captain roles cannot manage staff accounts', function (User $user) {
    $this->actingAs($user)
        ->get(route('staff-accounts.index'))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test('pages::staff-accounts.index')
        ->set('name', 'Blocked User')
        ->set('email', 'blocked@example.test')
        ->set('role', UserRole::Secretary->value)
        ->call('createStaff')
        ->assertForbidden();
})->with([
    'secretary' => fn () => User::factory()->create(),
    'purok chairperson' => fn () => User::factory()->purokChairperson()->create(),
]);

test('captain creates secretary with generated temporary password and audit event', function () {
    $captain = User::factory()->captain()->create();

    $component = Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->set('name', 'New Secretary')
        ->set('email', 'new.secretary@example.test')
        ->set('role', UserRole::Secretary->value)
        ->call('createStaff')
        ->assertHasNoErrors()
        ->assertSet('temporaryPasswordUserId', User::where('email', 'new.secretary@example.test')->value('id'));

    $staff = User::where('email', 'new.secretary@example.test')->firstOrFail();
    $temporaryPassword = $component->get('temporaryPassword');

    expect($temporaryPassword)->toBeString()
        ->and($staff->name)->toBe('New Secretary')
        ->and($staff->role)->toBe(UserRole::Secretary)
        ->and($staff->purok_id)->toBeNull()
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and($staff->password)->not->toBe($temporaryPassword)
        ->and(Hash::check($temporaryPassword, $staff->password))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_created',
        'auditable_type' => User::class,
        'auditable_id' => $staff->id,
    ]);
});

test('captain must assign a purok when creating a purok chairperson', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create(['name' => 'Purok 3']);

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->set('name', 'Missing Purok')
        ->set('email', 'missing.purok@example.test')
        ->set('role', UserRole::PurokChairperson->value)
        ->call('createStaff')
        ->assertHasErrors(['purokId' => ['required']]);

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->set('name', 'Assigned Chair')
        ->set('email', 'assigned.chair@example.test')
        ->set('role', UserRole::PurokChairperson->value)
        ->set('purokId', (string) $purok->id)
        ->call('createStaff')
        ->assertHasNoErrors();

    $staff = User::where('email', 'assigned.chair@example.test')->firstOrFail();

    expect($staff->role)->toBe(UserRole::PurokChairperson)
        ->and($staff->purok_id)->toBe($purok->id);
});

test('non purok roles cannot keep a purok assignment', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->set('name', 'Barangay Secretary')
        ->set('email', 'secretary.with.purok@example.test')
        ->set('role', UserRole::Secretary->value)
        ->set('purokId', (string) $purok->id)
        ->call('createStaff')
        ->assertHasNoErrors();

    $staff = User::where('email', 'secretary.with.purok@example.test')->firstOrFail();

    expect($staff->role)->toBe(UserRole::Secretary)
        ->and($staff->purok_id)->toBeNull();
});

test('staff account creation validates required fields and unique email', function () {
    $captain = User::factory()->captain()->create();
    $existing = User::factory()->create(['email' => 'taken@example.test']);

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('createStaff')
        ->assertHasErrors(['name' => ['required'], 'email' => ['required']]);

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->set('name', 'Duplicate Email')
        ->set('email', $existing->email)
        ->set('role', UserRole::Secretary->value)
        ->call('createStaff')
        ->assertHasErrors(['email' => ['unique']]);
});

test('captain updates staff profile role and purok assignment with audit events', function () {
    $captain = User::factory()->captain()->create();
    $staff = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.test',
    ]);
    $purok = Purok::factory()->create();

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('beginEdit', $staff->id)
        ->set('editName', 'Updated Chair')
        ->set('editEmail', 'updated.chair@example.test')
        ->set('editRole', UserRole::PurokChairperson->value)
        ->set('editPurokId', (string) $purok->id)
        ->set('editIsActive', true)
        ->call('updateStaff')
        ->assertHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe('Updated Chair')
        ->and($staff->email)->toBe('updated.chair@example.test')
        ->and($staff->role)->toBe(UserRole::PurokChairperson)
        ->and($staff->purok_id)->toBe($purok->id);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_role_changed',
        'auditable_id' => $staff->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_updated',
        'auditable_id' => $staff->id,
    ]);
});

test('captain activates and deactivates another staff account with audit events', function () {
    $captain = User::factory()->captain()->create();
    $staff = User::factory()->create(['is_active' => true]);

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('toggleActive', $staff->id)
        ->assertHasNoErrors();

    expect($staff->refresh()->is_active)->toBeFalse();

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('toggleActive', $staff->id)
        ->assertHasNoErrors();

    expect($staff->refresh()->is_active)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_deactivated',
        'auditable_id' => $staff->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_activated',
        'auditable_id' => $staff->id,
    ]);
});

test('captain cannot deactivate own account', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('toggleActive', $captain->id)
        ->assertHasErrors(['staff']);

    expect($captain->refresh()->is_active)->toBeTrue();
});

test('captain cannot remove the last active captain by role change', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('beginEdit', $captain->id)
        ->set('editName', $captain->name)
        ->set('editEmail', $captain->email)
        ->set('editRole', UserRole::Secretary->value)
        ->set('editIsActive', true)
        ->call('updateStaff')
        ->assertHasErrors(['editRole']);

    expect($captain->refresh()->role)->toBe(UserRole::Captain);
});

test('captain resets another staff password and sees generated password once', function () {
    $captain = User::factory()->captain()->create();
    $staff = User::factory()->create(['password' => 'old-password']);
    $oldHash = $staff->password;

    $component = Livewire::actingAs($captain)
        ->test('pages::staff-accounts.index')
        ->call('resetPassword', $staff->id)
        ->assertHasNoErrors()
        ->assertSet('temporaryPasswordUserId', $staff->id);

    $temporaryPassword = $component->get('temporaryPassword');
    $staff->refresh();

    expect($temporaryPassword)->toBeString()
        ->and($staff->password)->not->toBe($oldHash)
        ->and(Hash::check('old-password', $staff->password))->toBeFalse()
        ->and(Hash::check($temporaryPassword, $staff->password))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'account_password_reset',
        'auditable_id' => $staff->id,
    ]);
});
