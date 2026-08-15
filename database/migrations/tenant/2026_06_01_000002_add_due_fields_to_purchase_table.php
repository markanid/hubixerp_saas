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
        if (!Schema::hasTable('purchase')) {
            return;
        }

        Schema::table('purchase', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase', 'pu_due_days')) {
                $table->unsignedSmallInteger('pu_due_days')->nullable()->after('pu_balance');
            }

            if (!Schema::hasColumn('purchase', 'pu_due_date')) {
                $table->date('pu_due_date')->nullable()->after('pu_due_days')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('purchase')) {
            return;
        }

        Schema::table('purchase', function (Blueprint $table) {
            if (Schema::hasColumn('purchase', 'pu_due_date')) {
                $table->dropIndex(['pu_due_date']);
                $table->dropColumn('pu_due_date');
            }

            if (Schema::hasColumn('purchase', 'pu_due_days')) {
                $table->dropColumn('pu_due_days');
            }
        });
    }
};
