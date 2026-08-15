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
        Schema::create('purchase', function (Blueprint $table) {
            $table->unsignedBigInteger('pu_id')->autoIncrement()->primary();
            $table->string('pu_vno')->unique();
            $table->date('pu_date');
            $table->string('pu_bill_number',50)->nullable();
            $table->foreignId('pu_vendor')->constrained('vendor')->onDelete('cascade');
            $table->decimal('pu_amount', 10, 2);
            $table->decimal('pu_gst', 10, 2);
            $table->decimal('pu_discount', 10, 2);
            $table->decimal('pu_grandtotal', 10, 2);
            $table->decimal('pu_amount_payable', 10, 2);
            $table->decimal('pu_round', 10, 2);
            $table->decimal('pu_amount_paid', 10, 2);
            $table->decimal('pu_balance', 10, 2);
            $table->unsignedBigInteger('pu_paymode')->nullable();
            $table->foreign('pu_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->enum('pu_paid', ['NP', 'HP', 'FP'])->default('NP');
            $table->unsignedBigInteger('pu_user')->nullable();
            $table->foreign('pu_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('pu_status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase');
    }
};
