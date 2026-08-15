<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PrintSetting extends Model
{
    protected $fillable = [
        'document_type',
        'print_method',
        'paper_size',
        'orientation',
        'scale',
        'margin_mm',
        'auto_print',
        'printer_name',
    ];

    protected $casts = [
        'scale' => 'integer',
        'margin_mm' => 'integer',
        'auto_print' => 'boolean',
    ];

    public const DOCUMENTS = [
        'sale' => 'Sale',
        'purchase' => 'Purchase',
        'estimation' => 'Estimation',
        'service' => 'Service',
        'sale_return' => 'Sale Return',
        'purchase_return' => 'Purchase Return',
        'consumption' => 'Consumption',
    ];

    public const PAPER_SIZES = [
        'A4' => 'A4',
        'A5' => 'A5',
    ];

    public const ORIENTATIONS = [
        'portrait' => 'Portrait',
        'landscape' => 'Landscape',
    ];

    public const PRINT_METHODS = [
        'browser' => 'Browser Print',
        'local_agent' => 'Hubix Local Print Agent',
    ];

    public static function defaultsFor(string $documentType): array
    {
        $defaults = [
            'paper_size' => 'A4',
            'print_method' => 'browser',
            'orientation' => 'portrait',
            'scale' => 100,
            'margin_mm' => 10,
            'auto_print' => true,
            'printer_name' => null,
        ];

        if ($documentType === 'estimation') {
            $defaults['paper_size'] = 'A5';
            $defaults['scale'] = 90;
            $defaults['margin_mm'] = 6;
        }

        return array_merge(['document_type' => $documentType], $defaults);
    }

    public static function forDocument(string $documentType): self
    {
        if (!Schema::hasTable('print_settings')) {
            return new self(self::defaultsFor($documentType));
        }

        return self::where('document_type', $documentType)->first()
            ?? new self(self::defaultsFor($documentType));
    }

    public static function values(): array
    {
        $settings = [];

        foreach (self::DOCUMENTS as $documentType => $label) {
            $settings[$documentType] = self::forDocument($documentType);
        }

        return $settings;
    }
}
