$(document).ready(function() {

    let itemCount = 0;
    let purchaseItems = [];
    let lotMarginBasis = 'percentage';
    const purchaseCollectTax = window.purchaseCollectTax !== false;

    function purchaseNumber(value) {
        return parseFloat(value) || 0;
    }

    function updateLotPricingFromMarginAmount() {
        const mrp = purchaseNumber($('#batch_mrp').val());
        const marginAmount = Math.min(mrp, purchaseNumber($('#lot_margin_amount').val()));
        $('#lot_sale_price').val(Math.max(0, mrp - marginAmount).toFixed(2));
        $('#lot_margin_percentage').val((mrp > 0 ? (marginAmount / mrp) * 100 : 0).toFixed(2));
    }

    function updateLotPricingFromMarginPercentage() {
        const mrp = purchaseNumber($('#batch_mrp').val());
        const marginPercentage = Math.min(100, purchaseNumber($('#lot_margin_percentage').val()));
        const marginAmount = (mrp * marginPercentage) / 100;
        $('#lot_margin_amount').val(marginAmount.toFixed(2));
        $('#lot_sale_price').val(Math.max(0, mrp - marginAmount).toFixed(2));
    }

    function updateLotPricingFromMrp() {
        lotMarginBasis = 'amount';
        updateLotPricingFromMarginAmount();
    }

    $('#lot_margin_amount').on('input', function () {
        lotMarginBasis = 'amount';
        updateLotPricingFromMarginAmount();
    });
    $('#lot_margin_percentage').on('input', function () {
        lotMarginBasis = 'percentage';
        updateLotPricingFromMarginPercentage();
    });
    $('#batch_mrp').on('input', updateLotPricingFromMrp);

    function showPurchaseWarningToast(title, text) {
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

    function isSixBBill() {
        return String($('#pu_type').val()) === '2';
    }

    function shouldCollectPurchaseTax() {
        return purchaseCollectTax && !isSixBBill();
    }

    function updatePurchaseTaxColumnVisibility() {
        $('.purchase-tax-column').toggle(shouldCollectPurchaseTax());
    }

    // Before form submission, store table data in hidden field
    $("#addPurchase").submit(function (event) {
        event.preventDefault();
    
        // Check if purchaseItems is empty
        if (typeof purchaseItems === 'undefined' || purchaseItems.length === 0 || purchaseItems.length === 'NULL') {
            showPurchaseWarningToast('No Items Added', 'Please add at least one item before submitting the form.');
            return false;
        } else {
                // Set the hidden field value to the JSON string of purchaseItems
                $("#purchase_items").val(JSON.stringify(purchaseItems));
                
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

    let vendorSelector = null;
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
                        if (searchBy === 'vendor') {
                            return {
                                id: item.id,
                                text: item.cp_name + " - " + item.cp_phone + "",
                                cp_name: item.cp_name,
                                cp_phone: item.cp_phone
                            };
                        } else if (searchBy === 'product') {
                            return {
                                id: item.id,
                                text: item.product_code + " - " + item.product,
                                product_code: item.product_code,
                                product_name: item.product,
                                hsn_code: item.hsn_code,
                                p_price: item.pprice,
                                purchase_price: item.pprice,
                                unit: item.unit,
                                unit_qty: item.uqty,
                                gst: item.gst
                                ,is_batch_managed: item.is_batch_managed
                                ,mrp: item.mrp
                                ,sale_price: item.price
                                ,margin: item.margin
                                ,amt_margin: item.amt_margin
                            };
                        }
                    });

                    if (searchBy === 'vendor') {
                        results.push({
                            id: 'new',
                            text: ' Create New Vendor',
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
                $('#vendor_ph').val(data.cp_phone).trigger('change');
            } else if (searchBy === 'product') {
                $('#product_id').val(data.id);
                $('#product_code').val(data.product_code);
                $('#product_name').val(data.product_name);
                $('#gst').val(data.gst);
                $('#unit_qty').val(data.unit_qty);
                $('#unit').val(data.unit);
                $('#hsn_code').val(data.hsn_code);
                $('#purchase_price').val(data.purchase_price);
                $('#unit_price').val(data.purchase_price);
                $('#p_price').val(data.p_price);
                $('#product').data('batch-managed', data.is_batch_managed === true || data.is_batch_managed == 1);
                $('.batch-fields').toggle(window.batchInventoryMode === true && $('#product').data('batch-managed'));
                $('.mrp-fields').toggle(window.mrpInventoryMode === true);
                $('#batch_mrp').val(data.mrp || '');
                $('#lot_sale_price').val(data.sale_price || data.mrp || '');
                $('#lot_margin_percentage').val(data.margin || 0);
                $('#lot_margin_amount').val(data.amt_margin || 0);
                lotMarginBasis = 'percentage';
                updateLotPricingFromMarginPercentage();
                populateUnitDropdown(data.unit_qty);
            }
        });
    }

    $(document).on('submit', '#vendorForm', function (e) {
        e.preventDefault();

        const vendorName = $('#new_vendor_name').val();
        const vendorPhone = $('#new_vendor_phone').val();
        let isValid = true;

        // Clear old error messages
        $('.error-name').text('');
        $('.error-phone').text('');

        // Client-side validation
        if (!vendorName) {
            $('.error-name').text('Vendor name is required.');
            isValid = false;
        }
        if (!vendorPhone) {
            $('.error-phone').text('Phone number is required.');
            isValid = false;
        }

        if (!isValid) return; // Don't continue if form is invalid

        $.ajax({
            url: vendorAddRoute,
            type: 'POST',
            data: {
                cp_name: vendorName,
                cp_phone: vendorPhone,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                const vendorData = {
                    id: response.id,
                    cp_name: response.cp_name,
                    cp_phone: response.cp_phone
                };
                const newOption = new Option(`${response.cp_name} - ${response.cp_phone}`, response.id, true, true);
                vendorSelector.append(newOption).trigger('change');
                vendorSelector.trigger({ type: 'select2:select', params: { data: vendorData } });

                // Reset and hide modal
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
                showPurchaseWarningToast('Save Failed', 'Failed to add vendor.');
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
                    p_price: response.pprice,
                    purchase_price: response.pprice,
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
                    if (errors.pprice) $('.error-pprice').text(errors.pprice[0]);
                    return;
                }

                showPurchaseWarningToast('Product Save Failed', 'Failed to add product.');
            }
        });
    });

    $('#addVendorModal').on('hidden.bs.modal', function () {
        vendorSelector = null;
    });

    $('#addProductModal').on('hidden.bs.modal', function () {
        productSelector = null;
    });

    $('#addVendorModal').on('shown.bs.modal', function () {
        $('#new_vendor_name').trigger('focus');
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

    // Apply autocomplete to Vendor Name input
    initializeSelect2("#vendor", "vendor", purchaseSearchRoute);

    // Apply autocomplete to Vendor Phone input
    initializeSelect2("#vendor_ph", "vendor", purchaseSearchRoute);

    // Apply autocomplete to Product Name input
    initializeSelect2("#product", "product", purchaseSearchRoute);

     // Event handler for when the unit is changed
     $('#item_unit').change(function() {
        // Get the selected unit
        var selectedUnit = $(this).val();
        // Get the current purchase price and unit_qty
        var purchasePrice = parseFloat($('#p_price').val());
        var unitQty = parseFloat($('#unit_qty').val());

        // Check if unit_qty is greater than 1 and the selected unit is 'No\'s'
        if (unitQty > 1 && selectedUnit === "No.s") {
            // Update the purchase price as purchase_price / unit_qty
            if (!isNaN(purchasePrice) && !isNaN(unitQty) && unitQty > 1) {
                var newPurchasePrice = purchasePrice / unitQty;
                $('#purchase_price').val(newPurchasePrice.toFixed(2));  // Update the purchase price
                $('#unit_price').val(newPurchasePrice.toFixed(2));
            }
        }
        // Check if unit_qty is greater than 1 and the selected unit is not 'No\'s'
        if (unitQty > 1 && selectedUnit !== "No.s") {
            // Update the purchase price as purchase_price / unit_qty
            if (!isNaN(purchasePrice) && !isNaN(unitQty) && unitQty > 1) {
                var newPurchasePrice = purchasePrice;
                $('#purchase_price').val(newPurchasePrice.toFixed(2));  // Update the purchase price
                $('#unit_price').val(newPurchasePrice.toFixed(2));
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

    $('#payment_due_days, #purchase_date').on('input change dp.change', function () {
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
        const purchaseDate = $('#purchase_date').val();

        if (!$('#payment_due_days_row').is(':visible') || isNaN(dueDays) || !purchaseDate) {
            $dueDateText.text('');
            return;
        }

        const dueDate = moment(purchaseDate, 'DD/MM/YYYY').add(dueDays, 'days');
        $dueDateText.text(dueDate.isValid() ? `Due date: ${dueDate.format('DD/MM/YYYY')}` : '');
    }

    let existingItems = $('#existing_items').val();
    if (existingItems) {
        existingItems = JSON.parse(existingItems);
        
        existingItems.forEach((item, index) => {
            let itemGstRate = shouldCollectPurchaseTax() ? (parseFloat(item.product?.gst) || 0) : 0;
            let total_before_discount = (item.pud_uprice * item.pud_itemqty).toFixed(2);
            let formatted_total_before_discount = Number(total_before_discount).toLocaleString('en-IN');

            let total_discount = item.pud_adisc || 0;
            let formatted_total_discount = Number(total_discount).toLocaleString('en-IN');

            let total_after_discount = (total_before_discount - total_discount).toFixed(2);
            let formatted_total_after_discount = Number(total_after_discount).toLocaleString('en-IN');
        
            let taxable_value           = itemGstRate > 0 ? (total_after_discount / (1 + itemGstRate / 100)).toFixed(2) : total_after_discount;
            let formatted_taxable_value = Number(taxable_value).toLocaleString('en-IN');
            
            let gst_value               = (total_after_discount - taxable_value).toFixed(2);
            let formatted_gst_value     = Number(gst_value).toLocaleString('en-IN');

            // Push to the same array used in Create mode
            purchaseItems.push({
                product_id: item.pud_itemid,
                hsn_code: item.pud_hsn,
                quantity: item.pud_itemqty,
                free_quantity: item.pud_free || 0,
                unit: item.pud_unit,
                unit_price: item.pud_uprice,
                unit_qty: item.pud_uqty,
                total_before_discount: item.pud_price,
                discount_amount: item.pud_adisc,
                discount_percentage: item.pud_pdisc || 0,
                gst_value: gst_value,
                total_after_discount: item.pud_total
                ,batch_no: item.batch_no || null
                ,expiry_date: item.expiry_date || null
                ,mrp: item.batch_mrp || null
                ,sale_price: item.lot_sale_price || null
                ,margin_percentage: item.lot_margin_percentage || 0
                ,margin_amount: item.lot_margin_amount || 0
                ,margin_basis: 'percentage'
            });
             
            let newRow = `
                <tr data-gst="${itemGstRate}">
                    <td>${++itemCount}</td>
                    <td>${item.product?.product || ''}</td>
                    <td>${item.pud_hsn}</td>
                    <td><input type="number" class="form-control unit_price" value="${item.pud_uprice}" data-index="${itemCount - 1}"></td>
                    <td><input type="number" class="form-control quantity" value="${item.pud_itemqty}" data-index="${itemCount - 1}"></td>
                    <td>${item.pud_free || 0}</td>
                    ${window.batchInventoryMode ? `<td>${item.batch_no || ''}${item.expiry_date ? ' / ' + item.expiry_date : ''}${item.batch_mrp ? ' / MRP ' + item.batch_mrp : ''}</td>` : ''}
                    ${window.mrpInventoryMode ? `<td>MRP ${Number(item.batch_mrp || 0).toFixed(2)} / Sale ${Number(item.lot_sale_price || 0).toFixed(2)} / ${Number(item.lot_margin_percentage || 0).toFixed(2)}%</td>` : ''}
                    <td class="total_before_discount">${formatted_total_before_discount}</td>
                    <td class="total_discount">${formatted_total_discount}</td>
                    <td class="total_after_discount">${formatted_total_after_discount}</td>
                    <td class="taxable_value purchase-tax-column">${formatted_taxable_value}</td>
                    <td class="gst_value purchase-tax-column">${formatted_gst_value} (${itemGstRate}%)</td>
                    <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
                </tr>
            `;
            $("#purchase_table tbody").append(newRow);
        });

        // Recalculate totals
        updatePurchaseSummary();
    }

    $(".addBtn").on("click keypress", function (e) {
        e.preventDefault();
        // Fetch input values
        let product_id          = $("#product_code").val();
        let product             = $("#product_name").val();
        let hsn_code            = $("#hsn_code").val();
        let quantity            = $("#quantity").val();
        let free_quantity       = $("#free_quantity").val();
        let unit                = $("#item_unit").val();
        let unit_price          = $("#unit_price").val();
        let unit_qty            = $("#unit_qty").val();
        let discount_amount     = $("#amount_discount").val();
        let discount_percentage = $("#percentage_discount").val();
        let gst                 = shouldCollectPurchaseTax() ? ($("#gst").val() || 0) : 0;
        let isBatchManaged      = window.batchInventoryMode === true && $('#product').data('batch-managed');
        let batchNo             = isBatchManaged ? $.trim($("#batch_no").val()) : null;
        let expiryDate          = isBatchManaged ? $.trim($("#expiry_date").val()) : null;
        let batchMrp            = (isBatchManaged || window.mrpInventoryMode === true) ? $("#batch_mrp").val() : null;
        let lotSalePrice        = window.mrpInventoryMode === true ? $("#lot_sale_price").val() : null;
        let lotMarginPercentage = window.mrpInventoryMode === true ? $("#lot_margin_percentage").val() : null;
        let lotMarginAmount     = window.mrpInventoryMode === true ? $("#lot_margin_amount").val() : null;
        let marginBasis         = window.mrpInventoryMode === true ? lotMarginBasis : null;

        // Calculate total values
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
        if (!product ||!quantity ||!unit_price) {
            showPurchaseWarningToast('Missing Fields', 'Please fill in required fields (Product, Unit Price, Quantity)');
            return;
        }
        if (isBatchManaged && !batchNo) {
            showPurchaseWarningToast('Batch Required', 'Enter a batch number for this batch-managed product.');
            return;
        }
        if (window.mrpInventoryMode === true && !(parseFloat(batchMrp) > 0)) {
            showPurchaseWarningToast('MRP Required', 'Enter the MRP for this purchase lot.');
            return;
        }
        if (window.mrpInventoryMode === true && (lotSalePrice === '' || parseFloat(lotSalePrice) < 0 || parseFloat(lotSalePrice) > parseFloat(batchMrp))) {
            showPurchaseWarningToast('Invalid Sale Price', 'Enter a sale price between zero and the MRP for this purchase lot.');
            return;
        }
        if (window.mrpInventoryMode === true && marginBasis === 'amount' && parseFloat(lotMarginAmount) > parseFloat(batchMrp)) {
            showPurchaseWarningToast('Invalid Margin', 'Margin amount cannot exceed the MRP for this purchase lot.');
            return;
        }
        if (window.mrpInventoryMode === true && marginBasis === 'percentage' && parseFloat(lotMarginPercentage) > 100) {
            showPurchaseWarningToast('Invalid Margin', 'Margin percentage cannot exceed 100%.');
            return;
        }

        let newItem = {
            product_id: product_id,
            hsn_code: hsn_code,
            quantity: quantity,
            free_quantity: free_quantity || 0,
            unit: unit,
            unit_price: unit_price,
            unit_qty: unit_qty,
            total_before_discount: total_before_discount,
            discount_amount: total_discount,
            discount_percentage: discount_percentage,
            gst_value: gst_value,
            total_after_discount: total_after_discount
            ,batch_no: batchNo
            ,expiry_date: expiryDate
            ,mrp: batchMrp
            ,sale_price: lotSalePrice
            ,margin_percentage: lotMarginPercentage
            ,margin_amount: lotMarginAmount
            ,margin_basis: marginBasis
        };

        console.log("New item being added: ", newItem);
        purchaseItems.push(newItem);
        console.log("Current purchaseItems: ", purchaseItems);

        // Create a new row
        let newRow = `
            <tr data-gst="${gst}">
                <td>${++itemCount}</td>
                <td>${product}</td>
                <td>${hsn_code}</td>
                <td><input type="number" class="form-control unit_price" value="${unit_price}" data-index="${itemCount - 1}"></td>
                <td><input type="number" class="form-control quantity" value="${quantity}" data-index="${itemCount - 1}"></td>
                <td>${free_quantity || 0}</td>
                ${window.batchInventoryMode ? `<td>${batchNo || ''}${expiryDate ? ' / ' + expiryDate : ''}${batchMrp ? ' / MRP ' + batchMrp : ''}</td>` : ''}
                ${window.mrpInventoryMode ? `<td>MRP ${Number(batchMrp || 0).toFixed(2)} / Sale ${Number(lotSalePrice || 0).toFixed(2)} / ${Number(lotMarginPercentage || 0).toFixed(2)}%</td>` : ''}
                <td class="total_before_discount">${formatted_total_before_discount}</td>
                <td class="total_discount">${formatted_total_discount}</td>
                <td class="total_after_discount">${formatted_total_after_discount}</td>
                <td class="taxable_value purchase-tax-column">${formatted_taxable_value}</td>
                <td class="gst_value purchase-tax-column">${formatted_gst_value} (${gst}%)</td>
                <td><button type="button" class="btn btn-app-delete deleteRow"><i class="far fa-trash-alt"></i></button></td>
            </tr>
        `;

        // Append row to table
        $("#purchase_table tbody").append(newRow);

        // Reset input fields
        $('#product').val(null).empty().trigger('change');
        $("#product_id, #product_code, #product_name, #gst, #unit_qty, #unit, #hsn_code, #purchase_price, #unit_price, #p_price, #quantity").val("");
        $('#item_unit').empty().append('<option value="">Select Unit</option>');
        $("#product").focus();
        $("#free_quantity, #amount_discount, #percentage_discount").val(0);
        $("#batch_no, #expiry_date, #batch_mrp, #lot_sale_price, #lot_margin_percentage, #lot_margin_amount").val("");
        lotMarginBasis = 'percentage';
        $('.batch-fields').hide();
        $('.mrp-fields').toggle(window.mrpInventoryMode === true);
        $('#product').removeData('batch-managed');

        updatePurchaseSummary();
    });

    // Update calculations when unit price or quantity is changed
    $(document).on("input", ".unit_price, .quantity", function () {
        let row = $(this).closest("tr");
        let index = $(this).data("index");

        let newUnitPrice    = parseFloat(row.find(".unit_price").val()) || 0;
        let newQuantity     = parseFloat(row.find(".quantity").val()) || 0;
        let gst             = shouldCollectPurchaseTax() ? (parseFloat(row.data("gst")) || 0) : 0;

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
        purchaseItems[index].unit_price = newUnitPrice;
        purchaseItems[index].quantity = newQuantity;
        purchaseItems[index].total_before_discount = newTotalBeforeDiscount;
        purchaseItems[index].total_after_discount = newTotalAfterDiscount;
        purchaseItems[index].taxable_value = newTaxableValue;
        purchaseItems[index].gst_value = newGstValue;
 
        updatePurchaseSummary();
    });

    // Delete row event
    $(document).on("click", ".deleteRow", function () {
        let rowIndex = $(this).closest("tr").index();
        purchaseItems.splice(rowIndex, 1);
        $(this).closest("tr").remove();
        itemCount--;
        $("#purchase_table tbody tr").each(function (index) {
            $(this).find("td:first").text(index + 1); // Update serial number
        });

        updatePurchaseSummary();
    });

    function updatePurchaseSummary() {
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
        updatePurchaseTaxColumnVisibility();
        updatePaymentDueDaysVisibility();
    }

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });
    
     $('#pu_type').on('change', function() {
        let pu_type = $(this).val();
        $.ajax({
            url: getVoucherAddRoute,
            type: "POST",
            async: false,
            data: {pu_type: pu_type, pu_date: $('#purchase_date').val()},
            success: function(vno) {
                $('#voucher_no').val(vno);
            }
        });

        // 2️⃣ CLEAR TABLE BODY
        $('#purchase_table tbody').empty();

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

        // 5️⃣ CLEAR JSON SALE ITEMS (VERY IMPORTANT)
        $('#purchase_items').val('');
        purchaseItems = [];
        updatePurchaseTaxColumnVisibility();
        updatePaymentDueDaysVisibility();

    });

    updatePurchaseTaxColumnVisibility();
    updatePaymentDueDaysVisibility();

});
