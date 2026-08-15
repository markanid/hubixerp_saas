<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Pos\app\Services\PaymentService;
use Modules\Sale\app\Services\SaleVoucherService;
use Tests\TestCase;

class PosServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
    }

    public function test_payment_service_accepts_split_tender_without_exceeding_payable(): void
    {
        Schema::create('banking', function (Blueprint $table) {
            $table->id('bk_id');
            $table->string('bk_bank');
        });
        DB::table('banking')->insert([
            ['bk_id' => 1, 'bk_bank' => 'Cash'],
            ['bk_id' => 2, 'bk_bank' => 'UPI'],
        ]);

        $payments = (new PaymentService())->normalize([
            ['method' => 'cash', 'banking_id' => 1, 'amount' => 150],
            ['method' => 'upi', 'banking_id' => 2, 'amount' => 50],
            ['method' => 'card', 'banking_id' => 2, 'amount' => 0],
        ], 200);

        $this->assertCount(2, $payments);
        $this->assertSame(200.0, (float) $payments->sum('amount'));
        $this->assertSame(1, (new PaymentService())->primaryBankingId($payments));
    }

    public function test_payment_service_rejects_overpayment(): void
    {
        Schema::create('banking', function (Blueprint $table) {
            $table->id('bk_id');
            $table->string('bk_bank');
        });
        DB::table('banking')->insert(['bk_id' => 1, 'bk_bank' => 'Cash']);

        $this->expectException(ValidationException::class);

        (new PaymentService())->normalize([
            ['method' => 'cash', 'banking_id' => 1, 'amount' => 201],
        ], 200);
    }

    public function test_sale_voucher_service_advances_from_locked_sequence_only(): void
    {
        Schema::create('sale_voucher_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 9);
            $table->string('sale_type');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['financial_year', 'sale_type']);
        });

        DB::table('sale_voucher_sequences')->insert([
            'financial_year' => '2026-2027',
            'sale_type' => '1',
            'last_number' => 7,
        ]);

        $service = new SaleVoucherService();

        $this->assertSame('S26-0008', $service->next('1', '2026-06-22'));
        $this->assertSame(8, (int) DB::table('sale_voucher_sequences')->value('last_number'));
    }
}
