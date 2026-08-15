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
        Schema::create('estimation_details', function (Blueprint $table) {
            $table->unsignedBigInteger('esd_id')->autoIncrement()->primary();
            $table->unsignedBigInteger('esd_sid')->nullable();
            $table->foreign('esd_sid')->references('es_id')->on('estimation')->onDelete('cascade');
            $table->string('esd_itemid')->nullable();
            $table->foreign('esd_itemid')->references('product_code')->on('product')->onDelete('set null');
            $table->string('esd_hsn')->nullable();
            $table->decimal('esd_itemqty', 10, 2);
            $table->string('esd_unit', 10);
            $table->decimal('esd_uprice', 10, 2);
            $table->decimal('esd_uqty', 10, 2);
            $table->decimal('esd_price', 10, 2);
            $table->decimal('esd_adisc', 10, 2);
            $table->decimal('esd_pdisc', 10, 2);
            $table->decimal('esd_total', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimation_details');
    }
};
