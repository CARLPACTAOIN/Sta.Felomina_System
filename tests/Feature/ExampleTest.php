<?php

test('home redirects staff to the sign-in page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
