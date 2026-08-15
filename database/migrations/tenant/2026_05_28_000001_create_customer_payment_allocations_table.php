<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_ledgerbook_id');
            $table->unsignedBigInteger('source_ledgerbook_id');
            $table->string('source_type', 50);
            $table->bigInteger('source_id');
            $table->decimal('amount', 10, 2);

            $table->index('payment_ledgerbook_id');
            $table->index('source_ledgerbook_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
