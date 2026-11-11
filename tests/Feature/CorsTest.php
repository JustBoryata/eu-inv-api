<?php

beforeEach(function () {
    config(['cors.allowed_origins' => ['https://app.example.com']]);
});

function preflight(string $origin): array
{
    return [
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'authorization, content-type',
    ];
}

it('allows a preflight from a configured origin', function () {
    $this->options('/api/invoices', [], preflight('https://app.example.com'))
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

it('never grants an unlisted origin', function () {
    // With exactly one configured origin, php-cors always sends that origin,
    // which a browser on any other origin rejects.
    $this->options('/api/invoices', [], preflight('https://evil.example'))
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

    $this->getJson('/api/vat-rates', ['Origin' => 'https://evil.example'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
});

it('gives no allow-origin header to an unlisted origin when several are configured', function () {
    config(['cors.allowed_origins' => ['https://app.example.com', 'http://localhost:5173']]);

    $this->options('/api/invoices', [], preflight('https://evil.example'))
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    $this->getJson('/api/vat-rates', ['Origin' => 'https://evil.example'])
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    $this->options('/api/invoices', [], preflight('http://localhost:5173'))
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

it('exposes Retry-After to a configured origin', function () {
    $this->getJson('/api/vat-rates', ['Origin' => 'https://app.example.com'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
        ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
});

it('allows no cross-origin access when no origins are configured', function () {
    config(['cors.allowed_origins' => []]);

    $this->options('/api/invoices', [], preflight('https://app.example.com'))
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('does not handle the Sanctum CSRF cookie path', function () {
    expect(config('cors.paths'))->toBe(['api/*']);
});
