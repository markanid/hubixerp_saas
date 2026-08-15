<?php

namespace Modules\Returns\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\app\Models\Customer;
use Modules\Settings\app\Models\Company;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->filled('pr_id') ? 'sale.update' : 'sale.create');
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('return_items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['return_items' => $decoded]);
            }
        }

        if (!$this->filled('pr_vendor')) {
            $this->merge(['pr_vendor' => Customer::walkIn()->id]);
        }
    }

    public function rules(): array
    {
        $mrpMode = (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';

        return [
            'pr_id' => ['nullable', 'integer', 'exists:preturn,pr_id'],
            'pr_vno' => [
                'required',
                'string',
                'max:50',
                Rule::unique('preturn', 'pr_vno')->ignore($this->input('pr_id'), 'pr_id'),
            ],
            'pr_user' => ['nullable', 'exists:users,id'],
            'pr_date' => ['required', 'date_format:d/m/Y'],
            'pr_pvno' => ['nullable', 'string', 'max:100'],
            'pr_vendor' => ['required', 'exists:customer,id'],
            'pr_state_code' => ['nullable', 'exists:gst_state_codes,state_code'],
            'pr_is_igst' => ['nullable', 'boolean'],
            'pr_paymode' => ['required', 'exists:banking,bk_id'],
            'pr_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'return_items' => ['required', 'array', 'min:1'],
            'return_items.*.product_id' => ['required', 'exists:product,product_code'],
            'return_items.*.hsn_code' => ['nullable', 'string', 'max:50'],
            'return_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'return_items.*.unit' => ['required', 'string', 'max:10'],
            'return_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'return_items.*.unit_qty' => ['nullable', 'numeric', 'gt:0'],
            'return_items.*.mrp_stock_lot_id' => ['nullable', 'integer', 'exists:mrp_stock_lots,id'],
            'return_items.*.stock_mrp' => [Rule::requiredIf($mrpMode), 'nullable', 'numeric', 'gt:0'],
        ];
    }
}