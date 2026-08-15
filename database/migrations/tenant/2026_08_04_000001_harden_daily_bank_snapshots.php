<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('daily_bank')
            ->whereNull('financial_year')
            ->orderBy('db_id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $date = strtotime($row->db_date);
                    $year = (int) date('Y', $date);
                    $month = (int) date('m', $date);
                    $financialYear = $month >= 4
                        ? $year.'-'.($year + 1)
                        : ($year - 1).'-'.$year;

                    DB::table('daily_bank')
                        ->where('db_id', $row->db_id)
                        ->update(['financial_year' => $financialYear]);
                }
            }, 'db_id');

        $duplicates = DB::table('daily_bank')
            ->whereNotNull('db_bank')
            ->groupBy('db_bank', 'db_date')
            ->havingRaw('COUNT(*) > 1')
            ->select('db_bank', 'db_date', DB::raw('MAX(db_id) as keep_id'))
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('daily_bank')
                ->where('db_bank', $duplicate->db_bank)
                ->where('db_date', $duplicate->db_date)
                ->where('db_id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('daily_bank', function (Blueprint $table): void {
            $table->decimal('db_amount', 18, 2)->change();
            $table->string('financial_year', 9)->nullable(false)->change();
            $table->unique(['db_bank', 'db_date'], 'daily_bank_bank_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_bank', function (Blueprint $table): void {
            $table->dropUnique('daily_bank_bank_date_unique');
            $table->decimal('db_amount', 10, 2)->change();
            $table->string('financial_year', 9)->nullable()->change();
        });
    }
};
