<?php

namespace Modules\Service\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\app\Models\Customer;
use Modules\Settings\app\Models\Company;
use Modules\Service\app\Models\Service;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->filled('sv_id') ? 'service.update' : 'service.create');
    }

    protected function prepareForValidation(): void
    {
        foreach (['sale_items', 'service_items'] as $field) {
            $items = $this->input($field);

            if (is_string($items)) {
                $decoded = json_decode($items, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        if (!$this->filled('sv_customer')) {
            $this->merge(['sv_customer' => Customer::walkIn()->id]);
        }

        $this->merge([
            'sv_type' => $this->input('sv_type', '1'),
            'sale_items' => $this->input('sale_items', []),
            'service_items' => $this->input('service_items', []),
        ]);
    }

    public function rules(): array
    {
        $mrpMode = (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';

        return [
            'sv_id' => ['nullable', 'integer', 'exists:services,sv_id'],
            'sv_date' => ['required', 'date_format:d/m/Y'],
            'sv_type' => ['required', Rule::in($this->allowedServiceTypes())],
            'sv_customer' => ['required', 'exists:customer,id'],
            'sv_loc' => ['nullable', 'string', 'max:250'],
            'sv_vehicle' => ['nullable', 'string', 'max:100'],
            'sv_eway_bill_no' => ['nullable', 'string', 'max:255'],
            'sv_state_code' => ['nullable', 'exists:gst_state_codes,state_code'],
            'sv_is_igst' => ['nullable', 'boolean'],
            'sv_remark' => ['nullable', 'string', 'max:1000'],
            'sv_paymode' => ['required', 'exists:banking,bk_id'],
            'sv_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'sv_due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'sale_items' => ['array'],
            'sale_items.*.product_id' => ['required', 'exists:product,product_code'],
            'sale_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_items.*.unit' => ['required', 'string', 'max:10'],
            'sale_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'sale_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_items.*.manufacturing_date' => ['nullable', 'string', 'max:20'],
            'sale_items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'sale_items.*.mrp_stock_lot_id' => [Rule::requiredIf($mrpMode), 'nullable', 'integer', 'exists:mrp_stock_lots,id'],
            'service_items' => ['array'],
            'service_items.*.product_id' => ['required', 'exists:product,product_code'],
            'service_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'service_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'service_items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function allowedServiceTypes(): array
    {
        $allowed = Company::taxProfile()['is_composition'] ? ['1'] : ['1', '2'];

        if ($this->filled('sv_id')) {
            $existingType = Service::whereKey($this->input('sv_id'))->value('sv_type');

            if ($existingType !== null) {
                $allowed[] = (string) $existingType;
            }
        }

        return array_values(array_unique($allowed));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->input('sale_items', [])) === 0 && count($this->input('service_items', [])) === 0) {
                $validator->errors()->add('service_items', 'Please add at least one sale or service item.');
            }
        });
    }
}