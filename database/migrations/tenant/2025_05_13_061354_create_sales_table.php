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
        Schema::create('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('sa_id')->autoIncrement()->primary();
            $table->string('sa_vno')->unique();
            $table->string('sa_eway_bill_no')->nullable();
            $table->date('sa_date');
            $table->foreignId('sa_customer')->constrained('customer')->onDelete('cascade');
            $table->string('sa_state_code',2)->nullable();
            $table->string('sa_loc',250)->nullable();
            $table->string('sa_vehicle',100)->nullable();
            $table->decimal('sa_amount', 10, 2);
            $table->decimal('sa_gst', 10, 2);
            $table->decimal('sa_discount', 10, 2);
            $table->decimal('sa_grandtotal', 10, 2);
            $table->decimal('sa_amount_payable', 10, 2);
            $table->decimal('sa_round', 10, 2);
            $table->decimal('sa_amount_paid', 10, 2);
            $table->decimal('sa_balance', 10, 2);
            $table->unsignedBigInteger('sa_paymode')->nullable();
            $table->foreign('sa_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->enum('sa_paid', ['NP', 'HP', 'FP'])->default('NP');
            $table->enum('sa_type', ['0', '1', '2'])->default('1');
            $table->unsignedBigInteger('sa_user')->nullable();
            $table->foreign('sa_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->tinyInteger('sa_is_igst')->default(0); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
