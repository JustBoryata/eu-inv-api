<?php

use App\Models\Client;

it('creates a client in the signed-in organisation', function () {
    $user = signIn();

    $response = $this->postJson('/api/clients', [
        'name' => 'Acme EOOD',
        'vat_number' => 'bg 123 456 789',
        'registration_number' => '123456789',
        'address' => 'ul. Vitosha 1, Sofia',
        'country_code' => 'bg',
        'email' => 'billing@acme.test',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Acme EOOD')
        ->assertJsonPath('data.vat_number', 'BG123456789')
        ->assertJsonPath('data.country_code', 'BG');

    expect(Client::query()->findOrFail($response->json('data.id'))->organisation_id)
        ->toBe($user->organisation_id);
});

it('ignores an organisation_id sent in the request', function () {
    $user = signIn();
    $otherOrganisation = Client::factory()->create()->organisation_id;

    $response = $this->postJson('/api/clients', [
        'name' => 'Acme EOOD',
        'address' => 'Sofia',
        'country_code' => 'BG',
        'organisation_id' => $otherOrganisation,
    ])->assertCreated();

    expect(Client::query()->findOrFail($response->json('data.id'))->organisation_id)
        ->toBe($user->organisation_id);
});

it('validates a new client', function () {
    signIn();

    $this->postJson('/api/clients', ['country_code' => 'Bulgaria', 'email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'address', 'country_code', 'email']);
});

it('lists clients by name, paginated', function () {
    $user = signIn();
    Client::factory()->for($user->organisation)->create(['name' => 'Charlie']);
    Client::factory()->for($user->organisation)->create(['name' => 'Alpha']);
    Client::factory()->for($user->organisation)->create(['name' => 'Bravo']);

    $this->getJson('/api/clients?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Alpha', 'Bravo'])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('caps the page size', function () {
    signIn();

    $this->getJson('/api/clients?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('searches clients by name, ignoring case and treating wildcards literally', function () {
    $user = signIn();
    Client::factory()->for($user->organisation)->create(['name' => 'Acme EOOD']);
    Client::factory()->for($user->organisation)->create(['name' => '100% Organic OOD']);
    Client::factory()->for($user->organisation)->create(['name' => 'Globex AD']);

    $this->getJson('/api/clients?search=acme')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Acme EOOD']);

    $this->getJson('/api/clients?search='.urlencode('%'))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['100% Organic OOD']);
});

it('shows a client', function () {
    $user = signIn();
    $client = Client::factory()->for($user->organisation)->create();

    $this->getJson("/api/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.name', $client->name);
});

it('updates only the fields that were sent', function () {
    $user = signIn();
    $client = Client::factory()->for($user->organisation)->create([
        'name' => 'Acme EOOD',
        'vat_number' => 'BG123456789',
    ]);

    $this->patchJson("/api/clients/{$client->id}", ['email' => 'new@acme.test'])
        ->assertOk()
        ->assertJsonPath('data.email', 'new@acme.test')
        ->assertJsonPath('data.name', 'Acme EOOD')
        ->assertJsonPath('data.vat_number', 'BG123456789');
});

it('deletes a client', function () {
    $user = signIn();
    $client = Client::factory()->for($user->organisation)->create();

    $this->deleteJson("/api/clients/{$client->id}")->assertNoContent();

    expect(Client::query()->find($client->id))->toBeNull();
});

it('requires authentication', function () {
    $this->getJson('/api/clients')->assertUnauthorized();
});
