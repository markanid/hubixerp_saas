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
        Schema::create('stock_value', function (Blueprint $table) {
            $table->unsignedBigInteger('sv_id')->autoIncrement()->primary();
            $table->date('sv_date');
            $table->decimal('sv_pvalue',10,2);
            $table->decimal('sv_svalue',10,2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_value');
    }
};
