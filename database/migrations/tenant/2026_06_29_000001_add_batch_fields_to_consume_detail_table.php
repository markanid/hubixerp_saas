<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('consume_detail')) {
            return;
        }

        if (!Schema::hasColumn('consume_detail', 'stock_batch_id')) {
            Schema::table('consume_detail', function (Blueprint $table) {
                $table->foreignId('stock_batch_id')->nullable()->after('cond_itemid');
            });
        }

        $this->addForeignIfMissing('consume_detail', 'consume_detail_stock_batch_id_foreign', 'stock_batch_id', 'stock_batches', 'id');

        if (!Schema::hasColumn('consume_detail', 'batch_no')) {
            Schema::table('consume_detail', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('stock_batch_id');
            });
        }

        if (!Schema::hasColumn('consume_detail', 'expiry_date')) {
            Schema::table('consume_detail', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('batch_no');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('consume_detail')) {
            return;
        }

        if (Schema::hasColumn('consume_detail', 'stock_batch_id')) {
            $this->dropForeignIfExists('consume_detail', 'consume_detail_stock_batch_id_foreign');
        }

        Schema::table('consume_detail', function (Blueprint $table) {
            $columns = collect(['stock_batch_id', 'batch_no', 'expiry_date'])
                ->filter(fn ($column) => Schema::hasColumn('consume_detail', $column))
                ->all();

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }

    private function addForeignIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referencesTable,
        string $referencesColumn
    ): void {
        if (!Schema::hasTable($referencesTable) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $exists = DB::selectOne(
            'select constraint_name
             from information_schema.key_column_usage
             where table_schema = database() and table_name = ? and constraint_name = ?',
            [$table, $constraint]
        );

        if (!$exists) {
            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencesTable}` (`{$referencesColumn}`) ON DELETE SET NULL"
            );
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $exists = DB::selectOne(
            'select constraint_name
             from information_schema.key_column_usage
             where table_schema = database() and table_name = ? and constraint_name = ?',
            [$table, $constraint]
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
