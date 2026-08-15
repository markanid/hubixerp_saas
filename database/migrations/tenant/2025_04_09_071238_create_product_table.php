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
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 20)->unique();
            $table->string('product', 50);
            $table->string('hsn_code', 20)->nullable();

            $table->decimal('mrp', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();
            $table->decimal('amt_margin', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('pprice', 10, 2)->nullable();

            $table->string('unit', 10)->nullable();
            $table->decimal('uqty', 10, 2)->nullable();
            $table->decimal('gst', 10, 2)->nullable();

            $table->decimal('maxquantity', 10, 2)->nullable();
            $table->decimal('minquantity', 10, 2)->nullable();

            $table->unsignedBigInteger('brandid')->nullable()->index();
            $table->unsignedBigInteger('categoryid')->nullable()->index();
            $table->unsignedBigInteger('subcategoryid')->nullable()->index();
            $table->unsignedBigInteger('groupid')->nullable()->index();
            $table->unsignedBigInteger('typeid')->default('1');

            $table->foreign('brandid')->references('id')->on('brand')->onDelete('set null');
            $table->foreign('categoryid')->references('id')->on('category')->onDelete('set null');
            $table->foreign('subcategoryid')->references('id')->on('subcategory')->onDelete('set null');
            $table->foreign('groupid')->references('id')->on('groups')->onDelete('set null');
           
            $table->string('qrcode_image', 500)->nullable();     
            $table->string('bcode_image', 500)->nullable();
            $table->string('bar_code', 20)->unique();
            $table->string('product_image', 500)->nullable();
            $table->enum('status', ['0', '1'])->default('1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
