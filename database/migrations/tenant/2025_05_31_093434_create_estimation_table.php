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
        Schema::create('estimation', function (Blueprint $table) {
            $table->unsignedBigInteger('es_id')->autoIncrement()->primary();
            $table->string('es_vno')->unique();
            $table->date('es_date');
            $table->foreignId('es_customer')->constrained('customer')->onDelete('cascade');
            $table->decimal('es_amount', 10, 2);
            $table->decimal('es_discount', 10, 2);
            $table->decimal('es_grandtotal', 10, 2);
            $table->decimal('es_amount_payable', 10, 2);
            $table->decimal('es_round', 10, 2);
            $table->enum('es_type', ['0', '1'])->default('1');
            $table->unsignedBigInteger('es_user')->nullable();
            $table->foreign('es_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimation');
    }
};
