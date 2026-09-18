$(document).ready(function() {

    let itemCount = 0;
    let estimationItems = [];
    const useMrpPricingMode = typeof useEstimationMrpPricingMode !== 'undefined'
        && useEstimationMrpPricingMode === true;

    function toNumber(value) {
        return parseFloat(value) || 0;
    }

    function selectedBasePrice() {
        const salePrice = toNumber($('#sale_price').val());
        const productMrp = toNumber($('#mrp').val());
        const lotSalePrice = toNumber($('#mrp_stock_lot_id option:selected').data('sale-price'));

        if (!useMrpPricingMode) {
            return $('#mrp_stock_lot_id').val() ? lotSalePrice : salePrice;
        }

        return productMrp > 0 ? productMrp : salePrice;
    }

    function selectedUnitPrice(basePrice = selectedBasePrice()) {
        const selectedUnit = $('#item_unit').val();
        const unitQty = toNumber($('#unit_qty').val());

        if ((selectedUnit === 'No.s' || selectedUnit === 'Nos.') && unitQty > 1) {
            return basePrice / unitQty;
        }

        return basePrice;
    }

    function resolvedMarginPercent(salePrice, mrp, margin) {
        const explicitMargin = toNumber(margin);
        const salePriceValue = toNumber(salePrice);
        const mrpValue = toNumber(mrp);

        if (explicitMargin > 0) {
            return explicitMargin;
        }

        if (mrpValue > 0 && mrpValue > salePriceValue) {
            return ((mrpValue - salePriceValue) / mrpValue) * 100;
        }

        return 0;
    }

    function updateDiscountFromPercentage() {
        const percent = toNumber($('#percentage_discount').val());
        const quantity = toNumber($('#quantity').val()) || (useMrpPricingMode ? 1 : 0);
        const total = quantity * toNumber($('#unit_price').val());
        const amount = (percent / 100) * total;
        $('#amount_discount').val(amount.toFixed(2));
    }

    function refreshUnitPriceAndDiscount() {
        const basePrice = selectedBasePrice();
        $('#price').val(basePrice);

        if (basePrice > 0) {
            $('#unit_price').val(selectedUnitPrice(basePrice).toFixed(2));
        }

        if (useMrpPricingMode) {
            updateDiscountFromPercentage();
        }
    }

    function refreshEstimationDisplayedStock() {
        const packageStock = toNumber($('#current_stock').val());
        const unitQty = Math.max(toNumber($('#unit_qty').val()), 1);
        const selectedUnit = $('#item_unit').val();
        $('#in_stock').val((selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? packageStock * unitQty : packageStock);
    }

    function showEstimationWarningToast(title, text) {
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

    // Before form submission, store table data in hidden field
    $("#addEstimation").submit(function (event) {
        event.preventDefault();
        updatePaymentSummary();
    
        // Check if saleItems is empty
        if (typeof estimationItems === 'undefined' || estimationItems.length === 0 || estimationItems.length === 'NULL') {
            showEstimationWarningToast('No Items Added', 'Please add at least one item before submitting the form.');
            return false;
        } else if (typeof estimationAccountEffect !== 'undefined' && estimationAccountEffect && parseFloat($("#balance").val() || 0) > 0 && !$("#due_days").val()) {
            showEstimationWarningToast('Due Days Required', 'Please enter due days when the full amount is not paid.');
            return false;
        } else {
                // Set the hidden field value to the JSON string of saleItems
                $("#estimation_items").val(JSON.stringify(estimationItems));
                
                console.log("Form validated. Submitting...");
                this.submit();
            }
    });

    // CSRF token setup for AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Auto-open Select2 on focus
    $(document).on('focus', '.select2-selection.select2-selection--single', function (e) {
        const selectElement = $(this).closest('.select2-container').prev('select');
        if (!selectElement.data('select2-opened')) {
            selectElement.select2('open');
            selectElement.data('select2-opened', true);
        }
    });

    // Reset flag when closed
    $(document).on('select2:close', function (e) {
        $(e.target).data('select2-opened', false);
    });

    // Focus the search input when Select2 opens
    $(document).on('select2:open', function () {
        let searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    });

    let customerSelector = null;
    let productSelector = null;
    let productResultTerm = null;
    let productResults = [];

    function productSearchResult(item) {
        return {
            id: item.id,
            text: item.product_code + " - " + item.product,
            product_code: item.product_code,
            bar_code: item.bar_code,
            product_name: item.product,
            price: item.price,
            sale_price: item.price,
            mrp: item.mrp,
            margin: item.margin,
            amt_margin: item.amt_margin,
            unit: item.unit,
            unit_qty: item.uqty,
            current_stock: item.current_stock
        };
    }

    function initializeSelect2(selector, searchBy, route) {
        $(selector).select2({
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
                    let results = data.map(function (item) {
                        if (searchBy === 'customer') {
                            return {
                                id: item.id,
                                text: item.customer + " - " + item.phone + "",
                                customer: item.customer,
                                phone: item.phone
                            };
                        } else if (searchBy === 'product') {
                            return productSearchResult(item);
                        }
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
                        productResultTerm = (params.term || '').trim();
                        productResults = results.slice();
                        results.push({
                            id: 'new',
                            text: ' Create New Product',
                            is_new: true,
                            typed_name: params.term || ''
                        });
                    }
                    return { results };
                },
                cache: true
            },
            templateResult: function (data) {
                if (data.is_new) {
                    return $('<span style="color:white;"><i class="fas fa-plus"></i>' + data.text + '</span>');
                }
                return data.text;
            }
        });

        // Handle selection
        $(selector).on('select2:select', function (e) {
            let data = e.params.data;

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
                $('#phone').val(data.phone).trigger('change');
            } else if (searchBy === 'product') {
                $('#product_id').val(data.id);
                $('#product_code').val(data.product_code);
                $('#product_name').val(data.product_name);
                $('#unit_qty').val(data.unit_qty);
                $('#unit').val(data.unit);
                $('#sale_price').val(data.sale_price);
                $('#mrp').val(data.mrp);
                const marginPercent = resolvedMarginPercent(data.sale_price, data.mrp, data.margin);
                $('#margin').val(marginPercent);
                $('#amt_margin').val(data.amt_margin);
                $('#current_stock').val(data.current_stock);
                refreshEstimationDisplayedStock();
                $('#percentage_discount').val(useMrpPricingMode ? marginPercent.toFixed(2) : 0);
                $('#amount_discount').val(0);
                populateUnitDropdown(data.unit_qty);
                refreshUnitPriceAndDiscount();
                loadMrpLots(data.id);
            }
        });
    }

    function loadMrpLots(productId) {
        const $group = $('#mrp_lot_selector_group');
        const $select = $('#mrp_stock_lot_id');
        if (window.mrpInventoryMode !== true || estimationAccountEffect !== true || !$select.length) {
            $group.hide();
            return;
        }
        $group.show();
        $select.html('<option value="">Select Stock / MRP</option>');
        $.getJSON(estimationProductMrpLotsRoute.replace('__PRODUCT__', productId), function (lots) {
            lots.forEach(function (lot) {
                const text = `${lot.purchase_voucher || 'Opening/Return'} | ${lot.purchase_date || ''} | MRP: ${Number(lot.mrp).toFixed(2)} | Sale: ${Number(lot.sale_price).toFixed(2)}`;
                const option = new Option(text, lot.id);
                $(option).attr('data-mrp', lot.mrp)
                    .attr('data-sale-price', lot.sale_price)
                    .attr('data-margin-percentage', lot.margin_percentage)
                    .attr('data-margin-amount', lot.margin_amount)
                    .attr('data-available', lot.display_quantity);
                $select.append(option);
            });
            if (lots.length === 1) $select.val(String(lots[0].id)).trigger('change');
        });
    }

    $('#mrp_stock_lot_id').on('change', function () {
        const $option = $(this).find('option:selected');
        const mrp = toNumber($option.data('mrp'));
        if (mrp > 0) {
            $('#mrp').val(mrp);
            $('#sale_price').val(toNumber($option.data('sale-price')));
            $('#margin').val(toNumber($option.data('margin-percentage')));
            $('#amt_margin').val(toNumber($option.data('margin-amount')));
            $('#current_stock').val(toNumber($option.data('available')));
            refreshEstimationDisplayedStock();
            $('#percentage_discount').val(useMrpPricingMode ? toNumber($option.data('margin-percentage')).toFixed(2) : 0);
            refreshUnitPriceAndDiscount();
        }
    });
    

    $(document).on('submit', '#customerForm', function (e) {
        e.preventDefault();

        const customerName = $('#new_customer_name').val();
        const customerPhone = $('#new_customer_phone').val();
        let isValid = true;

        // Clear old error messages
        $('.error-name').text('');
        $('.error-phone').text('');

        // Client-side validation
        if (!customerName) {
            $('.error-name').text('Customer name is required.');
            isValid = false;
        }
        if (!customerPhone) {
            $('.error-phone').text('Phone number is required.');
            isValid = false;
        }

        if (!isValid) return; // Don't continue if form is invalid

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
                const newOption = new Option(`${response.customer} - ${response.phone}`, response.id, true, true);
                customerSelector.append(newOption).trigger('change');
                customerSelector.trigger({ type: 'select2:select', params: { data: customerData } });

                // Reset and hide modal
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
                showEstimationWarningToast('Save Failed', 'Failed to add customer.');
            }
        });
    });

    $(document).on('submit', '#productForm', function (e) {
        e.preventDefault();

        const product = $('#new_product_name').val().trim();
        const productCode = $('#new_product_code').val().trim();
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
                pprice: $('#new_pprice').val().trim(),
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
                    if (errors.pprice) $('.error-pprice').text(errors.pprice[0]);
                    return;
                }
                showEstimationWarningToast('Save Failed', 'Failed to add product.');
            }
        });
    });

    $('#addCustomerModal').on('hidden.bs.modal', function () {
        customerSelector = null;
    });

    $('#addCustomerModal').on('shown.bs.modal', function () {
        $('#new_customer_name').trigger('focus');
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

    // Function to populate the unit dropdown based on unit_qty
    function populateUnitDropdown(unit_qty) {
        var $itemUnit = $('#item_unit');
        $itemUnit.empty(); // Clear previous options
        var unit = $('#unit').val();

        if (unit_qty == 1) {
            // If unit_qty is 1, set the unit value to the dropdown
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
        } else if (unit_qty > 1) {
            // If unit_qty is greater than 1, add options 'Unit' and 'No'
            $itemUnit.append('<option value="' + unit + '">' + unit + '</option>');
            $itemUnit.append('<option value="No.s">' + 'No.s' + '</option>');
        }
    }

    // Apply autocomplete to Vendor Name input
    initializeSelect2("#customer", "customer", estimationSearchRoute);

    // Apply autocomplete to Vendor Phone input
    initializeSelect2("#phone", "customer", estimationSearchRoute);

    // Apply autocomplete to Product Name input
    initializeSelect2("#product", "product", estimationSearchRoute);

    let scannedProductRequest = null;
    let scannedProductVersion = 0;

    function cancelScannedProductSearch() {
        scannedProductVersion++;
        if (scannedProductRequest) {
            scannedProductRequest.abort();
            scannedProductRequest = null;
        }
    }

    function isProductSearchField(target) {
        const select = $('#product').data('select2');
        return select && select.isOpen() && select.dropdown.$search[0] === target;
    }

    document.addEventListener('input', function (event) {
        if (isProductSearchField(event.target)) cancelScannedProductSearch();
    }, true);

    // A scanner submits with Enter/Tab before Select2's delayed request finishes.
    document.addEventListener('keydown', function (event) {
        if (!['Enter', 'Tab'].includes(event.key) || !isProductSearchField(event.target)) return;
        const term = event.target.value.trim();
        if (!term) return;

        const select = $('#product').data('select2');
        const $highlighted = select.results.getHighlightedResults();
        const highlighted = $highlighted.length
            ? $.fn.select2.amd.require('select2/utils').GetData($highlighted[0], 'data')
            : null;
        const exactReady = productResults.some(item => [item.bar_code, item.product_code]
            .some(code => code != null && String(code) === term));
        if (event.key === 'Enter' && productResultTerm === term && highlighted && !exactReady
            && productResults.some(item => String(item.id) === String(highlighted.id))) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        if (event.repeat || scannedProductRequest) return;

        clearTimeout(select.dataAdapter._queryTimeout);
        if (select.dataAdapter._request) select.dataAdapter._request.abort();
        const version = ++scannedProductVersion;
        scannedProductRequest = $.getJSON(estimationSearchRoute, {query: term, searchBy: 'product'})
            .done(function (items) {
                if (version !== scannedProductVersion || !select.isOpen() || select.dropdown.$search.val().trim() !== term) return;
                const matches = items.map(productSearchResult);
                const exact = matches.find(item => [item.bar_code, item.product_code]
                    .some(code => code != null && String(code) === term));
                const result = exact || (matches.length === 1 ? matches[0] : null);
                if (!result) {
                    showEstimationWarningToast('Product Search', matches.length
                        ? 'Several products match. Select the required product from the list.'
                        : 'No product found for this barcode. Check the code or use Create New Product.');
                    select.trigger('query', {term: term});
                    return;
                }

                const $product = $('#product');
                $product.find('option').filter(function () { return String(this.value) === String(result.id); }).remove();
                $product.append(new Option(result.text, result.id, true, true)).trigger('change');
                $product.select2('close');
                $product.trigger({type: 'select2:select', params: {data: result}});
            })
            .fail(function (xhr, status) {
                if (version === scannedProductVersion && status !== 'abort') {
                    showEstimationWarningToast('Product Search', 'Could not load the product. Please scan again.');
                }
            })
            .always(function () {
                if (version === scannedProductVersion) scannedProductRequest = null;
            });
    }, true);

    $('#product').on('select2:close', cancelScannedProductSearch);

     // Event handler for when the unit is changed
     $('#item_unit').change(function() {
        refreshUnitPriceAndDiscount();
        refreshEstimationDisplayedStock();
     });

    $('#quantity').on('input', function () {
        if (useMrpPricingMode) {
            updateDiscountFromPercentage();
        }
    });

    //Discount field functionality
    $('#amount_discount').on('input', function () {
        let amount = toNumber($(this).val());
        let total = toNumber($('#quantity').val()) * toNumber($('#unit_price').val());
        let percent = total ? (amount / total) * 100 : 0;
        $('#percentage_discount').val(percent.toFixed(2));
    });

    $('#percentage_discount').on('input', function () {
        updateDiscountFromPercentage();
    });

    let existingItems = $('#existing_items').val();
    if (existingItems) {
        existingItems = JSON.parse(existingItems);
        
        existingItems.forEach((item, index) => {
            let total_before_discount = (item.esd_uprice * item.esd_itemqty).toFixed(2);
            let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');

            let total_discount = item.esd_adisc || 0;
            let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');

            let total_after_discount = (total_before_discount - total_discount).toFixed(2);
            let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');

            // Push to the same array used in Create mode
            estimationItems.push({
                product_id: item.esd_itemid,
                quantity: item.esd_itemqty,
                unit: item.esd_unit,
                unit_price: item.esd_uprice,
                unit_qty: item.esd_uqty,
                total_before_discount: item.esd_price,
                discount_amount: item.esd_adisc,
                discount_percentage: item.esd_pdisc || 0,
                total_after_discount: item.esd_total
                ,mrp_stock_lot_id: item.mrp_stock_lot_id || null
            });
             
            let newRow = `
                <tr>
                    <td>${++itemCount}</td>
                    <td>${item.product?.product || ''}</td>
                    ${window.mrpInventoryMode && estimationAccountEffect ? `<td>${item.mrp_stock_lot?.purchase_voucher || 'MRP lot'} / MRP ${Number(item.stock_mrp || 0).toFixed(2)}</td>` : ''}
                    <td><input type="number" class="form-control quantity" value="${item.esd_itemqty}" data-index="${itemCount - 1}"></td>
                    <td><input type="number" class="form-control unit_price" value="${item.esd_uprice}" data-index="${itemCount - 1}"></td>
                    <td class="total_before_discount">${formatted_total_before_discount}</td>
                    <td class="total_discount">${formatted_total_discount}</td>
                    <td class="total_after_discount">${formatted_total_after_discount}</td>
                    <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
                </tr>
            `;
            $("#estimation_table tbody").append(newRow);
        });

        updateEstimationSummary();
    }

    $(".addBtn").on("click keypress", function (e) {
        e.preventDefault();
        // Fetch input values
        let product_id          = $("#product_code").val();
        let product             = $("#product_name").val();
        let quantity            = $("#quantity").val();
        let unit                = $("#item_unit").val();
        let unit_price          = $("#unit_price").val();
        let unit_qty            = $("#unit_qty").val();
        let discount_amount     = $("#amount_discount").val();
        let discount_percentage = $("#percentage_discount").val();
        let mrpStockLotId       = window.mrpInventoryMode === true && estimationAccountEffect === true ? ($('#mrp_stock_lot_id').val() || null) : null;
 
        // Calculate total values
        let total_before_discount   = (unit_price * quantity).toFixed(2);
        let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');
        let total_discount          = (discount_amount || 0);
        let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');
        let total_after_discount    = (total_before_discount - total_discount).toFixed(2);
        let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        
        // Validate required fields
        if (!product || !unit_price || !quantity) {
            showEstimationWarningToast('Missing Fields', 'Please fill in required fields (Product, Unit Price, Quantity)');
            return;
        }
        if (window.mrpInventoryMode === true && estimationAccountEffect === true && !mrpStockLotId) {
            showEstimationWarningToast('Stock Lot Required', 'Select the MRP stock lot matching the product.');
            return;
        }

        let newItem = {
            product_id: product_id,
            quantity: quantity,
            unit: unit,
            unit_price: unit_price,
            unit_qty: unit_qty,
            total_before_discount: total_before_discount,
            discount_amount: total_discount,
            discount_percentage: discount_percentage,
            total_after_discount: total_after_discount
            ,mrp_stock_lot_id: mrpStockLotId
        };

        console.log("New item being added: ", newItem);
        estimationItems.push(newItem);
        console.log("Current estimationItems: ", estimationItems);

        // Create a new row
        let newRow = `
            <tr>
                <td>${++itemCount}</td>
                <td>${product}</td>
                ${window.mrpInventoryMode && estimationAccountEffect ? `<td>${$('#mrp_stock_lot_id option:selected').text()}</td>` : ''}
                <td><input type="number" class="form-control quantity" value="${quantity}" data-index="${itemCount - 1}"></td>
                <td><input type="number" class="form-control unit_price" value="${unit_price}" data-index="${itemCount - 1}"></td>
                <td class="total_before_discount">${formatted_total_before_discount}</td>
                <td class="total_discount">${formatted_total_discount}</td>
                <td class="total_after_discount">${formatted_total_after_discount}</td>
                <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
            </tr>
        `;

        // Append row to table
        $("#estimation_table tbody").append(newRow);

        // Reset input fields
        $('#product').val(null).empty().trigger('change');
        $("#product_id, #product_code, #product_name, #price, #sale_price, #mrp, #margin, #amt_margin, #current_stock, #unit_qty, #unit, #hsn_code, #unit_price, #quantity, #in_stock").val("");
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        $("#amount_discount, #percentage_discount").val(0);
        $('#mrp_stock_lot_id').val('').trigger('change');
        $('#mrp_lot_selector_group').hide();

        updateEstimationSummary();
    });

    // Update calculations when unit price or quantity is changed
    $(document).on("input", ".unit_price, .quantity", function () {
        let row = $(this).closest("tr");
        let index = $(this).data("index");

        let newUnitPrice    = parseFloat(row.find(".unit_price").val()) || 0;
        let newQuantity     = parseFloat(row.find(".quantity").val()) || 0;

    // Recalculate values
        let newTotalBeforeDiscount = (newUnitPrice * newQuantity).toFixed(2);
        let formatted_newTotalBeforeDiscount = Number(newTotalBeforeDiscount).toLocaleString('en-IN');
        let newDiscountAmount = parseFloat(row.find(".total_discount").text()) || 0;
        let formatted_newDiscountAmount = Number(newDiscountAmount).toLocaleString('en-IN');
        let newTotalAfterDiscount = (newTotalBeforeDiscount - newDiscountAmount).toFixed(2);
        let formatted_newTotalAfterDiscount = Number(newTotalAfterDiscount).toLocaleString('en-IN');

    // Update row values
        row.find(".total_before_discount").text(formatted_newTotalBeforeDiscount);
        row.find(".total_after_discount").text(formatted_newTotalAfterDiscount);

    // Update array with new values
        estimationItems[index].unit_price = newUnitPrice;
        estimationItems[index].quantity = newQuantity;
        estimationItems[index].total_before_discount = newTotalBeforeDiscount;
        estimationItems[index].total_after_discount = newTotalAfterDiscount;
 
        updateEstimationSummary();
    });

    // Delete row event
    $(document).on("click", ".deleteRow", function () {
        let rowIndex = $(this).closest("tr").index();
        estimationItems.splice(rowIndex, 1);
        $(this).closest("tr").remove();
        itemCount--;
        $("#estimation_table tbody tr").each(function (index) {
            $(this).find("td:first").text(index + 1); // Update serial number
        });

        updateEstimationSummary();
    });

    function updateEstimationSummary() {
        let newAmount = 0, newDiscount = 0, newAmountPayable = 0;

        $(".total_before_discount").each(function () {
            newAmount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_discount").each(function () {
            newDiscount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_after_discount").each(function () {
            newAmountPayable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        const roundedAmountPayable = Math.round(newAmountPayable);
        const roundOff = (roundedAmountPayable - newAmountPayable).toFixed(2);
        const formatted_newAmountPayable = roundedAmountPayable.toLocaleString('en-IN');

        $("#amount").val(newAmount);
        $("#discount").val(newDiscount);
        $("#grand_total").val(newAmountPayable);
        $("#amount_payable").val(roundedAmountPayable);
        $("#amount_payable1").html(formatted_newAmountPayable);
        $("#round_off").val(roundOff);
        $("#round_off1").html(roundOff);
        updatePaymentSummary();

        // ✅ Update table footer totals
        $("#footer_total_before_discount").html("Rs. " + newAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_discount_amount").html("Rs. " + newDiscount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_total_after_discount").html("Rs. " + newAmountPayable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
    }

    function updatePaymentSummary() {
        if (typeof estimationAccountEffect === 'undefined' || !estimationAccountEffect) {
            return;
        }

        let amountPayable = parseFloat(($("#amount_payable").val() || "0").toString().replace(/,/g, '')) || 0;
        let amountPaid = parseFloat(($("#amount_paid").val() || "0").toString().replace(/,/g, '')) || 0;

        if (amountPaid > amountPayable) {
            amountPaid = amountPayable;
            $("#amount_paid").val(amountPaid.toFixed(2));
        }

        let balance = Math.max(amountPayable - amountPaid, 0);
        $("#balance").val(balance.toFixed(2));
        $("#balance1").html(balance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        if (balance > 0) {
            $("#due_days_row").show();
        } else {
            $("#due_days_row").hide();
            $("#due_days").val("");
            $("#due_date_text").html("-");
        }

        updateDueDateText();
    }

    function updateDueDateText() {
        if (typeof estimationAccountEffect === 'undefined' || !estimationAccountEffect) {
            return;
        }

        let dueDays = parseInt($("#due_days").val(), 10);
        let dateValue = $("#estimation_date").val();

        if (isNaN(dueDays) || !dateValue) {
            $("#due_date_text").html("-");
            return;
        }

        let parts = dateValue.split("/");
        if (parts.length !== 3) {
            $("#due_date_text").html("-");
            return;
        }

        let dueDate = new Date(parts[2], parts[1] - 1, parts[0]);
        dueDate.setDate(dueDate.getDate() + dueDays);
        $("#due_date_text").html([
            String(dueDate.getDate()).padStart(2, "0"),
            String(dueDate.getMonth() + 1).padStart(2, "0"),
            dueDate.getFullYear()
        ].join("-"));
    }

    $(document).on("input change", "#amount_paid, #due_days, #estimation_date", function () {
        updatePaymentSummary();
    });

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });

    updatePaymentSummary();

});
