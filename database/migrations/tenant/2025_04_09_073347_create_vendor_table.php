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
        Schema::create('vendor', function (Blueprint $table) {
            $table->id();
            $table->string('cp_name', 20);
            $table->string('cp_phone', 20)->unique();
            $table->string('cp_email', 20)->nullable(); 
            $table->string('cp_cname', 50)->nullable(); 
            $table->string('cp_cphone', 20)->nullable(); 
            $table->string('cp_address', 100)->nullable();
            $table->string('cp_logo', 50)->nullable();
            $table->string('cp_website', 20)->nullable();
            $table->string('cp_gst_no', 20)->nullable();
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor');
    }
};
