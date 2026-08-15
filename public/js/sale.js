$(document).ready(function() {

    let itemCount = 0;
    let saleItems = [];
    const showManufacturingDate = window.showSaleManufacturingDate === true;
    const allowOutOfStockSale = window.allowOutOfStockSale === true;
    const saleCollectTax = window.saleCollectTax !== false;
    const useMrpPricingMode = window.useSaleMrpPricingMode === true
        || (window.saleSettings && window.saleSettings.mrp_pricing_mode === true);
    let availableMrpLots = [];
    let mrpLotRequest = null;
    let reopenSelectedProductMrp = false;

    function toNumber(value) {
        return parseFloat(value) || 0;
    }

    function selectedBasePrice() {
        const salePrice = toNumber($('#sale_price').val());
        const productMrp = toNumber($('#mrp').val());
        const $lot = $('#mrp_stock_lot_id');
        const lotSalePrice = toNumber($lot.data('sale-price'));

        if (!useMrpPricingMode) {
            return $lot.val() ? lotSalePrice : salePrice;
        }

        const lotMrp = toNumber($lot.data('mrp'));
        if (lotMrp > 0) {
            return lotMrp;
        }

        const batchMrp = toNumber($('#stock_batch_id option:selected').data('mrp'));
        if (batchMrp > 0) {
            return batchMrp;
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

    function refreshDisplayedStock() {
        const packageStock = toNumber($('#current_stock').val());
        const unitQty = Math.max(toNumber($('#unit_qty').val()), 1);
        const selectedUnit = $('#item_unit').val();
        $('#in_stock').val((selectedUnit === 'No.s' || selectedUnit === 'Nos.') ? packageStock * unitQty : packageStock);
    }

    function shouldCollectSaleTax() {
        return saleCollectTax && String($('#sa_type').val()) !== '0';
    }

    function updateSaleTaxColumnVisibility() {
        $('.sale-tax-column').toggle(shouldCollectSaleTax());
    }

    function saleAlertHeaderClass(icon) {
        const classes = {
            success: 'bg-success',
            info: 'bg-info',
            warning: 'bg-warning',
            error: 'bg-danger'
        };

        return classes[icon] || 'bg-info';
    }

    function ensureSaleAlertModal() {
        let modal = $('#sale-alert-modal');

        if (!modal.length) {
            $('body').append(`
                <div class="modal fade" id="sale-alert-modal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title"></h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0"></p>
                            </div>
                            <div class="modal-footer justify-content-between">
                                <button type="button" class="btn btn-default" data-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            modal = $('#sale-alert-modal');
        }

        return modal;
    }

    function showSaleAlert(icon, title, text) {
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
    $("#addSale").submit(function (event) {
        event.preventDefault();
    
        // Check if saleItems is empty
        if (typeof saleItems === 'undefined' || saleItems.length === 0 || saleItems.length === 'NULL') {
            showSaleAlert('warning', 'No Items Added', 'Please add at least one item before submitting the form.');
            return false;
        } else {
                // Set the hidden field value to the JSON string of saleItems
                $("#sale_items").val(JSON.stringify(saleItems));
                
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
                                mrp: item.mrp,
                                margin: item.margin,
                                amt_margin: item.amt_margin,
                                unit: item.unit,
                                unit_qty: item.uqty,
                                gst: item.gst,
                                current_stock: item.current_stock
                                ,is_batch_managed: item.is_batch_managed
                                ,stock_batch_id: item.stock_batch_id || null
                                ,mrp_stock_lot_id: item.mrp_stock_lot_id || null
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
                $('#gst').val(data.gst);
                $('#unit_qty').val(data.unit_qty);
                $('#unit').val(data.unit);
                $('#hsn_code').val(data.hsn_code);
                $('#sale_price').val(data.sale_price);
                $('#mrp').val(data.mrp);
                const marginPercent = resolvedMarginPercent(data.sale_price, data.mrp, data.margin);
                $('#margin').val(marginPercent);
                $('#amt_margin').val(data.amt_margin);
                $('#percentage_discount').val(useMrpPricingMode ? marginPercent.toFixed(2) : 0);
                $('#amount_discount').val(0);
                $('#current_stock').val(data.current_stock);
                $('#in_stock').val(data.current_stock);
                populateUnitDropdown(data.unit_qty);
                $('#product').data('batch-managed', data.is_batch_managed === true || data.is_batch_managed == 1);
                refreshUnitPriceAndDiscount();
                loadBatches(data.id, $('#product').data('batch-managed'), data.stock_batch_id || null);
                loadMrpLots(data.id, data.mrp_stock_lot_id || null);
            }
        });
    }

    function loadBatches(productId, isBatchManaged, preferredBatchId = null) {
        const $group = $('#batch_selector_group');
        const $select = $('#stock_batch_id');
        if (window.batchInventoryMode !== true || !isBatchManaged || !$select.length) {
            $group.hide();
            $select.html('<option value="">Auto FIFO / earliest expiry</option>');
            return;
        }
        $group.show();
        $select.html('<option value="">Auto FIFO / earliest expiry</option>');
        $.getJSON(productBatchesRoute.replace('__PRODUCT__', productId), function (batches) {
            batches.forEach(function (batch) {
                const text = `${batch.batch_no} | Exp: ${batch.expiry_date || 'N/A'} | Qty: ${batch.display_quantity}`;
                const option = new Option(text, batch.id, false, false);
                $(option).attr('data-mrp', batch.mrp || 0);
                $select.append(option);
            });
            if (preferredBatchId && $select.find(`option[value="${preferredBatchId}"]`).length) {
                $select.val(String(preferredBatchId)).trigger('change');
            }
        });
    }

    function loadMrpLots(productId, preferredLotId = null) {
        const $lotId = $('#mrp_stock_lot_id');
        if (window.mrpInventoryMode !== true || !$lotId.length) {
            return;
        }

        if (mrpLotRequest && mrpLotRequest.readyState !== 4) {
            mrpLotRequest.abort();
        }

        availableMrpLots = [];
        $lotId.val('').removeData();
        $('#mrp_lot_modal_table').hide();
        $('#mrp_lot_modal_message').text('Loading available MRP stock slots...').show();
        if (!preferredLotId) {
            $('#mrpStockLotModal').modal('show');
        }

        mrpLotRequest = $.getJSON(productMrpLotsRoute.replace('__PRODUCT__', productId))
            .done(function (lots) {
                if (String($('#product_id').val()) !== String(productId)) {
                    return;
                }

                availableMrpLots = Array.isArray(lots) ? lots : [];
                if (preferredLotId) {
                    const preferredLot = availableMrpLots.find(function (lot) {
                        return String(lot.id) === String(preferredLotId);
                    });
                    if (preferredLot) {
                        applyMrpLot(preferredLot);
                        return;
                    }
                }
                renderMrpLotModal();

                if (!availableMrpLots.length) {
                    return;
                }
            })
            .fail(function (xhr, status) {
                if (status === 'abort') {
                    return;
                }

                $('#mrp_lot_modal_table').hide();
                $('#mrp_lot_modal_message').text('Unable to load the available MRP stock slots. Select the product again to retry.').show();
            });
    }

    function renderMrpLotModal() {
        const $tbody = $('#mrp_lot_modal_table tbody').empty();
        const $message = $('#mrp_lot_modal_message');
        const selectedId = String($('#mrp_stock_lot_id').val() || '');

        if (!availableMrpLots.length) {
            $('#mrp_lot_modal_table').hide();
            $message.text('No available MRP stock slot was found for this product.').show();
            return;
        }

        $message.hide().text('');
        $('#mrp_lot_modal_table').show();

        availableMrpLots.forEach(function (lot) {
            const lotId = String(lot.id);
            const isSelected = selectedId === lotId;
            const rowLabel = `MRP ${toNumber(lot.mrp).toFixed(2)}, sale price ${toNumber(lot.sale_price).toFixed(2)}, stock quantity ${toNumber(lot.display_quantity).toFixed(2)}`;
            const $row = $('<tr>', {
                class: 'select-mrp-lot',
                tabindex: 0,
                role: 'button',
                'aria-label': rowLabel,
                'data-lot-id': lotId
            }).css('cursor', 'pointer').toggleClass('table-primary', isSelected);

            $row.append($('<td>', { class: 'text-center' }).text(toNumber(lot.mrp).toFixed(2)));
            $row.append($('<td>', { class: 'text-center' }).text(toNumber(lot.sale_price).toFixed(2)));
            $row.append($('<td>', { class: 'text-center' }).text(toNumber(lot.display_quantity).toFixed(2)));
            $tbody.append($row);
        });

        if ($('#mrpStockLotModal').hasClass('show')) {
            window.setTimeout(focusMrpLotRow, 0);
        }
    }

    function focusMrpLotRow() {
        const $rows = $('#mrp_lot_modal_table .select-mrp-lot');
        if (!$rows.length) {
            return;
        }

        const $selectedRow = $rows.filter('.table-primary').first();
        ($selectedRow.length ? $selectedRow : $rows.first()).trigger('focus');
    }

    function applyMrpLot(lot) {
        if (!lot) {
            return;
        }

        const mrp = toNumber(lot.mrp);
        const available = toNumber(lot.display_quantity);
        const salePrice = toNumber(lot.sale_price);
        const marginPercentage = toNumber(lot.margin_percentage);
        const marginAmount = toNumber(lot.margin_amount);
        const voucher = lot.purchase_voucher || 'Opening/Return';
        const purchaseDate = lot.purchase_date || '-';
        const label = `${voucher} | ${purchaseDate} | MRP: ${mrp.toFixed(2)} | Sale: ${salePrice.toFixed(2)}`;
        const $lotId = $('#mrp_stock_lot_id');

        $lotId.val(String(lot.id));
        $lotId.data('mrp', mrp);
        $lotId.data('sale-price', salePrice);
        $lotId.data('margin-percentage', marginPercentage);
        $lotId.data('margin-amount', marginAmount);
        $lotId.data('available', available);
        $lotId.data('label', label);

        $('#mrp').val(mrp);
        $('#sale_price').val(salePrice);
        $('#margin').val(marginPercentage);
        $('#amt_margin').val(marginAmount);
        $('#current_stock').val(available);
        $('#percentage_discount').val(useMrpPricingMode ? marginPercentage.toFixed(2) : 0);

        refreshDisplayedStock();
        refreshUnitPriceAndDiscount();
        renderMrpLotModal();
        $('#mrpStockLotModal').modal('hide');
        $('#quantity').focus();
    }

    function clearMrpLotSelection() {
        availableMrpLots = [];
        $('#mrp_stock_lot_id').val('').removeData();
        $('#mrp_lot_modal_table tbody').empty();
        $('#mrp_lot_modal_message').hide().text('');
    }
    

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
                showSaleAlert('error', 'Customer Save Failed', 'Failed to add customer.');
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
                    // Laravel validation errors
                    let errors = xhr.responseJSON.errors;
                    if (errors.product) $('.error-product-name').text(errors.product[0]);
                    if (errors.product_code) $('.error-product-code').text(errors.product_code[0]);
                    if (errors.price) $('.error-price').text(errors.price[0]);
                    return;
                }

                showSaleAlert('error', 'Product Save Failed', 'Failed to add product.');
            }
        });
    });


    $('#addCustomerModal').on('hidden.bs.modal', function () {
        customerSelector = null;
    });

    $('#addProductModal').on('hidden.bs.modal', function () {
        productSelector = null;
    });


    $('#addCustomerModal').on('shown.bs.modal', function () {
        $('#new_customer_name').trigger('focus');
    });

    
    $('#addProductModal').on('shown.bs.modal', function () {
        $('#new_product_name').trigger('focus');
            $.ajax({
            url: productNewCodeRoute, // define this in your blade
            type: 'POST',
            success: function (res) {
                console.log("New code:", res.code); 
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
    initializeSelect2("#customer", "customer", saleSearchRoute);

    // Apply autocomplete to Customer Phone input
    initializeSelect2("#phone", "customer", saleSearchRoute);

    // Apply autocomplete to Product Name input
    initializeSelect2("#product", "product", saleSearchRoute);

    $('#product').on('select2:opening', function () {
        reopenSelectedProductMrp = false;
    });

    $(document).on(
        'mousedown',
        '#select2-product-results .select2-results__option--selected, #select2-product-results .select2-results__option[aria-selected="true"]',
        function () {
            const selectedProductId = $('#product').val();
            const currentProductId = $('#product_id').val();
            reopenSelectedProductMrp = Boolean(
                selectedProductId
                && currentProductId
                && String(selectedProductId) === String(currentProductId)
            );
        }
    );

    $('#product').on('select2:close', function () {
        if (!reopenSelectedProductMrp) {
            return;
        }

        reopenSelectedProductMrp = false;
        const productId = $('#product_id').val();
        if (productId) {
            window.setTimeout(function () {
                loadMrpLots(productId);
            }, 0);
        }
    });

     // Event handler for when the unit is changed
     $('#item_unit').change(function() {
        // Get the selected unit
        var unitQty     = toNumber($('#unit_qty').val());

        refreshUnitPriceAndDiscount();
        refreshDisplayedStock();
        $('#quantity').val('');
        if (useMrpPricingMode) {
            updateDiscountFromPercentage();
        }
    });

    $('#stock_batch_id').on('change', function () {
        refreshUnitPriceAndDiscount();
    });

    $(document).on('click', '.select-mrp-lot', function () {
        const lotId = String($(this).data('lot-id'));
        const lot = availableMrpLots.find(function (candidate) {
            return String(candidate.id) === lotId;
        });
        applyMrpLot(lot);
    });

    $(document).on('keydown', '.select-mrp-lot', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
            return;
        }

        const $rows = $('#mrp_lot_modal_table .select-mrp-lot');
        const currentIndex = $rows.index(this);
        let targetIndex = currentIndex;

        if (event.key === 'ArrowDown') {
            targetIndex = Math.min(currentIndex + 1, $rows.length - 1);
        } else if (event.key === 'ArrowUp') {
            targetIndex = Math.max(currentIndex - 1, 0);
        } else if (event.key === 'Home') {
            targetIndex = 0;
        } else if (event.key === 'End') {
            targetIndex = $rows.length - 1;
        } else {
            return;
        }

        event.preventDefault();
        $rows.eq(targetIndex).trigger('focus');
    });

    $('#mrpStockLotModal').on('shown.bs.modal', focusMrpLotRow);

    //In Stock field functionality
    $('#quantity').on('input', function () {
        var quantity = parseFloat($(this).val());
        var inStock = parseFloat($('#in_stock').val());

        if (!isNaN(quantity) && !isNaN(inStock)) {
            if (quantity > inStock) {
                showSaleAlert('warning', 'Insufficient Stock', 'Entered quantity exceeds available stock.');
                if (!allowOutOfStockSale) {
                    const allowedQuantity = Math.max(inStock, 0);
                    $(this).val(allowedQuantity > 0 ? allowedQuantity : '');
                }
                $(this).focus();
            }
        }

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

    $('#payment_due_days, #sale_date').on('input change dp.change', function () {
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
        const saleDate = $('#sale_date').val();

        if (!$('#payment_due_days_row').is(':visible') || isNaN(dueDays) || !saleDate) {
            $dueDateText.text('');
            return;
        }

        const dueDate = moment(saleDate, 'DD/MM/YYYY').add(dueDays, 'days');
        $dueDateText.text(dueDate.isValid() ? `Due date: ${dueDate.format('DD/MM/YYYY')}` : '');
    }

    let existingItems = $('#existing_items').val();

    if (existingItems) {

        existingItems = JSON.parse(existingItems);
        existingItems.forEach((item, index) => {
            let itemGstRate = shouldCollectSaleTax() ? (parseFloat(item.product?.gst) || 0) : 0;
            let total_before_discount = (item.sad_uprice * item.sad_itemqty).toFixed(2);
            let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');

            let total_discount = item.sad_adisc || 0;
            let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');

            let total_after_discount = (total_before_discount - total_discount).toFixed(2);
            let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        
            let taxable_value           = itemGstRate > 0 ? (total_after_discount / (1 + itemGstRate / 100)).toFixed(2) : total_after_discount;
            let formatted_taxable_value = Number(taxable_value).toLocaleString('en-IN');
            
            let gst_value               = (total_after_discount - taxable_value).toFixed(2);
            let formatted_gst_value     = Number(gst_value).toLocaleString('en-IN');

            // Push to the same array used in Create mode
            saleItems.push({
                product_id: item.sad_itemid,
                hsn_code: item.sad_hsn,
                quantity: item.sad_itemqty,
                unit: item.sad_unit,
                unit_price: item.sad_uprice,
                unit_qty: item.sad_uqty,
                total_before_discount: item.sad_price,
                discount_amount: item.sad_adisc,
                discount_percentage: item.sad_pdisc || 0,
                gst_value: gst_value,
                total_after_discount: item.sad_total,
                manufacturing_date: showManufacturingDate ? item.manufacturing_date : null
                ,stock_batch_id: item.stock_batch_id || null
                ,mrp_stock_lot_id: item.mrp_stock_lot_id || null
            });

            let mfgDateCell = '';
            if (showManufacturingDate) {
                const mfg = (typeof displayDateDMY === 'function' && item.manufacturing_date)
                    ? displayDateDMY(item.manufacturing_date)
                    : (item.manufacturing_date ?? '');
                mfgDateCell = `<td>${mfg}</td>`;
            }
            let batchCell = window.batchInventoryMode ? `<td>${item.batch_no || 'FIFO allocation'}</td>` : '';
            let mrpLotCell = window.mrpInventoryMode ? `<td>${Number(item.stock_mrp || 0).toFixed(2)}</td>` : '';
            let newRow = `
                <tr data-gst="${itemGstRate}">
                    <td>${++itemCount}</td>
                    <td>${item.product?.product || ''}</td>
                    <td>${item.sad_hsn}</td>
                    ${mfgDateCell}
                    ${batchCell}
                    ${mrpLotCell}
                    <td><input type="number" class="form-control quantity" value="${item.sad_itemqty}" data-index="${itemCount - 1}"></td>
                    <td><input type="number" class="form-control unit_price" value="${item.sad_uprice}" data-index="${itemCount - 1}"></td>
                    <td class="total_before_discount">${formatted_total_before_discount}</td>
                    <td class="total_discount">${formatted_total_discount}</td>
                    <td class="total_after_discount">${formatted_total_after_discount}</td>
                    <td class="taxable_value sale-tax-column">${formatted_taxable_value}</td>
                    <td class="gst_value sale-tax-column">${formatted_gst_value} (${itemGstRate}%)</td>
                    <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
                </tr>
            `;
            $("#sale_table tbody").append(newRow);

        });

        updateSaleSummary();
    }

    $(".addBtn").on("click keypress", function (e) {
        e.preventDefault();
        // Fetch input values
        let product_id          = $("#product_code").val();
        let product             = $("#product_name").val();
        let hsn_code            = $("#hsn_code").val();
        let quantity            = $("#quantity").val();
        let unit                = $("#item_unit").val();
        let unit_price          = $("#unit_price").val();
        let unit_qty            = $("#unit_qty").val();
        let discount_amount     = $("#amount_discount").val();
        let discount_percentage = $("#percentage_discount").val();
        // let gst                 = $("#gst").val();
        let gst                 = shouldCollectSaleTax() ? ($("#gst").val() || 0) : 0;
        let stockBatchId        = window.batchInventoryMode === true && $('#product').data('batch-managed')
            ? ($('#stock_batch_id').val() || null) : null;
        let batchText           = stockBatchId ? $('#stock_batch_id option:selected').text() : 'Auto FIFO';
        let mrpStockLotId       = window.mrpInventoryMode === true ? ($('#mrp_stock_lot_id').val() || null) : null;
        let mrpLotText          = mrpStockLotId ? toNumber($('#mrp_stock_lot_id').data('mrp')).toFixed(2) : '';

        // Calculate total values
        let total_before_discount   = (unit_price * quantity).toFixed(2);
        let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');
        let total_discount          = (discount_amount || 0);
        let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');
        let total_after_discount    = (total_before_discount - total_discount).toFixed(2);
        let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        let taxable_value           = (total_after_discount / (1 + gst / 100)).toFixed(2);
        let formatted_taxable_value = Number(taxable_value).toLocaleString('en-IN');
        let gst_value               = (total_after_discount - taxable_value).toFixed(2);
        let formatted_gst_value     = Number(gst_value).toLocaleString('en-IN');
        
        // Validate required fields
        if (!product || !unit_price || !quantity) {
            showSaleAlert('warning', 'Missing Fields', 'Please fill in required fields (Product, Unit Price, Quantity)');
            return;
        }
        if (window.mrpInventoryMode === true && !mrpStockLotId) {
            showSaleAlert('warning', 'Stock Lot Required', 'Select the MRP stock lot matching the physical product.');
            if (availableMrpLots.length) {
                $('#mrpStockLotModal').modal('show');
            }
            return;
        }

        const requestedQuantity = parseFloat(quantity);
        const availableStock = parseFloat($('#in_stock').val());
        if (!allowOutOfStockSale && !isNaN(requestedQuantity) && !isNaN(availableStock) && requestedQuantity > availableStock) {
            showSaleAlert('warning', 'Insufficient Stock', 'This product cannot be added because the entered quantity exceeds available stock.');
            $('#quantity').focus();
            return;
        }

        let manufacturing_date = null;

        if (showManufacturingDate) {
            const val = $('#manufacturingdate').val(); // get input value
            if (val && val.trim() !== '') {
                manufacturing_date = val.trim(); // use only if non-empty
            } 
            // else {
            //     showSaleAlert('warning', 'Missing Manufacturing Date', 'Please select Manufacturing Date');
            //     return;
            // }
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
            // gst_value: gst_value,
            gst_value: shouldCollectSaleTax() ? gst_value : 0,
            total_after_discount: total_after_discount,
            manufacturing_date: manufacturing_date
            ,stock_batch_id: stockBatchId
            ,mrp_stock_lot_id: mrpStockLotId
        };

        console.log("New item being added: ", newItem);
        saleItems.push(newItem);
        console.log("Current saleItems: ", saleItems);

        let mfgDateCell = '';

        if (showManufacturingDate) {
            mfgDateCell = `<td>${manufacturing_date}</td>`;
        }
        let batchCell = window.batchInventoryMode ? `<td>${$('#product').data('batch-managed') ? batchText : ''}</td>` : '';
        let mrpLotCell = window.mrpInventoryMode ? `<td>${mrpLotText}</td>` : '';
        // Create a new row
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
                <td class="taxable_value sale-tax-column">${formatted_taxable_value}</td>
                <td class="gst_value sale-tax-column">${formatted_gst_value} (${gst}%)</td>
                <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
            </tr>
        `;

        // Append row to table
        $("#sale_table tbody").append(newRow);

        // Reset input fields
        $('#product').val(null).empty().trigger('change');
        $("#product_id, #product_code, #product_name, #price, #sale_price, #mrp, #margin, #amt_margin, #current_stock, #gst, #unit_qty, #unit, #hsn_code, #unit_price, #quantity, #in_stock").val("");
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        if (showManufacturingDate) {
            $('#manufacturingdate').datetimepicker('clear');
        }
        $("#product").focus();
        $("#amount_discount, #percentage_discount").val(0);
        $('#stock_batch_id').val('').trigger('change');
        $('#batch_selector_group').hide();
        clearMrpLotSelection();
        $('#product').removeData('batch-managed');

        updateSaleSummary();
    });

    // Update calculations when unit price or quantity is changed
    $(document).on("input", ".unit_price, .quantity", function () {
        let row = $(this).closest("tr");
        let index = $(this).data("index");

        let newUnitPrice    = parseFloat(row.find(".unit_price").val()) || 0;
        let newQuantity     = parseFloat(row.find(".quantity").val()) || 0;
        // let gst             = parseFloat(row.data("gst")) || 0;
        let gst             = shouldCollectSaleTax() ? (parseFloat(row.data("gst")) || 0) : 0;

    // Recalculate values
        let newTotalBeforeDiscount = (newUnitPrice * newQuantity).toFixed(2);
        let formatted_newTotalBeforeDiscount = Number(newTotalBeforeDiscount).toLocaleString('en-IN');
        let newDiscountAmount = parseFloat(row.find(".total_discount").text()) || 0;
        let formatted_newDiscountAmount = Number(newDiscountAmount).toLocaleString('en-IN');
        let newTotalAfterDiscount = (newTotalBeforeDiscount - newDiscountAmount).toFixed(2);
        let formatted_newTotalAfterDiscount = Number(newTotalAfterDiscount).toLocaleString('en-IN');
        let newTaxableValue = (newTotalAfterDiscount / (1 + gst / 100)).toFixed(2);
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

        updateSaleSummary();
    });

    // Delete row event
    $(document).on("click", ".deleteRow", function () {
        let rowIndex = $(this).closest("tr").index();
        saleItems.splice(rowIndex, 1);
        $(this).closest("tr").remove();
        itemCount--;
        $("#sale_table tbody tr").each(function (index) {
            $(this).find("td:first").text(index + 1); // Update serial number
            $(this).find(".quantity, .unit_price").attr("data-index", index).data("index", index);
        });

        updateSaleSummary();
    });

    function updateSaleSummary() {
        let newAmount = 0,
            newGST = 0,
            newDiscount = 0,
            newTaxable = 0,
            newAmountPayable = 0;

        $(".total_before_discount").each(function () {
            newAmount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".gst_value").each(function () {
            newGST += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_discount").each(function () {
            newDiscount += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".taxable_value").each(function () {
            newTaxable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });
        $(".total_after_discount").each(function () {
            newAmountPayable += parseFloat($(this).text().replace(/,/g, '')) || 0;
        });

        const roundedAmountPayable = Math.round(newAmountPayable);
        const roundOff = (roundedAmountPayable - newAmountPayable).toFixed(2);
        const formatted_newAmountPayable = roundedAmountPayable.toLocaleString('en-IN');

        const amount_paid = parseFloat($('#amount_paid').val()) || 0;
        const balance_to_pay = roundedAmountPayable - amount_paid;
        const formatted_balance_to_pay = balance_to_pay.toLocaleString('en-IN');

        $("#amount").val(newAmount);
        $("#totalgst").val(newGST);
        $("#discount").val(newDiscount);
        $("#grand_total").val(newTaxable);
        $("#amount_payable").val(roundedAmountPayable);
        $("#amount_payable1").html(formatted_newAmountPayable);
        $("#round_off").val(roundOff);
        $("#round_off1").html(roundOff);
        $('#balance_to_pay').val(balance_to_pay.toFixed(2));
        $("#balance_to_pay1").html(formatted_balance_to_pay);

        // ✅ Update table footer totals
        $("#footer_total_before_discount").html("Rs. " + newAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_discount_amount").html("Rs. " + newDiscount.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_total_after_discount").html("Rs. " + newAmountPayable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_taxable_value").html("Rs. " + newTaxable.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        $("#footer_gst_value").html("Rs. " + newGST.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        updateSaleTaxColumnVisibility();
        updatePaymentDueDaysVisibility();
    }

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });
    
     $('#sa_type').on('change', function() {
        let sa_type = $(this).val();

        $.ajax({
            url: getVoucherAddRoute,
            type: "POST",
            async: false,
            data: {
                sa_type: sa_type,
                sa_date: $('#sale_date').val()
            },
            success: function(vno) {
                $('#voucher_no').val(vno);
            }
        });

        // 2️⃣ CLEAR TABLE BODY
        $('#sale_table tbody').empty();
        saleItems = [];
        itemCount = 0;

        // 3️⃣ RESET FOOTER TOTALS
        $('#footer_total_before_discount').text('Rs. 0.00');
        $('#footer_discount_amount').text('Rs. 0.00');
        $('#footer_total_after_discount').text('Rs. 0.00');
        $('#footer_taxable_value').text('Rs. 0.00');
        $('#footer_gst_value').text('Rs. 0.00');

        // 4️⃣ RESET AMOUNT PAYABLE + ROUND OFF
        $('#amount').val(0);
        $('#totalgst').val(0);
        $('#discount').val(0);
        $('#grand_total').val(0);
        $('#round_off').val(0);
        $('#round_off1').text('0');
        $('#amount_payable').val(0);
        $('#amount_payable1').text('0');
        $('#payment_due_days').val('');
        updateSaleTaxColumnVisibility();
        updatePaymentDueDaysVisibility();

        // 5️⃣ CLEAR JSON SALE ITEMS (VERY IMPORTANT)
        $('#sale_items').val('');

    });

    updateSaleTaxColumnVisibility();

    updatePaymentDueDaysVisibility();

});
