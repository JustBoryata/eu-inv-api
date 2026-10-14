<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            // A percentage, e.g. 20.00. numeric, not float, so it multiplies exactly.
            $table->decimal('rate', 5, 2);
            $table->string('name');

            $table->unique(['country_code', 'rate']);
        });

        DB::statement('ALTER TABLE vat_rates ADD CONSTRAINT vat_rates_rate_range CHECK (rate >= 0 AND rate < 100)');

        // Reference data the API can't work without, so it ships with the schema
        // instead of a seeder that a deploy could forget to run. Every
        // country in config('invoicing.supported_countries') needs rows here.
        DB::table('vat_rates')->insert([
            ['country_code' => 'BG', 'rate' => '20.00', 'name' => 'Standard rate'],
            ['country_code' => 'BG', 'rate' => '9.00', 'name' => 'Reduced rate'],
            ['country_code' => 'BG', 'rate' => '0.00', 'name' => 'Zero rate'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};
