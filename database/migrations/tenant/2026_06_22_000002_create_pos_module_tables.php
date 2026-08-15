<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureSalesCompatibilityColumns();
        $this->seedSaleSequenceFromExistingInvoices();

        if (!Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('financial_year', 9);
                $table->string('counter_code', 50);
                $table->decimal('opening_cash', 15, 2)->default(0);
                $table->decimal('expected_cash', 15, 2)->default(0);
                $table->decimal('closing_cash', 15, 2)->nullable();
                $table->decimal('cash_difference', 15, 2)->nullable();
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->string('status', 20)->default('open');
                $table->text('closing_note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['counter_code', 'status']);
            });
        }

        if (!Schema::hasTable('pos_held_bills')) {
            Schema::create('pos_held_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('hold_no', 40)->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->json('cart');
                $table->json('payments')->nullable();
                $table->decimal('amount_payable', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['pos_session_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('pos_sale_payments')) {
            Schema::create('pos_sale_payments', function (Blueprint $table) {
                $table->id();
                $this->addMatchingIntegerColumn($table, 'sale_id', 'sales', 'sa_id');
                $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
                $table->unsignedBigInteger('banking_id');
                $table->string('method', 20);
                $table->decimal('amount', 15, 2);
                $table->string('reference_no')->nullable();
                $table->timestamps();

                $table->index(['sale_id', 'method']);
                $table->index(['pos_session_id', 'method']);
            });
            $this->addForeignIfMissing('pos_sale_payments', 'pos_sale_payments_sale_id_foreign', 'sale_id', 'sales', 'sa_id', 'cascade');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_payments');
        Schema::dropIfExists('pos_held_bills');
        Schema::dropIfExists('pos_sessions');
    }

    private function ensureSalesCompatibilityColumns(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('sa_id');
            }
            if (!Schema::hasColumn('sales', 'financial_year_id')) {
                $table->string('financial_year_id', 20)->nullable()->after('financial_year');
            }
            if (!Schema::hasColumn('sales', 'invoice_no')) {
                $table->string('invoice_no')->nullable()->after('sa_vno');
            }
            if (!Schema::hasColumn('sales', 'pos_session_id')) {
                $table->unsignedBigInteger('pos_session_id')->nullable()->after('sa_user');
            }
            if (!Schema::hasColumn('sales', 'sale_origin')) {
                $table->string('sale_origin', 20)->default('sales')->after('pos_session_id');
            }
        });

        $companyId = DB::table('company')->value('id');
        DB::table('sales')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('sales')->whereNull('invoice_no')->update(['invoice_no' => DB::raw('sa_vno')]);
        DB::table('sales')->whereNull('financial_year_id')->update(['financial_year_id' => DB::raw('financial_year')]);

        if (!$this->indexExists('sales', 'sales_company_fy_invoice_unique')) {
            DB::statement('ALTER TABLE `sales` ADD UNIQUE `sales_company_fy_invoice_unique` (`company_id`, `financial_year_id`, `invoice_no`)');
        }
    }

    private function seedSaleSequenceFromExistingInvoices(): void
    {
        if (!Schema::hasTable('sale_voucher_sequences') || !Schema::hasTable('sales')) {
            return;
        }

        $rows = DB::table('sales')
            ->select('financial_year', 'sa_type', DB::raw("MAX(CAST(SUBSTRING_INDEX(sa_vno, '-', -1) AS UNSIGNED)) as max_no"))
            ->whereNotNull('financial_year')
            ->whereNotNull('sa_vno')
            ->groupBy('financial_year', 'sa_type')
            ->get();

        foreach ($rows as $row) {
            DB::table('sale_voucher_sequences')->updateOrInsert(
                ['financial_year' => $row->financial_year, 'sale_type' => $row->sa_type],
                ['last_number' => max((int) $row->max_no, (int) DB::table('sale_voucher_sequences')
                    ->where('financial_year', $row->financial_year)
                    ->where('sale_type', $row->sa_type)
                    ->value('last_number'))]
            );
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::selectOne(
            'select index_name from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$table, $index]
        );
    }

    private function addMatchingIntegerColumn(Blueprint $table, string $column, string $sourceTable, string $sourceColumn): void
    {
        $definition = $this->columnDefinition($sourceTable, $sourceColumn);
        $type = strtolower((string) ($definition->column_type ?? 'bigint unsigned'));
        $unsigned = str_contains($type, 'unsigned');

        if (str_starts_with($type, 'bigint')) {
            $columnDefinition = $table->bigInteger($column);
        } elseif (str_starts_with($type, 'mediumint')) {
            $columnDefinition = $table->mediumInteger($column);
        } elseif (str_starts_with($type, 'smallint')) {
            $columnDefinition = $table->smallInteger($column);
        } elseif (str_starts_with($type, 'tinyint')) {
            $columnDefinition = $table->tinyInteger($column);
        } else {
            $columnDefinition = $table->integer($column);
        }

        if ($unsigned) {
            $columnDefinition->unsigned();
        }
    }

    private function columnDefinition(string $table, string $column): ?object
    {
        return DB::selectOne(
            'select column_type from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
            [$table, $column]
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
            'select constraint_name from information_schema.key_column_usage where table_schema = database() and table_name = ? and constraint_name = ?',
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
};
