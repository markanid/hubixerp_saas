<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class FinancialYear
{
    public static function active(): string
    {
        return session('financial_year') ?: self::forDate(now());
    }

    public static function forDate($date): string
    {
        $date = Carbon::parse($date);

        return $date->month >= 4
            ? $date->year . '-' . ($date->year + 1)
            : ($date->year - 1) . '-' . $date->year;
    }

    public static function bounds(?string $financialYear = null): array
    {
        $financialYear = $financialYear ?: self::active();
        if (!preg_match('/^(\d{4})-(\d{4})$/', $financialYear, $matches)) {
            throw ValidationException::withMessages(['financial_year' => 'The selected financial year is invalid.']);
        }

        return [
            Carbon::create((int) $matches[1], 4, 1)->startOfDay(),
            Carbon::create((int) $matches[2], 3, 31)->endOfDay(),
        ];
    }

    public static function assertDate($date, string $field = 'date'): Carbon
    {
        $parsed = $date instanceof Carbon
            ? $date->copy()
            : (is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)
                ? Carbon::createFromFormat('d/m/Y', $date)
                : Carbon::parse($date));
        [$start, $end] = self::bounds();

        if (!$parsed->betweenIncluded($start, $end)) {
            throw ValidationException::withMessages([
                $field => 'The date must be within the logged-in financial year (' . self::active() . ').',
            ]);
        }

        return $parsed;
    }

    public static function assertRecord(Model $record, string $dateColumn, string $field = 'record'): void
    {
        $recordYear = $record->getAttribute('financial_year') ?: self::forDate($record->getAttribute($dateColumn));
        if ($recordYear !== self::active()) {
            throw ValidationException::withMessages([
                $field => 'Only records in the logged-in financial year (' . self::active() . ') can be changed.',
            ]);
        }
    }

    public static function pickerOptions(): array
    {
        [$start, $end] = self::bounds();

        return ['minDate' => $start->format('Y-m-d'), 'maxDate' => $end->format('Y-m-d')];
    }

    /**
     * A safe default for new documents in the logged-in financial year.
     * Existing document dates must always be rendered from their own record.
     */
    public static function defaultTransactionDate(): Carbon
    {
        [$start, $end] = self::bounds();
        $today = now()->startOfDay();

        if ($today->betweenIncluded($start, $end)) {
            return $today;
        }

        return $today->lt($start) ? $start->copy() : $end->copy();
    }
}
