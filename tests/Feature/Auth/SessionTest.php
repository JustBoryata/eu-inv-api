<?php

use App\Models\User;

it('returns the current user with their organisation', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('pest')->plainTextToken)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.organisation.id', $user->organisation_id);
});

it('answers guests with 401 JSON, even without an Accept header', function () {
    // No login page exists to redirect to, so a missing Accept header must
    // still produce a JSON 401 rather than a redirect or a 500.
    $this->get('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('revokes only the token used to log out', function () {
    $user = User::factory()->create();
    $laptop = $user->createToken('laptop')->plainTextToken;
    $phone = $user->createToken('phone')->plainTextToken;

    $this->withToken($laptop)->postJson('/api/auth/logout')->assertNoContent();

    // The guard caches the resolved user between requests in one test.
    $this->app['auth']->forgetGuards();

    $this->withToken($laptop)->getJson('/api/me')->assertUnauthorized();
    $this->withToken($phone)->getJson('/api/me')->assertOk();
});

it('rejects an expired token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $this->travel(8)->days();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});
