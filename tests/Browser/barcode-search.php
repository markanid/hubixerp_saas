<?php
// Run: php -S 127.0.0.1:8765 tests/Browser/barcode-search.php
// Open http://127.0.0.1:8765/?mode=sale and /?mode=pos. No database is used.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/jquery.js' => '/public/admin-assets/plugins/jquery/jquery.min.js',
    '/select2.js' => '/public/admin-assets/plugins/select2/js/select2.full.js',
    '/select2.css' => '/public/admin-assets/plugins/select2/css/select2.css',
    '/sale.js' => '/public/js/sale.js',
    '/purchase.js' => '/public/js/purchase.js',
    '/estimation.js' => '/public/js/estimation.js',
];
if (isset($assets[$path])) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'application/javascript'));
    readfile($root.$assets[$path]);
    exit;
}
if ($path === '/pos-search.js') {
    header('Content-Type: application/javascript');
    $view = file_get_contents($root.'/Modules/Pos/resources/views/pos/index.blade.php');
    $start = strpos($view, '    let searchTimer = null;');
    $end = strpos($view, "    $('#cartBody').on('input'", $start);
    echo str_replace("{{ route('pos.products') }}", '/product-search', substr($view, $start, $end - $start));
    exit;
}
if ($path !== '/') {
    http_response_code(404);
    exit;
}
$mode = in_array($_GET['mode'] ?? 'sale', ['sale', 'pos', 'purchase', 'estimation'], true)
    ? $_GET['mode']
    : 'sale';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Barcode search regression checks</title>
<link rel="stylesheet" href="/select2.css">
<style>body{font:16px sans-serif;max-width:900px;margin:35px}input,select,button{margin:8px;padding:8px}#results{white-space:pre-wrap}#addProductModal{display:none}</style>
</head><body>
<h1><?= ucfirst($mode) ?> barcode regression checks</h1>
<p>Fixture products and delayed search responses; no sales or products are saved.</p>
<button id="run">Run regression checks</button>
<?php if ($mode !== 'pos'): ?>
<label>Product <select id="product" style="width:350px"></select></label>
<label>Loaded product <input id="product_name" readonly></label>
<input id="product_id" type="hidden"><input id="product_code" type="hidden">
<input id="unit_qty" type="hidden"><input id="unit" type="hidden"><input id="hsn_code" type="hidden">
<input id="gst" type="hidden"><input id="purchase_price" type="hidden"><input id="p_price" type="hidden">
<input id="sale_price" type="hidden"><input id="mrp" type="hidden"><input id="margin" type="hidden">
<input id="amt_margin" type="hidden"><input id="current_stock" type="hidden"><input id="in_stock" type="hidden">
<input id="percentage_discount" type="hidden"><input id="amount_discount" type="hidden"><input id="unit_price" type="hidden">
<select id="item_unit"></select><div id="mrp_lot_selector_group"><select id="mrp_stock_lot_id"></select></div>
<div id="addProductModal">Create New Product</div>
<?php else: ?>
<label>Barcode <input id="productSearch"></label><div id="productResults"></div>
<div id="cart"></div>
<?php endif; ?>
<pre id="results">Ready</pre>
<script src="/jquery.js"></script><script src="/select2.js"></script>
<script>
    const mode = <?= json_encode($mode) ?>;
    const fixtures = [
        {id: 1, product_code: 'PRD_001', product: 'Fixture One', bar_code: '001234567890', barcode: '001234567890', pprice: 15, price: 20, mrp: 25, margin: 20, amt_margin: 5, gst: 5, uqty: 1, unit: 'No.s', current_stock: 10},
        {id: 2, product_code: 'PRD_002', product: 'Fixture Two', bar_code: 'ABC-002', barcode: 'ABC-002', pprice: 22, price: 30, mrp: 35, margin: 14.29, amt_margin: 5, gst: 5, uqty: 1, unit: 'No.s', current_stock: 10}
    ];
    const notices = [];
    const added = [];
    let modalOpened = 0;
    let responseDelay = 400;
    const saleSearchRoute = '/product-search';
    const purchaseSearchRoute = '/product-search';
    const estimationSearchRoute = '/product-search';
    const estimationProductMrpLotsRoute = '/unused/__PRODUCT__';
    const estimationAccountEffect = false;
    const useEstimationMrpPricingMode = false;
    window.batchInventoryMode = false;
    window.mrpInventoryMode = false;
    window.purchaseCollectTax = true;
    window.saleCollectTax = true;
    const toastr = {warning: text => notices.push(text), error: text => notices.push(text)};
    $.fn.datetimepicker = function () { return this; };
    $.fn.modal = function (action) {
        if (this.is('#addProductModal') && action === 'show') modalOpened++;
        return this.toggle(action === 'show');
    };
    function renderProducts(products) {
        $('#productResults').empty();
        products.forEach(product => $('<button class="product-tile">').text(product.product).data('product', product).appendTo('#productResults'));
    }
    function addProduct(product) {
        added.push(product.id);
        $('#cart').text('Loaded: ' + added.join(', '));
    }
    $.ajaxTransport('+json', function (options) {
        if (!options.url.startsWith('/product-search')) return;
        let timer;
        return {
            send: function (headers, complete) {
                const params = new URL(options.url, location.origin).searchParams;
                const term = (params.get('query') || params.get('q') || '').trim();
                let products = fixtures.filter(item => [item.bar_code, item.product_code, item.product].some(value => value.includes(term)));
                if (!term || term === '::create-action-only::') products = [];
                timer = setTimeout(() => complete(term === 'FAIL' ? 500 : 200, 'OK', {
                    json: mode === 'pos' ? {products} : products
                }), responseDelay);
            },
            abort: function () { clearTimeout(timer); }
        };
    });
    // POS uses $.get without an explicit response type; request fixture JSON.
    $.ajaxPrefilter(function (options) {
        if (options.url.startsWith('/product-search')) options.dataTypes = ['json'];
    });
    window.addEventListener('error', event => { $('#results').append('\nSCRIPT ERROR: ' + event.message); });
