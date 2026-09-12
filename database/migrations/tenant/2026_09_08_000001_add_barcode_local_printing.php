<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('document_id');
        });

        if (Schema::hasTable('print_settings')) {
            DB::table('print_settings')->updateOrInsert(
                ['document_type' => 'barcode'],
                [
                    'print_method' => 'browser',
                    'paper_size' => 'A4',
                    'orientation' => 'portrait',
                    'scale' => 100,
                    'margin_mm' => 0,
                    'auto_print' => true,
                    'printer_name' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('print_settings')) {
            DB::table('print_settings')->where('document_type', 'barcode')->delete();
        }

        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
