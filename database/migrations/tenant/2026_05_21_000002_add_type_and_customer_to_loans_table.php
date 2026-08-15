<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'loan_type')) {
                $table->enum('loan_type', ['liability', 'asset'])->default('liability')->after('loan_date');
            }

            if (!Schema::hasColumn('loans', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('vendor_id');
                $table->foreign('customer_id')->references('id')->on('customer')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }

            if (Schema::hasColumn('loans', 'loan_type')) {
                $table->dropColumn('loan_type');
            }
        });
    }
};
