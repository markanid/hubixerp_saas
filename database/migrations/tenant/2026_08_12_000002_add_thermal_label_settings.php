<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barcode_settings')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('label_width_mm')->default(80)->after('selected_fields');
            $table->unsignedSmallInteger('label_height_mm')->default(40)->after('label_width_mm');
            $table->unsignedSmallInteger('label_margin_mm')->default(2)->after('label_height_mm');
            $table->boolean('auto_print')->default(true)->after('label_margin_mm');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('barcode_settings')) {
            return;
        }

        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->dropColumn(['label_width_mm', 'label_height_mm', 'label_margin_mm', 'auto_print']);
        });
    }
};
