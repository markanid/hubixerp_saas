$(document).ready(function() {

    let itemCount = 0;
    let itemSCount = 0;
    let saleItems = [];
    let serviceItems = [];
    const allowOutOfStockSale = window.allowOutOfStockSale === true;
    const showManufacturingDate = window.showServiceManufacturingDate === true;
    const serviceCollectTax = window.serviceCollectTax !== false;
    const useMrpPricingMode = window.useServiceMrpPricingMode === true;

    function serviceNumber(value) {
        return parseFloat(value) || 0;
    }

    function refreshServiceDisplayedStock() {
        const packageStock = serviceNumber($('#current_stock').val());
        const unitQty = Math.max(serviceNumber($('#unit_qty').val()), 1);
        const selectedUnit = $('#item_unit').val();
        $('#in_stock').val((selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? packageStock * unitQty : packageStock);
    }

    function refreshServiceLotPrice() {
        const $lot = $('#mrp_stock_lot_id option:selected');
        if (!$lot.val()) return;
        const basePrice = useMrpPricingMode ? serviceNumber($lot.data('mrp')) : serviceNumber($lot.data('sale-price'));
        const unitQty = Math.max(serviceNumber($('#unit_qty').val()), 1);
        const selectedUnit = $('#item_unit').val();
        const unitPrice = (selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? basePrice / unitQty : basePrice;
        $('#price').val(basePrice);
        $('#unit_price').val(unitPrice.toFixed(2));
        if (useMrpPricingMode) {
            $('#percentage_discount').val(serviceNumber($lot.data('margin-percentage')).toFixed(2)).trigger('input');
        } else {
            $('#percentage_discount, #amount_discount').val(0);
        }
    }

    function shouldCollectServiceTax() {
        return serviceCollectTax && String($('#sv_type').val()) !== '0';
    }

    function updateServiceTaxColumnVisibility() {
        $('.service-tax-column').toggle(shouldCollectServiceTax());
    }

    function resetServiceTotals() {
        $('#sale_table tbody, #service_table tbody').empty();
        saleItems = [];
        serviceItems = [];
        itemCount = 0;
        itemSCount = 0;

        $('#footer_total_before_discount, #footer_discount_amount, #footer_total_after_discount, #footer_taxable_value, #footer_gst_value, #svfooter_total_before_discount, #svfooter_discount_amount, #svfooter_total_after_discount, #svfooter_taxable_value, #svfooter_gst_value').text('Rs. 0.00');
        $('#amount, #totalgst, #discount, #grand_total, #round_off, #amount_payable').val(0);
        $('#round_off1, #amount_payable1').text('0');
        $('#balance_to_pay').val('0.00');
        $('#balance_to_pay1').text('0');
        $('#payment_due_days').val('');
        $('#sale_items, #service_items').val('');
        updatePaymentDueDaysVisibility();
    }

    function showServiceAlert(icon, title, text) {
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

    function hasSaleSectionItems() {
        return Array.isArray(saleItems) && saleItems.length > 0;
    }

    function setSaleSectionVisible(show) {
        $('#service_sale_section').toggle(show);
        $('#service_sale_choice_card').toggle(!show);

        if (show) {
            setTimeout(function () {
                $('#product').trigger('change.select2');
            }, 0);
        }
    }

    function clearSaleInputs() {
        $('#product').val(null).empty().trigger('change');
        $('#product_id, #product_code, #product_name, #price, #current_stock, #gst, #unit_qty, #unit, #hsn_code, #unit_price, #quantity, #in_stock').val('');
        $('#amount_discount, #percentage_discount').val(0);
        $('#item_unit').empty().append('<option value="">Select Unit</option>');

        if (showManufacturingDate) {
            $('#manufacturingdate').val('');
        }

        $('#stock_batch_id').val('').trigger('change');
        $('#service_batch_selector_group').hide();
    }

    function clearSaleSectionItems() {
        $('#sale_table tbody').empty();
        saleItems = [];
        itemCount = 0;
        clearSaleInputs();
        calculateTotals();
    }

    function hideSaleSectionWithConfirmation() {
        if (!hasSaleSectionItems()) {
            clearSaleInputs();
            setSaleSectionVisible(false);
            return;
        }

        const clearAndHide = function () {
            clearSaleSectionItems();
            setSaleSectionVisible(false);
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Remove Product Sale Items?',
                text: 'Hiding the sale section will remove the product sale items already added.',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, remove',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    clearAndHide();
                }
            });
            return;
        }

        if (confirm('Hiding the sale section will remove product sale items already added. Continue?')) {
            clearAndHide();
        }
    }

    // Before form submission, store table data in hidden field
    $("#addService").submit(function (event) {
        event.preventDefault();

        const hasSaleItems = Array.isArray(saleItems) && saleItems.length > 0;
        const hasServiceItems = Array.isArray(serviceItems) && serviceItems.length > 0;
    
        // Check if saleItems or serviceItems is empty
        if (!hasSaleItems && !hasServiceItems) {
            showServiceAlert('warning', 'No Items Added', 'Please add at least one sale or service item before submitting the form.');
            return false;
        } else {
                $("#sale_items").val(JSON.stringify(saleItems));
                $("#service_items").val(JSON.stringify(serviceItems));
                
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
    let productCreateType = 'product';
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
                            return {
                                id: item.id,
                                text: item.product_code + " - " + item.product,
                                product_code: item.product_code,
                                product_name: item.product,
                                hsn_code: item.hsn_code,
                                price: item.price,
                                sale_price: item.price,
                                unit: item.unit,
                                unit_qty: item.uqty,
                                gst: item.gst,
                                current_stock: item.current_stock,
                                is_batch_managed: item.is_batch_managed
                            };
                        } else if (searchBy === 'svproduct') {
                            return {
                                id: item.id,
                                text: item.product_code + " - " + item.product,
                                product_code: item.product_code,
                                product_name: item.product,
                                price: item.price,
                                sale_price: item.price,
                                gst: item.gst
                            };
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

                    if (searchBy === 'product' || searchBy === 'svproduct') {
                        results.push({
                            id: 'new',
                            text: searchBy === 'svproduct' ? ' Create New Service Item' : ' Create New Product',
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

            if ((searchBy === 'product' || searchBy === 'svproduct') && data.id === 'new') {
                productSelector = $(this);
                productCreateType = searchBy;
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
                $('#gst').val(data.gst);
                $('#unit_qty').val(data.unit_qty);
                $('#unit').val(data.unit);
                $('#hsn_code').val(data.hsn_code);
                $('#price').val(data.price);
                $('#unit_price').val(data.sale_price);
                $('#current_stock').val(data.current_stock);
                $('#in_stock').val(data.current_stock);
                populateUnitDropdown(data.unit_qty);
                $('#product').data('batch-managed', data.is_batch_managed === true || data.is_batch_managed == 1);
                loadServiceBatches(data.id, $('#product').data('batch-managed'));
                loadServiceMrpLots(data.id);
            } else if (searchBy === 'svproduct') {
                $('#svproduct_id').val(data.id);
                $('#svproduct_code').val(data.product_code);
                $('#svproduct_name').val(data.product_name);
                $('#svgst').val(data.gst);
                $('#svunit_price').val(data.sale_price);
                $('#svprice').val(data.price);
            }
        });
    }

    function loadServiceBatches(productId, isBatchManaged) {
        const $group = $('#service_batch_selector_group');
        const $select = $('#stock_batch_id');
        if (window.batchInventoryMode !== true || !isBatchManaged || !$select.length) {
            $group.hide();
            $select.html('<option value="">Auto FIFO / earliest expiry</option>');
            return;
        }
        $group.show();
        $select.html('<option value="">Auto FIFO / earliest expiry</option>');
        $.getJSON(serviceProductBatchesRoute.replace('__PRODUCT__', productId), function (batches) {
            batches.forEach(function (batch) {
                const text = `${batch.batch_no} | Exp: ${batch.expiry_date || 'N/A'} | Qty: ${batch.display_quantity}`;
                $select.append(new Option(text, batch.id, false, false));
            });
        });
    }

    function loadServiceMrpLots(productId) {
        const $group = $('#service_mrp_lot_selector_group');
        const $select = $('#mrp_stock_lot_id');
        if (window.mrpInventoryMode !== true || !$select.length) {
            $group.hide();
            return;
        }

        $group.show();
        $select.html('<option value="">Select Stock / MRP</option>');
        $.getJSON(serviceProductMrpLotsRoute.replace('__PRODUCT__', productId), function (lots) {
            lots.forEach(function (lot) {
                const text = `${lot.purchase_voucher || 'Opening/Return'} | ${lot.purchase_date || ''} | MRP: ${Number(lot.mrp).toFixed(2)} | Sale: ${Number(lot.sale_price).toFixed(2)}`;
                const option = new Option(text, lot.id);
                $(option).attr('data-mrp', lot.mrp)
                    .attr('data-sale-price', lot.sale_price)
                    .attr('data-margin-percentage', lot.margin_percentage)
                    .attr('data-available', lot.display_quantity);
                $select.append(option);
            });
            if (lots.length === 1) $select.val(String(lots[0].id)).trigger('change');
        });
    }

    $('#mrp_stock_lot_id').on('change', function () {
        const $option = $(this).find('option:selected');
        if (!$(this).val()) return;
        const available = parseFloat($option.data('available')) || 0;
        const mrp = parseFloat($option.data('mrp')) || 0;
        $('#current_stock').val(available);
        refreshServiceDisplayedStock();
        refreshServiceLotPrice();
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
                showServiceAlert('error', 'Customer Save Failed', 'Failed to add customer.');
            }
        });
    });

    $(document).on('submit', '#productForm', function (e) {
        e.preventDefault();

        let name    = $('#new_product_name').val().trim();
        let code    = $('#new_product_code').val().trim();
        let hsn     = $('#new_hsn').val().trim();
        let gst     = $('#new_gst').val();
        let pprice  = $('#new_pprice').val().trim();
        let mrp     = $('#new_mrp').val().trim();
        let margin  = $('#new_margin').val().trim();
        let price   = $('#new_price').val().trim();

        let isValid = true;
        $('.error-product-name').text('');
        $('.error-product-code').text('');
        $('.error-price').text('');

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

        if (!isValid) return;

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
                typeid: productCreateType === 'svproduct' ? 3 : 2,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                const productData = {
                    ...response,
                    product_name: response.product,
                    unit_qty: response.uqty,
                    sale_price: response.price
                };
                const newOption = new Option(
                    `${response.product_code} - ${response.product}`,
                    response.id,
                    true,
                    true
                );
                productSelector.append(newOption).trigger('change');
                productSelector.trigger({ type: 'select2:select', params: { data: productData } });

                $('#productForm')[0].reset();
                $('#addProductModal').modal('hide');
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    let errors = xhr.responseJSON.errors;
                    if (errors.product) $('.error-product-name').text(errors.product[0]);
                    if (errors.product_code) $('.error-product-code').text(errors.product_code[0]);
                    if (errors.price) $('.error-price').text(errors.price[0]);
                    return;
                }

                showServiceAlert('error', 'Product Save Failed', 'Failed to add product.');
            }
        });
    });

    $('#addCustomerModal').on('hidden.bs.modal', function () {
        customerSelector = null;
    });

    $('#addProductModal').on('hidden.bs.modal', function () {
        productSelector = null;
        productCreateType = 'product';
    });

    $('#addCustomerModal').on('shown.bs.modal', function () {
        $('#new_customer_name').trigger('focus');
    });

    $('#addProductModal').on('shown.bs.modal', function () {
        $('#new_product_name').trigger('focus');
        $.ajax({
            url: productNewCodeRoute,
            type: 'POST',
            success: function (res) {
                $('#new_product_code').val(res.code);
            }
        });
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

    // Apply autocomplete to Customer Name input
    initializeSelect2("#customer", "customer", serviceSearchRoute);

    // Apply autocomplete to Product Name input
    initializeSelect2("#product", "product", serviceSearchRoute);

    // Apply autocomplete to Product Name input
    initializeSelect2("#svproduct", "svproduct", serviceSearchRoute);

    $('#show_service_sale_section').on('click', function () {
        setSaleSectionVisible(true);
        $('#product').focus();
    });

    $('#hide_service_sale_section').on('click', function () {
        hideSaleSectionWithConfirmation();
    });

     // Event handler for when the unit is changed
     $('#item_unit').change(function() {
        // Get the selected unit
        var selectedUnit = $(this).val();
        var salePrice   = parseFloat($('#price').val());
        var unitQty     = parseFloat($('#unit_qty').val());

        // Update unit price based on selected unit
        if (!isNaN(salePrice) && !isNaN(unitQty) && unitQty > 1) {
            var newSalePrice = (selectedUnit === "No.s" || selectedUnit === "Nos.") ? salePrice / unitQty : salePrice;
            $('#unit_price').val(newSalePrice.toFixed(2));
        }

        // Update in_stock based on unit
        refreshServiceDisplayedStock();
        if (window.mrpInventoryMode === true && $('#mrp_stock_lot_id').val()) refreshServiceLotPrice();
        $('#quantity').val('');
    });

    //In Stock field functionality
    $('#quantity').on('input', function () {
        var quantity = parseFloat($(this).val());
        var inStock = parseFloat($('#in_stock').val());

        if (useMrpPricingMode && $('#mrp_stock_lot_id').val()) {
            $('#percentage_discount').trigger('input');
        }

        if (!isNaN(quantity) && !isNaN(inStock)) {
            if (quantity > inStock) {
                showServiceAlert('warning', 'Insufficient Stock', 'Entered quantity exceeds available stock.');
                if (!allowOutOfStockSale) {
                    const allowedQuantity = Math.max(inStock, 0);
                    $(this).val(allowedQuantity > 0 ? allowedQuantity : '');
                }
                $(this).focus();
            }
        }
    });

    //Discount field functionality
    $('#amount_discount').on('input', function () {
        let amount = parseFloat($(this).val()) || 0;
        let total = $('#quantity').val() * $('#unit_price').val();
        let percent = total ? (amount / total) * 100 : 0;
        $('#percentage_discount').val(percent.toFixed(2));
    });

    $('#percentage_discount').on('input', function () {
        let percent = parseFloat($(this).val()) || 0;
        let total = $('#quantity').val() * $('#unit_price').val();
        let amount = (percent / 100) * total;
        $('#amount_discount').val(amount.toFixed(2));
    });

    $('#svamount_discount').on('input', function () {
        let amount = parseFloat($(this).val()) || 0;
        let total = $('#svunit_price').val();
        let percent = total ? (amount / total) * 100 : 0;
        $('#svpercentage_discount').val(percent.toFixed(2));
    });

    $('#svpercentage_discount').on('input', function () {
        let percent = parseFloat($(this).val()) || 0;
        let total = $('#svunit_price').val();
        let amount = (percent / 100) * total;
        $('#svamount_discount').val(amount.toFixed(2));
    });

    //Amount paid functionality
    $('#amount_paid').on('input', function () {
        let amount_paid     = parseFloat($(this).val()) || 0;
        let amount_payable  = parseFloat($('#amount_payable').val()) || 0;
        let balance_to_pay = parseFloat(amount_payable) - parseFloat(amount_paid);
        let formatted_balance_to_pay = balance_to_pay.toLocaleString('en-IN');
        $('#balance_to_pay').val(balance_to_pay.toFixed(2));
        $("#balance_to_pay1").html(formatted_balance_to_pay);
        updatePaymentDueDaysVisibility();
    });

    $('#payment_due_days, #service_date').on('input change dp.change', function () {
        updatePaymentDueDateText();
    });

    function updatePaymentDueDaysVisibility() {
        const amountPaid = parseFloat($('#amount_paid').val()) || 0;
        const amountPayable = parseFloat($('#amount_payable').val()) || 0;
        const hasBalance = amountPayable > 0 && amountPaid < amountPayable;

        $('#payment_due_days_row').toggle(hasBalance);
        $('#payment_due_days').prop('required', false);
        if (!hasBalance) {
            $('#payment_due_days').val('');
        }
        updatePaymentDueDateText();
    }

    function updatePaymentDueDateText() {
        const $dueDateText = $('#payment_due_date_text');
        const dueDays = parseInt($('#payment_due_days').val(), 10);
        const serviceDate = $('#service_date').val();

        if (!$('#payment_due_days_row').is(':visible') || isNaN(dueDays) || !serviceDate) {
            $dueDateText.text('');
            return;
        }

        const dueDate = moment(serviceDate, 'DD/MM/YYYY').add(dueDays, 'days');
        $dueDateText.text(dueDate.isValid() ? `Due date: ${dueDate.format('DD/MM/YYYY')}` : '');
    }

    let existingsaleItems = $('#existing_sale_items').val();
    let existingserviceItems = $('#existing_service_items').val();
    
    if (existingsaleItems) {
        existingsaleItems = JSON.parse(existingsaleItems);
        
        existingsaleItems.forEach((item, index) => {
            let itemGstRate = shouldCollectServiceTax() ? (parseFloat(item.product?.gst) || 0) : 0;

            let total_before_discount = parseFloat(item.svd_price).toFixed(2);
            let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');

            let total_discount = item.svd_adisc || 0;
            let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');

            let total_after_discount = (total_before_discount - total_discount).toFixed(2);
            let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        
            let taxable_value           = itemGstRate > 0 ? (total_after_discount / (1 + itemGstRate / 100)).toFixed(2) : total_after_discount;
            let formatted_taxable_value = Number(taxable_value).toLocaleString('en-IN');
            
            let gst_value               = (total_after_discount - taxable_value).toFixed(2);
            let formatted_gst_value     = Number(gst_value).toLocaleString('en-IN');

            // Push to the same array used in Create mode
            saleItems.push({
                product_id: item.svd_itemid,
                hsn_code: item.svd_hsn,
                quantity: item.svd_itemqty,
                unit: item.svd_unit,
                unit_price: item.svd_uprice,
                unit_qty: item.svd_uqty,
                total_before_discount: item.svd_price,
                discount_amount: item.svd_adisc,
                discount_percentage: item.svd_pdisc || 0,
                gst_value: gst_value,
                total_after_discount: item.svd_total,
                manufacturing_date: showManufacturingDate ? item.manufacturing_date : null,
                stock_batch_id: item.stock_batch_id || null
                ,mrp_stock_lot_id: item.mrp_stock_lot_id || null
            });
            let mfgDateCell = showManufacturingDate ? `<td>${item.manufacturing_date || ''}</td>` : '';
            let batchCell = window.batchInventoryMode ? `<td>${item.batch_no || 'FIFO allocation'}</td>` : '';
            let mrpLotCell = window.mrpInventoryMode ? `<td>${item.mrp_stock_lot?.purchase_voucher || 'MRP lot'} / MRP ${Number(item.stock_mrp || 0).toFixed(2)}</td>` : '';
             
            let newRow = `
                <tr data-gst="${itemGstRate}">
                    <td>${++itemCount}</td>
                    <td>${item.product?.product || ''}</td>
                    <td>${item.svd_hsn}</td>
                    ${mfgDateCell}
                    ${batchCell}
                    ${mrpLotCell}
                    <td><input type="number" class="form-control quantity" value="${item.svd_itemqty}" data-index="${itemCount - 1}"></td>
                    <td><input type="number" class="form-control unit_price" value="${item.svd_uprice}" data-index="${itemCount - 1}"></td>
                    <td class="total_before_discount">${formatted_total_before_discount}</td>
                    <td class="total_discount">${formatted_total_discount}</td>
                    <td class="total_after_discount">${formatted_total_after_discount}</td>
                    <td class="taxable_value service-tax-column">${formatted_taxable_value}</td>
                    <td class="gst_value service-tax-column">${formatted_gst_value} (${itemGstRate}%)</td>
                    <td><button type="button" class="btn btn-danger btn-sm deleteRow"><i class="fa fa-trash"></i></button></td>
                </tr>
            `;
            $("#sale_table tbody").append(newRow);
        });

        // Recalculate totals
        calculateTotals();
    }

    if (existingserviceItems) {
        existingserviceItems = JSON.parse(existingserviceItems);
        existingserviceItems.forEach((item, index) => {
            let itemGstRate = shouldCollectServiceTax() ? (parseFloat(item.product?.gst) || 0) : 0;
        
            let svtotal_before_discount = parseFloat(item.svd_price).toFixed(2);
            let svformatted_total_before_discount = Number(svtotal_before_discount).toLocaleString('en-IN');

            let svtotal_discount = item.svd_adisc || 0;
            let svformatted_total_discount = Number(svtotal_discount).toLocaleString('en-IN');

            let svtotal_after_discount = (svtotal_before_discount - svtotal_discount).toFixed(2);
            let svformatted_total_after_discount = Number(svtotal_after_discount).toLocaleString('en-IN');
        
            let svtaxable_value           = itemGstRate > 0 ? (svtotal_after_discount / (1 + itemGstRate / 100)).toFixed(2) : svtotal_after_discount;
            let svformatted_taxable_value = Number(svtaxable_value).toLocaleString('en-IN');
            
            let svgst_value               = (svtotal_after_discount - svtaxable_value).toFixed(2);
            let svformatted_gst_value     = Number(svgst_value).toLocaleString('en-IN');

            // Push to the same array used in Create mode
            serviceItems.push({
                product_id: item.svd_itemid,
                unit_price: item.svd_uprice,
                discount_amount: item.svd_adisc,
                discount_percentage: item.svd_pdisc || 0,
                gst_value: svgst_value,
                total_after_discount: item.svd_total,
                remarks: item.svd_remark
            });
             
            let newSRow = `
                <tr data-gst="${itemGstRate}">
                    <td>${++itemSCount}</td>
                    <td>${item.product?.product || ''}</td>
                    <td class="svtotal_before_discount">${svformatted_total_before_discount}</td>
                    <td class="svtotal_discount">${svformatted_total_discount}</td>
                    <td class="svtotal_after_discount">${svformatted_total_after_discount}</td>
                    <td class="svtaxable_value service-tax-column">${svformatted_taxable_value}</td>
                    <td class="svgst_value service-tax-column">${svformatted_gst_value} (${itemGstRate}%)</td>
                    <td>${item.svd_remark} </td>
                    <td><button type="button" class="btn btn-danger btn-sm deleteSRow"><i class="fa fa-trash"></i></button></td>
                </tr>
            `;
            $("#service_table tbody").append(newSRow);
        });

        // Recalculate totals
        calculateTotals();
    }

    $(".addBtn").on("click keypress", function (e) {
        e.preventDefault();
        // Fetch input values for sales
        let product_id          = $("#product_code").val();
        let product             = $("#product_name").val();
        let hsn_code            = $("#hsn_code").val();
        let quantity            = $("#quantity").val();
        let unit                = $("#item_unit").val();
        let unit_price          = $("#unit_price").val();
        let unit_qty            = $("#unit_qty").val();
        let discount_amount     = $("#amount_discount").val();
        let discount_percentage = $("#percentage_discount").val();
        let gst                 = shouldCollectServiceTax() ? ($("#gst").val() || 0) : 0;
        let stockBatchId        = window.batchInventoryMode === true && $('#product').data('batch-managed')
            ? ($('#stock_batch_id').val() || null) : null;
        let batchText           = stockBatchId ? $('#stock_batch_id option:selected').text() : 'Auto FIFO';
        let mrpStockLotId       = window.mrpInventoryMode === true ? ($('#mrp_stock_lot_id').val() || null) : null;
        let mrpLotText          = mrpStockLotId ? $('#mrp_stock_lot_id option:selected').text() : '';
        let manufacturing_date  = null;

        // Calculate total values of sales
        let total_before_discount   = (unit_price * quantity).toFixed(2);
        let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');
        let total_discount          = (discount_amount || 0);
        let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');
        let total_after_discount    = (total_before_discount - total_discount).toFixed(2);
        let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        let taxable_value           = Number(gst) > 0 ? (total_after_discount / (1 + gst / 100)).toFixed(2) : total_after_discount;
        let formatted_taxable_value = Number(taxable_value).toLocaleString('en-IN');
        let gst_value               = (total_after_discount - taxable_value).toFixed(2);
        let formatted_gst_value     = Number(gst_value).toLocaleString('en-IN');
        
        // Validate required fields
        if (!product || !unit_price || !quantity) {
            showServiceAlert('warning', 'Missing Fields', 'Please fill in all required sales fields (Product, Unit Price, Quantity).');
            return;
        }
        if (window.mrpInventoryMode === true && !mrpStockLotId) {
            showServiceAlert('warning', 'Stock Lot Required', 'Select the MRP stock lot matching the physical product.');
            return;
        }

        const availableStock = parseFloat($('#in_stock').val()) || 0;
        if (!allowOutOfStockSale && parseFloat(quantity) > availableStock) {
            showServiceAlert('warning', 'Insufficient Stock', 'This product cannot be added because the entered quantity exceeds available stock.');
            return;
        }

        if (showManufacturingDate) {
            const val = $('#manufacturingdate').val();
            manufacturing_date = val && val.trim() !== '' ? val.trim() : null;
        }

        let newItem = {
            product_id: product_id,
            hsn_code: hsn_code,
            quantity: quantity,
            unit: unit,
            unit_price: unit_price,
            unit_qty: unit_qty,
            total_before_discount: total_before_discount,
            discount_amount: total_discount,
            discount_percentage: discount_percentage,
            gst_value: gst_value,
            total_after_discount: total_after_discount,
            manufacturing_date: manufacturing_date,
            stock_batch_id: stockBatchId
            ,mrp_stock_lot_id: mrpStockLotId
        };

        console.log("New item being added: ", newItem);
        saleItems.push(newItem);
        console.log("Current saleItems: ", saleItems);

        // Create a new row
        let mfgDateCell = showManufacturingDate ? `<td>${manufacturing_date || ''}</td>` : '';
        let batchCell = window.batchInventoryMode ? `<td>${$('#product').data('batch-managed') ? batchText : ''}</td>` : '';
        let mrpLotCell = window.mrpInventoryMode ? `<td>${mrpLotText}</td>` : '';
        let newRow = `
            <tr data-gst="${gst}">
                <td>${++itemCount}</td>
                <td>${product}</td>
                <td>${hsn_code}</td>
                ${mfgDateCell}
                ${batchCell}
                ${mrpLotCell}
                <td><input type="number" class="form-control quantity" value="${quantity}" data-index="${itemCount - 1}"></td>
                <td><input type="number" class="form-control unit_price" value="${unit_price}" data-index="${itemCount - 1}"></td>
                <td class="total_before_discount">${formatted_total_before_discount}</td>
                <td class="total_discount">${formatted_total_discount}</td>
                <td class="total_after_discount">${formatted_total_after_discount}</td>
                <td class="taxable_value service-tax-column">${formatted_taxable_value}</td>
                <td class="gst_value service-tax-column">${formatted_gst_value} (${gst}%)</td>
                <td><button type="button" class="btn btn-danger btn-sm deleteRow"><i class="fa fa-trash"></i></button></td>
            </tr>
        `;

        // Append row to table
        $("#sale_table tbody").append(newRow);

        // Reset input fields
        $('#product').val(null).empty().trigger('change');
        $("#product_id, #product_code, #product_name, #price, #current_stock, #gst, #unit_qty, #unit, #hsn_code, #unit_price, #quantity, #in_stock").val("");
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        if (showManufacturingDate) {
            $('#manufacturingdate').val('');
        }
        $("#product").focus();
        $("#amount_discount, #percentage_discount").val(0);
        $('#stock_batch_id').val('').trigger('change');
        $('#service_batch_selector_group').hide();
        $('#mrp_stock_lot_id').val('').trigger('change');
        $('#service_mrp_lot_selector_group').hide();
        $('#product').removeData('batch-managed');

        calculateTotals();
    });

    $(".addSBtn").click(function (e) {
        e.preventDefault();
        
        // Fetch input values for service
        let svproduct_id          = $("#svproduct_code").val();
        let svproduct             = $("#svproduct_name").val();
        let svunit_price          = $("#svunit_price").val();
        let svdiscount_amount     = $("#svamount_discount").val();
        let svdiscount_percentage = $("#svpercentage_discount").val();
        let svgst                 = shouldCollectServiceTax() ? ($("#svgst").val() || 0) : 0;
        let remarks               = $("#remarks").val();

        // Calculate total values of services
        let svformatted_unit_price          = Number(svunit_price).toLocaleString('en-IN');
        let svtotal_discount                = (svdiscount_amount || 0);
        let svformatted_total_discount      = Number(svtotal_discount).toLocaleString('en-IN');
        let svtotal_after_discount          = (svunit_price - svtotal_discount).toFixed(2);
        let svformatted_total_after_discount= Number(svtotal_after_discount).toLocaleString('en-IN');
        let svtaxable_value                 = Number(svgst) > 0 ? (svtotal_after_discount / (1 + svgst / 100)).toFixed(2) : svtotal_after_discount;
        let svformatted_taxable_value       = Number(svtaxable_value).toLocaleString('en-IN');
        let svgst_value                     = (svtotal_after_discount - svtaxable_value).toFixed(2);
        let svformatted_gst_value           = Number(svgst_value).toLocaleString('en-IN');

        // Validate service fields if service data is being entered
        if (!svproduct || !svunit_price) {
            showServiceAlert('warning', 'Missing Fields', 'Please fill in all required service fields: Service and Unit Price.');
            return;
        }
        
        let newSItem = {
            product_id: svproduct_id,
            unit_price: svunit_price,
            discount_amount: svtotal_discount,
            discount_percentage: svdiscount_percentage,
            gst_value: svgst_value,
            total_after_discount: svtotal_after_discount,
            remarks: remarks
        };
        console.log("New Service item being added: ", newSItem);
        serviceItems.push(newSItem);
        console.log("Current servicecItems: ", serviceItems);
        // Create a new row
        let newSRow = `
            <tr data-gst="${svgst}">
                <td>${++itemSCount}</td>
                <td>${svproduct}</td>
                <td class="svtotal_before_discount">${svformatted_unit_price}</td>
                <td class="svtotal_discount">${svformatted_total_discount}</td>
                <td class="svtotal_after_discount">${svformatted_total_after_discount}</td>
                <td class="svtaxable_value service-tax-column">${svformatted_taxable_value}</td>
                <td class="svgst_value service-tax-column">${svformatted_gst_value} (${svgst}%)</td>
                <td>${remarks}</td>
                <td><button type="button" class="btn btn-danger btn-sm deleteSRow"><i class="fa fa-trash"></i></button></td>
            </tr>
        `;

        // Append row to table
        $("#service_table tbody").append(newSRow);

        // Reset input fields
        $('#svproduct').val(null).empty().trigger('change');
        $("#svproduct_id, #svproduct_code, #svproduct_name, #svgst, #svunit_price, #remarks").val("");
        $("#svamount_discount, #svpercentage_discount").val(0);

        calculateTotals();
    });

    function calculateTotals() {
        let s_amount = 0, s_gst = 0, s_discount = 0, s_taxable = 0, s_payable = 0;
        let sv_amount = 0, sv_gst = 0, sv_discount = 0, sv_taxable = 0, sv_payable = 0;

        // Sales
        $(".total_before_discount").each(function () {
            s_amount  += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".gst_value").each(function () {
            s_gst += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_discount").each(function () {
            s_discount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".taxable_value").each(function () {
            s_taxable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_after_discount").each(function () {
            s_payable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        // Services
        $(".svtotal_before_discount").each(function () {
            sv_amount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".svgst_value").each(function () {
            sv_gst += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".svtotal_discount").each(function () {
            sv_discount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".svtaxable_value").each(function () {
            sv_taxable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".svtotal_after_discount").each(function () {
            sv_payable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        let totalAmount     = s_amount + sv_amount;
        let totalGST        = s_gst + sv_gst;
        let totalDiscount   = s_discount + sv_discount;
        let totalTaxable    = s_taxable + sv_taxable;
        let totalPayable    = s_payable + sv_payable;

        const roundedPayable = Math.round(totalPayable);
        const roundOff = (roundedPayable - totalPayable).toFixed(2);

        const amount_paid = parseFloat($('#amount_paid').val()) || 0;
        const balance_to_pay = roundedPayable - amount_paid;
        const formatted_balance_to_pay = balance_to_pay.toLocaleString('en-IN');

        $("#amount").val(totalAmount.toFixed(2));
        $("#totalgst").val(totalGST.toFixed(2));
        $("#discount").val(totalDiscount.toFixed(2));
        $("#grand_total").val(totalTaxable.toFixed(2));
        $("#amount_payable").val(roundedPayable);
        $("#amount_payable1").html(roundedPayable.toLocaleString('en-IN'));
        $("#round_off").val(roundOff);
        $("#round_off1").html(roundOff);
        $('#balance_to_pay').val(balance_to_pay.toFixed(2));
        $("#balance_to_pay1").html(formatted_balance_to_pay);
        updatePaymentDueDaysVisibility();

        // ✅ Update table sales footer totals
        $("#footer_total_before_discount").html("Rs. " + s_amount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_discount_amount").html("Rs. " + s_discount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_total_after_discount").html("Rs. " + s_payable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_taxable_value").html("Rs. " + s_taxable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_gst_value").html("Rs. " + s_gst.toLocaleString('en-IN', { minimumFractionDigits: 2 }));

        // ✅ Update table service footer totals
        $("#svfooter_total_before_discount").html("Rs. " + sv_amount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#svfooter_discount_amount").html("Rs. " + sv_discount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#svfooter_total_after_discount").html("Rs. " + sv_payable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#svfooter_taxable_value").html("Rs. " + sv_taxable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#svfooter_gst_value").html("Rs. " + sv_gst.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        updateServiceTaxColumnVisibility();
    }

    // Update calculations when unit price or quantity is changed
    $(document).on("input", ".unit_price, .quantity", function () {
        let row = $(this).closest("tr");
        let index = $(this).data("index");

        let newUnitPrice    = parseFloat(row.find(".unit_price").val()) || 0;
        let newQuantity     = parseFloat(row.find(".quantity").val()) || 0;
        let gst             = shouldCollectServiceTax() ? (parseFloat(row.data("gst")) || 0) : 0;

    // Recalculate values
        let newTotalBeforeDiscount = (newUnitPrice * newQuantity).toFixed(2);
        let formatted_newTotalBeforeDiscount = Number(newTotalBeforeDiscount).toLocaleString('en-IN');
        let newDiscountAmount = parseFloat(row.find(".total_discount").text()) || 0;
        let formatted_newDiscountAmount = Number(newDiscountAmount).toLocaleString('en-IN');
        let newTotalAfterDiscount = (newTotalBeforeDiscount - newDiscountAmount).toFixed(2);
        let formatted_newTotalAfterDiscount = Number(newTotalAfterDiscount).toLocaleString('en-IN');
        let newTaxableValue = gst > 0 ? (newTotalAfterDiscount / (1 + gst / 100)).toFixed(2) : newTotalAfterDiscount;
        let formatted_newTaxableValue = Number(newTaxableValue).toLocaleString('en-IN');
        let newGstValue = (newTotalAfterDiscount - newTaxableValue).toFixed(2);
        let formatted_newGstValue = Number(newGstValue).toLocaleString('en-IN');

    // Update row values
        row.find(".total_before_discount").text(formatted_newTotalBeforeDiscount);
        row.find(".total_after_discount").text(formatted_newTotalAfterDiscount);
        row.find(".taxable_value").text(formatted_newTaxableValue);
        row.find(".gst_value").text(`${formatted_newGstValue} (${gst}%)`);

    // Update array with new values
        saleItems[index].unit_price = newUnitPrice;
        saleItems[index].quantity = newQuantity;
        saleItems[index].total_before_discount = newTotalBeforeDiscount;
        saleItems[index].total_after_discount = newTotalAfterDiscount;
        saleItems[index].taxable_value = newTaxableValue;
        saleItems[index].gst_value = newGstValue;

        calculateTotals();
    });

    // Delete row event of sales
    $(document).on("click", ".deleteRow", function () {
        let rowIndex = $(this).closest("tr").index();
        saleItems.splice(rowIndex, 1);
        $(this).closest("tr").remove();
        itemCount--;
        $("#sale_table tbody tr").each(function (index) {
            $(this).find("td:first").text(index + 1); // Update serial number
            $(this).find(".quantity, .unit_price").attr("data-index", index).data("index", index);
        });

        calculateTotals();
    });

    // Delete row event of services
    $(document).on("click", ".deleteSRow", function () {
        let rowIndex = $(this).closest("tr").index();
        serviceItems.splice(rowIndex, 1);
        $(this).closest("tr").remove();
        itemSCount--;
        $("#service_table tbody tr").each(function (index) {
            $(this).find("td:first").text(index + 1); // Update serial number
        });

        calculateTotals();
    });

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });

    $('#sv_type').on('change', function () {
        resetServiceTotals();
        setSaleSectionVisible(false);
        updateServiceTaxColumnVisibility();
    });

    updateServiceTaxColumnVisibility();
    updatePaymentDueDaysVisibility();
    setSaleSectionVisible(hasSaleSectionItems());

});
