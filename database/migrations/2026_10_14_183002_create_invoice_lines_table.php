<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            // Cascade: lines are part of the invoice, and only drafts can be deleted.
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 500);
            $table->decimal('quantity', 12, 3);
            // Net unit price in cents.
            $table->bigInteger('unit_price');
            $table->foreignId('vat_rate_id')->constrained()->restrictOnDelete();
            // A copy of the rate's percentage. Refreshed when the invoice is issued,
            // and frozen from then on even if the rates table changes.
            $table->decimal('vat_rate', 5, 2);
            // quantity × unit_price, rounded half-up to whole cents.
            $table->bigInteger('net_amount');

            $table->unique(['invoice_id', 'position']);
        });

        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_unit_price_not_negative CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
