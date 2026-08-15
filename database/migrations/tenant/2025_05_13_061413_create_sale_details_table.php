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
        Schema::create('sale_details', function (Blueprint $table) {
            $table->unsignedBigInteger('sad_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('sad_sid')->nullable();
            $table->foreign('sad_sid')->references('sa_id')->on('sales')->onDelete('cascade');
            $table->string('sad_itemid')->nullable();
            $table->foreign('sad_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('sad_hsn')->nullable();
            $table->decimal('sad_itemqty', 10, 2);
            $table->decimal('sad_returnqty', 10, 2);
            $table->string('sad_unit', 10);
            $table->decimal('sad_uprice', 10, 2);
            $table->decimal('sad_uqty', 10, 2);
            $table->decimal('sad_price', 10, 2);
            $table->decimal('sad_adisc', 10, 2);
            $table->decimal('sad_pdisc', 10, 2);
            $table->decimal('sad_gst', 10, 2);
            $table->decimal('sad_total', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};
