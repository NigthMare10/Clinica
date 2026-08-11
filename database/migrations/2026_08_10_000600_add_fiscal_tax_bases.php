<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_total', 14, 2)->default(0)->after('subtotal');
            $table->decimal('taxable_15_total', 14, 2)->default(0)->after('exonerated_total');
            $table->decimal('taxable_18_total', 14, 2)->default(0)->after('taxable_15_total');
            $table->decimal('isv_15_total', 14, 2)->default(0)->after('taxable_18_total');
            $table->decimal('isv_18_total', 14, 2)->default(0)->after('isv_15_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_total', 'taxable_15_total', 'taxable_18_total', 'isv_15_total', 'isv_18_total']);
        });
    }
};
