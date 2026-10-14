<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One counter row per organisation. Issuing an invoice locks its row with
     * SELECT … FOR UPDATE, so concurrent issues queue up instead of reading
     * the same number.
     *
     * Not a Postgres SEQUENCE: nextval() isn't rolled back with the
     * transaction, so a failed issue would burn a number and leave a gap.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->foreignId('organisation_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
