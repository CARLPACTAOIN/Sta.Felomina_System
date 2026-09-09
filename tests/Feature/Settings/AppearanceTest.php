<?php

use App\Models\User;

test('appearance settings render all supported preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertSuccessful()
        ->assertSee('Display theme')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});
