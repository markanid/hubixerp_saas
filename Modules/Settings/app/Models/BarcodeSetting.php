<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BarcodeSetting extends Model
{
    public const CONTEXT_INVENTORY = 'inventory';

    public const LAYOUT_THERMAL = 'thermal';
    public const LAYOUT_COMMON = 'common';

    public const LAYOUTS = [
        self::LAYOUT_THERMAL => 'Custom / thermal label printer',
        self::LAYOUT_COMMON => 'Common sticker roll label (centred)',
    ];

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
        'label_columns',
        'label_column_gap_mm',
        'barcode_layout',
        'common_label_width_mm',
        'common_label_height_mm',
        'auto_print',
        'mask_purchase_price',
    ];

    protected $casts = [
        'selected_fields' => 'array',
        'label_width_mm' => 'integer',
        'label_height_mm' => 'integer',
        'label_margin_mm' => 'integer',
        'label_columns' => 'integer',
        'label_column_gap_mm' => 'float',
        'common_label_width_mm' => 'integer',
        'common_label_height_mm' => 'integer',
        'auto_print' => 'boolean',
        'mask_purchase_price' => 'boolean',
    ];

    public const THERMAL_DEFAULTS = [
        'label_width_mm' => 80,
        'label_height_mm' => 40,
        'label_margin_mm' => 2,
        'label_columns' => 1,
        'label_column_gap_mm' => 0,
        'auto_print' => true,
    ];

    public const COMMON_DEFAULTS = [
        'common_label_width_mm' => 50,
        'common_label_height_mm' => 25,
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
            'label_columns' => (int) ($setting?->label_columns ?? self::THERMAL_DEFAULTS['label_columns']),
            'label_column_gap_mm' => (float) ($setting?->label_column_gap_mm ?? self::THERMAL_DEFAULTS['label_column_gap_mm']),
            'auto_print' => (bool) ($setting?->auto_print ?? self::THERMAL_DEFAULTS['auto_print']),
        ];
    }

    public static function commonSettings(): array
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'common_label_width_mm')) {
            return self::COMMON_DEFAULTS;
        }

        $setting = self::where('context', self::CONTEXT_INVENTORY)->first();

        return [
            'common_label_width_mm' => (int) ($setting?->common_label_width_mm ?? self::COMMON_DEFAULTS['common_label_width_mm']),
            'common_label_height_mm' => (int) ($setting?->common_label_height_mm ?? self::COMMON_DEFAULTS['common_label_height_mm']),
        ];
    }

    public static function layoutMode(): string
    {
        if (!Schema::hasTable('barcode_settings') || !Schema::hasColumn('barcode_settings', 'barcode_layout')) {
            return self::LAYOUT_THERMAL;
        }

        return self::where('context', self::CONTEXT_INVENTORY)->value('barcode_layout') === self::LAYOUT_COMMON
            ? self::LAYOUT_COMMON
            : self::LAYOUT_THERMAL;
    }

    /** Returns the dimensions used by the active barcode printing layout. */
    public static function activeLabelSettings(): array
    {
        $layout = self::layoutMode();
        $thermal = self::thermalSettings();

        if ($layout !== self::LAYOUT_COMMON) {
            return array_merge($thermal, [
                'layout_mode' => self::LAYOUT_THERMAL,
                'label_rows' => 1,
                'label_row_gap_mm' => 0,
            ]);
        }

        $common = self::commonSettings();

        return [
            'layout_mode' => self::LAYOUT_COMMON,
            'label_width_mm' => $common['common_label_width_mm'],
            'label_height_mm' => $common['common_label_height_mm'],
            // Reuse the existing label padding so changing layouts never alters it unexpectedly.
            'label_margin_mm' => $thermal['label_margin_mm'],
            'label_columns' => 1,
            'label_rows' => 1,
            'label_column_gap_mm' => 0,
            'label_row_gap_mm' => 0,
            'auto_print' => $thermal['auto_print'],
        ];
    }

    /** A printer page is one complete row of stickers, excluding the feed gap. */
    public static function pageSize(array $settings): array
    {
        if (($settings['layout_mode'] ?? self::LAYOUT_THERMAL) === self::LAYOUT_COMMON) {
            $labelWidth = (float) ($settings['label_width_mm'] ?? self::COMMON_DEFAULTS['common_label_width_mm']);
            $labelHeight = (float) ($settings['label_height_mm'] ?? self::COMMON_DEFAULTS['common_label_height_mm']);

            return [
                'width_mm' => $labelWidth,
                'height_mm' => $labelHeight,
                'columns' => 1,
                'rows' => 1,
                'column_gap_mm' => 0,
                'row_gap_mm' => 0,
                'capacity' => 1,
            ];
        }

        $columns = max(1, min(4, (int) ($settings['label_columns'] ?? 1)));
        $gap = max(0, (float) ($settings['label_column_gap_mm'] ?? 0));

        return [
            'width_mm' => round((float) $settings['label_width_mm'] * $columns + $gap * ($columns - 1), 2),
            'height_mm' => (float) $settings['label_height_mm'],
            'columns' => $columns,
            'rows' => 1,
            'column_gap_mm' => $gap,
            'row_gap_mm' => 0,
            'capacity' => $columns,
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
