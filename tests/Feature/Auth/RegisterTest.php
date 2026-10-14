<?php

use App\Models\Organisation;
use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'organisation' => ['name' => 'Acme OOD', 'country_code' => 'BG'],
        'name' => 'Ana Petrova',
        'email' => 'ana@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'device_name' => 'pest',
    ], $overrides);
}

it('creates an organisation with its first user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', registrationPayload());

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.organisation.name', 'Acme OOD')
        ->assertJsonPath('data.organisation.country_code', 'BG')
        ->assertJsonStructure(['token']);

    $user = User::sole();
    expect($user->organisation->name)->toBe('Acme OOD')
        ->and(Organisation::count())->toBe(1);
});

it('issues a token that authenticates later requests', function () {
    $token = $this->postJson('/api/auth/register', registrationPayload())->json('token');

    $this->withToken($token)->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@example.com');
});

it('never returns the password hash', function () {
    $this->postJson('/api/auth/register', registrationPayload())
        ->assertJsonMissingPath('data.password');
});

it('stores the email in lowercase', function () {
    $this->postJson('/api/auth/register', registrationPayload(['email' => '  Ana@Example.COM ']))
        ->assertCreated();

    expect(User::sole()->email)->toBe('ana@example.com');
});

it('rejects an email that is already registered, whatever its case', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/api/auth/register', registrationPayload(['email' => 'ANA@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a country without VAT rates', function () {
    $this->postJson('/api/auth/register', registrationPayload(['organisation' => ['country_code' => 'DE']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('organisation.country_code');

    expect(Organisation::count())->toBe(0);
});

it('requires the password to be confirmed', function () {
    $this->postJson('/api/auth/register', registrationPayload(['password_confirmation' => 'something-else']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});
