<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 40)->index();
            $table->string('path');
            $table->char('sha256', 64);
            $table->string('mime_type', 30);
            $table->boolean('is_active')->default(false)->index();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_assets');
    }
};
