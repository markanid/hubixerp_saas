<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BarcodeSetting extends Model
{
    public const CONTEXT_INVENTORY = 'inventory';

    public const FIELDS = [
        'product_code' => 'Product Code',
        'product_name' => 'Product Name',
        'purchase_price' => 'Purchase Price',
        'mrp' => 'MRP',
        'sale_price' => 'Sale Price',
        'batch_number' => 'Batch Number',
        'expiry_date' => 'Expiry Date',
    ];

    public const DEFAULT_FIELDS = [
        'product_code',
        'product_name',
        'purchase_price',
        'mrp',
        'sale_price',
        'batch_number',
        'expiry_date',
    ];

    public const PURCHASE_PRICE_MASK = [
        '1' => 'K',
        '2' => 'V',
        '3' => 'M',
        '4' => 'O',
        '5' => 'U',
        '6' => 'L',
        '7' => 'I',
        '8' => 'N',
        '9' => 'E',
        '0' => 'X',
    ];

    protected $fillable = [
        'context',
        'selected_fields',
        'label_width_mm',
        'label_height_mm',
        'label_margin_mm',
        'auto_print',
        'mask_purchase_price',
    ];

    protected $casts = [
        'selected_fields' => 'array',
        'label_width_mm' => 'integer',
        'label_height_mm' => 'integer',
        'label_margin_mm' => 'integer',
        'auto_print' => 'boolean',
        'mask_purchase_price' => 'boolean',
    ];

    public const THERMAL_DEFAULTS = [
        'label_width_mm' => 80,
        'label_height_mm' => 40,
        'label_margin_mm' => 2,
        'auto_print' => true,
    ];

    public static function selectedFields(): array
    {
        if (!Schema::hasTable('barcode_settings')) {
            return self::DEFAULT_FIELDS;
        }

        $fields = self::where('context', self::CONTEXT_INVENTORY)->value('selected_fields');
        $fields = is_string($fields) ? json_decode($fields, true) : $fields;

        return collect(is_array($fields) ? $fields : self::DEFAULT_FIELDS)
            ->filter(fn ($field) => array_key_exists($field, self::FIELDS))
            ->unique()
            ->values()
            ->all();
    }

    public static function thermalSettings(): array
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'label_width_mm')) {
            return self::THERMAL_DEFAULTS;
        }

        $setting = self::where('context', self::CONTEXT_INVENTORY)->first();

        return [
            'label_width_mm' => (int) ($setting?->label_width_mm ?? self::THERMAL_DEFAULTS['label_width_mm']),
            'label_height_mm' => (int) ($setting?->label_height_mm ?? self::THERMAL_DEFAULTS['label_height_mm']),
            'label_margin_mm' => (int) ($setting?->label_margin_mm ?? self::THERMAL_DEFAULTS['label_margin_mm']),
            'auto_print' => (bool) ($setting?->auto_print ?? self::THERMAL_DEFAULTS['auto_print']),
        ];
    }

    public static function purchasePriceMaskEnabled(): bool
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'mask_purchase_price')) {
            return false;
        }

        return (bool) self::where('context', self::CONTEXT_INVENTORY)->value('mask_purchase_price');
    }

    public static function maskPurchasePrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $formatted = number_format((float) $value, 2, '.', '');

        return strtr($formatted, self::PURCHASE_PRICE_MASK);
    }
}
