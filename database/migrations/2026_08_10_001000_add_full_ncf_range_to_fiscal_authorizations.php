<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_authorizations', function (Blueprint $table) {
            $table->string('ncf_type', 10)->nullable()->after('document_type');
            $table->string('full_range_start', 60)->nullable()->after('range_end');
            $table->string('full_range_end', 60)->nullable()->after('full_range_start');
            $table->string('source', 60)->nullable()->after('full_range_end')->index();
            $table->unique(
                ['cai', 'rtn', 'full_range_start', 'full_range_end'],
                'fiscal_authorizations_exact_range_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_authorizations', function (Blueprint $table) {
            $table->dropUnique('fiscal_authorizations_exact_range_unique');
            $table->dropIndex(['source']);
            $table->dropColumn(['ncf_type', 'full_range_start', 'full_range_end', 'source']);
        });
    }
};
