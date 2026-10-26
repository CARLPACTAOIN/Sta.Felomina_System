<?php

test('application trusts forwarded proxy headers from reverse proxies', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'demo.ngrok-free.app',
        'X-Forwarded-Port' => '443',
    ])->get(route('login'));

    $response->assertOk();
    expect(request()->isSecure())->toBeTrue()
        ->and(request()->schemeAndHttpHost())->toBe('https://demo.ngrok-free.app')
        ->and(asset('build/assets/app.css'))->toBe('https://demo.ngrok-free.app/build/assets/app.css');
});
