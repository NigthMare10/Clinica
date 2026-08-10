<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('department')->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->json('hours')->nullable();
            $table->string('status', 30)->default('PLANNED')->index();
            $table->boolean('is_public')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('clinic_user', function (Blueprint $table) {
            $table->foreignUuid('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->primary(['clinic_id', 'user_id']);
        });

        Schema::create('clinic_doctor', function (Blueprint $table) {
            $table->foreignUuid('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('doctor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->primary(['clinic_id', 'doctor_id']);
        });

        Schema::create('patient_clinic', function (Blueprint $table) {
            $table->foreignUuid('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->string('medical_record_number', 100)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->primary(['clinic_id', 'patient_id']);
            $table->unique(['clinic_id', 'medical_record_number']);
        });

        Schema::table('medical_documents', function (Blueprint $table) {
            $table->foreignUuid('clinic_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->string('source_kind', 20)->default('UPLOADED')->after('clinic_id')->index();
            $table->string('certificate_kind', 30)->nullable()->after('source_kind')->index();
            $table->json('template_snapshot')->nullable()->after('processing_metadata');
            $table->timestamp('generated_at')->nullable()->after('template_snapshot');
            $table->index(['clinic_id', 'status', 'created_at']);
            $table->index(['clinic_id', 'patient_id', 'consultation_date']);
        });

        Schema::table('pdf_templates', function (Blueprint $table) {
            $table->foreignUuid('clinic_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('certificate_kind', 30)->nullable()->after('document_type')->index();
            $table->string('source_path')->nullable()->after('page_size');
            $table->unsignedInteger('version')->default(1)->after('source_path');
            $table->json('field_schema')->nullable()->after('coordinates');
            $table->foreignUuid('supersedes_id')->nullable()->after('field_schema')->constrained('pdf_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdf_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['certificate_kind', 'source_path', 'version', 'field_schema']);
        });
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'status', 'created_at']);
            $table->dropIndex(['clinic_id', 'patient_id', 'consultation_date']);
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropColumn(['source_kind', 'certificate_kind', 'template_snapshot', 'generated_at']);
        });
        Schema::dropIfExists('patient_clinic');
        Schema::dropIfExists('clinic_doctor');
        Schema::dropIfExists('clinic_user');
        Schema::dropIfExists('clinics');
    }
};
