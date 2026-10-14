<?php

it("shows the signed-in user's organisation", function () {
    $user = signIn();

    $this->getJson('/api/organisation')
        ->assertOk()
        ->assertJsonPath('data.id', $user->organisation_id)
        ->assertJsonPath('data.name', $user->organisation->name);
});

it('updates the seller details printed on invoices', function () {
    $user = signIn();

    $this->patchJson('/api/organisation', [
        'registration_number' => '206123456',
        'vat_number' => 'bg 206 123 456',
        'address' => 'bul. Bulgaria 1, Sofia',
    ])
        ->assertOk()
        ->assertJsonPath('data.registration_number', '206123456')
        ->assertJsonPath('data.vat_number', 'BG206123456')
        ->assertJsonPath('data.address', 'bul. Bulgaria 1, Sofia')
        ->assertJsonPath('data.name', $user->organisation->name);
});

it('does not let the country change', function () {
    $user = signIn();

    $this->patchJson('/api/organisation', ['country_code' => 'DE'])->assertOk();

    expect($user->organisation->refresh()->country_code)->toBe('BG');
});

it('does not allow an empty name', function () {
    signIn();

    $this->patchJson('/api/organisation', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
