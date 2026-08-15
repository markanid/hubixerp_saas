<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\app\Models\Purchase;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_voucher_sequences')) {
            Schema::create('purchase_voucher_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 20);
                $table->string('purchase_type', 10);
                $table->unsignedInteger('last_number')->default(0);
                $table->unique(['financial_year', 'purchase_type'], 'purchase_voucher_sequences_unique');
            });
        }

        if (!Schema::hasTable('purchase')) {
            return;
        }

        DB::table('purchase')
            ->select('pu_vno', 'pu_date', 'pu_type')
            ->whereNotNull('pu_vno')
            ->orderBy('pu_id')
            ->get()
            ->each(function ($purchase) {
                $number = $this->voucherNumber((string) $purchase->pu_vno);

                if ($number <= 0) {
                    return;
                }

                $financialYear = Purchase::getFinancialYear($purchase->pu_date);
                $purchaseType = (string) ($purchase->pu_type ?: (str_contains((string) $purchase->pu_vno, '/6B-') ? '2' : '1'));
                $current = (int) DB::table('purchase_voucher_sequences')
                    ->where('financial_year', $financialYear)
                    ->where('purchase_type', $purchaseType)
                    ->value('last_number');

                if ($number > $current) {
                    DB::table('purchase_voucher_sequences')->updateOrInsert(
                        ['financial_year' => $financialYear, 'purchase_type' => $purchaseType],
                        ['last_number' => $number]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_voucher_sequences');
    }

    private function voucherNumber(string $voucher): int
    {
        if (!preg_match('/(\d+)$/', $voucher, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }
};
