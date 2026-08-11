<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cai', 100);
            $table->string('rtn', 30);
            $table->string('establishment', 20);
            $table->string('point_of_issue', 20);
            $table->string('document_type', 30)->default('FACTURA_CONTADO');
            $table->string('ncf_prefix', 30);
            $table->unsignedBigInteger('range_start');
            $table->unsignedBigInteger('range_end');
            $table->unsignedBigInteger('next_number');
            $table->unsignedTinyInteger('number_padding')->default(8);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'ncf_prefix', 'range_start']);
            $table->index(['clinic_id', 'is_active', 'valid_from', 'valid_until'], 'fiscal_authorizations_lookup_index');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fiscal_authorization_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            // This optional link does not participate in the medical-document lifecycle.
            $table->foreignUuid('medical_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->string('ncf', 60)->nullable()->unique();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_tax_id', 100)->nullable();
            $table->unsignedTinyInteger('recipient_age')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 10)->default('HNL');
            $table->char('source_hash', 64)->nullable()->index();
            $table->char('issued_hash', 64)->nullable()->unique();
            $table->char('qr_token_hash', 64)->nullable()->unique();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('exempt_total', 14, 2)->default(0);
            $table->decimal('exonerated_total', 14, 2)->default(0);
            $table->decimal('tax_15_total', 14, 2)->default(0);
            $table->decimal('tax_18_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('voided_at')->nullable()->index();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['clinic_id', 'status', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description');
            $table->string('service_code', 60)->nullable();
            $table->foreignUuid('medical_document_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('tax_category', 20);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['invoice_id', 'position']);
        });

        Schema::create('billing_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_price', 14, 2)->default(0);
            $table->string('tax_type', 20)->default('EXENTO');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('invoice_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_audits');
        Schema::dropIfExists('billing_services');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fiscal_authorizations');
    }
};
