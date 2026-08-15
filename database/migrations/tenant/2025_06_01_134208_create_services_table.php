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
        Schema::create('services', function (Blueprint $table) {
            $table->unsignedBigInteger('sv_id')->autoIncrement()->primary();
            $table->string('sv_vno')->unique();
            $table->date('sv_date');
            $table->foreignId('sv_customer')->constrained('customer')->onDelete('cascade');
            $table->string('sv_loc',250)->nullable();
            $table->string('sv_vehicle',100)->nullable();
            $table->decimal('sv_amount', 10, 2);
            $table->decimal('sv_gst', 10, 2);
            $table->decimal('sv_discount', 10, 2);
            $table->decimal('sv_grandtotal', 10, 2);
            $table->decimal('sv_amount_payable', 10, 2);
            $table->decimal('sv_round', 10, 2);
            $table->decimal('sv_amount_paid', 10, 2);
            $table->decimal('sv_balance', 10, 2);
            $table->unsignedBigInteger('sv_paymode')->nullable();
            $table->foreign('sv_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->enum('sv_paid', ['NP', 'HP', 'FP'])->default('NP');
            $table->unsignedBigInteger('sv_user')->nullable();
            $table->foreign('sv_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
