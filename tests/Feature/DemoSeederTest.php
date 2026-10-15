<?php

use App\Models\Invoice;
use App\Models\Organisation;
use Database\Seeders\DemoSeeder;

it('creates a demo organisation the README quickstart can log in to', function () {
    $this->seed(DemoSeeder::class);

    $this->postJson('/api/auth/login', [
        'email' => DemoSeeder::EMAIL,
        'password' => DemoSeeder::PASSWORD,
        'device_name' => 'test',
    ])->assertOk()->assertJsonPath('data.organisation.name', 'Демо Студио ЕООД');
});

it('creates one invoice in each state, numbered by the real issue flow', function () {
    $this->seed(DemoSeeder::class);

    $invoices = Invoice::query()->orderBy('id')->get();

    expect($invoices->pluck('status')->map->value->all())->toBe(['paid', 'issued', 'draft'])
        ->and($invoices->pluck('number')->all())->toBe(['0000000001', '0000000002', null])
        ->and($invoices[1]->vat_breakdown)->toHaveCount(2)
        ->and($invoices->whereNotNull('pdf_path'))->toHaveCount(2);
});

it('does nothing when the demo user already exists', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Organisation::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(3);
});
