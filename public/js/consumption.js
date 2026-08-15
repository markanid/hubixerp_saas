$(document).ready(function() {
    let itemCount = 0;
    let consumptionItems = [];

    function showConsumptionAlert(icon, title, text) {
        if (typeof $ !== 'undefined' && typeof $(document).Toasts === 'function') {
            $(document).Toasts('create', {
                class: 'bg-warning',
                title: title || 'Warning',
                body: text || title,
                autohide: true,
                delay: 4000
            });
            return;
        }

        if (typeof toastr !== 'undefined') {
            toastr.warning(text || title, title || 'Warning');
            return;
        }

        console.warn(text ? `${title}: ${text}` : title);
    }

    $("#addConsumption").submit(function(event) {
        event.preventDefault();

        if (!Array.isArray(consumptionItems) || consumptionItems.length === 0) {
            showConsumptionAlert('warning', 'No Items Added', 'Please add at least one item before submitting the form.');
            return false;
        }

        $("#consumption_items").val(JSON.stringify(consumptionItems));
        this.submit();
    });

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).on('focus', '.select2-selection.select2-selection--single', function() {
        const selectElement = $(this).closest('.select2-container').prev('select');
        if (!selectElement.data('select2-opened')) {
            selectElement.select2('open');
            selectElement.data('select2-opened', true);
        }
    });

    $(document).on('select2:close', function(e) {
        $(e.target).data('select2-opened', false);
    });

    $(document).on('select2:open', function() {
        const searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    });

    function initializeProductSelect() {
        $('#product').select2({
            minimumInputLength: 1,
            ajax: {
                url: consumptionSearchRoute,
                type: 'GET',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        query: params.term,
                        searchBy: 'product'
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.id,
                                text: item.product_code + ' - ' + item.product,
                                product_code: item.product_code,
                                product_name: item.product,
                                hsn_code: item.hsn_code,
                                price: item.price,
                                unit: item.unit,
                                unit_qty: item.uqty,
                                current_stock: item.current_stock,
                                is_batch_managed: item.is_batch_managed
                            };
                        })
                    };
                },
                cache: true
            }
        });

        $('#product').on('select2:select', function(e) {
            const data = e.params.data;

            $('#product_id').val(data.id);
            $('#product_code').val(data.product_code);
            $('#product_name').val(data.product_name);
            $('#unit_qty').val(data.unit_qty);
            $('#unit').val(data.unit);
            $('#hsn_code').val(data.hsn_code);
            $('#unit_price').val(data.price);
            $('#price').val(data.price);
            $('#current_stock').val(data.current_stock);
            $('#in_stock').val(data.current_stock);
            $('#product').data('batch-managed', data.is_batch_managed === true || data.is_batch_managed == 1);
            $('#product').data('product-db-id', data.id);
            populateUnitDropdown(data.unit_qty);
            loadBatches(data.id, $('#product').data('batch-managed'));
            loadMrpLots(data.id);
        });
    }

    function loadBatches(productId, isBatchManaged) {
        const $group = $('#batch_selector_group');
        const $select = $('#stock_batch_id');

        if (window.batchInventoryMode !== true || !isBatchManaged || !$select.length) {
            $group.hide();
            $select.html('<option value="">Auto FIFO / earliest expiry</option>');
            return;
        }

        $group.show();
        $select.html('<option value="">Auto FIFO / earliest expiry</option>');
        $.getJSON(productBatchesRoute.replace('__PRODUCT__', productId), function(batches) {
            batches.forEach(function(batch) {
                const text = `${batch.batch_no} | Exp: ${batch.expiry_date || 'N/A'} | Qty: ${batch.display_quantity}`;
                $select.append(new Option(text, batch.id, false, false));
            });
        });
    }

    function loadMrpLots(productId) {
        const $group = $('#mrp_lot_selector_group');
        const $select = $('#mrp_stock_lot_id');
        if (window.mrpInventoryMode !== true || !$select.length) {
            $group.hide();
            return;
        }

        $group.show();
        $select.html('<option value="">Select Stock / MRP</option>');
        $.getJSON(productMrpLotsRoute.replace('__PRODUCT__', productId), function(lots) {
            lots.forEach(function(lot) {
                const text = `${lot.purchase_voucher || 'Opening/Return'} | ${lot.purchase_date || ''} | MRP: ${Number(lot.mrp).toFixed(2)} | Sale: ${Number(lot.sale_price).toFixed(2)}`;
                const option = new Option(text, lot.id);
                $(option).attr('data-mrp', lot.mrp).attr('data-rate', lot.display_purchase_rate).attr('data-available', lot.display_quantity);
                $select.append(option);
            });
            if (lots.length === 1) $select.val(String(lots[0].id)).trigger('change');
        });
    }

    function populateUnitDropdown(unitQty) {
        const $itemUnit = $('#item_unit');
        const unit = $('#unit').val();
        $itemUnit.empty();

        if (Number(unitQty) === 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
        } else if (Number(unitQty) > 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
            $itemUnit.append('<option value="No.s">No.s</option>');
        }
    }

    function refreshConsumptionDisplayedStock() {
        const packageStock = parseFloat($('#current_stock').val()) || 0;
        const unitQty = Math.max(parseFloat($('#unit_qty').val()) || 1, 1);
        const selectedUnit = $('#item_unit').val();
        $('#in_stock').val((selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? packageStock * unitQty : packageStock);
    }

    function resetItemInputs() {
        $('#product').val(null).empty().trigger('change');
        $('#product_id, #product_code, #product_name, #hsn_code, #unit_price, #quantity, #remark, #price, #current_stock, #unit_qty, #unit, #in_stock').val('');
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        $('#stock_batch_id').val('').trigger('change');
        $('#batch_selector_group').hide();
        $('#mrp_stock_lot_id').val('').trigger('change');
        $('#mrp_lot_selector_group').hide();
        $('#product').removeData('batch-managed').removeData('product-db-id');
    }

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function batchLabel(item) {
        if (item.batch_no) {
            return item.batch_no;
        }

        if (item.stock_batch_id) {
            return 'Selected batch';
        }

        return 'Auto FIFO';
    }

    function renderRow(item, index) {
        const batchCell = window.batchInventoryMode ? `<td>${batchLabel(item)}</td>` : '';
        const mrpLotCell = window.mrpInventoryMode ? `<td>${item.mrp_lot_label || (item.stock_mrp ? 'MRP ' + Number(item.stock_mrp).toFixed(2) : '')}</td>` : '';
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${item.product_name || ''}</td>
                <td>${item.hsn_code || ''}</td>
                ${batchCell}
                ${mrpLotCell}
                <td><input type="number" class="form-control quantity" value="${item.quantity}" data-index="${index}"></td>
                <td><input type="number" class="form-control unit_price" value="${item.unit_price}" data-index="${index}"></td>
                <td class="final_total text-right">${formatCurrency(item.final_total)}</td>
                <td><input type="text" class="form-control remark" value="${item.remark || ''}" data-index="${index}"></td>
                <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
            </tr>
        `;
    }

    function renderTable() {
        itemCount = consumptionItems.length;
        const rows = consumptionItems.map((item, index) => renderRow(item, index)).join('');
        $('#consumption_table tbody').html(rows);
        calculateTotals();
    }

    initializeProductSelect();
    if ($('#stock_batch_id').length) {
        $('#stock_batch_id').select2({ width: '100%' });
    }
    if ($('#mrp_stock_lot_id').length) {
        $('#mrp_stock_lot_id').select2({ width: '100%' });
        $('#mrp_stock_lot_id').on('change', function () {
            const $option = $(this).find('option:selected');
            if (!$(this).val()) return;
            $('#current_stock').val(parseFloat($option.data('available')) || 0);
            refreshConsumptionDisplayedStock();
            $('#price, #unit_price').val((parseFloat($option.data('rate')) || 0).toFixed(2));
        });
    }

    $('#item_unit').change(function() {
        const selectedUnit = $(this).val();
        const purchasePrice = parseFloat($('#price').val());
        const unitQty = parseFloat($('#unit_qty').val());
        const inStock = parseFloat($('#current_stock').val());

        if (!isNaN(purchasePrice) && !isNaN(unitQty) && unitQty > 1) {
            const newPurchasePrice = (selectedUnit === 'No.s' || selectedUnit === 'Nos.')
                ? purchasePrice / unitQty
                : purchasePrice;
            $('#unit_price').val(newPurchasePrice.toFixed(2));
        }

        refreshConsumptionDisplayedStock();

        $('#quantity').val('');
    });

    $('#quantity').on('input', function() {
        const quantity = parseFloat($(this).val());
        const inStock = parseFloat($('#in_stock').val());

        if (!isNaN(quantity) && !isNaN(inStock) && quantity > inStock) {
            showConsumptionAlert('warning', 'Insufficient Stock', 'Entered quantity exceeds available stock.');
            $(this).focus();
        }
    });

    let existingItems = $('#existing_items').val();
    if (existingItems) {
        existingItems = JSON.parse(existingItems);
        consumptionItems = existingItems.map(function(item) {
            return {
                product_id: item.cond_itemid,
                product_name: item.product ? item.product.product : '',
                hsn_code: item.cond_hsn,
                quantity: item.cond_qty,
                unit: item.cond_unit,
                unit_price: item.cond_uprice,
                unit_qty: item.cond_uqty,
                final_total: item.cond_total,
                remark: item.cond_remark || '',
                stock_batch_id: item.stock_batch_id || null,
                mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                stock_mrp: item.stock_mrp || null,
                mrp_lot_label: item.mrp_stock_lot
                    ? `${item.mrp_stock_lot.purchase_voucher || 'Purchase'} | MRP ${Number(item.stock_mrp || 0).toFixed(2)}`
                    : null,
                batch_no: item.batch_no || null
            };
        });
        renderTable();
    }

    $('.addBtn').on('click keypress', function(e) {
        e.preventDefault();

        const productId = $('#product_code').val();
        const product = $('#product_name').val();
        const hsnCode = $('#hsn_code').val();
        const quantity = parseFloat($('#quantity').val());
        const unit = $('#item_unit').val();
        const unitPrice = parseFloat($('#unit_price').val());
        const unitQty = parseFloat($('#unit_qty').val()) || 1;
        const remark = $('#remark').val();
        const stockBatchId = window.batchInventoryMode === true && $('#product').data('batch-managed')
            ? ($('#stock_batch_id').val() || null)
            : null;
        const batchText = stockBatchId ? $('#stock_batch_id option:selected').text() : 'Auto FIFO';
        const mrpStockLotId = window.mrpInventoryMode === true ? ($('#mrp_stock_lot_id').val() || null) : null;
        const mrpLotText = mrpStockLotId ? $('#mrp_stock_lot_id option:selected').text() : '';

        if (!productId || !product || !unit || isNaN(unitPrice) || isNaN(quantity) || quantity <= 0) {
            showConsumptionAlert('warning', 'Missing Fields', 'Please fill in required fields (Product, Unit Price, Quantity).');
            return;
        }
        if (window.mrpInventoryMode === true && !mrpStockLotId) {
            showConsumptionAlert('warning', 'Stock Lot Required', 'Select the MRP stock lot being consumed.');
            return;
        }

        const availableStock = parseFloat($('#in_stock').val());
        if (!isNaN(availableStock) && quantity > availableStock) {
            showConsumptionAlert('warning', 'Insufficient Stock', 'This product cannot be added because the entered quantity exceeds available stock.');
            $('#quantity').focus();
            return;
        }

        const finalTotal = (unitPrice * quantity).toFixed(2);
        consumptionItems.push({
            product_id: productId,
            product_name: product,
            hsn_code: hsnCode,
            quantity: quantity,
            unit: unit,
            unit_price: unitPrice,
            unit_qty: unitQty,
            final_total: finalTotal,
            remark: remark,
            stock_batch_id: stockBatchId,
            mrp_stock_lot_id: mrpStockLotId,
            stock_mrp: mrpStockLotId ? ($('#mrp_stock_lot_id option:selected').data('mrp') || null) : null,
            mrp_lot_label: mrpLotText,
            batch_no: $('#product').data('batch-managed') ? batchText : ''
        });

        renderTable();
        resetItemInputs();
    });

    $(document).on('input', '.unit_price, .quantity, .remark', function() {
        const row = $(this).closest('tr');
        const index = Number($(this).data('index'));
        const newUnitPrice = parseFloat(row.find('.unit_price').val()) || 0;
        const newQuantity = parseFloat(row.find('.quantity').val()) || 0;
        const newRemark = row.find('.remark').val();

        if (newUnitPrice <= 0 || newQuantity <= 0) {
            return;
        }

        const newTotal = (newUnitPrice * newQuantity).toFixed(2);
        consumptionItems[index].unit_price = newUnitPrice;
        consumptionItems[index].quantity = newQuantity;
        consumptionItems[index].final_total = newTotal;
        consumptionItems[index].remark = newRemark;

        row.find('.final_total').text(formatCurrency(newTotal));
        calculateTotals();
    });

    $(document).on('click', '.deleteRow', function() {
        const rowIndex = $(this).closest('tr').index();
        consumptionItems.splice(rowIndex, 1);
        renderTable();
    });

    function calculateTotals() {
        const totalAmount = consumptionItems.reduce(function(total, item) {
            return total + (parseFloat(item.final_total) || 0);
        }, 0);

        $('#amount').val(totalAmount.toFixed(2));
        $('#amount_payable1').html(formatCurrency(totalAmount));
        $('#footer_total_amount').text(formatCurrency(totalAmount));
    }

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });
});
