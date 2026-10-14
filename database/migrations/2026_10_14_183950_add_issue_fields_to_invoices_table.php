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
            // 10 digits, zero-padded, as the Bulgarian VAT Act requires.
            $table->char('number', 10)->nullable();
            // The legal date on the invoice, in the seller's time zone.
            $table->date('issue_date')->nullable();
            $table->timestampTz('issued_at')->nullable();

            // Copied from the organisation and the client at issue, so later
            // edits to either can't rewrite a document that has been sent.
            $table->string('seller_name')->nullable();
            $table->string('seller_registration_number', 20)->nullable();
            $table->string('seller_vat_number', 20)->nullable();
            $table->text('seller_address')->nullable();
            $table->char('seller_country_code', 2)->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_registration_number', 20)->nullable();
            $table->string('buyer_vat_number', 20)->nullable();
            $table->text('buyer_address')->nullable();
            $table->char('buyer_country_code', 2)->nullable();

            // The last line of defence for gap-free numbering: even a bug in the
            // locking can't produce two invoices with one number. Drafts have
            // no number, and Postgres lets NULLs repeat in a unique index.
            $table->unique(['organisation_id', 'number']);
            $table->index(['organisation_id', 'issue_date']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE invoices ADD CONSTRAINT invoices_issued_fields CHECK (
                (status = 'draft' AND number IS NULL AND issue_date IS NULL AND issued_at IS NULL)
                OR (status <> 'draft' AND number IS NOT NULL AND issue_date IS NOT NULL AND issued_at IS NOT NULL
                    AND seller_name IS NOT NULL AND buyer_name IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_issued_fields');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'number']);
            $table->dropIndex(['organisation_id', 'issue_date']);
            $table->dropColumn([
                'number', 'issue_date', 'issued_at',
                'seller_name', 'seller_registration_number', 'seller_vat_number', 'seller_address', 'seller_country_code',
                'buyer_name', 'buyer_registration_number', 'buyer_vat_number', 'buyer_address', 'buyer_country_code',
            ]);
        });
    }
};
