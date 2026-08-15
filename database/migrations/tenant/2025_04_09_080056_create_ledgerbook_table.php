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
        Schema::create('ledgerbook', function (Blueprint $table) {
            $table->unsignedBigInteger('lb_id')->autoIncrement()->primary();
            $table->string('lb_vno', 50);
            $table->bigInteger('lb_vid');
            $table->date('lb_date');
            $table->string('lb_type', 50);
            $table->decimal('lb_amount',10,2);
            $table->decimal('lb_opbalance',10,2);
            $table->decimal('lb_tramount',10,2);
            $table->decimal('lb_clbalance',10,2);
            $table->unsignedBigInteger('lb_paymode')->nullable()->index();
            $table->foreign('lb_paymode')->references('bk_id')->on('banking')->onDelete('set null');
            $table->bigInteger('lb_payee');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgerbook');
    }
};
