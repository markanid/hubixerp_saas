<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgerbook', function (Blueprint $table) {
            $table->index(['lb_payee', 'lb_date', 'lb_id'], 'ledgerbook_outstanding_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('ledgerbook', function (Blueprint $table) {
            $table->dropIndex('ledgerbook_outstanding_lookup');
        });
    }
};
