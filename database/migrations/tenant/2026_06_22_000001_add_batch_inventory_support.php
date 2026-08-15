<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company', 'inventory_mode')) {
            Schema::table('company', fn (Blueprint $table) => $table->string('inventory_mode', 20)->default('standard'));
        }
        if (!Schema::hasColumn('company', 'expiry_alert_days')) {
            Schema::table('company', fn (Blueprint $table) => $table->unsignedInteger('expiry_alert_days')->default(30));
        }

        if (!Schema::hasColumn('product', 'is_batch_managed')) {
            Schema::table('product', fn (Blueprint $table) => $table->boolean('is_batch_managed')->default(false)->index());
        }

        $productCode = $this->productCodeDefinition();
        $this->addIndexIfMissing('product', 'product_code', 'product_product_code_batch_fk_index');

        if (!Schema::hasTable('stock_batches')) {
            Schema::create('stock_batches', function (Blueprint $table) use ($productCode) {
                $table->id();
                // HubixERP uses product.product_code as its inventory identity.
                $table->string('product_id', $productCode['length']);
                $table->string('batch_no', 100);
                $table->date('expiry_date')->nullable();
                $table->decimal('purchase_rate', 15, 2)->default(0);
                $table->decimal('mrp', 15, 2)->default(0);
                $table->decimal('quantity', 15, 2)->default(0);
                $table->decimal('available_quantity', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'batch_no'], 'stock_batches_product_batch_unique');
                $table->index(['product_id', 'available_quantity', 'expiry_date'], 'stock_batches_fifo_index');
                $table->index(['expiry_date', 'available_quantity'], 'stock_batches_expiry_index');
            });
        }
        $this->matchProductCodeColumn('stock_batches', 'product_id', false);
        $this->addForeignIfMissing('stock_batches', 'stock_batches_product_id_foreign', 'product_id', 'product', 'product_code', 'cascade');

        if (!Schema::hasTable('batch_movements')) {
            Schema::create('batch_movements', function (Blueprint $table) use ($productCode) {
                $table->id();
                $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnDelete();
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
                $table->decimal('sale_rate', 15, 2)->default(0);
                $table->foreignId('reversal_of_id')->nullable()->constrained('batch_movements')->nullOnDelete();
                $table->timestamps();

                $table->index(['reference_type', 'reference_id'], 'batch_movements_reference_index');
                $table->index(['reference_detail_id', 'movement_type'], 'batch_movements_detail_index');
                $table->index(['product_id', 'movement_date'], 'batch_movements_product_date_index');
            });
        }
        $this->matchProductCodeColumn('batch_movements', 'product_id', false);
        $this->addForeignIfMissing('batch_movements', 'batch_movements_product_id_foreign', 'product_id', 'product', 'product_code', 'cascade');

        if (!Schema::hasColumn('purchase_detail', 'batch_no')) {
            Schema::table('purchase_detail', fn (Blueprint $table) => $table->string('batch_no', 100)->nullable()->after('pud_itemid'));
        }
        if (!Schema::hasColumn('purchase_detail', 'expiry_date')) {
            Schema::table('purchase_detail', fn (Blueprint $table) => $table->date('expiry_date')->nullable()->after('batch_no'));
        }
        if (!Schema::hasColumn('purchase_detail', 'batch_mrp')) {
            Schema::table('purchase_detail', fn (Blueprint $table) => $table->decimal('batch_mrp', 15, 2)->nullable()->after('expiry_date'));
        }

        if (!Schema::hasColumn('sale_details', 'stock_batch_id')) {
            Schema::table('sale_details', fn (Blueprint $table) => $table->foreignId('stock_batch_id')->nullable()->after('sad_itemid'));
        }
        $this->addForeignIfMissing('sale_details', 'sale_details_stock_batch_id_foreign', 'stock_batch_id', 'stock_batches', 'id', 'set null');
        if (!Schema::hasColumn('sale_details', 'batch_no')) {
            Schema::table('sale_details', fn (Blueprint $table) => $table->string('batch_no', 100)->nullable()->after('stock_batch_id'));
        }
        if (!Schema::hasColumn('sale_details', 'expiry_date')) {
            Schema::table('sale_details', fn (Blueprint $table) => $table->date('expiry_date')->nullable()->after('batch_no'));
        }

        if (!Schema::hasColumn('preturn_details', 'stock_batch_id')) {
            Schema::table('preturn_details', fn (Blueprint $table) => $table->foreignId('stock_batch_id')->nullable()->after('prd_itemid'));
        }
        $this->addForeignIfMissing('preturn_details', 'preturn_details_stock_batch_id_foreign', 'stock_batch_id', 'stock_batches', 'id', 'set null');
        if (!Schema::hasColumn('preturn_details', 'batch_no')) {
            Schema::table('preturn_details', fn (Blueprint $table) => $table->string('batch_no', 100)->nullable()->after('stock_batch_id'));
        }

        if (Schema::hasTable('service_details')) {
            if (!Schema::hasColumn('service_details', 'stock_batch_id')) {
                Schema::table('service_details', fn (Blueprint $table) => $table->foreignId('stock_batch_id')->nullable()->after('svd_itemid'));
            }
            $this->addForeignIfMissing('service_details', 'service_details_stock_batch_id_foreign', 'stock_batch_id', 'stock_batches', 'id', 'set null');
            if (!Schema::hasColumn('service_details', 'batch_no')) {
                Schema::table('service_details', fn (Blueprint $table) => $table->string('batch_no', 100)->nullable()->after('stock_batch_id'));
            }
            if (!Schema::hasColumn('service_details', 'expiry_date')) {
                Schema::table('service_details', fn (Blueprint $table) => $table->date('expiry_date')->nullable()->after('batch_no'));
            }
        }
    }

    public function down(): void
    {
        Schema::table('preturn_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_batch_id');
            $table->dropColumn('batch_no');
        });
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_batch_id');
            $table->dropColumn(['batch_no', 'expiry_date']);
        });
        Schema::table('purchase_detail', function (Blueprint $table) {
            $table->dropColumn(['batch_no', 'expiry_date', 'batch_mrp']);
        });
        Schema::dropIfExists('batch_movements');
        Schema::dropIfExists('stock_batches');
        Schema::table('product', fn (Blueprint $table) => $table->dropColumn('is_batch_managed'));
        Schema::table('company', fn (Blueprint $table) => $table->dropColumn(['inventory_mode', 'expiry_alert_days']));
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

    private function matchProductCodeColumn(string $table, string $column, bool $nullable): void
    {
        $definition = $this->productCodeDefinition();
        $nullSql = $nullable ? 'NULL' : 'NOT NULL';
        $charsetSql = $definition['charset'] ? " CHARACTER SET {$definition['charset']}" : '';
        $collationSql = $definition['collation'] ? " COLLATE {$definition['collation']}" : '';

        DB::statement(
            "ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR({$definition['length']}){$charsetSql}{$collationSql} {$nullSql}"
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
            'select constraint_name
             from information_schema.key_column_usage
             where table_schema = database() and table_name = ? and constraint_name = ?',
            [$table, $constraint]
        );

        if ($exists) {
            return;
        }

        $deleteSql = match ($onDelete) {
            'cascade' => 'ON DELETE CASCADE',
            'set null' => 'ON DELETE SET NULL',
            default => '',
        };

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencesTable}` (`{$referencesColumn}`) {$deleteSql}"
        );
    }

    private function addIndexIfMissing(string $table, string $column, string $indexName): void
    {
        $exists = DB::selectOne(
            'select index_name
             from information_schema.statistics
             where table_schema = database() and table_name = ? and column_name = ?
             limit 1',
            [$table, $column]
        );

        if ($exists) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
    }
};
