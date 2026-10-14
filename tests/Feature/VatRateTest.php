<?php

use App\Models\VatRate;

it("lists the VAT rates of the organisation's country, highest first", function () {
    signIn();

    $this->getJson('/api/vat-rates')
        ->assertOk()
        ->assertJsonPath('data.*.rate', ['20.00', '9.00', '0.00'])
        ->assertJsonPath('data.0.name', 'Standard rate');
});

it('leaves out rates of other countries', function () {
    VatRate::query()->insert(['country_code' => 'DE', 'rate' => '19.00', 'name' => 'Standard rate']);
    signIn();

    $this->getJson('/api/vat-rates')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissing(['rate' => '19.00']);
});

it('has rates for every supported country', function () {
    // Registration accepts these countries, so each one needs rates to invoice with.
    foreach (config()->array('invoicing.supported_countries') as $country) {
        expect(VatRate::query()->where('country_code', $country)->exists())
            ->toBeTrue("No VAT rates for {$country}");
    }
});
