<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') && !Schema::hasColumn('services', 'sv_type')) {
            Schema::table('services', function (Blueprint $table) {
                $table->tinyInteger('sv_type')->default(1)->after('sv_date');
            });
        }

        if (Schema::hasTable('service_details') && !Schema::hasColumn('service_details', 'manufacturing_date')) {
            Schema::table('service_details', function (Blueprint $table) {
                $table->string('manufacturing_date', 20)->nullable()->after('svd_hsn');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_details') && Schema::hasColumn('service_details', 'manufacturing_date')) {
            Schema::table('service_details', function (Blueprint $table) {
                $table->dropColumn('manufacturing_date');
            });
        }

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'sv_type')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('sv_type');
            });
        }
    }
};
