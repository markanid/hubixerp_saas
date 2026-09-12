<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('print_settings') && !Schema::hasColumn('print_settings', 'header_mode')) {
            Schema::table('print_settings', function (Blueprint $table) {
                $table->string('header_mode', 20)->default('full')->after('auto_print');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('print_settings') && Schema::hasColumn('print_settings', 'header_mode')) {
            Schema::table('print_settings', function (Blueprint $table) {
                $table->dropColumn('header_mode');
            });
        }
    }
};
