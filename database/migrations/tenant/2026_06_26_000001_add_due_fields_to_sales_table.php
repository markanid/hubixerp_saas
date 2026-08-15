<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'sa_due_days')) {
                $table->unsignedSmallInteger('sa_due_days')->nullable()->after('sa_balance');
            }

            if (!Schema::hasColumn('sales', 'sa_due_date')) {
                $table->date('sa_due_date')->nullable()->after('sa_due_days')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'sa_due_date')) {
                $table->dropIndex(['sa_due_date']);
                $table->dropColumn('sa_due_date');
            }

            if (Schema::hasColumn('sales', 'sa_due_days')) {
                $table->dropColumn('sa_due_days');
            }
        });
    }
};
