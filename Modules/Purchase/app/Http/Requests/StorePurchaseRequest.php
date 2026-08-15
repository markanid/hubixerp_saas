<?php

namespace Modules\Purchase\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Purchase\app\Models\Purchase;
use Modules\Settings\app\Models\Company;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->filled('pu_id') ? 'purchase.update' : 'purchase.create');
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('purchase_items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['purchase_items' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $mrpMode = (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';

        return [
            'pu_id' => ['nullable', 'integer', 'exists:purchase,pu_id'],
            'pu_date' => ['required', 'date_format:d/m/Y'],
            'pu_type' => ['required', Rule::in($this->allowedPurchaseTypes())],
            'pu_bill_number' => ['nullable', 'string', 'max:50'],
            'pu_vendor' => ['required', 'exists:vendor,id'],
            'pu_paymode' => ['required', 'exists:banking,bk_id'],
            'pu_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'pu_due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'pu_user' => ['nullable', 'exists:users,id'],
            'purchase_items' => ['required', 'array', 'min:1'],
            'purchase_items.*.product_id' => ['required', 'exists:product,product_code'],
            'purchase_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'purchase_items.*.free_quantity' => ['nullable', 'numeric', 'min:0'],
            'purchase_items.*.unit' => ['required', 'string', 'max:10'],
            'purchase_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'purchase_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'purchase_items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'purchase_items.*.expiry_date' => ['nullable', 'string', 'max:20'],
            'purchase_items.*.mrp' => [Rule::requiredIf($mrpMode), 'nullable', 'numeric', $mrpMode ? 'gt:0' : 'min:0'],
            'purchase_items.*.sale_price' => [Rule::requiredIf($mrpMode), 'nullable', 'numeric', 'gte:0'],
            'purchase_items.*.margin_percentage' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'purchase_items.*.margin_amount' => ['nullable', 'numeric', 'gte:0'],
            'purchase_items.*.margin_basis' => ['nullable', Rule::in(['amount', 'percentage'])],
        ];
    }

    private function allowedPurchaseTypes(): array
    {
        $allowed = Company::taxProfile()['is_composition'] ? ['1'] : ['1', '2'];

        if ($this->filled('pu_id')) {
            $existingType = Purchase::whereKey($this->input('pu_id'))->value('pu_type');

            if ($existingType !== null) {
                $allowed[] = (string) $existingType;
            }
        }

        return array_values(array_unique($allowed));
    }
}