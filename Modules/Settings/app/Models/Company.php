<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Master\Database\Factories\CompanyFactory;

class Company extends Model
{
    protected $fillable = [
        'company',
        'licence_no',
        'address',
        'phone',
        'email',
        'website',
        'gst_no',
        'bank_name',
        'bank_ifsc',
        'bank_acno',
        'bank_branch',
        'tags',
        'company_logo',
        'qr_code',
        'inventory_mode',
        'expiry_alert_days',
        'currency_code',
        'currency_symbol',
        'tax_type',
        'gst_scheme',
    ];
    protected $table = 'company';
    public $timestamps = false;
    use HasFactory;

    public function getCompanyDetails(){
        $row = $this->first();
        return [
            'title'             => 'Company Details',
            'id'                => $row->id ?? null,
            'name'              => $row->company ?? '',
            'address'           => $row->address ?? '',
            'phone_number'      => $row->phone ?? '',
            'email'             => $row->email ?? '',
            'website_url'       => $row->website ?? '',
            'license_number'    => $row->licence_no ?? '',
            'gst_number'        => $row->gst_no ?? '',
            'bank_name'         => $row->bank_name ?? '',
            'bank_ifsc'         => $row->bank_ifsc ?? '',
            'bank_acno'         => $row->bank_acno ?? '',
            'bank_branch'       => $row->bank_branch ?? '',
            'tags'              => $row->tags ?? '',
            'logo'              => !empty($row) && !empty($row->company_logo) ? $row->company_logo : null,
            'qr_code'           => !empty($row) && !empty($row->qr_code) ? $row->qr_code : null,
            'inventory_mode'    => $row->inventory_mode ?? 'standard',
            'expiry_alert_days' => $row->expiry_alert_days ?? 30,
            'currency_code'     => $row->currency_code ?? 'INR',
            'currency_symbol'   => $row->currency_symbol ?? '₹',
            'tax_type'          => $row->tax_type ?? 'gst',
            'gst_scheme'        => $row->gst_scheme ?? 'regular',
        ];
    }

    public static function taxProfile(): array
    {
        $row = self::query()->first();
        $taxType = strtolower((string) ($row?->tax_type ?? 'gst'));
        $gstScheme = strtolower((string) ($row?->gst_scheme ?? 'regular'));
        $isComposition = $taxType === 'gst' && $gstScheme === 'composition';

        return [
            'tax_type' => $taxType,
            'tax_label' => $taxType === 'vat' ? 'VAT' : 'GST',
            'gst_scheme' => $gstScheme,
            'is_composition' => $isComposition,
            'collect_tax' => !$isComposition,
        ];
    }

    public static function createOrUpdateCompany($data)
    {
        return self::updateOrCreate(
            ['id' => $data['id'] ?? null], // Search by ID
            array_filter($data) // Remove null values before updating
        );
    }
    /**
     * The attributes that are mass assignable.
     */

    // protected static function newFactory(): CompanyFactory
    // {
    //     // return CompanyFactory::new();
    // }
}
