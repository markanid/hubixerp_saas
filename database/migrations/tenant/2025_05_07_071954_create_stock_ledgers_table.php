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
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('stock_item_id')->nullable();     // item ID (foreign key)
            $table->date('stock_date');                      // date of transaction
            $table->string('stock_type');                    // 'purchase', 'sale', 'return', 'consumption', etc.
            $table->unsignedBigInteger('stock_ref_id')->nullable(); // ID of purchase/sale/etc.
            $table->decimal('stock_in', 10, 2)->default(0);       // qty added to stock
            $table->decimal('stock_out', 10, 2)->default(0);        // qty removed from stock
            $table->decimal('stock_balance', 10, 2)->default(0);// resulting stock balance after this entry
            
            $table->foreign('stock_item_id')->references('product_code')->on('product')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
