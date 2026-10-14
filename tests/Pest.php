<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Authenticate as a user (a fresh one by default) for the rest of the test.
 *
 * From here on the tenant scope also applies to queries in the test body,
 * not only to the requests the test makes.
 */
function signIn(?User $user = null): User
{
    $user ??= User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}
