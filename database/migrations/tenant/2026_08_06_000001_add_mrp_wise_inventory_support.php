<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $productCode = $this->productCodeDefinition();

        if (!Schema::hasTable('mrp_stock_lots')) {
            Schema::create('mrp_stock_lots', function (Blueprint $table) use ($productCode) {
                $table->id();
                $table->string('product_id', $productCode['length']);
                $table->unsignedBigInteger('purchase_id')->nullable();
                $table->unsignedBigInteger('purchase_detail_id')->nullable();
                $table->string('purchase_voucher', 100)->nullable();
                $table->date('purchase_date');
                $table->decimal('purchase_rate', 15, 2)->default(0);
                $table->decimal('mrp', 15, 2);
                $table->decimal('quantity', 15, 2)->default(0);
                $table->decimal('available_quantity', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['product_id', 'available_quantity', 'purchase_date'], 'mrp_lots_available_index');
                $table->index(['purchase_id', 'purchase_detail_id'], 'mrp_lots_purchase_index');
            });
        }
        $this->matchProductCodeColumn('mrp_stock_lots', 'product_id');
        $this->addForeignIfMissing('mrp_stock_lots', 'mrp_stock_lots_product_id_foreign', 'product_id', 'product', 'product_code', 'cascade');

        if (!Schema::hasTable('mrp_stock_movements')) {
            Schema::create('mrp_stock_movements', function (Blueprint $table) use ($productCode) {
                $table->id();
                $table->foreignId('mrp_stock_lot_id')->constrained('mrp_stock_lots')->cascadeOnDelete();
                $table->string('product_id', $productCode['length']);
                $table->string('movement_type', 30);
                $table->string('reference_type', 50);
                $table->unsignedBigInteger('reference_id');
                $table->unsignedBigInteger('reference_detail_id')->nullable();
                $table->date('movement_date');
                $table->decimal('quantity_in', 15, 2)->default(0);
                $table->decimal('quantity_out', 15, 2)->default(0);
                $table->decimal('balance_after', 15, 2)->default(0);
                $table->decimal('purchase_rate', 15, 2)->default(0);
                $table->decimal('mrp', 15, 2)->default(0);
                $table->decimal('sale_rate', 15, 2)->default(0);
                $table->foreignId('reversal_of_id')->nullable()->constrained('mrp_stock_movements')->nullOnDelete();
                $table->timestamps();

                $table->index(['reference_type', 'reference_id'], 'mrp_movements_reference_index');
                $table->index(['reference_detail_id', 'movement_type'], 'mrp_movements_detail_index');
                $table->index(['product_id', 'movement_date'], 'mrp_movements_product_date_index');
            });
        }
        $this->matchProductCodeColumn('mrp_stock_movements', 'product_id');
        $this->addForeignIfMissing('mrp_stock_movements', 'mrp_stock_movements_product_id_foreign', 'product_id', 'product', 'product_code', 'cascade');

        foreach (['sale_details', 'preturn_details', 'service_details', 'estimation_details', 'consume_detail'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasColumn($table, 'mrp_stock_lot_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('mrp_stock_lot_id')->nullable()->index());
            }
            $this->addForeignIfMissing($table, "{$table}_mrp_stock_lot_id_foreign", 'mrp_stock_lot_id', 'mrp_stock_lots', 'id', 'set null');

            if (!Schema::hasColumn($table, 'stock_mrp')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->decimal('stock_mrp', 15, 2)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['sale_details', 'preturn_details', 'service_details', 'estimation_details', 'consume_detail'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'mrp_stock_lot_id')) {
                $this->dropForeignIfPresent($table, "{$table}_mrp_stock_lot_id_foreign");
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('mrp_stock_lot_id'));
            }
            if (Schema::hasColumn($table, 'stock_mrp')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('stock_mrp'));
            }
        }

        Schema::dropIfExists('mrp_stock_movements');
        Schema::dropIfExists('mrp_stock_lots');
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

    private function addForeignIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referencesTable,
        string $referencesColumn,
        string $onDelete
    ): void {
        $exists = DB::selectOne(
            'select constraint_name from information_schema.key_column_usage
             where table_schema = database() and table_name = ? and constraint_name = ?',
            [$table, $constraint]
        );

        if ($exists) {
            return;
        }

        $deleteSql = $onDelete === 'cascade' ? 'ON DELETE CASCADE' : 'ON DELETE SET NULL';
        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencesTable}` (`{$referencesColumn}`) {$deleteSql}"
        );
    }

    private function dropForeignIfPresent(string $table, string $constraint): void
    {
        $exists = DB::selectOne(
            'select constraint_name from information_schema.table_constraints
             where table_schema = database() and table_name = ? and constraint_name = ?',
            [$table, $constraint]
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
