<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_verification_logs', function (Blueprint $table) {
            $table->timestamp('verified_at', 6)->nullable()->after('result')->index();
            $table->boolean('identity_verified')->default(false)->after('verified_at');
            $table->json('context')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('document_verification_logs', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'identity_verified', 'context']);
        });
    }
};
