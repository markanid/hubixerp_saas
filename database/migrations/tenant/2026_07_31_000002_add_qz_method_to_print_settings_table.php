<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('print_settings') && !Schema::hasColumn('print_settings', 'print_method')) {
            Schema::table('print_settings', function (Blueprint $table) {
                $table->string('print_method', 20)->default('browser')->after('document_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('print_settings') && Schema::hasColumn('print_settings', 'print_method')) {
            Schema::table('print_settings', function (Blueprint $table) {
                $table->dropColumn('print_method');
            });
        }
    }
};
