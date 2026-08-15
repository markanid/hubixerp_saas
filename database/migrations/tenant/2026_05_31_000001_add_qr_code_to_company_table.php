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
        Schema::table('company', function (Blueprint $table) {
            if (!Schema::hasColumn('company', 'qr_code')) {
                $table->string('qr_code', 100)->nullable()->after('company_logo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (Schema::hasColumn('company', 'qr_code')) {
                $table->dropColumn('qr_code');
            }
        });
    }
};
