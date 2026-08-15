<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_labels')) {
            $productCode = $this->productCodeDefinition();

            Schema::create('inventory_labels', function (Blueprint $table) use ($productCode) {
                $table->id();
                $table->string('product_id', $productCode['length']);
                $table->string('label_type', 20);
                $table->unsignedBigInteger('mrp_stock_lot_id')->nullable()->unique();
                $table->unsignedBigInteger('stock_batch_id')->nullable()->unique();
                $table->string('barcode', 32)->unique();
                $table->timestamps();

                $table->index(['product_id', 'label_type'], 'inventory_labels_product_type_index');
            });

            $this->matchProductCodeColumn('inventory_labels', 'product_id');

            Schema::table('inventory_labels', function (Blueprint $table) {
                $table->foreign('product_id')
                    ->references('product_code')
                    ->on('product')
                    ->cascadeOnDelete();
                $table->foreign('mrp_stock_lot_id')
                    ->references('id')
                    ->on('mrp_stock_lots')
                    ->cascadeOnDelete();
                $table->foreign('stock_batch_id')
                    ->references('id')
                    ->on('stock_batches')
                    ->cascadeOnDelete();
            });
        }

        $this->backfillMrpLabels();
        $this->backfillBatchLabels();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_labels');
    }

    private function backfillMrpLabels(): void
    {
        DB::table('mrp_stock_lots')
            ->where(function ($query) {
                $query->whereNull('purchase_voucher')
                    ->orWhere('purchase_voucher', '!=', 'OPENING-DEFICIT');
            })
            ->where(function ($query) {
                $query->where('quantity', '>', 0)
                    ->orWhere('available_quantity', '>', 0);
            })
            ->orderBy('id')
            ->chunkById(500, function ($lots) {
                foreach ($lots as $lot) {
                    DB::table('inventory_labels')->insertOrIgnore([
                        'product_id' => $lot->product_id,
                        'label_type' => 'mrp',
                        'mrp_stock_lot_id' => $lot->id,
                        'stock_batch_id' => null,
                        'barcode' => $this->barcodeFor('mrp', (int) $lot->id),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function backfillBatchLabels(): void
    {
        DB::table('stock_batches')
            ->where(function ($query) {
                $query->where('quantity', '>', 0)
                    ->orWhere('available_quantity', '>', 0);
            })
            ->orderBy('id')
            ->chunkById(500, function ($batches) {
                foreach ($batches as $batch) {
                    DB::table('inventory_labels')->insertOrIgnore([
                        'product_id' => $batch->product_id,
                        'label_type' => 'batch',
                        'mrp_stock_lot_id' => null,
                        'stock_batch_id' => $batch->id,
                        'barcode' => $this->barcodeFor('batch', (int) $batch->id),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function barcodeFor(string $type, int $sourceId): string
    {
        if ($sourceId > 999999999) {
            throw new RuntimeException('Inventory label source ID is too large for the barcode format.');
        }

        $prefix = $type === 'mrp' ? '81' : '82';
        $payload = $prefix . str_pad((string) $sourceId, 9, '0', STR_PAD_LEFT);

        return $payload . $this->luhnCheckDigit($payload);
    }

    private function luhnCheckDigit(string $payload): int
    {
        $sum = 0;
        $digits = str_split($payload . '0');
        $parity = count($digits) % 2;

        foreach ($digits as $index => $digit) {
            $value = (int) $digit;
            if ($index % 2 === $parity) {
                $value *= 2;
                if ($value > 9) {
                    $value -= 9;
                }
            }
            $sum += $value;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function productCodeDefinition(): array
    {
        $row = DB::selectOne(
            'select character_maximum_length as length, character_set_name as charset, collation_name as collation
             from information_schema.columns
             where table_schema = database() and table_name = ? and column_name = ?',
            ['product', 'product_code']
        );

        return [
            'length' => (int) ($row->length ?? 20),
            'charset' => $row->charset ?? null,
            'collation' => $row->collation ?? null,
        ];
    }

    private function matchProductCodeColumn(string $table, string $column): void
    {
        $definition = $this->productCodeDefinition();
        $charsetSql = $definition['charset'] ? " CHARACTER SET {$definition['charset']}" : '';
        $collationSql = $definition['collation'] ? " COLLATE {$definition['collation']}" : '';

        DB::statement(
            "ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR({$definition['length']}){$charsetSql}{$collationSql} NOT NULL"
        );
    }
};
