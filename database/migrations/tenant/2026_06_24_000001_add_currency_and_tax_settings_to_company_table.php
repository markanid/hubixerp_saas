<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company', 'currency_code')) {
            Schema::table('company', fn (Blueprint $table) => $table->string('currency_code', 3)->default('INR'));
        }

        if (!Schema::hasColumn('company', 'currency_symbol')) {
            Schema::table('company', fn (Blueprint $table) => $table->string('currency_symbol', 10)->default('₹'));
        }

        if (!Schema::hasColumn('company', 'tax_type')) {
            Schema::table('company', fn (Blueprint $table) => $table->string('tax_type', 10)->default('gst'));
        }
    }

    public function down(): void
    {
        $columns = collect(['currency_code', 'currency_symbol', 'tax_type'])
            ->filter(fn (string $column) => Schema::hasColumn('company', $column))
            ->all();

        if (!empty($columns)) {
            Schema::table('company', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
