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
            // Set by RenderInvoicePdf once the file is on disk. Null means
            // "not rendered yet" (or a draft, which never gets a PDF).
            $table->string('pdf_path')->nullable();
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_paid_fields CHECK ((status = 'paid') = (paid_at IS NOT NULL))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_draft_has_no_pdf CHECK (status <> 'draft' OR pdf_path IS NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_draft_has_no_pdf');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_paid_fields');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'pdf_path']);
        });
    }
};
