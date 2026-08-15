<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expence')) {
            return;
        }

        DB::table('expence')
            ->where('status', '0')
            ->orderBy('id')
            ->chunkById(100, function ($expenses) {
                $ids = $expenses->pluck('id')->all();

                if (Schema::hasTable('ledgerbook')) {
                    DB::table('ledgerbook')
                        ->where('lb_type', 'exp')
                        ->whereIn('lb_vid', $ids)
                        ->delete();
                }

                foreach ($expenses as $expense) {
                    if (!empty($expense->docum)) {
                        Storage::disk('public')->delete('expense_logos/' . $expense->docum);
                    }
                }

                DB::table('expence')->whereIn('id', $ids)->delete();
            });
    }

    public function down(): void
    {
        // Permanent cleanup cannot be reversed.
    }
};
