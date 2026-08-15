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
        Schema::create('stock', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_id')->autoIncrement()->primary();
            $table->string('stock_date', 20);
            $table->string('stock_product_id')->nullable();
            $table->foreign('stock_product_id')->references('product_code')->on('product')->onDelete('cascade');
            $table->decimal('stock_qty', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
