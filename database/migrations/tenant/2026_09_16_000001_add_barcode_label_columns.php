<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('label_columns')->default(1);
            $table->decimal('label_column_gap_mm', 4, 1)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('barcode_settings', function (Blueprint $table) {
            $table->dropColumn(['label_columns', 'label_column_gap_mm']);
        });
    }
};
