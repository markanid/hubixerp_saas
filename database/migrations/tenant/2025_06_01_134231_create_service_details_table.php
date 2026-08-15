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
        Schema::create('service_details', function (Blueprint $table) {
            $table->unsignedBigInteger('svd_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('svd_sid')->nullable();
            $table->foreign('svd_sid')->references('sv_id')->on('services')->onDelete('cascade');
            $table->string('svd_itemid')->nullable();
            $table->foreign('svd_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('svd_hsn')->nullable();
            $table->decimal('svd_itemqty', 10, 2);
            $table->string('svd_unit', 10);
            $table->decimal('svd_uprice', 10, 2);
            $table->decimal('svd_uqty', 10, 2);
            $table->decimal('svd_price', 10, 2);
            $table->decimal('svd_adisc', 10, 2);
            $table->decimal('svd_pdisc', 10, 2);
            $table->decimal('svd_gst', 10, 2);
            $table->decimal('svd_total', 10, 2);
            $table->enum('svd_type', ['sale', 'service'])->default('service');
            $table->enum('status', ['0', '1'])->default('1');
            $table->string('svd_remark', 500);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_details');
    }
};
