<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Settings\app\Http\Controllers\CompanyController;
use Tests\TestCase;

class CompanyPrintSettingsUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_mode')->default('standard');
            $table->unsignedSmallInteger('expiry_alert_days')->default(30);
            $table->string('currency_code')->default('INR');
            $table->string('currency_symbol')->nullable();
            $table->string('tax_type')->default('gst');
            $table->string('gst_scheme')->default('regular');
        });
        Schema::create('sale_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('value')->default(false);
            $table->timestamps();
        });
        Schema::create('estimation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('value')->default(false);
            $table->timestamps();
        });
        Schema::create('barcode_settings', function (Blueprint $table) {
            $table->id();
            $table->string('context')->unique();
            $table->json('selected_fields')->nullable();
            $table->unsignedSmallInteger('label_width_mm')->default(80);
            $table->unsignedSmallInteger('label_height_mm')->default(40);
            $table->unsignedSmallInteger('label_margin_mm')->default(2);
            $table->boolean('auto_print')->default(true);
            $table->boolean('mask_purchase_price')->default(false);
            $table->timestamps();
        });
        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->unique();
            $table->string('print_method')->default('browser');
            $table->string('paper_size')->default('A4');
            $table->string('orientation')->default('portrait');
            $table->unsignedSmallInteger('scale')->default(100);
            $table->unsignedSmallInteger('margin_mm')->default(10);
            $table->boolean('auto_print')->default(true);
            $table->string('header_mode')->default('full');
            $table->string('printer_name')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_16_000001_add_barcode_label_columns.php'))->up();

        DB::table('company')->insert([
            'id' => 1,
            'inventory_mode' => 'standard',
            'expiry_alert_days' => 30,
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'tax_type' => 'gst',
            'gst_scheme' => 'regular',
        ]);
    }

    public function test_service_header_mode_and_barcode_layout_are_saved(): void
    {
        $request = Request::create('/company/settings/update', 'POST', [
            'inventory_mode' => 'standard',
            'expiry_alert_days' => 30,
            'currency_code' => 'INR',
            'tax_type' => 'gst',
            'gst_scheme' => 'regular',
            'barcode_fields' => ['product_code', 'product_name'],
            'label_width_mm' => 62,
            'label_height_mm' => 31,
            'label_margin_mm' => 3,
            'label_columns' => 2,
            'label_column_gap_mm' => 2.5,
            'mask_purchase_price' => 1,
            'print_settings' => [
                'service' => [
                    'print_method' => 'browser',
                    'paper_size' => 'A4',
                    'orientation' => 'portrait',
                    'scale' => 100,
                    'margin_mm' => 10,
                    'auto_print' => 1,
                    'header_mode' => 'compact',
                ],
            ],
        ]);

        app(CompanyController::class)->updateSettings(
            $request,
            $this->mock(MrpInventoryService::class)
        );

        $this->assertDatabaseHas('print_settings', [
            'document_type' => 'service',
            'header_mode' => 'compact',
        ]);
        $this->assertDatabaseHas('barcode_settings', [
            'context' => 'inventory',
            'label_width_mm' => 62,
            'label_height_mm' => 31,
            'label_margin_mm' => 3,
            'label_columns' => 2,
            'label_column_gap_mm' => 2.5,
            'mask_purchase_price' => 1,
        ]);
    }
}
