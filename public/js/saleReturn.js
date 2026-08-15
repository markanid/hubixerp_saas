$(document).ready(function() {

    let itemCount = 0;
    let returnItems = [];
    let customerSelector = null;
    let productSelector = null;

    function showSaleReturnAlert(icon, title, text) {
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

    $("#addSaleReturn").submit(function (event) {
        event.preventDefault();

        if (typeof returnItems === 'undefined' || returnItems.length === 0) {
            showSaleReturnAlert('warning', 'No Items Added', 'Please add at least one item before submitting the form.');
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
                        if (searchBy === 'customer') {
                            return {
                                id: item.id,
                                text: item.customer + ' - ' + item.phone,
                                customer: item.customer,
                                phone: item.phone
                            };
                        }

                        return {
                            id: item.id,
                            text: item.product_code + ' - ' + item.product,
                            product_code: item.product_code,
                            product_name: item.product,
                            hsn_code: item.hsn_code,
                            price: item.price,
                            sale_price: item.price,
                            mrp: item.mrp,
                            unit: item.unit,
                            unit_qty: item.uqty,
                            gst: item.gst
                        };
                    });

                    if (searchBy === 'customer') {
                        results.push({
                            id: 'new',
                            text: ' Create New Customer',
                            is_new: true,
                            typed_name: params.term || ''
                        });
                    }

                    if (searchBy === 'product') {
                        results.push({
                            id: 'new',
                            text: ' Create New Product',
                            is_new: true,
                            typed_name: params.term || ''
                        });
                    }

                    return { results };
                },
                cache: true,
                error: function (xhr) {
                    console.error('Sale-return lookup failed', xhr.status, xhr.responseText);
                }
            },
            templateResult: function (data) {
                if (data.is_new) {
                    return $('<span style="color:white;"><i class="fas fa-plus"></i>' + data.text + '</span>');
                }
                return data.text;
            }
        });

        $(selector).on('select2:select', function (event) {
            const data = event.params.data;

            if (searchBy === 'customer' && data.id === 'new') {
                customerSelector = $(this);
                customerSelector.val(null).trigger('change');
                $('#new_customer_name').val(data.typed_name || '');
                $('#addCustomerModal').modal('show');
                return;
            }

            if (searchBy === 'product' && data.id === 'new') {
                productSelector = $(this);
                productSelector.val(null).trigger('change');
                $('#new_product_name').val(data.typed_name || '');
                $('#addProductModal').modal('show');
                return;
            }

            if (searchBy === 'customer') {
                $('#customer_id').val(data.id);
                $('#phone').val(data.phone);
                return;
            }

            $('#product_id').val(data.id);
            $('#product_code').val(data.product_code);
            $('#product_name').val(data.product_name);
            $('#gst').val(data.gst);
            $('#unit_qty').val(data.unit_qty);
            $('#unit').val(data.unit);
            $('#hsn_code').val(data.hsn_code);
            $('#unit_price').val(data.sale_price);
            $('#price').val(data.price);
            $('#return_mrp').val(data.mrp || '');
            populateUnitDropdown(data.unit_qty);
        });
    }

    function populateUnitDropdown(unitQty) {
        const $itemUnit = $('#item_unit');
        const unit = $('#unit').val();
        $itemUnit.empty();

        if (unitQty == 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
        } else if (unitQty > 1) {
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
            $itemUnit.append('<option value="No.s">No.s</option>');
        }
    }

    initializeSelect2('#customer', 'customer', saleReturnSearchRoute);
    initializeSelect2('#product', 'product', saleReturnSearchRoute);
    $('#pr_state_code').select2({ width: '100%' });

    $(document).on('submit', '#customerForm', function (event) {
        event.preventDefault();

        const customerName = $('#new_customer_name').val().trim();
        const customerPhone = $('#new_customer_phone').val().trim();
        $('.error-name, .error-phone').text('');

        if (!customerName) $('.error-name').text('Customer name is required.');
        if (!customerPhone) $('.error-phone').text('Phone number is required.');
        if (!customerName || !customerPhone) return;

        $.ajax({
            url: customerAddRoute,
            type: 'POST',
            data: {
                customer: customerName,
                phone: customerPhone,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                const customerData = {
                    id: response.id,
                    customer: response.customer,
                    phone: response.phone
                };
                const option = new Option(`${response.customer} - ${response.phone}`, response.id, true, true);
                customerSelector.append(option).trigger('change');
                customerSelector.trigger({ type: 'select2:select', params: { data: customerData } });
                $('#customerForm')[0].reset();
                $('#addCustomerModal').modal('hide');
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.customer) $('.error-name').text(errors.customer[0]);
                    if (errors.phone) $('.error-phone').text(errors.phone[0]);
                    return;
                }
                showSaleReturnAlert('error', 'Customer Save Failed', 'Failed to add customer.');
            }
        });
    });

    $(document).on('submit', '#productForm', function (event) {
        event.preventDefault();

        const product = $('#new_product_name').val().trim();
        const productCode = $('#new_product_code').val().trim();
        const pprice = $('#new_pprice').val().trim();
        const price = $('#new_price').val().trim();
        $('.error-product-name, .error-product-code, .error-price').text('');

        if (!product) $('.error-product-name').text('Product name required');
        if (!productCode) $('.error-product-code').text('Product code required');
        if (!price) $('.error-price').text('Sale price required');
        if (!product || !productCode || !price) return;

        $.ajax({
            url: productAddRoute,
            type: 'POST',
            data: {
                product: product,
                product_code: productCode,
                hsn_code: $('#new_hsn').val().trim(),
                gst: $('#new_gst').val(),
                pprice: pprice,
                mrp: $('#new_mrp').val().trim(),
                margin: $('#new_margin').val().trim(),
                price: price,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                const productData = {
                    ...response,
                    product_name: response.product,
                    unit_qty: response.uqty,
                    sale_price: response.price
                };
                const option = new Option(`${response.product_code} - ${response.product}`, response.id, true, true);
                productSelector.append(option).trigger('change');
                productSelector.trigger({ type: 'select2:select', params: { data: productData } });
                $('#productForm')[0].reset();
                $('#addProductModal').modal('hide');
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.product) $('.error-product-name').text(errors.product[0]);
                    if (errors.product_code) $('.error-product-code').text(errors.product_code[0]);
                    if (errors.price) $('.error-price').text(errors.price[0]);
                    return;
                }
                showSaleReturnAlert('error', 'Product Save Failed', 'Failed to add product.');
            }
        });
    });

    $('#addCustomerModal').on('shown.bs.modal', function () {
        $('#new_customer_name').trigger('focus');
    }).on('hidden.bs.modal', function () {
        customerSelector = null;
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
    }).on('hidden.bs.modal', function () {
        productSelector = null;
    });

    $('#bill_number').on('change blur', function () {
        loadOriginalSale($(this).val());
    });

    $('#bill_number').on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadOriginalSale($(this).val());
            $('#product').focus();
        }
    });

    function loadOriginalSale(voucher) {
        voucher = (voucher || '').trim();
        if (!voucher || $('#edit_mode').val() === 'true') {
            return;
        }

        $.ajax({
            url: saleReturnSearchRoute,
            type: 'GET',
            dataType: 'json',
            data: {
                query: voucher,
                searchBy: 'sale'
            },
            success: function (sale) {
                if (!sale) {
                    return;
                }

                if (sale.customer && sale.customer.id) {
                    $('#customer_id').val(sale.customer.id);
                    $('#phone').val(sale.customer.phone || '');
                    const customerText = `${sale.customer.customer} - ${sale.customer.phone || ''}`;
                    const customerOption = new Option(customerText, sale.customer.id, true, true);
                    $('#customer').empty().append(customerOption).trigger('change');
                }

                $('#pr_state_code').val(sale.state_code || '').trigger('change');
                $('#pr_is_igst').prop('checked', sale.is_igst === true || sale.is_igst == 1);

                if (Array.isArray(sale.items)) {
                    resetReturnItems();
                    sale.items.forEach(function (item) {
                        addReturnItem({
                            product_id: item.product_id,
                            product: item.product,
                            hsn_code: item.hsn_code,
                            quantity: item.quantity,
                            unit: item.unit,
                            unit_price: item.unit_price,
                            unit_qty: item.unit_qty,
                            gst: item.gst,
                            mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                            stock_mrp: item.stock_mrp || null,
                            mrp_lot_label: item.mrp_lot_label || null
                        });
                    });
                    updateSaleReturnSummary();
                }
            },
            error: function (xhr) {
                if (xhr.status === 404) {
                    showSaleReturnAlert('info', 'Sale Not Found', 'No active sale was found for this bill number. You can continue as a manual sale return.');
                    return;
                }

                showSaleReturnAlert('error', 'Sale Lookup Failed', 'Unable to load the sale bill details.');
            }
        });
    }

    $('#item_unit').change(function() {
        const selectedUnit = $(this).val();
        const salePrice = parseFloat($('#price').val());
        const unitQty = parseFloat($('#unit_qty').val());

        if (!isNaN(salePrice) && !isNaN(unitQty) && unitQty > 1) {
            const newSalePrice = (selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? salePrice / unitQty : salePrice;
            $('#unit_price').val(newSalePrice.toFixed(2));
        }

        $('#quantity').val('');
    });

    $('#amount_paid').on('input', function () {
        updateSaleReturnSummary();
    });

    let existingItems = $('#existing_items').val();
    if (existingItems) {
        existingItems = JSON.parse(existingItems);

        existingItems.forEach((item) => {
            const gst = parseFloat(item.product?.gst) || 0;
            const total = parseFloat(item.prd_total || (item.prd_uprice * item.prd_itemqty)).toFixed(2);
            const taxableValue = (total / (1 + gst / 100)).toFixed(2);
            const gstValue = (total - taxableValue).toFixed(2);

            addReturnItem({
                product_id: item.prd_itemid,
                product: item.product?.product || '',
                hsn_code: item.prd_hsn,
                quantity: item.prd_itemqty,
                unit: item.prd_unit,
                unit_price: item.prd_uprice,
                unit_qty: item.prd_uqty,
                gst: gst,
                mrp_stock_lot_id: item.mrp_stock_lot_id || null,
                stock_mrp: item.stock_mrp || null,
                mrp_lot_label: item.mrp_stock_lot
                    ? `${item.mrp_stock_lot.purchase_voucher || 'Purchase'} | MRP ${Number(item.stock_mrp || 0).toFixed(2)}`
                    : null
            });
        });

        updateSaleReturnSummary();
    }

    $('.addBtn').on('click keypress', function (event) {
        event.preventDefault();

        const productId = $('#product_code').val();
        const product = $('#product_name').val();
        const hsnCode = $('#hsn_code').val();
        const quantity = $('#quantity').val();
        const unit = $('#item_unit').val();
        const unitPrice = $('#unit_price').val();
        const unitQty = $('#unit_qty').val();
        const gst = parseFloat($('#gst').val()) || 0;
        const stockMrp = window.mrpInventoryMode === true ? (parseFloat($('#return_mrp').val()) || 0) : null;

        if (!product || !unitPrice || !quantity || !unit) {
            showSaleReturnAlert('warning', 'Missing Fields', 'Please fill in required fields (Product, Unit, Unit Price, Quantity).');
            return;
        }
        if (window.mrpInventoryMode === true && !(stockMrp > 0)) {
            showSaleReturnAlert('warning', 'MRP Required', 'Enter the MRP printed on the returned product.');
            $('#return_mrp').focus();
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
            mrp_stock_lot_id: null,
            stock_mrp: stockMrp,
            mrp_lot_label: stockMrp ? `Manual return | MRP ${Number(stockMrp).toFixed(2)}` : null
        });

        $('#product').val(null).empty().trigger('change');
        $('#product_code, #product_name, #hsn_code, #unit_price, #quantity, #price, #gst, #unit_qty, #unit, #return_mrp').val('');
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        $('#product').focus();

        updateSaleReturnSummary();
    });

    function addReturnItem(item) {
        const gst = parseFloat(item.gst) || 0;
        const quantity = parseFloat(item.quantity) || 0;
        const unitPrice = parseFloat(item.unit_price) || 0;
        const total = (unitPrice * quantity).toFixed(2);
        const taxableValue = (total / (1 + gst / 100)).toFixed(2);
        const gstValue = (total - taxableValue).toFixed(2);

        returnItems.push({
            product_id: item.product_id,
            hsn_code: item.hsn_code,
            quantity: item.quantity,
            unit: item.unit,
            unit_price: item.unit_price,
            unit_qty: item.unit_qty,
            gst_value: gstValue,
            total: total,
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
            mrpLotLabel: item.mrp_lot_label || (item.stock_mrp ? `MRP ${Number(item.stock_mrp).toFixed(2)}` : '')
        });
    }

    function appendReturnRow(row) {
        const formattedTotal = Number(row.total).toLocaleString('en-IN');
        const formattedTaxableValue = Number(row.taxableValue).toLocaleString('en-IN');
        const formattedGstValue = Number(row.gstValue).toLocaleString('en-IN');
        const mrpCell = window.mrpInventoryMode ? `<td>${row.mrpLotLabel || ''}</td>` : '';

        const newRow = `
            <tr data-gst="${row.gst}">
                <td>${++itemCount}</td>
                <td>${row.product}</td>
                <td>${row.hsnCode || ''}</td>
                <td><input type="number" class="form-control quantity" value="${row.quantity}" data-index="${itemCount - 1}"></td>
                <td><input type="number" class="form-control unit_price" value="${row.unitPrice}" data-index="${itemCount - 1}"></td>
                ${mrpCell}
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
        $('#return_items').val('');
        updateSaleReturnSummary();
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

        updateSaleReturnSummary();
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

        updateSaleReturnSummary();
    });

    function updateSaleReturnSummary() {
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

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });

});
