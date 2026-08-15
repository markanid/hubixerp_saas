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
        Schema::create('preturn', function (Blueprint $table) {
            $table->unsignedBigInteger('pr_id')->autoIncrement()->primary();
            $table->string('pr_vno')->unique();
            $table->string('pr_pvno');
            $table->date('pr_date');
            $table->unsignedBigInteger('pr_vendor');
            $table->decimal('pr_amount', 10, 2);
            $table->decimal('pr_gst', 10, 2);
            $table->decimal('pr_amount_payable', 10, 2);
            $table->decimal('pr_round', 10, 2);
            $table->decimal('pr_amount_paid', 10, 2);
            $table->decimal('pr_balance', 10, 2);
            $table->unsignedBigInteger('pr_paymode')->nullable();
            $table->foreign('pr_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->enum('pr_type', ['1', '2'])->default('1');
            $table->enum('pr_paid', ['NP', 'HP', 'FP'])->default('NP');
            $table->unsignedBigInteger('pr_user')->nullable();
            $table->foreign('pr_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preturn');
    }
};
