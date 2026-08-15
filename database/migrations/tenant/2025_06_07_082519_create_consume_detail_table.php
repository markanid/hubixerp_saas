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
        Schema::create('consume_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('cond_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('cond_cid')->nullable();
            $table->foreign('cond_cid')->references('con_id')->on('consume')->onDelete('cascade');
            $table->string('cond_itemid')->nullable();
            $table->foreign('cond_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('cond_hsn')->nullable();
            $table->decimal('cond_qty', 10, 2);
            $table->string('cond_unit', 10);
            $table->decimal('cond_uprice', 10, 2);
            $table->decimal('cond_uqty', 10, 2);
            $table->decimal('cond_total', 10, 2);
            $table->string('cond_remark', 100);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consume_detail');
    }
};
