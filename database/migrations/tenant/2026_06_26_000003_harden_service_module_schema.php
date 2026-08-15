<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_voucher_sequences')) {
            Schema::create('service_voucher_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 9)->unique();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('services')) {
            DB::table('services')
                ->select('sv_vno', 'sv_date')
                ->whereNotNull('sv_vno')
                ->orderBy('sv_id')
                ->get()
                ->each(function ($service) {
                    $number = $this->voucherNumber((string) $service->sv_vno);

                    if ($number <= 0) {
                        return;
                    }

                    $financialYear = $this->financialYear($service->sv_date);
                    $current = (int) DB::table('service_voucher_sequences')
                        ->where('financial_year', $financialYear)
                        ->value('last_number');

                    if ($number > $current) {
                        DB::table('service_voucher_sequences')->updateOrInsert(
                            ['financial_year' => $financialYear],
                            ['last_number' => $number]
                        );
                    }
                });

            Schema::table('services', function (Blueprint $table) {
                if (!Schema::hasColumn('services', 'sv_eway_bill_no')) {
                    $table->string('sv_eway_bill_no')->nullable()->after('sv_customer');
                }
                if (!Schema::hasColumn('services', 'sv_state_code')) {
                    $table->string('sv_state_code', 10)->nullable()->after('sv_vehicle');
                }
                if (!Schema::hasColumn('services', 'sv_is_igst')) {
                    $table->boolean('sv_is_igst')->default(false)->after('sv_state_code');
                }
                if (!Schema::hasColumn('services', 'sv_remark')) {
                    $table->string('sv_remark', 1000)->nullable()->after('sv_is_igst');
                }
                if (!Schema::hasColumn('services', 'sv_due_days')) {
                    $table->unsignedInteger('sv_due_days')->nullable()->after('sv_balance');
                }
                if (!Schema::hasColumn('services', 'sv_due_date')) {
                    $table->date('sv_due_date')->nullable()->after('sv_due_days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                foreach (['sv_eway_bill_no', 'sv_state_code', 'sv_is_igst', 'sv_remark', 'sv_due_days', 'sv_due_date'] as $column) {
                    if (Schema::hasColumn('services', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('service_voucher_sequences');
    }

    private function voucherNumber(string $voucher): int
    {
        if (!preg_match('/(\d+)$/', $voucher, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function financialYear(string $date): string
    {
        $year = (int) date('Y', strtotime($date));
        $month = (int) date('m', strtotime($date));

        return $month >= 4
            ? $year . '-' . ($year + 1)
            : ($year - 1) . '-' . $year;
    }
};
