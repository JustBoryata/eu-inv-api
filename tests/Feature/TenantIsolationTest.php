<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->ours = Client::factory()->create();
    $this->theirs = Client::factory()->create();

    signIn(User::factory()->for($this->ours->organisation)->create());
});

it('lists only our own clients', function () {
    $this->getJson('/api/clients')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->ours->id]);
});

it("answers 404, not 403, for another tenant's client", function (string $method) {
    // 403 would confirm that the id exists; 404 reveals nothing.
    $this->json($method, "/api/clients/{$this->theirs->id}", ['name' => 'Hijacked'])
        ->assertNotFound();
})->with(['GET', 'PATCH', 'DELETE']);

it("leaves another tenant's client untouched", function () {
    $this->patchJson("/api/clients/{$this->theirs->id}", ['name' => 'Hijacked']);
    $this->deleteJson("/api/clients/{$this->theirs->id}");

    Auth::forgetGuards();

    expect(Client::query()->find($this->theirs->id))
        ->not->toBeNull()
        ->name->not->toBe('Hijacked');
});

it('does not scope queries when nobody is signed in', function () {
    // Queue workers and console commands have no user and must see every row.
    Auth::forgetGuards();

    expect(Client::query()->count())->toBe(2);
});
