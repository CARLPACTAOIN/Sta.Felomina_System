<?php

use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\PurokResolutionFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('captain can view the Purok management page', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create(['name' => 'Purok 8', 'code' => 'purok-8']);

    $this->actingAs($captain)
        ->get(route('puroks.index'))
        ->assertSuccessful()
        ->assertSee('Purok 8')
        ->assertSee('Add Purok');
});

test('non captain roles cannot manage Puroks through routes or Livewire actions', function (User $user) {
    $this->actingAs($user)
        ->get(route('puroks.index'))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test('pages::puroks.index')
        ->set('name', 'Blocked Purok')
        ->set('code', 'blocked-purok')
        ->set('sortOrder', '8')
        ->call('createPurok')
        ->assertForbidden();

    expect(Purok::where('code', 'blocked-purok')->exists())->toBeFalse();
})->with([
    'secretary' => fn () => User::factory()->create(),
    'purok chairperson' => fn () => User::factory()->purokChairperson()->create(),
]);

test('captain creates a Purok with a required resolution PDF and audit event', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $file = UploadedFile::fake()->create('purok-8-resolution.pdf', 512, 'application/pdf');

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->set('name', 'Purok 8')
        ->set('code', 'purok-8')
        ->set('sortOrder', '8')
        ->set('resolutionPdf', $file)
        ->call('createPurok')
        ->assertHasNoErrors()
        ->assertSee('Purok 8');

    $purok = Purok::where('code', 'purok-8')->firstOrFail();
    $resolutionFile = $purok->currentResolutionFile;

    expect($purok->name)->toBe('Purok 8')
        ->and($purok->sort_order)->toBe(8)
        ->and($resolutionFile)->not->toBeNull()
        ->and($resolutionFile?->original_name)->toBe('purok-8-resolution.pdf')
        ->and($resolutionFile?->uploaded_by_id)->toBe($captain->id);

    Storage::disk('local')->assertExists($resolutionFile->path);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'purok_created',
        'auditable_type' => Purok::class,
        'auditable_id' => $purok->id,
    ]);
});

test('Purok creation validates the required fields and PDF attachment', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->set('sortOrder', '')
        ->call('createPurok')
        ->assertHasErrors([
            'name' => ['required'],
            'code' => ['required'],
            'sortOrder' => ['required'],
            'resolutionPdf' => ['required'],
        ]);
});

test('Purok creation rejects a non PDF resolution attachment', function () {
    $captain = User::factory()->captain()->create();

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->set('name', 'Purok 8')
        ->set('code', 'purok-8')
        ->set('sortOrder', '8')
        ->set('resolutionPdf', UploadedFile::fake()->image('resolution.png'))
        ->call('createPurok')
        ->assertHasErrors(['resolutionPdf' => ['mimes']]);

    expect(Purok::where('code', 'purok-8')->exists())->toBeFalse();
});

test('captain updates and deactivates a Purok with audit events', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create([
        'name' => 'Original Purok',
        'code' => 'original-purok',
        'sort_order' => 4,
        'is_active' => true,
    ]);

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->call('beginEdit', $purok->id)
        ->set('editName', 'Updated Purok')
        ->set('editCode', 'updated-purok')
        ->set('editSortOrder', '9')
        ->set('editIsActive', false)
        ->call('updatePurok')
        ->assertHasNoErrors();

    $purok->refresh();

    expect($purok->name)->toBe('Updated Purok')
        ->and($purok->code)->toBe('updated-purok')
        ->and($purok->sort_order)->toBe(9)
        ->and($purok->is_active)->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'purok_updated',
        'auditable_id' => $purok->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'purok_deactivated',
        'auditable_id' => $purok->id,
    ]);
});

test('captain replaces a resolution PDF while retaining the earlier file', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();
    $previous = PurokResolutionFile::factory()->for($purok)->create([
        'path' => 'purok-resolutions/previous.pdf',
        'uploaded_by_id' => $captain->id,
    ]);
    Storage::disk('local')->put($previous->path, 'previous resolution');
    $purok->currentResolutionFile()->associate($previous)->save();

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->call('beginEdit', $purok->id)
        ->set('replacementResolutionPdf', UploadedFile::fake()->create('replacement.pdf', 256, 'application/pdf'))
        ->call('replaceResolution')
        ->assertHasNoErrors();

    $purok->refresh()->load('currentResolutionFile');

    expect($purok->currentResolutionFile?->id)->not->toBe($previous->id)
        ->and($purok->resolutionFiles()->count())->toBe(2);

    Storage::disk('local')->assertExists($previous->path);
    Storage::disk('local')->assertExists($purok->currentResolutionFile?->path);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'purok_resolution_replaced',
        'auditable_id' => $purok->id,
    ]);
});

test('only a captain can download a Purok resolution PDF', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $purok = Purok::factory()->create();
    $resolutionFile = PurokResolutionFile::factory()->for($purok)->create([
        'path' => 'purok-resolutions/current.pdf',
        'uploaded_by_id' => $captain->id,
    ]);
    Storage::disk('local')->put($resolutionFile->path, 'resolution');
    $purok->currentResolutionFile()->associate($resolutionFile)->save();

    $this->actingAs($captain)
        ->get(route('puroks.resolution.download', $purok))
        ->assertSuccessful();

    $this->actingAs($secretary)
        ->get(route('puroks.resolution.download', $purok))
        ->assertForbidden();
});

test('captain deletes an unused Purok and its private resolution files', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();
    $resolutionFile = PurokResolutionFile::factory()->for($purok)->create([
        'path' => 'purok-resolutions/delete-me.pdf',
        'uploaded_by_id' => $captain->id,
    ]);
    Storage::disk('local')->put($resolutionFile->path, 'resolution');
    $purok->currentResolutionFile()->associate($resolutionFile)->save();

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->call('deletePurok', $purok->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($purok);
    Storage::disk('local')->assertMissing($resolutionFile->path);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'purok_deleted',
        'auditable_type' => Purok::class,
        'auditable_id' => $purok->id,
    ]);
});

test('captain cannot delete a Purok with assigned records', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::factory()->create();
    User::factory()->create(['purok_id' => $purok->id]);

    Livewire::actingAs($captain)
        ->test('pages::puroks.index')
        ->call('deletePurok', $purok->id)
        ->assertHasErrors(['purok']);

    $this->assertModelExists($purok);
    expect(AuditLog::where('action', 'purok_deleted')->exists())->toBeFalse();
});
