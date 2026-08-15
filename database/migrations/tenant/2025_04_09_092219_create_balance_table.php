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
        Schema::create('balance', function (Blueprint $table) {
            $table->unsignedBigInteger('balance_id')->autoIncrement()->primary();
            $table->date('balance_date');
            $table->unsignedBigInteger('balance_pmode')->nullable()->index();
            $table->foreign('balance_pmode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->decimal('balance_close',10,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance');
    }
};
