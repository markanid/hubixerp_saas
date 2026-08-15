<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase') && !Schema::hasColumn('purchase', 'pu_type')) {
            Schema::table('purchase', function (Blueprint $table) {
                $table->enum('pu_type', ['1', '2'])->default('1')->after('pu_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase') && Schema::hasColumn('purchase', 'pu_type')) {
            Schema::table('purchase', function (Blueprint $table) {
                $table->dropColumn('pu_type');
            });
        }
    }
};
