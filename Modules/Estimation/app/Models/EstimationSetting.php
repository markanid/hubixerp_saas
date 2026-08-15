<?php

namespace Modules\Estimation\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'boolean',
    ];

    public static function values(): array
    {
        return static::query()
            ->get(['key', 'value'])
            ->mapWithKeys(fn (self $setting) => [$setting->key => (bool) $setting->value])
            ->all();
    }
}
