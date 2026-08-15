<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('estimation_settings')) {
            Schema::create('estimation_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->boolean('value')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('estimation')) {
            Schema::table('estimation', function (Blueprint $table) {
                if (!Schema::hasColumn('estimation', 'es_account_effect')) {
                    $table->boolean('es_account_effect')->default(false)->after('es_type');
                }
                if (!Schema::hasColumn('estimation', 'es_paymode')) {
                    $table->unsignedBigInteger('es_paymode')->nullable()->after('es_round');
                }
                if (!Schema::hasColumn('estimation', 'es_amount_paid')) {
                    $table->decimal('es_amount_paid', 10, 2)->default(0)->after('es_paymode');
                }
                if (!Schema::hasColumn('estimation', 'es_balance')) {
                    $table->decimal('es_balance', 10, 2)->default(0)->after('es_amount_paid');
                }
                if (!Schema::hasColumn('estimation', 'es_due_days')) {
                    $table->unsignedInteger('es_due_days')->nullable()->after('es_balance');
                }
                if (!Schema::hasColumn('estimation', 'es_due_date')) {
                    $table->date('es_due_date')->nullable()->after('es_due_days');
                }
                if (!Schema::hasColumn('estimation', 'es_paid')) {
                    $table->string('es_paid', 5)->nullable()->after('es_due_date');
                }
            });
        }

        if (Schema::hasTable('estimation_details')) {
            Schema::table('estimation_details', function (Blueprint $table) {
                if (!Schema::hasColumn('estimation_details', 'stock_batch_id')) {
                    $table->foreignId('stock_batch_id')->nullable()->after('esd_itemid');
                }
                if (!Schema::hasColumn('estimation_details', 'batch_no')) {
                    $table->string('batch_no', 100)->nullable()->after('stock_batch_id');
                }
                if (!Schema::hasColumn('estimation_details', 'expiry_date')) {
                    $table->date('expiry_date')->nullable()->after('batch_no');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('estimation_details')) {
            Schema::table('estimation_details', function (Blueprint $table) {
                foreach (['expiry_date', 'batch_no', 'stock_batch_id'] as $column) {
                    if (Schema::hasColumn('estimation_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('estimation')) {
            Schema::table('estimation', function (Blueprint $table) {
                foreach (['es_paid', 'es_due_date', 'es_due_days', 'es_balance', 'es_amount_paid', 'es_paymode', 'es_account_effect'] as $column) {
                    if (Schema::hasColumn('estimation', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('estimation_settings');
    }
};
