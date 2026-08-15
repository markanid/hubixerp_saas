<?php

namespace Modules\Estimation\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Estimation\app\Models\EstimationSetting;

class EstimationSettingController extends Controller
{
    public function index()
    {
        return redirect()->route('company.settings');
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'in:affect_customer_accounts'],
        ]);

        $selectedFields = $validated['fields'] ?? [];

        EstimationSetting::updateOrCreate(
            ['key' => 'affect_customer_accounts'],
            ['value' => in_array('affect_customer_accounts', $selectedFields, true) ? 1 : 0]
        );

        return redirect()->route('company.settings')->with('success', 'Estimation settings updated!');
    }
}
