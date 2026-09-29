<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barcode_settings') || Schema::hasColumn('barcode_settings', 'barcode_layout')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->string('barcode_layout', 20)->default('thermal');
            $table->unsignedSmallInteger('common_label_width_mm')->default(50);
            $table->unsignedSmallInteger('common_label_height_mm')->default(25);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'barcode_layout')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_layout',
                'common_label_width_mm',
                'common_label_height_mm',
            ]);
        });
    }
};
