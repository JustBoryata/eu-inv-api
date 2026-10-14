<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'ana@example.com',
        'password' => 'correct-horse-battery',
    ]);
});

it('returns a token for valid credentials', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'Ana@Example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'pest',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonStructure(['token']);
});

it('gives the same error for a wrong password and an unknown email', function (string $email, string $password) {
    $this->postJson('/api/auth/login', [
        'email' => $email,
        'password' => $password,
        'device_name' => 'pest',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);
})->with([
    'wrong password' => ['ana@example.com', 'wrong-password'],
    'unknown email' => ['nobody@example.com', 'correct-horse-battery'],
]);

it('throttles repeated attempts for the same email', function () {
    $attempt = fn () => $this->postJson('/api/auth/login', [
        'email' => 'ana@example.com',
        'password' => 'wrong-password',
        'device_name' => 'pest',
    ]);

    foreach (range(1, 5) as $_) {
        $attempt()->assertUnprocessable();
    }

    $attempt()->assertTooManyRequests();
});
