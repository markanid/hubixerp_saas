<?php

namespace Modules\Sale\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\app\Models\Customer;
use Modules\Sale\app\Models\Sale;
use Modules\Settings\app\Models\Company;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->filled('sa_id') ? 'sale.update' : 'sale.create');
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('sale_items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['sale_items' => $decoded]);
            }
        }

        if (!$this->filled('sa_customer')) {
            $this->merge(['sa_customer' => Customer::walkIn()->id]);
        }
    }

    public function rules(): array
    {
        $mrpMode = (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';

        return [
            'sa_id' => ['nullable', 'integer', 'exists:sales,sa_id'],
            'sa_date' => ['required', 'date_format:d/m/Y'],
            'sa_customer' => ['required', 'exists:customer,id'],
            'sa_loc' => ['nullable', 'string', 'max:250'],
            'sa_vehicle' => ['nullable', 'string', 'max:100'],
            'sa_paymode' => ['required', 'exists:banking,bk_id'],
            'sa_type' => ['required', Rule::in($this->allowedSaleTypes())],
            'sa_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'sa_due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'sa_eway_bill_no' => ['nullable', 'string', 'max:255'],
            'sa_state_code' => ['nullable', 'exists:gst_state_codes,state_code'],
            'sa_is_igst' => ['nullable', 'boolean'],
            'sa_remark' => ['nullable', 'string', 'max:1000'],
            'sale_items' => ['required', 'array', 'min:1'],
            'sale_items.*.product_id' => ['required', 'exists:product,product_code'],
            'sale_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_items.*.unit' => ['required', 'string', 'max:10'],
            'sale_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'sale_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_items.*.manufacturing_date' => ['nullable', 'string', 'max:20'],
            'sale_items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'sale_items.*.mrp_stock_lot_id' => [Rule::requiredIf($mrpMode), 'nullable', 'integer', 'exists:mrp_stock_lots,id'],
        ];
    }

    private function allowedSaleTypes(): array
    {
        $allowed = Company::taxProfile()['is_composition'] ? ['1'] : ['1', '2'];

        if ($this->filled('sa_id')) {
            $existingType = Sale::whereKey($this->input('sa_id'))->value('sa_type');

            if ($existingType !== null) {
                $allowed[] = (string) $existingType;
            }
        }

        return array_values(array_unique($allowed));
    }
}