<?php

use App\Models\DocumentRecord;
use App\Models\User;

test('public registration routes are unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Public registration is not available')
        ->assertDontSee('Sign up');
});

test('inactive staff cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive authenticated sessions are ended', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('captain can render administrative foundation pages', function (string $routeName) {
    $user = User::factory()->captain()->create();

    $this->actingAs($user)->get(route($routeName))->assertSuccessful();
})->with([
    'document archive' => 'documents.index',
    'pending approvals' => 'approvals.index',
    'categorization rules' => 'categorization-rules.index',
    'resident directory' => 'residents.index',
    'households' => 'households.index',
    'reports' => 'reports.index',
    'notifications' => 'notifications.index',
    'staff accounts' => 'staff-accounts.index',
    'audit logs' => 'audit-logs.index',
]);

test('captain cannot open secretary document preparation routes or controls', function () {
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->create();

    $this->actingAs($captain)
        ->get(route('documents.create'))
        ->assertForbidden();

    $this->actingAs($captain)
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertDontSee('Create document record')
        ->assertDontSee('Create first Draft');

    $this->actingAs($captain)
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertDontSee('Edit Metadata')
        ->assertDontSee('Download QR')
        ->assertDontSee('Upload Draft PDF')
        ->assertDontSee('Replace Draft PDF')
        ->assertDontSee('Submit for Approval');
});

test('secretary cannot open captain-only administration', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with([
    'staff accounts' => 'staff-accounts.index',
    'audit logs' => 'audit-logs.index',
]);

test('purok chairperson sees resident workspace without document administration', function () {
    $user = User::factory()->purokChairperson()->create();

    $this->actingAs($user)
        ->get(route('residents.index'))
        ->assertSuccessful()
        ->assertSee('Purok Residents')
        ->assertDontSee('Document Archive');

    $this->actingAs($user)->get(route('documents.index'))->assertForbidden();
});
