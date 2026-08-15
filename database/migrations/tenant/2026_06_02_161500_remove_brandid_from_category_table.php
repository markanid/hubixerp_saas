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
        Schema::table('category', function (Blueprint $table) {
            $table->dropForeign(['brandid']);
            $table->dropColumn('brandid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category', function (Blueprint $table) {
            $table->unsignedBigInteger('brandid')->default(1);
            $table->foreign('brandid')->references('id')->on('brand')->onDelete('cascade');
        });
    }
};
