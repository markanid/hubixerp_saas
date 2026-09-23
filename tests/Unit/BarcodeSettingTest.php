<?php

namespace Tests\Unit;

use Modules\Settings\app\Models\BarcodeSetting;
use PHPUnit\Framework\TestCase;

class BarcodeSettingTest extends TestCase
{
    public function test_page_size_covers_a_full_row_without_adding_a_feed_gap(): void
    {
        $page = BarcodeSetting::pageSize([
            'label_width_mm' => 38,
            'label_height_mm' => 25,
            'label_columns' => 2,
            'label_column_gap_mm' => 2.5,
        ]);
        $this->assertSame(78.5, $page['width_mm']);
        $this->assertSame(25.0, $page['height_mm']);
        $this->assertSame(2, $page['columns']);
        $this->assertSame(60.0, BarcodeSetting::pageSize([
            'label_width_mm' => 60,
            'label_height_mm' => 40,
        ])['width_mm']);
    }

    public function test_common_roll_layout_uses_one_sticker_as_its_printer_page(): void
    {
        $page = BarcodeSetting::pageSize([
            'layout_mode' => BarcodeSetting::LAYOUT_COMMON,
            'label_width_mm' => 50,
            'label_height_mm' => 25,
        ]);

        $this->assertSame(50.0, $page['width_mm']);
        $this->assertSame(25.0, $page['height_mm']);
        $this->assertSame(1, $page['columns']);
        $this->assertSame(1, $page['capacity']);
    }

    public function test_purchase_price_mask_uses_the_configured_digit_key(): void
    {
        $this->assertSame(
            'KVMOULINEX.XU',
            BarcodeSetting::maskPurchasePrice('1234567890.05')
        );
    }

    public function test_purchase_price_format_preserves_punctuation_and_handles_empty_values(): void
    {
        $this->assertSame('-KVMO.UX', BarcodeSetting::maskPurchasePrice('-1234.50'));
        $this->assertSame('-', BarcodeSetting::maskPurchasePrice(null));
    }
}
