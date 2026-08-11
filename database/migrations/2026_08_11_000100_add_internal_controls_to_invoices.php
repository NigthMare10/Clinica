<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('order_number', 20)->nullable()->unique()->after('ncf');
            $table->string('invoice_control_number', 24)->nullable()->unique()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropUnique(['invoice_control_number']);
            $table->dropColumn(['order_number', 'invoice_control_number']);
        });
    }
};
