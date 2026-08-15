<!-- Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="productForm" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <div>
                    <h5 class="modal-title mb-0" id="addProductModalLabel"><i class="fas fa-box-open mr-2"></i>Add New Product</h5>
                    <small class="text-white-50">Enter the product identity and selling details</small>
                </div>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>

            <div class="modal-body p-4">
                <div class="border rounded p-3 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mr-2" style="width:30px;height:30px"><i class="fas fa-tag"></i></span>
                        <div><h6 class="mb-0">Product Details</h6><small class="text-muted">Basic information used in searches and invoices</small></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-8">
                            <label for="new_product_name">Product Name <span class="text-danger">*</span></label>
                            <input type="text" id="new_product_name" class="form-control" placeholder="Enter product name" autocomplete="off">
                            <small class="text-danger error-product-name"></small>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="new_product_code">Product Code</label>
                            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-barcode"></i></span></div><input type="text" id="new_product_code" class="form-control bg-light" readonly></div>
                            <small class="text-danger error-product-code"></small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6 mb-md-0"><label for="new_hsn">HSN Code</label><input type="text" id="new_hsn" class="form-control" placeholder="Optional HSN code" autocomplete="off"></div>
                        <div class="form-group col-md-6 mb-0">
                            <label for="new_gst">GST Rate</label>
                            <select id="new_gst" class="form-control">
                                <option value="">Select GST rate</option>
                                <option value="0"{{ isset($product->gst) && $product->gst == '0' ? 'selected' : '' }}>0%</option>
                                <option value="5"{{ isset($product->gst) && $product->gst == '5' ? 'selected' : '' }}>5%</option>
                                <option value="12"{{ isset($product->gst) && $product->gst == '12' ? 'selected' : '' }}>12%</option>
                                <option value="18"{{ isset($product->gst) && $product->gst == '18' ? 'selected' : '' }}>18%</option>
                                <option value="28"{{ isset($product->gst) && $product->gst == '28' ? 'selected' : '' }}>28%</option>
                            </select>
                            @if ($errors->has('gst'))<span class="text-danger">{{ $errors->first('gst') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="border rounded bg-light p-3">
                    <div class="d-flex align-items-center mb-3">
                        <span class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mr-2" style="width:30px;height:30px"><i class="fas fa-calculator"></i></span>
                        <div><h6 class="mb-0">Pricing</h6><small class="text-muted">MRP, margin and sale price calculate automatically</small></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6"><label for="new_pprice">Purchase Price</label><input type="number" min="0" step="0.01" id="new_pprice" class="form-control" placeholder="0.00"><small class="text-danger error-pprice"></small></div>
                        <div class="form-group col-md-6"><label for="new_mrp">MRP</label><input type="number" min="0" step="0.01" id="new_mrp" class="form-control" placeholder="0.00"></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4 mb-md-0"><label for="new_margin">Margin Percentage</label><div class="input-group"><input type="number" min="0" max="100" step="0.01" id="new_margin" class="form-control" placeholder="0.00"><div class="input-group-append"><span class="input-group-text">%</span></div></div></div>
                        <div class="form-group col-md-4 mb-md-0"><label for="new_amt_margin">Margin Amount</label><input type="number" min="0" step="0.01" id="new_amt_margin" class="form-control" placeholder="0.00"></div>
                        <div class="form-group col-md-4 mb-0"><label for="new_price">Sale Price <span class="text-danger">*</span></label><input type="number" min="0" step="0.01" id="new_price" class="form-control border-primary font-weight-bold" placeholder="0.00"><small class="text-danger error-price"></small></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancel</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Save Product</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const closeButton = document.querySelector('#addProductModal .modal-header .close');
    if (closeButton) {
        closeButton.classList.add('text-white');
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.innerHTML = '<span aria-hidden="true">&times;</span>';
    }

    function numericValue(element) {
        const value = parseFloat(element.value);
        return Number.isFinite(value) ? value : 0;
    }

    function modalPricingInputs() {
        return {
            mrp: document.getElementById('new_mrp'),
            margin: document.getElementById('new_margin'),
            amount: document.getElementById('new_amt_margin'),
            price: document.getElementById('new_price')
        };
    }

    function updateFromMargin(inputs) {
        const mrp = numericValue(inputs.mrp);
        const margin = numericValue(inputs.margin);
        if (mrp <= 0) return;

        const amount = (mrp * margin) / 100;
        inputs.amount.value = amount.toFixed(2);
        inputs.price.value = Math.max(0, mrp - amount).toFixed(2);
    }

    function updateFromAmount(inputs) {
        const mrp = numericValue(inputs.mrp);
        const amount = numericValue(inputs.amount);
        if (mrp <= 0) return;

        inputs.margin.value = ((amount * 100) / mrp).toFixed(2);
        inputs.price.value = Math.max(0, mrp - amount).toFixed(2);
    }

    function updateFromPrice(inputs) {
        const mrp = numericValue(inputs.mrp);
        const price = numericValue(inputs.price);
        if (mrp <= 0 || price > mrp) return;

        const amount = mrp - price;
        inputs.amount.value = amount.toFixed(2);
        inputs.margin.value = ((amount * 100) / mrp).toFixed(2);
    }

    document.addEventListener('input', function (event) {
        if (!event.target.closest('#addProductModal')) return;

        const inputs = modalPricingInputs();
        if (!inputs.mrp || !inputs.margin || !inputs.amount || !inputs.price) return;

        if (event.target === inputs.margin) {
            updateFromMargin(inputs);
        } else if (event.target === inputs.amount) {
            updateFromAmount(inputs);
        } else if (event.target === inputs.price) {
            updateFromPrice(inputs);
        } else if (event.target === inputs.mrp) {
            if (inputs.margin.value !== '') {
                updateFromMargin(inputs);
            } else if (inputs.price.value !== '') {
                updateFromPrice(inputs);
            }
        }
    });
})();
</script>
