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
        Schema::create('purchase_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('pud_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('pud_pid')->nullable();
            $table->foreign('pud_pid')->references('pu_id')->on('purchase')->onDelete('cascade');
            $table->string('pud_itemid')->nullable();
            $table->foreign('pud_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('pud_hsn')->nullable();
            $table->decimal('pud_itemqty', 10, 2);
            $table->decimal('pud_returnqty', 10, 2);
            $table->decimal('pud_free', 10, 2);
            $table->string('pud_unit', 10);
            $table->decimal('pud_uprice', 10, 2);
            $table->decimal('pud_uqty', 10, 2);
            $table->decimal('pud_price', 10, 2);
            $table->decimal('pud_adisc', 10, 2);
            $table->decimal('pud_pdisc', 10, 2);
            $table->decimal('pud_gst', 10, 2);
            $table->decimal('pud_total', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_detail');
    }
};
