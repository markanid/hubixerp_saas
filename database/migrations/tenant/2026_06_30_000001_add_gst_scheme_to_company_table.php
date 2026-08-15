<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company', 'gst_scheme')) {
            Schema::table('company', function (Blueprint $table) {
                $table->string('gst_scheme', 20)->default('regular')->after('tax_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company', 'gst_scheme')) {
            Schema::table('company', function (Blueprint $table) {
                $table->dropColumn('gst_scheme');
            });
        }
    }
};
