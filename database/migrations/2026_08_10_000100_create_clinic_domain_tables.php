<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->json('common_reasons')->nullable();
            $table->json('services')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_public')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('professional_name')->nullable();
            $table->string('credential_type', 50)->nullable();
            $table->string('credential_number', 100)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('biography')->nullable();
            $table->json('schedules')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('seal_path')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
            $table->unique(['credential_type', 'credential_number']);
        });

        Schema::create('doctor_specialty', function (Blueprint $table) {
            $table->foreignUuid('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('specialty_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['doctor_id', 'specialty_id']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 30)->nullable();
            $table->string('document_number', 100)->nullable()->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->unique(['document_type', 'document_number']);
        });

        Schema::create('pdf_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('document_type', 50)->nullable()->index();
            $table->string('page_size', 20)->nullable();
            $table->unsignedInteger('qr_page')->default(1);
            $table->decimal('qr_x', 8, 2)->default(0);
            $table->decimal('qr_y', 8, 2)->default(0);
            $table->decimal('qr_width', 8, 2)->default(28);
            $table->decimal('qr_height', 8, 2)->default(28);
            $table->json('coordinates')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('medical_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50)->index();
            $table->string('status', 50)->index();
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('pdf_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reissue_of_id')->nullable()->constrained('medical_documents')->nullOnDelete();
            $table->foreignUuid('replaced_by_id')->nullable()->constrained('medical_documents')->nullOnDelete();
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('issued_path')->nullable();
            $table->char('original_sha256', 64)->index();
            $table->char('issued_sha256', 64)->nullable()->index();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('public_code', 40)->nullable()->unique();
            $table->unsignedTinyInteger('age_at_consultation')->nullable();
            $table->date('consultation_date')->nullable()->index();
            $table->time('consultation_time')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('medical_reason')->nullable();
            $table->text('diagnosis')->nullable();
            $table->date('leave_start_date')->nullable();
            $table->date('leave_end_date')->nullable();
            $table->unsignedSmallInteger('leave_days')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('confirmed_fields')->nullable();
            $table->json('inconsistencies')->nullable();
            $table->json('processing_metadata')->nullable();
            $table->boolean('digital_signature_detected')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'created_at']);
        });

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_document_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 30);
            $table->longText('raw_text')->nullable();
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->json('candidates')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
            $table->index(['medical_document_id', 'created_at']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 30);
            $table->string('path');
            $table->char('sha256', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['medical_document_id', 'version']);
        });

        Schema::create('document_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('field')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['medical_document_id', 'created_at']);
        });

        Schema::create('document_verification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20)->index();
            $table->boolean('successful')->index();
            $table->string('result', 40)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->char('uploaded_sha256', 64)->nullable();
            $table->timestamps();
            $table->index(['medical_document_id', 'created_at']);
        });

        Schema::create('site_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('document_verification_logs');
        Schema::dropIfExists('document_audit_logs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('medical_documents');
        Schema::dropIfExists('pdf_templates');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('doctor_specialty');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('specialties');
    }
};
