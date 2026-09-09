<?php

test('published verification preview clearly shows verified status and document actions', function () {
    $this->get(route('verification.preview', 'published'))
        ->assertSuccessful()
        ->assertSee('Verified Official Barangay Document')
        ->assertSee('View Official Document')
        ->assertSee('Download PDF');
});

test('unpublished verification previews never offer official document actions', function (string $state, string $heading) {
    $this->get(route('verification.preview', $state))
        ->assertSuccessful()
        ->assertSee($heading)
        ->assertSee('does not confirm authenticity')
        ->assertDontSee('View Official Document')
        ->assertDontSee('Download PDF');
})->with([
    'draft' => ['draft', 'Draft / Not Yet Verified'],
    'pending approval' => ['pending', 'Pending / Not Yet Verified'],
]);

test('invalid verification preview state is not found', function () {
    $this->get('/verification-preview/invalid')->assertNotFound();
});
