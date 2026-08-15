<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_vno')->unique();
            $table->date('loan_date');
            $table->enum('loan_type', ['liability', 'asset'])->default('liability');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendor')->onDelete('set null');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('set null');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')->references('bk_id')->on('banking')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->string('financial_year', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
