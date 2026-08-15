$(document).ready(function() {
    let itemCount = 0;
    let returnItems = [];
    let lastLoadedPurchase = null;

    function showPurchaseReturnAlert(icon, title, text) {
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

    $("#addPurchaseReturn").submit(function (event) {
        event.preventDefault();

        if (typeof returnItems === 'undefined' || returnItems.length === 0) {
            showPurchaseReturnAlert('warning', 'No Items Added', 'Please add at least one item before submitting the form.');
            return false;
        }

        $("#return_items").val(JSON.stringify(returnItems));
        this.submit();
    });

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).on('focus', '.select2-selection.select2-selection--single', function () {
        const selectElement = $(this).closest('.select2-container').prev('select');
        if (!selectElement.data('select2-opened')) {
            selectElement.select2('open');
            selectElement.data('select2-opened', true);
        }
    });

    $(document).on('select2:close', function (event) {
        $(event.target).data('select2-opened', false);
    });

    $(document).on('select2:open', function () {
        const searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    });

    let vendorSelector = null;
    let productSelector = null;

    function initializeSelect2(selector, searchBy, route) {
        $(selector).select2({
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: route,
                type: 'GET',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        query: params.term || '::create-action-only::',
                        searchBy: searchBy
                    };
                },
                processResults: function (data, params) {
                    const results = data.map(function (item) {
                        if (searchBy === 'vendor') {
                            return {
                                id: item.id,
                                text: item.cp_name + ' - ' + item.cp_phone,
                                cp_name: item.cp_name,
                                cp_phone: item.cp_phone
                            };
                        }

                        return {
                            id: item.id,
                            text: item.product_code + ' - ' + item.product,
                            product_code: item.product_code,
                            product_name: item.product,
                            hsn_code: item.hsn_code,
                            p_price: item.pprice,
                            purchase_price: item.pprice,
                            unit: item.unit,
                            unit_qty: item.uqty,
                            gst: item.gst,
                            current_stock: item.current_stock,
                            is_batch_managed: item.is_batch_managed,
                            batches: item.batches || [],
                            mrp_lots: item.mrp_lots || []
                        };
                    });

                    if (searchBy === 'vendor') {
                        results.push({
                            id: 'new',
                            text: 'Create New Vendor',
                            is_new: true,
                            typed_name: params.term || ''
                        });
                    }

                    if (searchBy === 'product') {
                        results.push({
                            id: 'new',
                            text: 'Create New Product',
                            is_new: true,
                            typed_name: params.term || ''
                        });
                    }

                    return { results };
                },
                cache: true,
                error: function (xhr) {
                    console.error('Purchase-return lookup failed', xhr.status, xhr.responseText);
                }
            },
            templateResult: function (data) {
                if (data.is_new) {
                    return $('<span style="color:white;"><i class="fas fa-plus"></i> ' + data.text + '</span>');
                }
                return data.text;
            }
        });

        $(selector).on('select2:select', function (event) {
            const data = event.params.data;

            if (searchBy === 'vendor' && data.id === 'new') {
                vendorSelector = $(this);
                vendorSelector.val(null).trigger('change');
                $('#new_vendor_name').val(data.typed_name || '');
                $('#addVendorModal').modal('show');
                return;
            }

            if (searchBy === 'product' && data.id === 'new') {
                productSelector = $(this);
                productSelector.val(null).trigger('change');
                $('#new_product_name').val(data.typed_name || '');
                $('#addProductModal').modal('show');
                return;
            }

            if (searchBy === 'vendor') {
                $('#vendor_id').val(data.id);
                $('#vendor_ph').val(data.cp_phone);
                return;
            }

            fillProductFields(data);
        });
    }

    function fillProductFields(data) {
        $('#product_id').val(data.id || '');
        $('#product_code').val(data.product_code);
        $('#product_name').val(data.product_name);
        $('#gst').val(data.gst);
        $('#unit_qty').val(data.unit_qty);
        $('#unit').val(data.unit);
        $('#hsn_code').val(data.hsn_code);
        $('#purchase_price').val(data.purchase_price);
        $('#unit_price').val(data.purchase_price);
        $('#p_price').val(data.p_price || data.purchase_price);
        $('#current_stock').val(data.current_stock || '');
        $('#in_stock').val(data.current_stock || '');
        $('#product').data('batch-managed', data.is_batch_managed === true || data.is_batch_managed == 1);
        populateBatchDropdown(data.batches || []);
        $('#return_batch_group').toggle(window.batchInventoryMode === true && $('#product').data('batch-managed'));
        populateMrpLotDropdown(data.mrp_lots || []);
        $('#return_mrp_lot_group').toggle(window.mrpInventoryMode === true);
        populateUnitDropdown(data.unit_qty, data.unit);
    }

    function populateBatchDropdown(batches) {
        const $batch = $('#stock_batch_id').empty().append('<option value="">Select batch</option>');
        batches.forEach(function (batch) {
            $batch.append(new Option(`${batch.batch_no} | Exp: ${batch.expiry_date || 'N/A'} | Qty: ${batch.available_quantity}`, batch.id));
        });
        $batch.trigger('change');
    }

    function populateMrpLotDropdown(lots) {
        const $lot = $('#mrp_stock_lot_id').empty().append('<option value="">Select Stock / MRP</option>');
        lots.forEach(function (item) {
            const text = `${item.purchase_voucher || 'Opening/Return'} | ${item.purchase_date || ''} | MRP: ${Number(item.mrp).toFixed(2)} | Rate: ${Number(item.display_purchase_rate).toFixed(2)} | Qty: ${item.available_quantity}`;
            const option = new Option(text, item.id);
            $(option).attr('data-mrp', item.mrp).attr('data-available', item.available_quantity);
            $lot.append(option);
        });
        if (lots.length === 1) {
            $lot.val(String(lots[0].id));
        }
        $lot.trigger('change');
    }

    function populateUnitDropdown(unitQty, unitValue) {
        const $itemUnit = $('#item_unit');
        const unit = unitValue || $('#unit').val();
        $itemUnit.empty();

        if (unitQty == 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
        } else if (unitQty > 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
            $itemUnit.append('<option value="No.s">No.s</option>');
        } else {
            $itemUnit.append('<option value="">Select Unit</option>');
        }
    }

    initializeSelect2('#vendor', 'vendor', purchaseReturnSearchRoute);
    initializeSelect2('#product', 'product', purchaseReturnSearchRoute);
    $('#stock_batch_id').select2({ width: '100%' });
    $('#mrp_stock_lot_id').select2({ width: '100%' });
    $('#mrp_stock_lot_id').on('change', function () {
        const available = parseFloat($(this).find('option:selected').data('available')) || 0;
        if (!$(this).val()) return;
        $('#current_stock').val(available);
        const unitQty = Math.max(parseFloat($('#unit_qty').val()) || 1, 1);
        const selectedUnit = $('#item_unit').val();
        $('#in_stock').val((selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? available * unitQty : available);
    });

    $('#bill_number').on('change blur', function () {
        loadOriginalPurchase($(this).val());
    });

    $('#bill_number').on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadOriginalPurchase($(this).val());
            $('#product').focus();
        }
    });

    function loadOriginalPurchase(voucher) {
        voucher = (voucher || '').trim();
        if (!voucher || $('#edit_mode').val() === 'true' || voucher === lastLoadedPurchase) {
            return;
        }

        $.ajax({
            url: purchaseReturnSearchRoute,
            type: 'GET',
            dataType: 'json',
            data: {
                query: voucher,
                searchBy: 'purchase'
            },
            success: function (purchase) {
                lastLoadedPurchase = voucher;

                if (!purchase) {
                    return;
                }

                if (purchase.vendor) {
                    $('#vendor_id').val(purchase.vendor.id);
                    $('#vendor_ph').val(purchase.vendor.cp_phone || '');
                    const option = new Option(
                        `${purchase.vendor.cp_name || ''} - ${purchase.vendor.cp_phone || ''}`,
                        purchase.vendor.id,
                        true,
                        true
                    );
                    $('#vendor').append(option).trigger('change');
                }

                resetReturnItems();
                (purchase.items || []).forEach(function (item) {
                    addReturnItem({
                        product_id: item.product_id,
                        product: item.product,
                        hsn_code: item.hsn_code,
                        quantity: item.quantity,
                        unit: item.unit,
                        unit_price: item.unit_price,
                        unit_qty: item.unit_qty,
                        gst: item.gst,
                        stock_batch_id: item.stock_batch_id || null,
                        batch_no: item.batch_no || null,
                        mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                        stock_mrp: item.stock_mrp || null,
                        mrp_lot_label: item.mrp_lot_label || null
                    });
                });

                updatePurchaseReturnSummary();
            },
            error: function (xhr) {
                lastLoadedPurchase = null;
                if (xhr.status !== 404) {
                    console.error('Original purchase lookup failed', xhr.status, xhr.responseText);
                }
            }
        });
    }

    $('#item_unit').on('change', function() {
        const selectedUnit = $(this).val();
        const purchasePrice = parseFloat($('#p_price').val());
        const unitQty = parseFloat($('#unit_qty').val());
        const inStock = parseFloat($('#current_stock').val());

        if (!isNaN(purchasePrice) && !isNaN(unitQty) && unitQty > 1) {
            const newPurchasePrice = (selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? purchasePrice / unitQty : purchasePrice;
            $('#purchase_price').val(newPurchasePrice.toFixed(2));
            $('#unit_price').val(newPurchasePrice.toFixed(2));
        }

        if (!isNaN(inStock) && !isNaN(unitQty)) {
            const displayStock = (selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? inStock * unitQty : inStock;
            $('#in_stock').val(displayStock);
        }

        $('#quantity').val('');
    });

    $('#quantity').on('input', function () {
        const quantity = parseFloat($(this).val());
        const inStock = parseFloat($('#in_stock').val());

        if (!isNaN(quantity) && !isNaN(inStock) && quantity > inStock) {
            showPurchaseReturnAlert('warning', 'Insufficient Stock', 'Entered quantity exceeds available stock.');
            $(this).focus();
        }
    });

    $('#amount_paid').on('input', function () {
        updatePurchaseReturnSummary();
    });

    let existingItems = $('#existing_items').val();
    if (existingItems) {
        existingItems = JSON.parse(existingItems);

        existingItems.forEach((item) => {
            addReturnItem({
                product_id: item.prd_itemid,
                product: item.product?.product || '',
                hsn_code: item.prd_hsn,
                quantity: item.prd_itemqty,
                unit: item.prd_unit,
                unit_price: item.prd_uprice,
                unit_qty: item.prd_uqty,
                gst: item.product?.gst || 0,
                total: item.prd_total,
                gst_value: item.prd_gst,
                stock_batch_id: item.stock_batch_id || null,
                batch_no: item.batch_no || null,
                mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                stock_mrp: item.stock_mrp || null,
                mrp_lot_label: item.mrp_stock_lot
                    ? `${item.mrp_stock_lot.purchase_voucher || 'Purchase'} | MRP ${Number(item.stock_mrp || 0).toFixed(2)}`
                    : null
            });
        });

        updatePurchaseReturnSummary();
    }

    $('.addBtn').on('click keypress', function (event) {
        event.preventDefault();

        const productId = $('#product_code').val();
        const product = $('#product_name').val() || $('#product option:selected').text();
        const hsnCode = $('#hsn_code').val();
        const quantity = $('#quantity').val();
        const unit = $('#item_unit').val();
        const unitPrice = $('#unit_price').val();
        const unitQty = $('#unit_qty').val();
        const gst = parseFloat($('#gst').val()) || 0;
        const isBatchManaged = window.batchInventoryMode === true && $('#product').data('batch-managed');
        const stockBatchId = isBatchManaged ? ($('#stock_batch_id').val() || null) : null;
        const batchNo = stockBatchId ? $('#stock_batch_id option:selected').text().split(' | ')[0] : null;
        const mrpStockLotId = window.mrpInventoryMode === true ? ($('#mrp_stock_lot_id').val() || null) : null;
        const stockMrp = mrpStockLotId ? ($('#mrp_stock_lot_id option:selected').data('mrp') || null) : null;
        const mrpLotLabel = mrpStockLotId ? $('#mrp_stock_lot_id option:selected').text() : null;

        if (!productId || !product || !unitPrice || !quantity || !unit) {
            showPurchaseReturnAlert('warning', 'Missing Fields', 'Please fill in required fields (Product, Unit, Unit Price, Quantity).');
            return;
        }

        if (isBatchManaged && !stockBatchId) {
            showPurchaseReturnAlert('warning', 'Batch Required', 'Please select the batch being returned.');
            return;
        }
        if (window.mrpInventoryMode === true && !mrpStockLotId) {
            showPurchaseReturnAlert('warning', 'Stock Lot Required', 'Please select the MRP purchase lot being returned.');
            return;
        }

        addReturnItem({
            product_id: productId,
            product: product,
            hsn_code: hsnCode,
            quantity: quantity,
            unit: unit,
            unit_price: unitPrice,
            unit_qty: unitQty,
            gst: gst,
            stock_batch_id: stockBatchId,
            batch_no: batchNo,
            mrp_stock_lot_id: mrpStockLotId,
            stock_mrp: stockMrp,
            mrp_lot_label: mrpLotLabel
        });

        $('#product').val(null).empty().trigger('change');
        $('#product_code, #product_name, #hsn_code, #unit_price, #quantity, #purchase_price, #p_price, #gst, #unit_qty, #unit, #current_stock, #in_stock').val('');
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        $('#return_batch_group').hide();
        $('#stock_batch_id').empty().append('<option value="">Select batch</option>').trigger('change');
        $('#return_mrp_lot_group').hide();
        $('#mrp_stock_lot_id').empty().append('<option value="">Select Stock / MRP</option>').trigger('change');
        $('#product').focus();

        updatePurchaseReturnSummary();
    });

    function addReturnItem(item) {
        const gst = parseFloat(item.gst) || 0;
        const quantity = parseFloat(item.quantity) || 0;
        const unitPrice = parseFloat(item.unit_price) || 0;
        const total = item.total !== undefined ? parseFloat(item.total).toFixed(2) : (unitPrice * quantity).toFixed(2);
        const taxableValue = (total / (1 + gst / 100)).toFixed(2);
        const gstValue = item.gst_value !== undefined ? parseFloat(item.gst_value).toFixed(2) : (total - taxableValue).toFixed(2);

        returnItems.push({
            product_id: item.product_id,
            hsn_code: item.hsn_code,
            quantity: item.quantity,
            unit: item.unit,
            unit_price: item.unit_price,
            unit_qty: item.unit_qty,
            gst_value: gstValue,
            total: total,
            stock_batch_id: item.stock_batch_id || null,
            batch_no: item.batch_no || null,
            mrp_stock_lot_id: item.mrp_stock_lot_id || null,
            stock_mrp: item.stock_mrp || null
        });

        appendReturnRow({
            product: item.product,
            hsnCode: item.hsn_code,
            quantity: item.quantity,
            unitPrice: item.unit_price,
            total: total,
            taxableValue: taxableValue,
            gstValue: gstValue,
            gst: gst,
            batchNo: item.batch_no,
            mrpLotLabel: item.mrp_lot_label || (item.stock_mrp ? `MRP ${Number(item.stock_mrp).toFixed(2)}` : '')
        });
    }

    function appendReturnRow(row) {
        const formattedTotal = Number(row.total).toLocaleString('en-IN');
        const formattedTaxableValue = Number(row.taxableValue).toLocaleString('en-IN');
        const formattedGstValue = Number(row.gstValue).toLocaleString('en-IN');

        const batchCell = window.batchInventoryMode ? `<td>${row.batchNo || ''}</td>` : '';
        const mrpLotCell = window.mrpInventoryMode ? `<td>${row.mrpLotLabel || ''}</td>` : '';
        const newRow = `
            <tr data-gst="${row.gst}">
                <td>${++itemCount}</td>
                <td>${row.product}</td>
                <td>${row.hsnCode || ''}</td>
                <td><input type="number" class="form-control unit_price" value="${row.unitPrice}" data-index="${itemCount - 1}"></td>
                <td><input type="number" class="form-control quantity" value="${row.quantity}" data-index="${itemCount - 1}"></td>
                ${batchCell}
                ${mrpLotCell}
                <td class="total">${formattedTotal}</td>
                <td class="taxable_value">${formattedTaxableValue}</td>
                <td class="gst_value">${formattedGstValue} (${row.gst}%)</td>
                <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
            </tr>
        `;

        $('#return_table tbody').append(newRow);
    }

    function resetReturnItems() {
        returnItems = [];
        itemCount = 0;
        $('#return_table tbody').empty();
    }

    $(document).on('input', '.unit_price, .quantity', function () {
        const row = $(this).closest('tr');
        const index = $(this).data('index');
        const newUnitPrice = parseFloat(row.find('.unit_price').val()) || 0;
        const newQuantity = parseFloat(row.find('.quantity').val()) || 0;
        const gst = parseFloat(row.data('gst')) || 0;
        const newTotal = (newUnitPrice * newQuantity).toFixed(2);
        const newTaxableValue = (newTotal / (1 + gst / 100)).toFixed(2);
        const newGstValue = (newTotal - newTaxableValue).toFixed(2);

        row.find('.total').text(Number(newTotal).toLocaleString('en-IN'));
        row.find('.taxable_value').text(Number(newTaxableValue).toLocaleString('en-IN'));
        row.find('.gst_value').text(`${Number(newGstValue).toLocaleString('en-IN')} (${gst}%)`);

        returnItems[index].unit_price = newUnitPrice;
        returnItems[index].quantity = newQuantity;
        returnItems[index].total = newTotal;
        returnItems[index].taxable_value = newTaxableValue;
        returnItems[index].gst_value = newGstValue;

        updatePurchaseReturnSummary();
    });

    $(document).on('click', '.deleteRow', function () {
        const rowIndex = $(this).closest('tr').index();
        returnItems.splice(rowIndex, 1);
        $(this).closest('tr').remove();
        itemCount--;

        $('#return_table tbody tr').each(function (index) {
            $(this).find('td:first').text(index + 1);
            $(this).find('.quantity, .unit_price').attr('data-index', index).data('index', index);
        });

        updatePurchaseReturnSummary();
    });

    function updatePurchaseReturnSummary() {
        let newGST = 0;
        let newTaxable = 0;
        let newAmountPayable = 0;

        $('.gst_value').each(function () {
            newGST += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $('.taxable_value').each(function () {
            newTaxable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $('.total').each(function () {
            newAmountPayable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        const roundedAmountPayable = Math.round(newAmountPayable);
        const roundOff = (roundedAmountPayable - newAmountPayable).toFixed(2);
        const formattedAmountPayable = roundedAmountPayable.toLocaleString('en-IN');
        const amountPaid = parseFloat($('#amount_paid').val()) || 0;
        const balanceToPay = roundedAmountPayable - amountPaid;

        $('#amount').val(newAmountPayable);
        $('#totalgst').val(newGST);
        $('#amount_payable').val(roundedAmountPayable);
        $('#amount_payable1').html(formattedAmountPayable);
        $('#round_off').val(roundOff);
        $('#round_off1').html(roundOff);
        $('#balance_to_pay').val(balanceToPay.toFixed(2));
        $('#balance_to_pay1').html(balanceToPay.toLocaleString('en-IN'));
        $('#footer_total').html('Rs. ' + newAmountPayable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $('#footer_taxable_value').html('Rs. ' + newTaxable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $('#footer_gst_value').html('Rs. ' + newGST.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
    }

    $(document).on('submit', '#vendorForm', function (event) {
        event.preventDefault();

        const vendorName = $('#new_vendor_name').val();
        const vendorPhone = $('#new_vendor_phone').val();
        let isValid = true;

        $('.error-name').text('');
        $('.error-phone').text('');

        if (!vendorName) {
            $('.error-name').text('Vendor name is required.');
            isValid = false;
        }

        if (!vendorPhone) {
            $('.error-phone').text('Phone number is required.');
            isValid = false;
        }

        if (!isValid) {
            return;
        }

        $.ajax({
            url: vendorAddRoute,
            type: 'POST',
            data: {
                cp_name: vendorName,
                cp_phone: vendorPhone,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (vendorSelector) {
                    const option = new Option(response.cp_name + ' - ' + response.cp_phone, response.id, true, true);
                    vendorSelector.append(option).trigger('change');
                    $('#vendor_id').val(response.id);
                    $('#vendor_ph').val(response.cp_phone);
                }

                $('#vendorForm')[0].reset();
                $('#addVendorModal').modal('hide');
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.cp_name) $('.error-name').text(errors.cp_name[0]);
                    if (errors.cp_phone) $('.error-phone').text(errors.cp_phone[0]);
                    return;
                }
                showPurchaseReturnAlert('error', 'Save Failed', 'Failed to add vendor.');
            }
        });
    });

    $(document).on('submit', '#productForm', function (event) {
        event.preventDefault();

        const name = $('#new_product_name').val().trim();
        const code = $('#new_product_code').val().trim();
        const hsn = $('#new_hsn').val().trim();
        const gst = $('#new_gst').val();
        const pprice = $('#new_pprice').val().trim();
        const mrp = $('#new_mrp').val().trim();
        const margin = $('#new_margin').val().trim();
        const price = $('#new_price').val().trim();
        let isValid = true;

        $('.error-product-name').text('');
        $('.error-product-code').text('');
        $('.error-price').text('');
        $('.error-pprice').text('');

        if (!name) {
            $('.error-product-name').text('Product name required');
            isValid = false;
        }

        if (!code) {
            $('.error-product-code').text('Product code required');
            isValid = false;
        }

        if (!price) {
            $('.error-price').text('Sale price required');
            isValid = false;
        }

        if (!pprice) {
            $('.error-pprice').text('Purchase price required');
            isValid = false;
        }

        if (!isValid) {
            return;
        }

        $.ajax({
            url: productAddRoute,
            type: 'POST',
            data: {
                product: name,
                product_code: code,
                hsn_code: hsn,
                gst: gst,
                pprice: pprice,
                mrp: mrp,
                margin: margin,
                price: price,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (productSelector) {
                    const productData = {
                        ...response,
                        product_name: response.product,
                        unit_qty: response.uqty,
                        p_price: response.pprice,
                        purchase_price: response.pprice
                    };
                    const option = new Option(response.product_code + ' - ' + response.product, response.id, true, true);
                    productSelector.append(option).trigger('change');
                    productSelector.trigger({ type: 'select2:select', params: { data: productData } });
                }

                $('#productForm')[0].reset();
                $('#addProductModal').modal('hide');
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.product) $('.error-product-name').text(errors.product[0]);
                    if (errors.product_code) $('.error-product-code').text(errors.product_code[0]);
                    if (errors.price) $('.error-price').text(errors.price[0]);
                    if (errors.pprice) $('.error-pprice').text(errors.pprice[0]);
                    return;
                }

                showPurchaseReturnAlert('error', 'Save Failed', 'Failed to add product.');
            }
        });
    });

    $('#addVendorModal').on('hidden.bs.modal', function () {
        vendorSelector = null;
    });

    $('#addVendorModal').on('shown.bs.modal', function () {
        $('#new_vendor_name').trigger('focus');
    });

    $('#addProductModal').on('shown.bs.modal', function () {
        $('#new_product_name').trigger('focus');
        $.ajax({
            url: productNewCodeRoute,
            type: 'POST',
            success: function (response) {
                $('#new_product_code').val(response.code);
            }
        });
    });

    $('#addProductModal').on('hidden.bs.modal', function () {
        productSelector = null;
    });

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });

    updatePurchaseReturnSummary();
});
