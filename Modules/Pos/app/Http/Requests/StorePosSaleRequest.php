<?php

namespace Modules\Pos\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\app\Models\Customer;
use Modules\Settings\app\Models\Company;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('pos.access');
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('sa_customer')) {
            $this->merge(['sa_customer' => Customer::walkIn()->id]);
        }
    }

    public function rules(): array
    {
        $mrpMode = (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';

        return [
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'sale_date' => ['nullable', 'date'],
            'sa_customer' => ['required', 'exists:customer,id'],
            'sa_type' => ['required', Rule::in($this->allowedSaleTypes())],
            'sa_state_code' => ['nullable', 'exists:gst_state_codes,state_code'],
            'sa_is_igst' => ['nullable', 'boolean'],
            'sa_remark' => ['nullable', 'string', 'max:1000'],
            'sale_items' => ['required', 'array', 'min:1'],
            'sale_items.*.product_id' => ['required', 'exists:product,product_code'],
            'sale_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_items.*.unit' => ['required', 'string', 'max:10'],
            'sale_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'sale_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'sale_items.*.mrp_stock_lot_id' => [Rule::requiredIf($mrpMode), 'nullable', 'integer', 'exists:mrp_stock_lots,id'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,upi'],
            'payments.*.banking_id' => ['required', 'integer', 'exists:banking,bk_id'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function allowedSaleTypes(): array
    {
        return Company::taxProfile()['is_composition'] ? ['1'] : ['1', '2'];
    }
}