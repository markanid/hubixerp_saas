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
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('company', 50);
            $table->string('licence_no', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('fax', 25)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('website', 50)->nullable();
            $table->string('company_logo', 100)->nullable();
            $table->string('qr_code', 100)->nullable();
            $table->string('gst_no', 50)->nullable();
            $table->string('tin_no', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_ifsc', 50)->nullable();
            $table->string('bank_acno', 50)->nullable();
            $table->string('bank_branch', 50)->nullable();
            $table->string('tags', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company');
    }
};
