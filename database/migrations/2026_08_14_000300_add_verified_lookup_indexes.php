<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_documents', function (Blueprint $table): void {
            $table->index(['public_code', 'is_current_revision'], 'medical_documents_public_code_current_index');
        });
        Schema::table('patients', function (Blueprint $table): void {
            $table->index(['last_name', 'first_name', 'id'], 'patients_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('medical_documents', fn (Blueprint $table) => $table->dropIndex('medical_documents_public_code_current_index'));
        Schema::table('patients', fn (Blueprint $table) => $table->dropIndex('patients_name_index'));
    }
};
