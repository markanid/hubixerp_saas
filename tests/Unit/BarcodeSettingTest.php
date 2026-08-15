<?php

namespace Tests\Unit;

use Modules\Settings\app\Models\BarcodeSetting;
use PHPUnit\Framework\TestCase;

class BarcodeSettingTest extends TestCase
{
    public function test_purchase_price_mask_uses_the_configured_digit_key(): void
    {
        $this->assertSame(
            'KVMOULIN6X.XU',
            BarcodeSetting::maskPurchasePrice('1234567890.05')
        );
    }

    public function test_purchase_price_format_preserves_punctuation_and_handles_empty_values(): void
    {
        $this->assertSame('-KVMO.UX', BarcodeSetting::maskPurchasePrice('-1234.50'));
        $this->assertSame('-', BarcodeSetting::maskPurchasePrice(null));
    }
}
