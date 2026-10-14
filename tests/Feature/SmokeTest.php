<?php

use Illuminate\Support\Facades\DB;

it('responds with the application name at the root', function () {
    $this->getJson('/')
        ->assertOk()
        ->assertExactJson(['name' => config('app.name')]);
});

it('exposes a health check', function () {
    $this->get('/up')->assertOk();
});

it('runs the test suite against PostgreSQL', function () {
    // The locking and constraints this project relies on behave differently
    // on SQLite, so a silent fallback to it would make the suite meaningless.
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});
