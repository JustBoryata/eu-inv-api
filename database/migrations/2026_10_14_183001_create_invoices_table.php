<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            // Restrict: a client with invoices can't disappear from under them.
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            // Always EUR in v1, but stored so multi-currency doesn't need a schema change.
            $table->char('currency', 3)->default('EUR');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            // Money in integer cents. Recomputed from the lines on every write.
            $table->bigInteger('net_total')->default(0);
            $table->bigInteger('vat_total')->default(0);
            $table->bigInteger('gross_total')->default(0);
            // Stored rather than recomputed on read, so an issued invoice never
            // depends on rounding code that might change later.
            $table->jsonb('vat_breakdown')->default('[]');
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'client_id']);
        });

        // The application enforces these too. The database is the last line of defence.
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_valid CHECK (status IN ('draft', 'issued', 'paid'))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_gross_is_net_plus_vat CHECK (gross_total = net_total + vat_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
