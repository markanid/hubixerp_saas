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
        Schema::create('expence', function (Blueprint $table) {
            $table->id();
            $table->string('exp_vno')->unique();
            $table->unsignedBigInteger('categoryid')->nullable();
            $table->foreign('categoryid')->references('id')->on('excategory')->onDelete('set null');
            $table->date('exdate');
            $table->decimal('amount', 10, 2)->nullable();
            $table->unsignedBigInteger('ex_paymode')->nullable();
            $table->foreign('ex_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->text('remarks')->nullable();
            $table->string('docum', 500)->nullable();
            $table->unsignedBigInteger('exp_user')->nullable();
            $table->foreign('exp_user')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expence');
    }
};
