<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_kind', 30);
            $table->foreignUuid('billing_service_id')->constrained()->restrictOnDelete();
            $table->decimal('default_quantity', 12, 3)->default(1);
            $table->decimal('price_override', 14, 2)->nullable();
            $table->string('tax_category', 20)->default('EXENTO');
            $table->string('default_payment_method', 40)->default('EFECTIVO');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['clinic_id', 'certificate_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
