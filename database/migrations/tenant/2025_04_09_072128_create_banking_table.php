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
        Schema::create('banking', function (Blueprint $table) {
            $table->unsignedBigInteger('bk_id')->autoIncrement()->primary();
            $table->string('bk_bank', 50);
            $table->string('bk_account', 50);
            $table->string('bk_branch', 20);
            $table->string('bk_ifsc', 20);
            $table->decimal('bk_opbalance', 10, 2);
            $table->decimal('bk_clbalance', 10, 2);
            $table->enum('bk_status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banking');
    }
};
