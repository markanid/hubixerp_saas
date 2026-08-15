<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mrp_stock_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('mrp_stock_lots', 'sale_price')) {
                $table->decimal('sale_price', 15, 2)->nullable()->after('mrp');
            }
            if (!Schema::hasColumn('mrp_stock_lots', 'margin_percentage')) {
                $table->decimal('margin_percentage', 10, 2)->nullable()->after('sale_price');
            }
            if (!Schema::hasColumn('mrp_stock_lots', 'margin_amount')) {
                $table->decimal('margin_amount', 15, 2)->nullable()->after('margin_percentage');
            }
        });

        if (Schema::hasTable('purchase_detail')) {
            Schema::table('purchase_detail', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_detail', 'lot_sale_price')) {
                    $table->decimal('lot_sale_price', 15, 2)->nullable()->after('batch_mrp');
                }
                if (!Schema::hasColumn('purchase_detail', 'lot_margin_percentage')) {
                    $table->decimal('lot_margin_percentage', 10, 2)->nullable()->after('lot_sale_price');
                }
                if (!Schema::hasColumn('purchase_detail', 'lot_margin_amount')) {
                    $table->decimal('lot_margin_amount', 15, 2)->nullable()->after('lot_margin_percentage');
                }
            });
        }

        DB::table('mrp_stock_lots')->orderBy('id')->chunkById(200, function ($lots) {
            $products = DB::table('product')
                ->whereIn('product_code', $lots->pluck('product_id')->unique()->all())
                ->get(['product_code', 'price'])
                ->keyBy('product_code');

            foreach ($lots as $lot) {
                $mrp = round((float) $lot->mrp, 2);
                $productPrice = (float) ($products->get($lot->product_id)->price ?? $mrp);
                $salePrice = round(max(0, min($mrp, $productPrice)), 2);
                $marginAmount = round(max(0, $mrp - $salePrice), 2);
                $marginPercentage = $mrp > 0 ? round(($marginAmount / $mrp) * 100, 2) : 0;

                DB::table('mrp_stock_lots')->where('id', $lot->id)->update([
                    'sale_price' => $salePrice,
                    'margin_percentage' => $marginPercentage,
                    'margin_amount' => $marginAmount,
                ]);

                if ($lot->purchase_detail_id && Schema::hasTable('purchase_detail')) {
                    DB::table('purchase_detail')->where('pud_id', $lot->purchase_detail_id)->update([
                        'lot_sale_price' => $salePrice,
                        'lot_margin_percentage' => $marginPercentage,
                        'lot_margin_amount' => $marginAmount,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_detail')) {
            Schema::table('purchase_detail', function (Blueprint $table) {
                $columns = array_values(array_filter(
                    ['lot_sale_price', 'lot_margin_percentage', 'lot_margin_amount'],
                    fn (string $column) => Schema::hasColumn('purchase_detail', $column)
                ));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::table('mrp_stock_lots', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['sale_price', 'margin_percentage', 'margin_amount'],
                fn (string $column) => Schema::hasColumn('mrp_stock_lots', $column)
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