</script>
<script src="/<?= $mode === 'pos' ? 'pos-search' : $mode ?>.js"></script>
<script>
    const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
    function assert(condition, message) {
        if (!condition) throw new Error(message);
        $('#results').append('\nPASS: ' + message);
    }
    function input(field, value) {
        field.value = value;
        field.dispatchEvent(new Event('input', {bubbles: true}));
    }
    function key(field, value) {
        const code = {Enter: 13, Tab: 9, ArrowDown: 40}[value];
        field.dispatchEvent(new KeyboardEvent('keydown', {key: value, keyCode: code, which: code, bubbles: true, cancelable: true}));
        field.dispatchEvent(new KeyboardEvent('keyup', {key: value, keyCode: code, which: code, bubbles: true, cancelable: true}));
    }
    async function saleField() {
        $('#product').select2('open');
        await pause(450);
        return document.querySelector('.select2-container--open .select2-search__field');
    }
    $('#run').on('click', async function () {
        this.disabled = true;
        $('#results').text('Running ' + mode + ' checks...');
        try {
            if (mode !== 'pos') {
                let field = await saleField();
                input(field, '001234567890');
                key(field, 'Enter');
                assert(modalOpened === 0, 'Fast scanner Enter does not open Create New Product');
                await pause(700);
                assert($('#product_id').val() === '1' && $('#product_name').val() === 'Fixture One', 'Delayed barcode response loads the scanned product');
                field = await saleField();
                input(field, 'ABC-002');
                key(field, 'Tab');
                await pause(700);
                assert($('#product_id').val() === '2', 'Alphanumeric barcode with Tab loads the product');
                field = await saleField();
                input(field, 'ABC-002');
                await pause(800);
                $('#product_name').val('');
                key(field, 'Enter');
                await pause(700);
                assert($('#product_name').val() === 'Fixture Two', 'A ready barcode result reloads an already selected product');
                field = await saleField();
                input(field, 'UNKNOWN');
                key(field, 'Enter');
                await pause(800);
                assert(modalOpened === 0 && notices.some(text => text.includes('No product found')), 'Unknown barcode shows a warning without opening creation');
                $('#product').select2('close');
                field = await saleField();
                input(field, '001234567890');
                key(field, 'Enter');
                input(field, 'ABC-002');
                key(field, 'Enter');
                await pause(750);
                assert($('#product_id').val() === '2', 'Superseded scan cannot load the previous product');
                field = await saleField();
                input(field, 'Fixture');
                await pause(800);
                key(field, 'ArrowDown');
                key(field, 'Enter');
                await pause(100);
                assert($('#product_id').val() === '2', 'Manual keyboard selection of the second match still works');
                field = await saleField();
                input(field, 'FAIL');
                key(field, 'Enter');
                await pause(700);
                assert(notices.some(text => text.includes('Could not load')), 'Lookup failure reports an error without product creation');
                input(field, 'UNKNOWN');
                await pause(800);
                $('.select2-results__option').filter(function () { return $(this).text().includes('Create New Product'); }).trigger('mouseup');
                assert(modalOpened === 1, 'Explicit Create New Product selection still opens the modal');
            } else {
                const field = document.querySelector('#productSearch');
                input(field, '001234567890');
                key(field, 'Enter');
                await pause(700);
                assert(added.join(',') === '1', 'Scanner Enter adds the exact product once');
                input(field, 'ABC-002');
                key(field, 'Tab');
                await pause(700);
                assert(added.join(',') === '1,2', 'Alphanumeric barcode with Tab adds the exact product');
                input(field, 'Fixture One');
                await pause(700);
                assert(added.length === 2 && $('.product-tile').length === 1, 'Partial name search displays a choice without premature insertion');
                input(field, '001234567890');
                await pause(150);
                input(field, 'ABC-002');
                await pause(750);
                assert(added.join(',') === '1,2,2', 'Old response cannot add a product after the query changes');
                input(field, 'UNKNOWN');
                key(field, 'Enter');
                await pause(700);
                assert(added.length === 3 && notices.some(text => text.includes('No product found')), 'Unknown barcode does not add an item');
                input(field, 'FAIL');
                key(field, 'Enter');
                await pause(700);
                assert(notices.some(text => text.includes('Could not load')), 'Lookup failure is reported');
                input(field, '001234567890');
                await pause(700);
                assert(added.join(',') === '1,2,2,1', 'Scanner without a terminator resolves an exact barcode');
                input(field, 'Fixture');
                await pause(700);
                $('.product-tile').last().trigger('click');
                await pause(200);
                assert(added.join(',') === '1,2,2,1,2' && field.value === '', 'Manual tile selection still adds and clears the search');
            }
            $('#results').append('\nALL CHECKS PASSED');
        } catch (error) {
            $('#results').append('\nFAIL: ' + error.message);
        }
    });
</script></body></html>
