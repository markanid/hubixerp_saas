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
        Schema::create('consume', function (Blueprint $table) {
            $table->unsignedBigInteger('con_id')->autoIncrement()->primary();
            $table->string('con_vno')->unique();
            $table->date('con_date');
            $table->decimal('con_amount', 10, 2);
            $table->unsignedBigInteger('con_user')->nullable();
            $table->foreign('con_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consume');
    }
};
