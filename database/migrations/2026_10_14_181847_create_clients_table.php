<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // Both optional: a private person (B2C) has neither. Format checks
            // and VIES lookups belong to the P3 validator package.
            $table->string('vat_number', 20)->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->text('address');
            $table->char('country_code', 2);
            $table->string('email')->nullable();
            $table->timestamps();

            // Every list query filters by organisation and sorts by name.
            $table->index(['organisation_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
