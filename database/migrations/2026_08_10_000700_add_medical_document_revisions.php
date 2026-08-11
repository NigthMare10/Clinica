<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('medical_documents', 'revision_number')) {
            Schema::table('medical_documents', function (Blueprint $table) {
                $table->dropUnique(['public_code']);
                $table->unsignedInteger('revision_number')->default(1)->after('public_code');
                $table->boolean('is_current_revision')->default(true)->after('revision_number')->index();
            });
        }

        if (! Schema::hasTable('medical_document_revisions')) {
            Schema::create('medical_document_revisions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('medical_document_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignUuid('source_document_id')->constrained('medical_documents')->restrictOnDelete();
                $table->foreignUuid('corrected_by')->constrained('users')->restrictOnDelete();
                $table->unsignedInteger('revision_number');
                $table->text('reason');
                $table->json('source_snapshot');
                $table->json('current_snapshot')->nullable();
                $table->timestamps();
                $table->unique(['source_document_id', 'revision_number'], 'medical_revision_source_number_unique');
            });
        } else {
            Schema::table('medical_document_revisions', function (Blueprint $table) {
                $table->unique(['source_document_id', 'revision_number'], 'medical_revision_source_number_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_document_revisions');
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->dropIndex(['is_current_revision']);
            $table->dropColumn(['revision_number', 'is_current_revision']);
            $table->unique('public_code');
        });
    }
};
