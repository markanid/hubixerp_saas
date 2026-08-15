<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('print_settings')) {
            Schema::create('print_settings', function (Blueprint $table) {
                $table->id();
                $table->string('document_type')->unique();
                $table->string('print_method', 20)->default('browser');
                $table->string('paper_size', 20)->default('A4');
                $table->string('orientation', 20)->default('portrait');
                $table->unsignedSmallInteger('scale')->default(100);
                $table->unsignedSmallInteger('margin_mm')->default(10);
                $table->boolean('auto_print')->default(true);
                $table->string('printer_name')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        foreach ($this->defaults() as $documentType => $settings) {
            DB::table('print_settings')->updateOrInsert(
                ['document_type' => $documentType],
                array_merge($settings, ['updated_at' => $now, 'created_at' => $now])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_settings');
    }

    private function defaults(): array
    {
        return [
            'sale' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
            'purchase' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
            'estimation' => ['print_method' => 'browser', 'paper_size' => 'A5', 'orientation' => 'portrait', 'scale' => 90, 'margin_mm' => 6, 'auto_print' => true],
            'service' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
            'sale_return' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
            'purchase_return' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
            'consumption' => ['print_method' => 'browser', 'paper_size' => 'A4', 'orientation' => 'portrait', 'scale' => 100, 'margin_mm' => 10, 'auto_print' => true],
        ];
    }
};
