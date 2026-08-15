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
        Schema::create('preturn_details', function (Blueprint $table) {
            $table->unsignedBigInteger('prd_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('prd_prid')->nullable();
            $table->foreign('prd_prid')->references('pr_id')->on('preturn')->onDelete('cascade');
            $table->string('prd_itemid')->nullable();
            $table->foreign('prd_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('prd_hsn')->nullable();
            $table->decimal('prd_itemqty', 10, 2);
            $table->decimal('prd_uqty', 10, 2);
            $table->string('prd_unit', 10);
            $table->decimal('prd_uprice', 10, 2);
            $table->decimal('prd_gst', 10, 2);
            $table->decimal('prd_total', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preturn_details');
    }
};
