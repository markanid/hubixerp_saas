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
        Schema::create('daily_bank', function (Blueprint $table) {
            $table->unsignedBigInteger('db_id')->autoIncrement()->primary();
            $table->date('db_date');
            $table->unsignedBigInteger('db_bank')->nullable()->index();
            $table->foreign('db_bank')->references('bk_id')->on('banking')->onDelete('set null');
            $table->decimal('db_amount',10,2);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_bank');
    }
};
