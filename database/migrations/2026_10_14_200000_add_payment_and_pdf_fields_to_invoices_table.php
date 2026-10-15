<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestampTz('paid_at')->nullable();
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_paid_fields CHECK ((status = 'paid') = (paid_at IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_paid_fields');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
