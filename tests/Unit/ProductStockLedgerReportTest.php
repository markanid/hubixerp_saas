<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\ProductStockLedgerReport;
use Tests\TestCase;

class ProductStockLedgerReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('stock_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('stock_item_id');
            $table->date('stock_date');
            $table->string('stock_type');
            $table->unsignedBigInteger('stock_ref_id')->nullable();
            $table->decimal('stock_in', 10, 2)->default(0);
            $table->decimal('stock_out', 10, 2)->default(0);
            $table->decimal('stock_balance', 10, 2)->default(0);
        });

        foreach ([
            'purchase' => ['pu_id', 'pu_vno', 'pu_status'],
            'sales' => ['sa_id', 'sa_vno', 'status'],
            'services' => ['sv_id', 'sv_vno', 'status'],
            'preturn' => ['pr_id', 'pr_vno', 'status'],
            'consume' => ['con_id', 'con_vno', 'status'],
            'estimation' => ['es_id', 'es_vno', 'status'],
            'product' => ['id', 'product_code', 'status'],
        ] as $name => [$id, $voucher, $status]) {
            Schema::create($name, function (Blueprint $table) use ($name, $id, $voucher, $status): void {
                $table->unsignedBigInteger($id)->primary();
                $table->string($voucher);
                $table->string($status)->default('1');
                if ($name === 'preturn') {
                    $table->string('pr_type');
                }
            });
        }
    }

    public function test_report_carries_prior_years_and_reconstructs_balances_in_date_and_id_order(): void
    {
        $this->movement('2025-03-31', 'INIT', 12.5, 0);
        $sale = $this->movement('2026-04-02', 's', 0, 3.25);
        $purchase = $this->movement('2026-04-01', 'p', 5, 0);
        $reversal = $this->movement('2026-04-02', 'sd', 3.25, 0);
        $replacement = $this->movement('2026-04-02', 's', 0, 4);
        $this->movement('2026-04-03', 'p', 100, 0);
        $this->movement('2026-04-01', 'p', 999, 0, 'OTHER');

        $report = (new ProductStockLedgerReport)->forProduct('PRD_001', '2026-04-01', '2026-04-02');

        $this->assertEquals(12.5, $report['opening']);
        $this->assertEquals(8.25, $report['total_in']);
        $this->assertEquals(7.25, $report['total_out']);
        $this->assertEquals(13.5, $report['closing']);
        $this->assertSame([$purchase, $sale, $reversal, $replacement], $report['rows']->pluck('id')->all());
        $this->assertEquals([12.5, 17.5, 14.25, 17.5], $report['rows']->pluck('opening')->all());
        $this->assertEquals([17.5, 14.25, 17.5, 13.5], $report['rows']->pluck('closing')->all());
        $this->assertSame('Sale reversal', $report['rows'][2]['transaction']);
        // Reading the report must not repair or otherwise modify stored business data.
        $this->assertSame(7, DB::table('stock_ledgers')->count());
        $this->assertEquals(9999, DB::table('stock_ledgers')->where('id', $sale)->value('stock_balance'));
    }

    public function test_empty_period_preserves_opening_and_an_unused_product_has_zero_balances(): void
    {
        $this->movement('2026-03-31', 'p', 10, 0);
        $service = new ProductStockLedgerReport;
        $report = $service->forProduct('PRD_001', '2026-04-01', '2026-04-30');

        $this->assertTrue($report['rows']->isEmpty());
        $this->assertEquals(10, $report['opening']);
        $this->assertEquals(10, $report['closing']);
        $this->assertEquals(0, $report['total_in']);
        $this->assertEquals(0, $report['total_out']);

        $unused = $service->forProduct('UNUSED', '2026-04-01', '2026-04-30');
        $this->assertEquals(0, $unused['opening']);
        $this->assertEquals(0, $unused['closing']);
    }

    public function test_report_renders_transaction_columns_units_and_negative_balances(): void
    {
        $this->movement('2026-04-01', 'sv', 0, 2.5);
        $this->movement('2026-04-01', '<legacy>', 0, 0);
        $report = (new ProductStockLedgerReport)->forProduct('PRD_001', '2026-04-01', '2026-04-01');
        $product = (new Product)->forceFill(['id' => 1, 'product_code' => 'PRD_001', 'unit' => 'Packet', 'uqty' => 10]);

        $html = view('product::products.partials.stock-ledger', [
            'product' => $product,
            'stockLedger' => $report,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        foreach (['Opening Stock', 'Transaction Date', 'Service sale', 'Qty In', 'Qty Out', 'Closing Stock', '1 Packet = 10.00 No.s.', '-2.50', '&lt;legacy&gt;'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<legacy>', $html);
        $this->assertStringContainsString('text-danger', $html);
    }

    public function test_vouchers_link_to_the_correct_source_and_cancelled_document_views(): void
    {
        DB::table('purchase')->insert(['pu_id' => 1, 'pu_vno' => 'P26-0001', 'pu_status' => '0']);
        DB::table('sales')->insert(['sa_id' => 1, 'sa_vno' => 'S26-0001']);
        DB::table('services')->insert(['sv_id' => 1, 'sv_vno' => 'SV26-0001']);
        DB::table('preturn')->insert([
            ['pr_id' => 1, 'pr_vno' => 'SR26-0001', 'pr_type' => '2', 'status' => '1'],
            ['pr_id' => 2, 'pr_vno' => 'PR26-0001', 'pr_type' => '1', 'status' => '0'],
        ]);
        DB::table('consume')->insert(['con_id' => 1, 'con_vno' => 'C26-0001']);
        DB::table('estimation')->insert(['es_id' => 1, 'es_vno' => 'ES26-0001']);
        DB::table('product')->insert(['id' => 1, 'product_code' => 'PRD_001']);

        $cases = [
            ['p', 1, 'P26-0001', 'purchases.showCancelled'],
            ['pd', 1, 'P26-0001', 'purchases.showCancelled'],
            ['s', 1, 'S26-0001', 'sales.show'],
            ['sd', 1, 'S26-0001', 'sales.show'],
            ['sv', 1, 'SV26-0001', 'services.show'],
            ['sr', 1, 'SR26-0001', 'sales-returns.show'],
            ['pr', 2, 'PR26-0001', 'purchase-returns.showCancelled'],
            ['c', 1, 'C26-0001', 'consumptions.show'],
            ['es', 1, 'ES26-0001', 'estimations.show'],
            ['ADJUST', 1, 'PRD_001', 'products.show'],
        ];
        foreach ($cases as [$type, $id]) {
            $this->movement('2026-04-01', $type, 1, 0, reference: $id);
        }

        $report = (new ProductStockLedgerReport)->forProduct('PRD_001', '2026-04-01', '2026-04-01');
        foreach ($cases as $index => [$type, $id, $voucher, $route]) {
            $this->assertSame($voucher, $report['rows'][$index]['voucher']);
            $this->assertSame(route($route, $id), $report['rows'][$index]['voucher_url']);
        }

        $html = view('product::products.partials.stock-ledger', [
            'product' => (new Product)->forceFill(['id' => 1, 'uqty' => 1]),
            'stockLedger' => $report,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $this->assertStringContainsString('Voucher Number', $html);
        $this->assertStringContainsString('href="'.route('sales.show', 1).'">S26-0001</a>', $html);
    }

    public function test_missing_sources_and_wrong_return_types_have_no_link(): void
    {
        DB::table('preturn')->insert(['pr_id' => 1, 'pr_vno' => 'SR26-0001', 'pr_type' => '2']);
        $this->movement('2026-04-01', 's', 0, 1, reference: 999);
        $this->movement('2026-04-01', 'pr', 0, 1);
        $this->movement('2026-04-01', 's', 0, 1, reference: null);

        $report = (new ProductStockLedgerReport)->forProduct('PRD_001', '2026-04-01', '2026-04-01');
        $this->assertSame('#999 (unavailable)', $report['rows'][0]['voucher']);
        $this->assertSame('#1 (unavailable)', $report['rows'][1]['voucher']);
        $this->assertSame('—', $report['rows'][2]['voucher']);
        $this->assertSame([null, null, null], $report['rows']->pluck('voucher_url')->all());
    }

    private function movement(string $date, string $type, float $in, float $out, string $product = 'PRD_001', ?int $reference = 1): int
    {
        return DB::table('stock_ledgers')->insertGetId([
            'stock_item_id' => $product,
            'stock_date' => $date,
            'stock_type' => $type,
            'stock_ref_id' => $reference,
            'stock_in' => $in,
            'stock_out' => $out,
            'stock_balance' => 9999,
        ]);
    }
}
