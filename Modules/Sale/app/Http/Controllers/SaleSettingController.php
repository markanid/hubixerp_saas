<?php

namespace Modules\Sale\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sale\app\Models\SaleSetting;


class SaleSettingController extends Controller
{

    public function index()
    {
        return redirect()->route('company.settings');
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'in:manufacturing_date,allow_out_of_stock_sale,mrp_pricing_mode'],
        ]);
        $availableFields = ['manufacturing_date', 'allow_out_of_stock_sale', 'mrp_pricing_mode'];
        $selectedFields = $validated['fields'] ?? [];

        foreach ($availableFields as $field) {
            SaleSetting::updateOrCreate(
                ['key' => $field],
                ['value' => in_array($field, $selectedFields, true) ? 1 : 0]
            );
        }

        return redirect()->route('company.settings')->with('success', 'Sale settings updated!');
    }

}
