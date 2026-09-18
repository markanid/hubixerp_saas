<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS - {{ config('app.name', 'HubixERP') }}</title>
    <link rel="stylesheet" href="{{ asset('admin-assets/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/dist/css/adminlte.min.css') }}">
    <style>
        html, body { height: 100%; overflow: hidden; background: #f4f6f9; }
        .pos-shell { height: 100vh; display: grid; grid-template-columns: minmax(420px, 1fr) 420px; gap: 10px; padding: 10px; }
        .pos-panel { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; min-height: 0; display: flex; flex-direction: column; }
        .pos-toolbar { padding: 10px; border-bottom: 1px solid #e9ecef; display: grid; grid-template-columns: 1fr 150px 150px; gap: 8px; }
        .product-area { flex: 1; min-height: 0; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1fr) 304px; gap: 10px; padding: 8px; }
        .product-results { min-height: 0; overflow: auto; display: grid; grid-template-columns: repeat(auto-fill, 200px); gap: 10px; align-content: start; align-items: start; }
        .product-tile { width: 200px; height: 150px; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; cursor: pointer; min-height: 0; overflow: hidden; display: grid; grid-template-columns: 66px minmax(0, 1fr); grid-template-rows: 66px 1fr; gap: 10px; background: #fff; }
        .product-tile:hover { border-color: #17a2b8; box-shadow: 0 0 0 2px rgba(23,162,184,.12); }
        .product-img-wrap { grid-column: 1; grid-row: 1; width: 66px; height: 66px; aspect-ratio: 1 / 1; border-radius: 5px; background: #f1f3f5; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .product-img { width: 100%; height: 100%; object-fit: cover; }
        .product-name { grid-column: 1 / -1; grid-row: 2; line-height: 1.2; overflow: hidden; align-self: start; font-size: 1.05rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
        .product-code { min-height: 19px; word-break: break-word; text-align: right; }
        .product-info { grid-column: 2; grid-row: 1; min-width: 0; display: grid; grid-template-rows: repeat(3, 1fr); align-items: center; text-align: right; }
        .product-price { white-space: nowrap; font-size: 1.05rem; }
        .product-stock { min-width: 0; text-align: right; }
        .stock-pill { display: inline-flex; align-items: center; justify-content: center; gap: 4px; max-width: 100%; border-radius: 999px; padding: 4px 8px; font-size: 10px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stock-good { color: #0f5132; background: #d1e7dd; }
        .stock-low { color: #664d03; background: #fff3cd; }
        .stock-out { color: #842029; background: #f8d7da; }
        .stock-high { color: #084298; background: #cfe2ff; }
        #toast-container > .toast-warning,
        #toast-container > .toast-warning .toast-message,
        #toast-container > .toast-warning .toast-title {
            color: #111 !important;
        }
        #toast-container > .toast-warning {
            background-image: none !important;
        }
        #toast-container > .toast-warning::before {
            content: "\f071";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            color: #dc3545;
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }
        .cart-table-wrap { overflow: auto; flex: 1; }
        .cart-table th, .cart-table td { vertical-align: middle; white-space: nowrap; }
        .cart-table .qty-input, .cart-table .disc-input { width: 76px; }
        .totals { border-top: 1px solid #e9ecef; padding: 10px; background: #fafafa; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .total-row.payable { font-size: 1.35rem; font-weight: 700; }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .session-strip { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-bottom: 1px solid #e9ecef; background: #f8f9fa; }
        .btn-icon { width: 38px; min-width: 38px; }
        .touch-keypad { width: 100%; align-self: start; background: #fff; border: 1px solid #cfd4da; border-radius: 6px; box-shadow: 0 8px 22px rgba(0,0,0,.12); padding: 10px; display: block; }
        .touch-keypad-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; font-weight: 700; color: #495057; }
        .touch-keypad-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .touch-keypad-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 7px; }
        .touch-keypad-grid .btn { min-height: 52px; font-size: 1.18rem; font-weight: 700; }
        .touch-keypad-grid .btn-done { font-size: .95rem; }
        .btn-pos-recall { color: #fff; background: #042661; border-color: #042661; }
        .btn-pos-recall:hover, .btn-pos-recall:focus { color: #fff; background: #031d4a; border-color: #031d4a; }
        .btn-pos-hold { color: #fff; background: #6c757d; border-color: #6c757d; }
        .btn-pos-hold:hover, .btn-pos-hold:focus { color: #fff; background: #5a6268; border-color: #545b62; }
        .btn-pos-back { color: #fff; background: #000; border-color: #000; }
        .btn-pos-back:hover, .btn-pos-back:focus { color: #fff; background: #222; border-color: #222; }
        .btn-pos-clear { color: #fff; background: #6f42c1; border-color: #6f42c1; }
        .btn-pos-clear:hover, .btn-pos-clear:focus { color: #fff; background: #59339d; border-color: #59339d; }
        .btn-pos-tab { color: #111; background: #add8e6; border-color: #add8e6; }
        .btn-pos-tab:hover, .btn-pos-tab:focus { color: #111; background: #91c9dc; border-color: #91c9dc; }
        @media (max-width: 991px) {
            body { overflow: auto; }
            .pos-shell { height: auto; min-height: 100vh; grid-template-columns: 1fr; }
            .product-area { grid-template-columns: 1fr; }
        }
        @media print {
            body * { visibility: hidden; }
        }
    </style>
</head>
<body>
<div class="pos-shell">
    <section class="pos-panel">
        <div class="session-strip">
            <strong>{{ $company?->company ?? config('app.name') }}</strong>
            <span class="badge badge-info">{{ $financialYear }}</span>
            <span id="sessionBadge" class="badge {{ $session ? 'badge-success' : 'badge-secondary' }}">{{ $session ? 'Open: '.$session->counter_code : 'Closed' }}</span>
            <button class="btn btn-sm btn-danger btn-flat ml-auto" data-toggle="modal" data-target="#sessionModal" {{ $session ? '' : 'disabled' }}>
                <i class="fas fa-lock mr-1"></i> Close
            </button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sales.index') }}"><i class="fas fa-list"></i></a>
        </div>
        <div class="pos-toolbar">
            <input type="text" id="productSearch" class="form-control form-control-lg" placeholder="Scan barcode or search product" autofocus>
            <select id="saleType" class="form-control form-control-lg">
                @foreach(($allowedSaleTypes ?? ['1', '2']) as $type)
                    <option value="{{ $type }}">{{ (string) $type === '2' ? 'B2B' : 'B2C' }}</option>
                @endforeach
            </select>
            @if(($taxProfile['collect_tax'] ?? true) && ($taxProfile['tax_type'] ?? 'gst') === 'gst')
            <select id="stateCode" class="form-control form-control-lg">
                <option value="">State</option>
                @foreach($states as $state)
                    <option value="{{ $state->state_code }}">{{ $state->state_name }}</option>
                @endforeach
            </select>
            @endif
        </div>
        <div id="productArea" class="product-area">
            <div id="productResults" class="product-results"></div>
            <div id="touchKeypad" class="touch-keypad" aria-label="Numeric keypad">
                <div class="touch-keypad-header">
                    <span id="touchKeypadLabel" class="touch-keypad-title">Number</span>
                </div>
                <div class="touch-keypad-grid">
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="7">7</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="8">8</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="9">9</button>
                    <button type="button" class="btn btn-pos-back btn-flat" data-keypad-action="back"><i class="fas fa-backspace"></i></button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="4">4</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="5">5</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="6">6</button>
                    <button type="button" class="btn btn-pos-clear btn-flat" data-keypad-action="clear">C</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="1">1</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="2">2</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="3">3</button>
                    <button type="button" class="btn btn-success btn-flat btn-done" data-keypad-action="done">Done</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="0">0</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value="00">00</button>
                    <button type="button" class="btn btn-light btn-flat" data-keypad-value=".">.</button>
                    <button type="button" class="btn btn-pos-tab btn-flat btn-done" data-keypad-action="tab">Tab</button>
                </div>
            </div>
        </div>
    </section>

    <section class="pos-panel">
        <div class="p-2 border-bottom">
            <div class="input-group">
                <select id="customerId" class="form-control">
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (int) $customer->id === (int) $walkInCustomerId ? 'selected' : '' }}>{{ $customer->customer }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                    @endforeach
                </select>
                <div class="input-group-append">
                    <button type="button" class="btn btn-primary btn-icon" data-toggle="modal" data-target="#customerModal" title="Add customer">
                        <i class="fas fa-user-plus"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="cart-table-wrap">
            <table class="table table-sm table-hover cart-table mb-0">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Disc</th>
                    <th class="text-right">Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="cartBody">
                <tr class="empty-cart"><td colspan="5" class="text-center text-muted py-5">No items</td></tr>
                </tbody>
            </table>
        </div>
        <div class="totals">
            <div class="total-row"><span>Subtotal</span><span id="totalAmount">0.00</span></div>
            <div class="total-row"><span>Discount</span><span id="totalDiscount">0.00</span></div>
            <div class="total-row"><span>GST</span><span id="totalGst">0.00</span></div>
            <div class="total-row"><span>Round</span><span id="totalRound">0.00</span></div>
            <div class="total-row payable"><span>Payable</span><span id="totalPayable">0.00</span></div>
            <div class="payment-grid mb-2">
                <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control payment-amount touch-number" data-method="cash" data-keypad-label="Cash" placeholder="Cash">
                <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control payment-amount touch-number" data-method="card" data-keypad-label="Card" placeholder="Card">
                <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control payment-amount touch-number" data-method="upi" data-keypad-label="UPI" placeholder="UPI">
                <select id="paymentBank" class="form-control">
                    @foreach($banks as $bank)
                        <option value="{{ $bank->bk_id }}">{{ $bank->bk_bank }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex">
                <button id="holdBtn" class="btn btn-pos-hold btn-flat flex-fill mr-1"><i class="fas fa-pause"></i> Hold</button>
                <button id="recallBtn" class="btn btn-pos-recall btn-flat flex-fill mx-1"><i class="fas fa-history"></i> Recall</button>
                <button id="saveBtn" class="btn btn-success btn-flat flex-fill ml-1"><i class="fas fa-check"></i> Pay</button>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">POS Session</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="mb-2"><strong>Counter:</strong> {{ $session?->counter_code ?? 'Not opened' }}</div>
                <div class="mb-2"><strong>Expected Cash:</strong> {{ number_format((float) ($session?->expected_cash ?? 0), 2) }}</div>
                <input id="closingCash" type="number" min="0" step="0.01" inputmode="decimal" class="form-control mb-2 touch-number" data-keypad-label="Closing cash" placeholder="Closing cash">
                <textarea id="closingNote" class="form-control" rows="2" placeholder="Closing note"></textarea>
            </div>
            <div class="modal-footer">
                <button id="closeSessionBtn" class="btn btn-danger btn-flat" {{ $session ? '' : 'disabled' }}>Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="holdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Held Bills</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><div id="holdList" class="list-group"></div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Customer</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="newCustomerName">Customer name</label>
                    <input id="newCustomerName" type="text" class="form-control" maxlength="255" autocomplete="off">
                </div>
                <div class="form-group mb-0">
                    <label for="newCustomerPhone">Phone</label>
                    <input id="newCustomerPhone" type="text" inputmode="numeric" class="form-control touch-number" data-keypad-mode="integer" data-keypad-label="Phone" maxlength="20" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button id="saveCustomerBtn" type="button" class="btn btn-primary btn-flat"><i class="fas fa-save mr-1"></i> Save</button>
                <button type="button" class="btn btn-secondary btn-flat" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('admin-assets/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('admin-assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('admin-assets/plugins/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('admin-assets/plugins/toastr/toastr.min.js') }}"></script>
<script>
    let posSession = @json($session);
    let allowOutOfStockSale = @json($allowOutOfStockSale);
    let useMrpPricingMode = @json($useMrpPricingMode);
    let cart = [];
    let totals = {amount: 0, discount: 0, gst: 0, round: 0, amount_payable: 0};
    const currencySymbol = @json($company?->currency_symbol ?: '₹');
    const dashboardUrl = @json(route('profile.dashboard'));
    const bankId = () => parseInt($('#paymentBank').val(), 10);

    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

    function money(value) { return (parseFloat(value || 0)).toFixed(2); }
    function productBatchId(product) {
        return product.stock_batch_id ?? (product.batches && product.batches.length ? product.batches[0].id : null);
    }

    function productMrpLotId(product) {
        return product.mrp_stock_lot_id ?? (product.mrp_lots && product.mrp_lots.length === 1 ? product.mrp_lots[0].id : null);
    }

    function lineKey(product) {
        const productId = product.product_code ?? product.product_id;
        return String(productId) + ':' + String(productBatchId(product) ?? '') + ':' + String(productMrpLotId(product) ?? '');
    }

    const dummyProductImage = "{{ asset('uploads/avatar.png') }}";

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function renderProducts(products) {
        $('#productResults').html(products.map(product => `
            <div class="product-tile" data-code="${product.product_code}">
                <div class="product-img-wrap">
                    <img class="product-img" src="${escapeHtml(product.image_url || dummyProductImage)}" alt="${escapeHtml(product.product)}" onerror="this.onerror=null;this.src='${dummyProductImage}'">
                </div>
                <div class="product-info">
                    <div>
                        <div class="product-code text-muted small">${escapeHtml(product.product_code)}</div>
                        <div class="product-stock">
                            <span class="stock-pill ${escapeHtml(product.stock_class || 'stock-good')}">
                                <i class="fas fa-cubes"></i> ${escapeHtml(product.stock_label || 'Stock')} ${product.current_stock} ${escapeHtml(product.unit || '')}
                            </span>
                        </div>
                        <div class="product-price font-weight-bold text-primary">${escapeHtml(currencySymbol)} ${money(product.price)}</div>
                    </div>
                </div>
                <strong class="d-block product-name">${escapeHtml(product.product)}</strong>
            </div>
        `).join(''));
        $('#productResults .product-tile').each(function (index) {
            $(this).data('product', products[index]);
        });
    }

    function addProduct(product) {
        if (product.mrp_mode && !product.mrp_stock_lot_id) {
            const lots = product.mrp_lots || [];
            if (!lots.length) {
                toastr.warning(`${product.product} has no MRP stock lot available.`);
                return;
            }
            if (lots.length > 1) {
                const options = {};
                lots.forEach(lot => {
                    options[lot.id] = `${lot.purchase_voucher || 'Opening/Return'} | ${lot.purchase_date || ''} | MRP ${money(lot.mrp)} | Sale ${money(lot.sale_price)}`;
                });
                Swal.fire({
                    title: 'Select Stock / MRP',
                    input: 'select',
                    inputOptions: options,
                    inputPlaceholder: 'Select the MRP printed on the product',
                    showCancelButton: true,
                    inputValidator: value => value ? undefined : 'Select an MRP stock lot.'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const lot = lots.find(item => String(item.id) === String(result.value));
                    if (lot) addProduct({...product, mrp_stock_lot_id: lot.id, selected_mrp_lot: lot});
                });
                return;
            }
            product = {...product, mrp_stock_lot_id: lots[0].id, selected_mrp_lot: lots[0]};
        }

        if (!allowOutOfStockSale && parseFloat(product.raw_stock || 0) <= 0) {
            toastr.warning(`${product.product} is out of stock.`);
            return;
        }
        const stockBatchId = productBatchId(product);
        const mrpStockLotId = productMrpLotId(product);
        const selectedMrpLot = product.selected_mrp_lot || (product.mrp_lots || []).find(lot => String(lot.id) === String(mrpStockLotId));
        const key = lineKey(product);
        const existing = cart.find(item => lineKey(item) === key);
        if (existing) {
            existing.quantity = parseFloat(existing.quantity) + 1;
            if (existing.auto_mrp_discount) {
                existing.discount_amount = (existing.quantity * existing.unit_price * existing.margin_percentage) / 100;
            }
        } else {
            cart.push({
                product_id: product.product_code,
                name: product.product,
                quantity: 1,
                unit: product.unit || 'No.s',
                uqty: parseFloat(product.uqty || 1),
                unit_price: selectedMrpLot
                    ? parseFloat(useMrpPricingMode ? selectedMrpLot.mrp : selectedMrpLot.sale_price)
                    : parseFloat(product.price || 0),
                discount_amount: selectedMrpLot && useMrpPricingMode
                    ? parseFloat(selectedMrpLot.margin_amount || 0)
                    : 0,
                margin_percentage: selectedMrpLot ? parseFloat(selectedMrpLot.margin_percentage || 0) : 0,
                auto_mrp_discount: Boolean(selectedMrpLot && useMrpPricingMode),
                gst: product.gst,
                stock_batch_id: stockBatchId,
                mrp_stock_lot_id: mrpStockLotId,
                stock_mrp: selectedMrpLot ? parseFloat(selectedMrpLot.mrp || 0) : null,
                raw_stock: selectedMrpLot ? parseFloat(selectedMrpLot.available_quantity || 0) : parseFloat(product.raw_stock || 0),
                current_stock: selectedMrpLot ? parseFloat(selectedMrpLot.display_quantity || 0) : parseFloat(product.current_stock || 0)
            });
        }
        enforceCartStock({notify: true});
        renderCart();
        calculate();
    }

    function renderCart() {
        if (!cart.length) {
            $('#cartBody').html('<tr class="empty-cart"><td colspan="5" class="text-center text-muted py-5">No items</td></tr>');
            return;
        }
        $('#cartBody').html(cart.map((item, index) => `
            <tr>
                <td><strong>${item.name}</strong><div class="small text-muted">${item.product_id}${item.stock_mrp ? ' | MRP ' + money(item.stock_mrp) : ''}</div></td>
                <td><input type="number" min="0.01" step="0.01" inputmode="decimal" class="form-control form-control-sm qty-input touch-number" data-keypad-label="Quantity" data-index="${index}" value="${item.quantity}"></td>
                <td><input type="number" min="0" step="0.01" inputmode="decimal" class="form-control form-control-sm disc-input touch-number" data-keypad-label="Discount" data-index="${index}" value="${item.discount_amount || 0}"></td>
                <td class="text-right">${money((item.quantity * item.unit_price) - (item.discount_amount || 0))}</td>
                <td><button class="btn btn-sm btn-outline-danger remove-item btn-icon" data-index="${index}"><i class="fas fa-times"></i></button></td>
            </tr>
        `).join(''));
    }

    function saleItems() {
        return cart.map(item => ({
            product_id: item.product_id,
            quantity: parseFloat(item.quantity),
            unit: item.unit,
            unit_price: parseFloat(item.unit_price),
            discount_amount: parseFloat(item.discount_amount || 0),
            stock_batch_id: item.stock_batch_id,
            mrp_stock_lot_id: item.mrp_stock_lot_id
        }));
    }

    function requestedRawQuantity(item) {
        const qty = parseFloat(item.quantity || 0);
        const unitQty = Math.max(parseFloat(item.uqty || 1), 1);
        return ['No.s', 'Nos.'].includes(item.unit) ? qty : qty * unitQty;
    }

    function maxDisplayQuantity(item) {
        const rawStock = Math.max(parseFloat(item.raw_stock || 0), 0);
        const unitQty = Math.max(parseFloat(item.uqty || 1), 1);
        return ['No.s', 'Nos.'].includes(item.unit) ? rawStock : (rawStock / unitQty);
    }

    function stockProblemItems() {
        return cart.filter(item => requestedRawQuantity(item) > parseFloat(item.raw_stock || 0) + 0.00001);
    }

    function enforceCartStock(options = {}) {
        const notify = options.notify !== false;
        const problems = stockProblemItems();
        if (!problems.length) return true;

        const notified = new Set();
        problems.forEach(item => {
            const maxQty = maxDisplayQuantity(item);
            const key = item.product_id + ':' + (item.stock_batch_id || '') + ':' + (item.mrp_stock_lot_id || '');
            if (allowOutOfStockSale) {
                if (notify && !notified.has(key)) {
                    const message = maxQty <= 0
                        ? `${item.name} is out of stock. Sale setting allows continuing.`
                        : `${item.name} exceeds stock. Sale setting allows continuing.`;
                    toastr.warning(message);
                    notified.add(key);
                }
                return;
            }

            if (notify && !notified.has(key)) {
                toastr.warning(`${item.name} has only ${money(maxQty)} ${item.unit} available.`);
                notified.add(key);
            }
            item.quantity = maxQty > 0 ? maxQty : 0;
        });

        if (!allowOutOfStockSale) {
            cart = cart.filter(item => parseFloat(item.quantity || 0) > 0);
        }

        return allowOutOfStockSale;
    }

    function payments() {
        return $('.payment-amount').map(function () {
            return {
                method: $(this).data('method'),
                banking_id: bankId(),
                amount: parseFloat($(this).val() || 0),
                reference_no: null
            };
        }).get().filter(payment => payment.amount > 0);
    }

    function calculate() {
        if (!cart.length) {
            totals = {amount: 0, discount: 0, gst: 0, round: 0, amount_payable: 0};
            updateTotals();
            return;
        }
        $.post('{{ route('pos.calculate') }}', {sa_type: $('#saleType').val(), sale_items: saleItems()})
            .done(data => { totals = data; updateTotals(); })
            .fail(xhr => toastr.error(xhr.responseJSON?.message || 'Could not calculate cart.'));
    }

    function updateTotals() {
        $('#totalAmount').text(money(totals.amount));
        $('#totalDiscount').text(money(totals.discount));
        $('#totalGst').text(money(totals.gst));
        $('#totalRound').text(money(totals.round));
        $('#totalPayable').text(money(totals.amount_payable));
    }

    function validationMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors || {};
        const firstKey = Object.keys(errors)[0];
        return firstKey ? errors[firstKey][0] : (xhr.responseJSON?.message || fallback);
    }

    function customerOptionLabel(customer) {
        return customer.customer + (customer.phone ? ' - ' + customer.phone : '');
    }

    function customerAlert(icon, title, text) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            confirmButtonText: 'OK',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary btn-flat'
            }
        });
    }

    function customerToast(icon, title) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: toast => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: icon,
            title: title
        });
    }

    function customerAddedAlert(label) {
        customerToast('success', `${label} added successfully`);
    }

    let activeNumericInput = null;

    function commitNumericInput() {
        if (activeNumericInput && activeNumericInput.length && $.contains(document, activeNumericInput[0])) {
            activeNumericInput.trigger('input').trigger('change');
        }
    }

    function showTouchKeypad($input) {
        if (activeNumericInput && activeNumericInput[0] !== $input[0]) {
            commitNumericInput();
        }

        activeNumericInput = $input;
        $('#touchKeypadLabel').text($input.data('keypad-label') || $input.attr('placeholder') || 'Number');
        $('#touchKeypad [data-keypad-value="."]').prop('disabled', $input.data('keypad-mode') === 'integer');
    }

    function clearTouchKeypadTarget(commit = true) {
        if (commit) {
            commitNumericInput();
        }

        activeNumericInput = null;
        $('#touchKeypadLabel').text('Number');
        $('#touchKeypad [data-keypad-value="."]').prop('disabled', false);
    }

    function setKeypadValue(value) {
        if (!activeNumericInput || !activeNumericInput.length) return;

        const maxLength = parseInt(activeNumericInput.attr('maxlength') || 0, 10);
        activeNumericInput.val(String(value || '').slice(0, maxLength || undefined));
    }

    function appendKeypadValue(value) {
        if (!activeNumericInput || !activeNumericInput.length) return;

        const mode = activeNumericInput.data('keypad-mode') || 'decimal';
        let current = String(activeNumericInput.val() || '');

        if (value === '.' && (mode === 'integer' || current.includes('.'))) return;
        if (value === '.' && current === '') current = '0';
        if (value === '00' && current === '') value = '0';

        setKeypadValue(current + value);
    }

    function focusNextNumericInput() {
        const inputs = $('.touch-number:visible:not(:disabled)');
        if (!inputs.length) return;

        const currentIndex = activeNumericInput && activeNumericInput.length ? inputs.index(activeNumericInput[0]) : -1;
        const nextIndex = (currentIndex + 1) % inputs.length;

        commitNumericInput();

        const refreshedInputs = $('.touch-number:visible:not(:disabled)');
        const next = refreshedInputs.eq(Math.min(nextIndex, refreshedInputs.length - 1));

        if (next.length) {
            next.trigger('focus');
            if (next.is('input')) {
                next[0].select();
            }
        }
    }

    $(document).on('focusin click', '.touch-number', function () {
        showTouchKeypad($(this));
    });

    $('#touchKeypad').on('mousedown touchstart', function (event) {
        event.stopPropagation();
    });

    $('#touchKeypad').on('click', '[data-keypad-value]', function () {
        appendKeypadValue(String($(this).data('keypad-value')));
    });

    $('#touchKeypad').on('click', '[data-keypad-action]', function () {
        const action = $(this).data('keypad-action');

        if (action === 'back') {
            setKeypadValue(String(activeNumericInput ? activeNumericInput.val() : '').slice(0, -1));
            return;
        }

        if (action === 'clear') {
            setKeypadValue('');
            return;
        }

        if (action === 'tab') {
            focusNextNumericInput();
            return;
        }

        if (action === 'done' || action === 'enter') {
            clearTouchKeypadTarget();
        }
    });

    $(document).on('mousedown touchstart', function (event) {
        if ($(event.target).closest('#touchKeypad, .touch-number').length) return;
        clearTouchKeypadTarget();
    });

    function requireSession() {
        if (!posSession) {
            $('#sessionModal').modal('show');
            toastr.warning('Open a POS session first.');
            return false;
        }
        return true;
    }

    let searchTimer = null;
    let productSearchRequest = null;
    let productSearchVersion = 0;

    function cancelProductSearch() {
        clearTimeout(searchTimer);
        productSearchVersion++;
        if (productSearchRequest) {
            productSearchRequest.abort();
            productSearchRequest = null;
        }
    }

    function searchProducts(submitted = false) {
        cancelProductSearch();
        const query = $('#productSearch').val().trim();
        $('#productResults').empty();
        if (!query) return;
        const version = productSearchVersion;
        const search = () => {
            productSearchRequest = $.get('{{ route('pos.products') }}', {q: query}).done(data => {
                if (version !== productSearchVersion || $('#productSearch').val().trim() !== query) return;
                const products = data.products || [];
                renderProducts(products);
                const exact = products.find(product => [product.barcode, product.product_code]
                    .some(code => code != null && String(code) === query));
                const product = exact || (submitted && products.length === 1 ? products[0] : null);
                if (product) {
                    cancelProductSearch();
                    addProduct(product);
                    $('#productSearch').val('').focus();
                    $('#productResults').empty();
                } else if (submitted && !products.length) {
                    toastr.warning('No product found for this barcode.');
                }
            }).fail((xhr, status) => {
                if (version === productSearchVersion && status !== 'abort') {
                    toastr.error('Could not load products. Please scan again.');
                }
            }).always(() => {
                if (version === productSearchVersion) productSearchRequest = null;
            });
        };
        if (submitted) search();
        else searchTimer = setTimeout(search, 120);
    }

    $('#productSearch').on('input', function () {
        searchProducts();
    }).on('keydown', function (event) {
        if (!['Enter', 'Tab'].includes(event.key) || !$(this).val().trim()) return;
        event.preventDefault();
        if (!event.repeat) searchProducts(true);
    });

    $('#productResults').on('click', '.product-tile', function () {
        cancelProductSearch();
        addProduct($(this).data('product'));
        $('#productSearch').val('').focus();
        $('#productResults').empty();
    });

    $('#cartBody').on('input', '.qty-input,.disc-input', function () {
        const index = $(this).data('index');
        if ($(this).hasClass('qty-input')) {
            cart[index].quantity = parseFloat($(this).val() || 0);
            if (cart[index].auto_mrp_discount) {
                cart[index].discount_amount = (cart[index].quantity * cart[index].unit_price * cart[index].margin_percentage) / 100;
            }
        }
        if ($(this).hasClass('disc-input')) {
            cart[index].discount_amount = parseFloat($(this).val() || 0);
            cart[index].auto_mrp_discount = false;
        }
        enforceCartStock({notify: false});
        renderCart();
        calculate();
    });

    $('#cartBody').on('click', '.remove-item', function () {
        cart.splice($(this).data('index'), 1);
        renderCart();
        calculate();
    });

    $('#saleType').on('change', calculate);

    $('#customerModal').on('shown.bs.modal', function () {
        $('#newCustomerName').trigger('focus');
    });

    $('#saveCustomerBtn').on('click', function () {
        const $button = $(this);
        const payload = {
            customer: $('#newCustomerName').val().trim(),
            phone: $('#newCustomerPhone').val().trim()
        };

        if (!payload.customer || !payload.phone) {
            customerAlert('warning', 'Missing details', 'Customer name and phone are required.');
            return;
        }

        $button.prop('disabled', true);

        $.post('{{ route('pos.customers.store') }}', payload)
            .done(customer => {
                const label = customerOptionLabel(customer);
                $('#customerId').append(new Option(label, customer.id, true, true));
                $('#newCustomerName, #newCustomerPhone').val('');
                $('#customerModal').one('hidden.bs.modal', () => customerAddedAlert(label));
                $('#customerModal').modal('hide');
            })
            .fail(xhr => customerToast('error', validationMessage(xhr, 'Could not add customer.')))
            .always(() => $button.prop('disabled', false));
    });

    $('#closeSessionBtn').on('click', function () {
        if (!posSession) return;
        const $button = $(this).prop('disabled', true);
        $.post('{{ url('pos/sessions') }}/' + posSession.id + '/close', {
            closing_cash: $('#closingCash').val(),
            closing_note: $('#closingNote').val()
        }).done(() => {
            window.location.href = dashboardUrl;
        }).fail(xhr => {
            $button.prop('disabled', false);
            toastr.error(xhr.responseJSON?.message || 'Could not close session.');
        });
    });

    $('#saveBtn').on('click', function () {
        if (!requireSession() || !cart.length) return;
        if (!enforceCartStock({notify: false})) {
            renderCart();
            calculate();
            return;
        }
        $.post('{{ route('pos.sales.store') }}', {
            pos_session_id: posSession.id,
            sale_date: '{{ now()->toDateString() }}',
            sa_customer: $('#customerId').val(),
            sa_type: $('#saleType').val(),
            sa_state_code: $('#stateCode').val(),
            sale_items: saleItems(),
            payments: payments()
        }).done(data => {
            toastr.success(data.message);
            cart = [];
            renderCart();
            calculate();
            $('.payment-amount').val('');
            window.open(data.sale.receipt_url, '_blank', 'width=420,height=720');
        }).fail(xhr => toastr.error(xhr.responseJSON?.message || 'Sale could not be saved.'));
    });

    $('#holdBtn').on('click', function () {
        if (!requireSession() || !cart.length) return;
        if (!enforceCartStock({notify: false})) {
            renderCart();
            calculate();
            return;
        }
        $.post('{{ route('pos.holds.store') }}', {
            pos_session_id: posSession.id,
            sa_customer: $('#customerId').val(),
            customer_name: $('#customerId option:selected').text(),
            sa_type: $('#saleType').val(),
            sale_items: saleItems(),
            payments: payments()
        }).done(() => {
            cart = [];
            renderCart();
            calculate();
            toastr.success('Bill held.');
        }).fail(xhr => toastr.error(xhr.responseJSON?.message || 'Could not hold bill.'));
    });

    $('#recallBtn').on('click', function () {
        if (!requireSession()) return;
        $.get('{{ route('pos.holds.index') }}', {pos_session_id: posSession.id}).done(data => {
            $('#holdList').html((data.holds || []).map(hold => `
                <button type="button" class="list-group-item list-group-item-action recall-hold" data-id="${hold.id}">
                    <strong>${escapeHtml(hold.hold_no)}</strong>
                    <span class="float-right">${money(hold.amount_payable)}</span>
                    <div class="small text-muted">${escapeHtml(hold.customer_name || '')}</div>
                </button>
            `).join('') || '<div class="text-muted">No held bills</div>');
            $('#holdModal').modal('show');
        });
    });

    $('#holdList').on('click', '.recall-hold', function () {
        const $button = $(this).prop('disabled', true);
        const id = $(this).data('id');
        $.get('{{ url('pos/holds') }}/' + id).done(data => {
            const hold = data.hold;
            cart = (data.items || []).map(item => ({
                product_id: item.product_id || item.product_code,
                name: item.name || item.product || item.product_id,
                quantity: parseFloat(item.quantity || 0),
                unit: item.unit || 'No.s',
                uqty: parseFloat(item.uqty || 1),
                unit_price: parseFloat(item.unit_price || 0),
                discount_amount: parseFloat(item.discount_amount || 0),
                gst: parseFloat(item.gst || 0),
                raw_stock: parseFloat(item.raw_stock || 0),
                current_stock: parseFloat(item.current_stock || 0),
                stock_batch_id: item.stock_batch_id || null,
                mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                stock_mrp: item.stock_mrp ? parseFloat(item.stock_mrp) : null,
                margin_percentage: parseFloat(item.margin_percentage || 0),
                auto_mrp_discount: false
            }));

            if (hold.customer_id) {
                if (!$('#customerId option[value="' + hold.customer_id + '"]').length) {
                    $('#customerId').append(new Option(hold.customer_name || 'Customer', hold.customer_id));
                }
                $('#customerId').val(String(hold.customer_id));
            }
            $('#saleType').val(String(hold.cart?.sa_type || '1'));
            $('.payment-amount').val('');
            (hold.payments || []).forEach(payment => {
                const $input = $('.payment-amount[data-method="' + payment.method + '"]');
                $input.val(money(parseFloat($input.val() || 0) + parseFloat(payment.amount || 0)));
                if (payment.banking_id) $('#paymentBank').val(String(payment.banking_id));
            });

            renderCart();
            calculate();
            $('#holdModal').modal('hide');
            $.ajax({url: '{{ url('pos/holds') }}/' + id, method: 'DELETE'})
                .fail(() => toastr.warning('Bill recalled, but could not be removed from the held list.'));
            toastr.success('Held bill recalled.');
        }).fail(xhr => {
            $button.prop('disabled', false);
            toastr.error(xhr.responseJSON?.message || 'Could not recall the held bill.');
        });
    });
</script>
</body>
</html>
