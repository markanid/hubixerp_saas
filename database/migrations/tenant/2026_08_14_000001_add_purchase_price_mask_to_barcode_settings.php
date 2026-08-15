<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barcode_settings') || Schema::hasColumn('barcode_settings', 'mask_purchase_price')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->boolean('mask_purchase_price')->default(false)->after('auto_print');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'mask_purchase_price')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->dropColumn('mask_purchase_price');
        });
    }
};
