$(document).ready(function() {
    customer_set = false;

    setTimeout(function() {
        autoLoadSalesOrderLines();
    }, 1000); // Small delay to ensure everything is initialized

    //Prevent enter key function except texarea
    $('form').on('keyup keypress', function(e) {
        var keyCode = e.keyCode || e.which;
        if (keyCode === 13 && e.target.tagName != 'TEXTAREA') {
            e.preventDefault();
            return false;
        }
    });

    //For edit pos form
    if ($('form#edit_pos_sell_form').length > 0) {
        pos_total_row();
        pos_form_obj = $('form#edit_pos_sell_form');
    } else {
        pos_form_obj = $('form#add_pos_sell_form');
    }
    if ($('form#edit_pos_sell_form').length > 0 || $('form#add_pos_sell_form').length > 0) {
        initialize_printer();
    }

    $('select#select_location_id').change(function() {
        reset_pos_form();

        var default_price_group = $(this).find(':selected').data('default_price_group')
        if (default_price_group) {
            if($("#price_group option[value='" + default_price_group + "']").length > 0) {
                $("#price_group").val(default_price_group);
                $("#price_group").change();
            }
        }

        //Set default invoice scheme for location
        if ($('#invoice_scheme_id').length) {
            if($('input[name="is_direct_sale"]').length > 0){
                //default scheme for sale screen
                var invoice_scheme_id = $(this).find(':selected').data('default_sale_invoice_scheme_id');
            } else {
                var invoice_scheme_id =  $(this).find(':selected').data('default_invoice_scheme_id');
            }
            
            $("#invoice_scheme_id").val(invoice_scheme_id).change();
        }

        //Set default invoice layout for location
        if ($('#invoice_layout_id').length) {
            let invoice_layout_id = $(this).find(':selected').data('default_invoice_layout_id');
            $("#invoice_layout_id").val(invoice_layout_id).change();
        }
        
        //Set default price group
        if ($('#default_price_group').length) {
            var dpg = default_price_group ?
            default_price_group : 0;
            $('#default_price_group').val(dpg);
        }

        set_payment_type_dropdown();

        if ($('#types_of_service_id').length && $('#types_of_service_id').val()) {
            $('#types_of_service_id').change();
        }
    });

    //get customer
    $('select#customer_id').select2({
        ajax: {
            url: '/contacts/customers',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                };
            },
            processResults: function(data) {
                return {
                    results: data,
                };
            },
        },
        templateResult: function (data) { 
            var template = '';
            if (data.supplier_business_name) {
                template += data.supplier_business_name + "<br>";
            }
            template += data.text + "<br>" + LANG.mobile + ": " + data.mobile;

            if (typeof(data.total_rp) != "undefined") {
                var rp = data.total_rp ? data.total_rp : 0;
                template += "<br><i class='fa fa-gift text-success'></i> " + rp;
            }

            return template;
        },
        minimumInputLength: 1,
        language: {
            noResults: function() {
                var name = $('#customer_id')
                    .data('select2')
                    .dropdown.$search.val();
                return (
                    '<button type="button" data-name="' +
                    name +
                    '" class="btn btn-link add_new_customer"><i class="fa fa-plus-circle fa-lg" aria-hidden="true"></i> ' +
                    __translate('add_name_as_new_customer', { name: name }) +
                    '</button>'
                );
            },
        },
        escapeMarkup: function(markup) {
            return markup;
        },
    }).on('select2:select', function(e) {
        var data = e.params.data;
        
        // Show and update mobile and address
        $('.customer-details').show();
        $('#customer_mobile').text(data.mobile || '');
        $('#customer_address').text(data.address_line_1 || '');
        
        // Set contact ID for edit button
        $('.edit_customer').data('id', data.id);

        if (data.pay_term_number) {
            $('input#pay_term_number').val(data.pay_term_number);
        } else {
            $('input#pay_term_number').val('');
        }

        if (data.pay_term_type) {
            $('#add_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
            $('#edit_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
        } else {
            $('#add_sell_form select[name="pay_term_type"]').val('');
            $('#edit_sell_form select[name="pay_term_type"]').val('');
        }
        
        update_shipping_address(data);
        $('#advance_balance_text').text(__currency_trans_from_en(data.balance), true);
        $('#advance_balance').val(data.balance);

        if (data.price_calculation_type == 'selling_price_group') {
            $('#price_group').val(data.selling_price_group_id);
            $('#price_group').change();
        } else {
            $('#price_group').val('');
            $('#price_group').change();
        }
        if ($('.contact_due_text').length) {
            get_contact_due(data.id);
        }
    });    

    $(document).on('click', '.add_new_customer', function() {
        $('#customer_id').select2('close'); // Close the select2 dropdown
        var name = $(this).data('name') || ''; // Get the name, if any

        // Fetch the create form via AJAX
        $.ajax({
            url: '/contacts/create', // Explicitly call the create route
            dataType: 'html',
            success: function(result) {
                $('.contact_modal')
                    .html(result) // Load the create form
                    .modal('show');

                // Pre-fill the name and set contact type to customer
                $('.contact_modal').find('input#name').val(name);
                $('.contact_modal')
                    .find('select#contact_type')
                    .val('customer')
                    .closest('div.contact_type_div')
                    .addClass('hide');

                // Add a hidden input field to indicate the request is from Sale POS
                $('.contact_modal')
                    .find('#contact_add_form')
                    .append('<input type="hidden" name="from_sale_pos" value="1">');

                // Initialize Select2 for any select elements in the form
                $('.contact_modal').find('.select2').select2();

                // Unbind any previous submit handlers to prevent multiple bindings
                $('#contact_add_form').off('submit');

                // Unbind jQuery Validation to prevent interference
                if ($('#contact_add_form').data('validator')) {
                    $('#contact_add_form').validate().destroy();
                }

                // Flag to prevent multiple submissions
                let isSubmitting = false;

                // Handle form submission
                $('#contact_add_form').on('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission
                    e.stopImmediatePropagation(); // Stop other handlers from firing

                    // Check if the form is already being submitted
                    if (isSubmitting) {
                        console.log('Form submission already in progress, ignoring duplicate submission.');
                        return;
                    }

                    // Set the flag to indicate submission is in progress
                    isSubmitting = true;

                    // Disable the submit button to prevent multiple clicks
                    const $submitButton = $(this).find('button[type="submit"]');
                    $submitButton.prop('disabled', true).text('Saving...');

                    console.log('Submitting form to create new contact...');

                    $.ajax({
                        url: $(this).attr('action'), // Use the form's action URL
                        method: 'POST',
                        data: $(this).serialize(), // Serialize form data
                        success: function(response) {
                            // Clear any previous Toastr messages
                            if (typeof toastr !== 'undefined') {
                                console.log('Clearing previous Toastr messages');
                                setTimeout(function() {
                                    toastr.clear();
                                }, 100);
                            }

                            // Debug: Log the response to ensure it's correct
                            console.log('Create contact response:', response);

                            // Check if the operation was successful
                            if (response.success) {
                                // Close the modal
                                $('.contact_modal').modal('hide');

                                // Access the data from response.data
                                var customerData = response.data;

                                // Create the option data with all necessary fields
                                var newOption = {
                                    id: customerData.id,
                                    text: customerData.name,
                                    mobile: customerData.mobile,
                                    address_line_1: customerData.address_line_1,
                                    supplier_business_name: customerData.supplier_business_name
                                };

                                // Add the new option to the Select2 dropdown
                                var newOptionElement = new Option(newOption.text, newOption.id, true, true);
                                $('#customer_id').append(newOptionElement);

                                // Trigger the select event to update the UI
                                $('#customer_id').val(newOption.id).trigger('change');
                                $('#customer_id').trigger({
                                    type: 'select2:select',
                                    params: {
                                        data: newOption
                                    }
                                });

                                // Display green success message
                                if (typeof toastr !== 'undefined') {
                                    toastr.success(response.msg || 'Customer added successfully!');
                                } else {
                                    alert(response.msg || 'Customer added successfully!');
                                }
                            } else {
                                // Display red error message
                                if (typeof toastr !== 'undefined') {
                                    toastr.error(response.msg || 'Error creating customer.');
                                } else {
                                    alert(response.msg || 'Error creating customer.');
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            // Clear any previous Toastr messages
                            if (typeof toastr !== 'undefined') {
                                console.log('Clearing previous Toastr messages on error');
                                setTimeout(function() {
                                    toastr.clear();
                                }, 100);
                            }

                            // Display red error message for AJAX failure
                            if (typeof toastr !== 'undefined') {
                                toastr.error('Error creating customer. Please try again.');
                            } else {
                                alert('Error creating customer. Please try again.');
                            }
                            console.error('Error creating customer:', status, error);
                        },
                        complete: function() {
                            // Reset the flag and re-enable the submit button
                            isSubmitting = false;
                            $submitButton.prop('disabled', false).text('Save');
                        }
                    });
                });

                // Unbind the submit handler when the modal is closed to prevent multiple bindings
                $('.contact_modal').on('hidden.bs.modal', function() {
                    $('#contact_add_form').off('submit');
                    console.log('Unbound submit handler for #contact_add_form on modal close.');
                });
            },
            error: function(xhr, status, error) {
                // Clear any previous Toastr messages
                if (typeof toastr !== 'undefined') {
                    console.log('Clearing previous Toastr messages on form load error');
                    setTimeout(function() {
                        toastr.clear();
                    }, 100);
                }

                // Display red error message for form loading failure
                if (typeof toastr !== 'undefined') {
                    toastr.error('Error loading the customer create form. Please try again.');
                } else {
                    alert('Error loading the customer create form. Please try again.');
                }
                console.error('Error loading the customer create form:', status, error);
            }
        });
    });

    // Handle edit button click
    $(document).on('click', '.edit_customer', function() {
        var contactId = $(this).data('id');
        var defaultCustomerId = $('#default_customer_id').val();
    
        // Check if contactId is empty or matches the default_customer_id
        if (!contactId || contactId === defaultCustomerId) {
            // Display a message prompting the user to select a customer
            if (typeof toastr !== 'undefined') {
                toastr.warning('Please select a customer to edit. The default "Walk-In Customer" cannot be edited.');
            } else {
                alert('Please select a customer to edit. The default "Walk-In Customer" cannot be edited.');
            }
            return; // Exit the function to prevent editing
        }
    
        // Proceed with editing if a valid contactId is available
        if (contactId) {
            // Open the contact modal with the edit data
            $.ajax({
                url: '/contacts/' + contactId + '/edit',
                dataType: 'html',
                success: function(result) {
                    $('.contact_modal')
                        .html(result)
                        .modal('show');
    
                    // Add a hidden input field to indicate the request is from Sale POS
                    $('.contact_modal')
                        .find('#contact_edit_form')
                        .append('<input type="hidden" name="from_sale_pos" value="1">');
    
                    // Initialize Select2 for any select elements in the form
                    $('.contact_modal').find('.select2').select2();
    
                    // Unbind any previous submit handlers to prevent multiple bindings
                    $('#contact_edit_form').off('submit');
    
                    // Unbind jQuery Validation to prevent interference
                    if ($('#contact_edit_form').data('validator')) {
                        $('#contact_edit_form').validate().destroy();
                    }
    
                    // Handle form submission
                    $('#contact_edit_form').on('submit', function(e) {
                        e.preventDefault(); // Prevent default form submission
                        e.stopImmediatePropagation(); // Stop other handlers from firing
    
                        $.ajax({
                            url: $(this).attr('action'), // Use the form's action URL
                            method: 'POST', // Laravel uses POST with _method=PUT for updates
                            data: $(this).serialize(), // Serialize form data
                            success: function(response) {
                                // Clear any previous Toastr messages
                                if (typeof toastr !== 'undefined') {
                                    console.log('Clearing previous Toastr messages');
                                    setTimeout(function() {
                                        toastr.clear();
                                    }, 100);
                                }
    
                                // Debug: Log the response to ensure it's correct
                                console.log('Update contact response:', response);
    
                                // Check if the operation was successful
                                if (response.success) {
                                    // Close the modal
                                    $('.contact_modal').modal('hide');
    
                                    // Access the updated data from response.data
                                    var customerData = response.data;
    
                                    // Create the option data with all necessary fields
                                    var newOption = {
                                        id: customerData.id,
                                        text: customerData.name,
                                        mobile: customerData.mobile,
                                        address_line_1: customerData.address_line_1,
                                        supplier_business_name: customerData.supplier_business_name
                                    };
    
                                    // Update the specific option in the customer dropdown
                                    var $existingOption = $('#customer_id').find('option[value="' + newOption.id + '"]');
                                    if ($existingOption.length) {
                                        $existingOption.remove(); // Remove the old option
                                    }
                                    // Add the updated option
                                    $('#customer_id').append(new Option(newOption.text, newOption.id, true, true));
    
                                    // Trigger the select event to update the UI
                                    $('#customer_id').val(newOption.id).trigger('change');
                                    $('#customer_id').trigger({
                                        type: 'select2:select',
                                        params: {
                                            data: newOption
                                        }
                                    });
    
                                    // Display success message
                                    if (typeof toastr !== 'undefined') {
                                        toastr.success(response.msg || 'Customer updated successfully!');
                                    } else {
                                        alert(response.msg || 'Customer updated successfully!');
                                    }
                                } else {
                                    // Display red error message
                                    if (typeof toastr !== 'undefined') {
                                        toastr.error(response.msg || 'Error updating customer.');
                                    } else {
                                        alert(response.msg || 'Error updating customer.');
                                    }
                                }
                            },
                            error: function(xhr, status, error) {
                                // Clear any previous Toastr messages
                                if (typeof toastr !== 'undefined') {
                                    console.log('Clearing previous Toastr messages on error');
                                    setTimeout(function() {
                                        toastr.clear();
                                    }, 100);
                                }
    
                                // Display red error message for AJAX failure
                                if (typeof toastr !== 'undefined') {
                                    toastr.error('Error updating customer. Please try again.');
                                } else {
                                    alert('Error updating customer. Please try again.');
                                }
                                console.error('Error updating customer:', status, error);
                            }
                        });
                    });
    
                    // Unbind the submit handler when the modal is closed
                    $('.contact_modal').on('hidden.bs.modal', function() {
                        $('#contact_edit_form').off('submit');
                        console.log('Unbound submit handler for #contact_edit_form on modal close.');
                    });
                },
                error: function(xhr, status, error) {
                    // Clear any previous Toastr messages
                    if (typeof toastr !== 'undefined') {
                        console.log('Clearing previous Toastr messages on form load error');
                        setTimeout(function() {
                            toastr.clear();
                        }, 100);
                    }
    
                    // Display red error message for form loading failure
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Error loading the customer edit form. Please try again.');
                    } else {
                        alert('Error loading the customer edit form. Please try again.');
                    }
                    console.error('Error loading the customer edit form:', status, error);
                }
            });
        }
    });

    set_default_customer();

    function set_default_customer() {
        var default_customer_id = $('#default_customer_id').val();
        var default_customer_name = $('#default_customer_name').val();
        var default_customer_balance = $('#default_customer_balance').val();
        var default_customer_address = $('#default_customer_address').val();
        var exists = default_customer_id ? $('select#customer_id option[value=' + default_customer_id + ']').length : 0;
        if (exists == 0 && default_customer_id) {
            $('select#customer_id').append(
                $('<option>', { value: default_customer_id, text: default_customer_name })
            );
        }
        $('#advance_balance_text').text(__currency_trans_from_en(default_customer_balance), true);
        $('#advance_balance').val(default_customer_balance);
        $('#shipping_address_modal').val(default_customer_address);
        if (default_customer_address) {
            $('#shipping_address').val(default_customer_address);
        }
        $('select#customer_id')
            .val(default_customer_id)
            .trigger('change');
    
        if ($('#default_selling_price_group').length) {
            $('#price_group').val($('#default_selling_price_group').val());
            $('#price_group').change();
        }
    
        //initialize tags input (tagify)
        if ($("textarea#repair_defects").length > 0 && !customer_set) {
            let suggestions = [];
            if ($("input#pos_repair_defects_suggestion").length > 0 && $("input#pos_repair_defects_suggestion").val().length > 2) {
                suggestions = JSON.parse($("input#pos_repair_defects_suggestion").val());    
            }
            let repair_defects = document.querySelector('textarea#repair_defects');
            tagify_repair_defects = new Tagify(repair_defects, {
                      whitelist: suggestions,
                      maxTags: 100,
                      dropdown: {
                        maxItems: 100,           // <- mixumum allowed rendered suggestions
                        classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
                        enabled: 0,             // <- show suggestions on focus
                        closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
                      }
                    });
        }
    
        customer_set = true;
    }
    
    function reset_pos_form(){
        //If on edit page then redirect to Add POS page
        if($('form#edit_pos_sell_form').length > 0){
            setTimeout(function() {
                window.location = $("input#pos_redirect_url").val();
            }, 4000);
            return true;
        }
        
        //reset all repair defects tags
        if ($("#repair_defects").length > 0) {
            tagify_repair_defects.removeAllTags();
        }
    
        if(pos_form_obj[0]){
            pos_form_obj[0].reset();
        }
        if(sell_form[0]){
            sell_form[0].reset();
        }
        set_default_customer();
        set_location();
    
        $('tr.product_row').remove();
        $('span.total_quantity, span.price_total, span#total_discount, span#order_tax, span#total_payable,span#price_totalss, span#shipping_charges_amount').text(0);
        $('span.total_payable_span', 'span.total_paying', 'span.balance_due').text(0);
    
        $('#modal_payment').find('.remove_payment_row').each( function(){
            $(this).closest('.payment_row').remove();
        });
    
        if ($('#is_credit_sale').length) {
            $('#is_credit_sale').val(0);
        }
    
        //Reset discount
        __write_number($('input#discount_amount'), $('input#discount_amount').data('default'));
        $('input#discount_type').val($('input#discount_type').data('default'));
    
        //Reset tax rate
        $('input#tax_rate_id').val($('input#tax_rate_id').data('default'));
        __write_number($('input#tax_calculation_amount'), $('input#tax_calculation_amount').data('default'));
    
        $('select.payment_types_dropdown').val('cash').trigger('change');
        $('#price_group').trigger('change');
    
        //Reset shipping
        __write_number($('input#shipping_charges'), $('input#shipping_charges').data('default'));
        $('input#shipping_details').val($('input#shipping_details').data('default'));
        $('input#shipping_address, input#shipping_status, input#delivered_to').val('');
        if($('input#is_recurring').length > 0){
            $('input#is_recurring').iCheck('update');
        };
        if($('#invoice_layout_id').length > 0){
            $('#invoice_layout_id').trigger('change');
        };
        $('span#round_off_text').text(0);
    
        //repair module extra  fields reset
        if ($('#repair_device_id').length > 0) {
            $('#repair_device_id').val('').trigger('change');
        }
    
        //Status is hidden in sales order
        if ($('#status').length > 0 && $('#status').is(":visible")) {
            $('#status').val('').trigger('change');
        }
        if ($('#transaction_date').length > 0) {
            $('#transaction_date').data("DateTimePicker").date(moment());
        }
        if ($('.paid_on').length > 0) {
            $('.paid_on').data("DateTimePicker").date(moment());
        }
        if ($('#commission_agent').length > 0) {
            $('#commission_agent').val('').trigger('change');
        } 
    
        //reset contact due
        $('.contact_due_text').find('span').text('');
        $('.contact_due_text').addClass('hide');
    
        $(document).trigger('sell_form_reset');
    }

    $('form#quick_add_contact')
        .submit(function(e) {
            e.preventDefault();
        })
        .validate({
            rules: {
                contact_id: {
                    remote: {
                        url: '/contacts/check-contacts-id',
                        type: 'post',
                        data: {
                            contact_id: function() {
                                return $('#contact_id').val();
                            },
                            hidden_id: function() {
                                return $('#hidden_id').length ? $('#hidden_id').val() : '';
                            },
                        },
                    },
                },
                mobile: {
                    required: true
                }
            },
            messages: {
                contact_id: {
                    remote: LANG.contact_id_already_exists,
                },
                mobile: {
                    required: "Mobile number is required."
                }
            },
            submitHandler: function(form) {
                var mobile = $('#mobile').val();
                $.ajax({
                    method: 'POST',
                    url: base_path + '/check-mobile',
                    dataType: 'json',
                    data: {
                        contact_id: $('#hidden_id').length ? $('#hidden_id').val() : '',
                        mobile_number: mobile
                    },
                    beforeSend: function(xhr) {
                        __disable_submit_button($(form).find('button[type="submit"]'));
                    },
                    success: function(result) {
                        if (result.is_mobile_exists) {
                            swal({
                                title: "Mobile Already Registered",
                                text: result.msg,
                                icon: "warning",
                                button: "OK",
                            }).then(() => {
                                $('#mobile').focus();
                            });
                        } else {
                            submitQuickContactForm(form);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(__('messages.something_went_wrong'));
                    }
                });
            }
        });
    $('.contact_modal').on('hidden.bs.modal', function() {
        const $form = $('form#contact_add_form');
        if ($form.length) {
            $form.find('button[type="submit"]').removeAttr('disabled');
            $form[0].reset();
        }
        const $editForm = $('form#contact_edit_form');
        if ($editForm.length) {
            $editForm.find('button[type="submit"]').removeAttr('disabled');
            $editForm[0].reset();
        }
    });

    if ($('#search_product').length) {
        //Add Product
        $('#search_product')
            .autocomplete({
                delay: 1000,
                source: function(request, response) {
                    var price_group = '';
                    var search_fields = [];
                    $('.search_fields:checked').each(function(i){
                      search_fields[i] = $(this).val();
                    });

                    if ($('#price_group').length > 0) {
                        price_group = $('#price_group').val();
                    }
                    $.getJSON(
                        '/products/list',
                        {
                            price_group: price_group,
                            location_id: $('input#location_id').val(),
                            term: request.term,
                            not_for_selling: 0,
                            search_fields: search_fields
                        },
                        response
                    );
                },
                minLength: 2,
                response: function(event, ui) {
                    if (ui.content.length == 1) {
                        ui.item = ui.content[0];

                        var is_overselling_allowed = false;
                        if($('input#is_overselling_allowed').length) {
                            is_overselling_allowed = true;
                        }
                        var for_so = false;
                        if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                            for_so = true;
                        }

                        if ((ui.item.enable_stock == 1 && ui.item.qty_available > 0) || 
                                (ui.item.enable_stock == 0) || is_overselling_allowed || for_so) {
                            $(this)
                                .data('ui-autocomplete')
                                ._trigger('select', 'autocompleteselect', ui);
                            $(this).autocomplete('close');
                        }
                    } else if (ui.content.length == 0) {
                        toastr.error(LANG.no_products_found);
                        $('input#search_product').select();
                    }
                },
                focus: function(event, ui) {
                    if (ui.item.qty_available <= 0) {
                        return false;
                    }
                },
                select: function(event, ui) {
                    var searched_term = $(this).val();
                    var is_overselling_allowed = false;
                    if($('input#is_overselling_allowed').length) {
                        is_overselling_allowed = true;
                    }
                    var for_so = false;
                    if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                        for_so = true;
                    }

                    var is_draft=false;
                    if($('input#status') && ($('input#status').val()=='quotation' || 
                    $('input#status').val()=='draft')) {
                        var is_draft=true;
                    }

                    if (ui.item.enable_stock != 1 || ui.item.qty_available > 0 || is_overselling_allowed || for_so || is_draft) {
                        $(this).val(null);

                        //Pre select lot number only if the searched term is same as the lot number
                        var purchase_line_id = ui.item.purchase_line_id && searched_term == ui.item.lot_number ? ui.item.purchase_line_id : null;
                        pos_product_row(ui.item.variation_id, purchase_line_id);
                    } else {
                        alert(LANG.out_of_stock);
                    }
                },
            })
            .autocomplete('instance')._renderItem = function(ul, item) {
                var is_overselling_allowed = false;
                if($('input#is_overselling_allowed').length) {
                    is_overselling_allowed = true;
                }

                var for_so = false;
                if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                    for_so = true;
                }
                var is_draft=false;
                if($('input#status') && ($('input#status').val()=='quotation' || 
                $('input#status').val()=='draft')) {
                    var is_draft=true;
                }

            if (item.enable_stock == 1 && item.qty_available <= 0 && !is_overselling_allowed && !for_so && !is_draft) {
                var string = '<li class="ui-state-disabled">' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }
                var selling_price = item.selling_price;
                if (item.variation_group_price) {
                    selling_price = item.variation_group_price;
                }
                string +=
                    ' (' +
                    item.sub_sku +
                    ')' +
                    '<br> Price: ' +
                    selling_price +
                    ' (Out of stock) </li>';
                return $(string).appendTo(ul);
            } else {
                var string = '<div>' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }

                var selling_price = item.selling_price;
                if (item.variation_group_price) {
                    selling_price = item.variation_group_price;
                }

                string += ' (' + item.sub_sku + ')' + '<br> Price: ' + selling_price;
                if (item.enable_stock == 1) {
                    var qty_available = __currency_trans_from_en(item.qty_available, false, false, __currency_precision, true);
                    string += ' - ' + qty_available + item.unit;
                }
                string += '</div>';

                return $('<li>')
                    .append(string)
                    .appendTo(ul);
            }
        };
    }

    //If change in unit price update price including tax and line total
    $('table#pos_table tbody').on('change', 'input.pos_unit_price', function() {
        var unit_price = __read_number($(this));
        var tr = $(this).parents('tr');

        //calculate discounted unit price
        var discounted_unit_price = calculate_discounted_unit_price(tr);

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var quantity = __read_number(tr.find('input.pos_quantity'));

        var unit_price_inc_tax = __add_percent(discounted_unit_price, tax_rate);
        var line_total = quantity * unit_price_inc_tax;

        __write_number(tr.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);
        __write_number(tr.find('input.pos_line_total'), line_total);
        tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));
        pos_each_row(tr);
        pos_total_row();
        round_row_to_iraqi_dinnar(tr);
    });

    //If change in tax rate then update unit price according to it.
    $('table#pos_table tbody').on('change', 'select.tax_id', function() {
        var tr = $(this).parents('tr');

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));

        var discounted_unit_price = __get_principle(unit_price_inc_tax, tax_rate);
        var unit_price = get_unit_price_from_discounted_unit_price(tr, discounted_unit_price);
        __write_number(tr.find('input.pos_unit_price'), unit_price);
        pos_each_row(tr);
    });

    //If change in unit price including tax, update unit price
    $('table#pos_table tbody').on('change', 'input.pos_unit_price_inc_tax', function() {
        var unit_price_inc_tax = __read_number($(this));

        if (iraqi_selling_price_adjustment) {
            unit_price_inc_tax = round_to_iraqi_dinnar(unit_price_inc_tax);
            __write_number($(this), unit_price_inc_tax);
        }

        var tr = $(this).parents('tr');

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var quantity = __read_number(tr.find('input.pos_quantity'));

        var line_total = quantity * unit_price_inc_tax;
        var discounted_unit_price = __get_principle(unit_price_inc_tax, tax_rate);
        var unit_price = get_unit_price_from_discounted_unit_price(tr, discounted_unit_price);

        __write_number(tr.find('input.pos_unit_price'), unit_price);
        __write_number(tr.find('input.pos_line_total'), line_total, false, 2);
        tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));

        pos_each_row(tr);
        pos_total_row();
    });

    //Change max quantity rule if lot number changes
    $('table#pos_table tbody').on('change', 'select.lot_number', function() {
        var qty_element = $(this)
            .closest('tr')
            .find('input.pos_quantity');

        var tr = $(this).closest('tr');
        var multiplier = 1;
        var unit_name = '';
        var sub_unit_length = tr.find('select.sub_unit').length;
        if (sub_unit_length > 0) {
            var select = tr.find('select.sub_unit');
            multiplier = parseFloat(select.find(':selected').data('multiplier'));
            unit_name = select.find(':selected').data('unit_name');
        }
        var allow_overselling = qty_element.data('allow-overselling');
        if ($(this).val() && !allow_overselling) {
            var lot_qty = $('option:selected', $(this)).data('qty_available');
            var max_err_msg = $('option:selected', $(this)).data('msg-max');

            if (sub_unit_length > 0) {
                lot_qty = lot_qty / multiplier;
                var lot_qty_formated = __number_f(lot_qty, false);
                max_err_msg = __translate('lot_max_qty_error', {
                    max_val: lot_qty_formated,
                    unit_name: unit_name,
                });
            }

            qty_element.attr('data-rule-max-value', lot_qty);
            qty_element.attr('data-msg-max-value', max_err_msg);

            qty_element.rules('add', {
                'max-value': lot_qty,
                messages: {
                    'max-value': max_err_msg,
                },
            });
        } else {
            var default_qty = qty_element.data('qty_available');
            var default_err_msg = qty_element.data('msg_max_default');
            if (sub_unit_length > 0) {
                default_qty = default_qty / multiplier;
                var lot_qty_formated = __number_f(default_qty, false);
                default_err_msg = __translate('pos_max_qty_error', {
                    max_val: lot_qty_formated,
                    unit_name: unit_name,
                });
            }

            qty_element.attr('data-rule-max-value', default_qty);
            qty_element.attr('data-msg-max-value', default_err_msg);

            qty_element.rules('add', {
                'max-value': default_qty,
                messages: {
                    'max-value': default_err_msg,
                },
            });
        }
        qty_element.trigger('change');
    });

    //Change in row discount type or discount amount
    $('table#pos_table tbody').on(
        'change',
        'select.row_discount_type, input.row_discount_amount',
        function() {
            var tr = $(this).parents('tr');

            //calculate discounted unit price
            var discounted_unit_price = calculate_discounted_unit_price(tr);

            var tax_rate = tr
                .find('select.tax_id')
                .find(':selected')
                .data('rate');
            var quantity = __read_number(tr.find('input.pos_quantity'));

            var unit_price_inc_tax = __add_percent(discounted_unit_price, tax_rate);
            var line_total = quantity * unit_price_inc_tax;

            __write_number(tr.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);
            __write_number(tr.find('input.pos_line_total'), line_total, false, 2);
            tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));
            pos_each_row(tr);
            pos_total_row();
            round_row_to_iraqi_dinnar(tr);
        }
    );

    //Remove row on click on remove row
    $('table#pos_table tbody').on('click', 'i.pos_remove_row', function() {
        $(this)
            .parents('tr')
            .remove();
        pos_total_row();
    });

    //Cancel the invoice
    $('button#pos-cancel').click(function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(confirm => {
            if (confirm) {
                reset_pos_form();
            }
        });
    });

    //Save invoice as draft
    $('button#pos-draft').click(function() {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_valid = isValidPosForm();
        if (is_valid != true) {
            return;
        }

        var data = pos_form_obj.serialize();
        data = data + '&status=draft';
        var url = pos_form_obj.attr('action');

        disable_pos_form_actions();
        $.ajax({
            method: 'POST',
            url: url,
            data: data,
            dataType: 'json',
            success: function(result) {
                enable_pos_form_actions();
                if (result.success == 1) {
                    reset_pos_form();
                    toastr.success(result.msg);
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    //Save invoice as Quotation
    $('button#pos-quotation').click(function() {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_valid = isValidPosForm();
        if (is_valid != true) {
            return;
        }

        var data = pos_form_obj.serialize();
        data = data + '&status=quotation';
        var url = pos_form_obj.attr('action');

        disable_pos_form_actions();
        $.ajax({
            method: 'POST',
            url: url,
            data: data,
            dataType: 'json',
            success: function(result) {
                enable_pos_form_actions();
                if (result.success == 1) {
                    reset_pos_form();
                    toastr.success(result.msg);

                    //Check if enabled or not
                    if (result.receipt.is_enabled) {
                        pos_print(result.receipt);
                    }
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    //Finalize invoice, open payment modal
    $('button#pos-finalize').click(function() {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
            }
        }

        $('#modal_payment').modal('show');
    });

    $('#modal_payment').one('shown.bs.modal', function() {
        $('#modal_payment')
            .find('input')
            .filter(':visible:first')
            .focus()
            .select();
        if ($('form#edit_pos_sell_form').length == 0) {
            $(this).find('#method_0').change();
        }
    });

    //Finalize without showing payment options
    $('button.pos-express-finalize').click(function() {

        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
            }
        }

        var pay_method = $(this).data('pay_method');

        //If pay method is credit sale submit form
        if (pay_method == 'credit_sale') {
            $('#is_credit_sale').val(1);
            pos_form_obj.submit();
            return true;
        } else {
            if ($('#is_credit_sale').length) {
                $('#is_credit_sale').val(0);
            }
        }

        //Check for remaining balance & add it in 1st payment row
        var total_payable = __read_number($('input#final_total_input'));
        var total_paying = __read_number($('input#total_paying_input'));
        if (total_payable > total_paying) {
            var bal_due = total_payable - total_paying;

            var first_row = $('#payment_rows_div')
                .find('.payment-amount')
                .first();
            var first_row_val = __read_number(first_row);
            first_row_val = first_row_val + bal_due;
            __write_number(first_row, first_row_val);
            first_row.trigger('change');
        }

        //Change payment method.
        var payment_method_dropdown = $('#payment_rows_div')
            .find('.payment_types_dropdown')
            .first();
        
            payment_method_dropdown.val(pay_method);
            payment_method_dropdown.change();
        if (pay_method == 'card') {
            $('div#card_details_modal').modal('show');
        } else if (pay_method == 'suspend') {
            $('div#confirmSuspendModal').modal('show');
        } else {
            pos_form_obj.submit();
        }
    });

    $('div#card_details_modal').on('shown.bs.modal', function(e) {
        $('input#card_number').focus();
    });

    $('div#confirmSuspendModal').on('shown.bs.modal', function(e) {
        $(this)
            .find('textarea')
            .focus();
    });

    //on save card details
    $('button#pos-save-card').click(function() {
        $('input#card_number_0').val($('#card_number').val());
        $('input#card_holder_name_0').val($('#card_holder_name').val());
        $('input#card_transaction_number_0').val($('#card_transaction_number').val());
        $('select#card_type_0').val($('#card_type').val());
        $('input#card_month_0').val($('#card_month').val());
        $('input#card_year_0').val($('#card_year').val());
        $('input#card_security_0').val($('#card_security').val());

        $('div#card_details_modal').modal('hide');
        pos_form_obj.submit();
    });

    $('button#pos-suspend').click(function() {
        $('input#is_suspend').val(1);
        $('div#confirmSuspendModal').modal('hide');
        pos_form_obj.submit();
        $('input#is_suspend').val(0);
    });

    //fix select2 input issue on modal
    $('#modal_payment')
        .find('.select2')
        .each(function() {
            $(this).select2({
                dropdownParent: $('#modal_payment'),
            });
        });

    $('button#add-payment-row').click(function() {
        var row_index = $('#payment_row_index').val();
        var location_id = $('input#location_id').val();
        $.ajax({
            method: 'POST',
            url: '/sells/pos/get_payment_row',
            data: { row_index: row_index, location_id: location_id },
            dataType: 'html',
            success: function(result) {
                if (result) {
                    var appended = $('#payment_rows_div').append(result);

                    var total_payable = __read_number($('input#final_total_input'));
                    var total_paying = __read_number($('input#total_paying_input'));
                    var b_due = total_payable - total_paying;
                    $(appended)
                        .find('input.payment-amount')
                        .focus();
                    $(appended)
                        .find('input.payment-amount')
                        .last()
                        .val(__currency_trans_from_en(b_due, false))
                        .change()
                        .select();
                    __select2($(appended).find('.select2'));
                    $(appended).find('#method_' + row_index).change();
                    $('#payment_row_index').val(parseInt(row_index) + 1);
                }
            },
        });
    });

    $(document).on('click', '.remove_payment_row', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                $(this)
                    .closest('.payment_row')
                    .remove();
                calculate_balance_due();
            }
        });
    });

    pos_form_validator = pos_form_obj.validate({
        submitHandler: function(form) {
            // var total_payble = __read_number($('input#final_total_input'));
            // var total_paying = __read_number($('input#total_paying_input'));
            var cnf = true;

            //Ignore if the difference is less than 0.5
            if ($('input#in_balance_due').val() >= 0.5) {
                cnf = confirm(LANG.paid_amount_is_less_than_payable);
                // if( total_payble > total_paying ){
                // 	cnf = confirm( LANG.paid_amount_is_less_than_payable );
                // } else if(total_payble < total_paying) {
                // 	alert( LANG.paid_amount_is_more_than_payable );
                // 	cnf = false;
                // }
            }

            var total_advance_payments = 0;
            $('#payment_rows_div').find('select.payment_types_dropdown').each( function(){
                if ($(this).val() == 'advance') {
                    total_advance_payments++
                };
            });

            if (total_advance_payments > 1) {
                alert(LANG.advance_payment_cannot_be_more_than_once);
                return false;
            }

            var is_msp_valid = true;
            //Validate minimum selling price if hidden
            $('.pos_unit_price_inc_tax').each( function(){
                if (!$(this).is(":visible") && $(this).data('rule-min-value')) {
                    var val = __read_number($(this));
                    var error_msg_td = $(this).closest('tr').find('.pos_line_total_text').closest('td');
                    if (val > $(this).data('rule-min-value')) {
                        is_msp_valid = false;
                        error_msg_td.append( '<label class="error">' + $(this).data('msg-min-value') + '</label>');
                    } else {
                        error_msg_td.find('label.error').remove();
                    }
                }
            });

            if (!is_msp_valid) {
                return false;
            }

            if (cnf) {
                disable_pos_form_actions();

                var data = $(form).serialize();
                data = data + '&status=final';
                var url = $(form).attr('action');
                $.ajax({
                    method: 'POST',
                    url: url,
                    data: data,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success == 1) {
                            if (result.whatsapp_link) {
                                window.open(result.whatsapp_link);
                            }
                            $('#modal_payment').modal('hide');
                            toastr.success(result.msg);

                            reset_pos_form();

                            //Check if enabled or not
                            if (result.receipt.is_enabled) {
                                pos_print(result.receipt);
                            }
                        } else {
                            toastr.error(result.msg);
                        }

                        enable_pos_form_actions();
                    },
                });
            }
            return false;
        },
    });

    $(document).on('change', '.payment-amount', function() {
        calculate_balance_due();
    });

    //Update discount
    $('button#posEditDiscountModalUpdate').click(function() {

        //if discount amount is not valid return false
        if (!$("#discount_amount_modal").valid()) {
            return false;
        }
        //Close modal
        $('div#posEditDiscountModal').modal('hide');

        //Update values
        $('input#discount_type').val($('select#discount_type_modal').val());
        __write_number($('input#discount_amount'), __read_number($('input#discount_amount_modal')));

        if ($('#reward_point_enabled').length) {
            var reward_validation = isValidatRewardPoint();
            if (!reward_validation['is_valid']) {
                toastr.error(reward_validation['msg']);
                $('#rp_redeemed_modal').val(0);
                $('#rp_redeemed_modal').change();
            }
            updateRedeemedAmount();
        }

        pos_total_row();
    });

    //Shipping
    $('button#posShippingModalUpdate').click(function() {
        //Close modal
        $('div#posShippingModal').modal('hide');

        //update shipping details
        $('input#shipping_details').val($('#shipping_details_modal').val());

        $('input#shipping_address').val($('#shipping_address_modal').val());
        $('input#shipping_status').val($('#shipping_status_modal').val());
        $('input#delivered_to').val($('#delivered_to_modal').val());

        //Update shipping charges
        __write_number(
            $('input#shipping_charges'),
            __read_number($('input#shipping_charges_modal'))
        );

        //$('input#shipping_charges').val(__read_number($('input#shipping_charges_modal')));

        pos_total_row();
    });

    $('#posShippingModal').on('shown.bs.modal', function() {
        $('#posShippingModal')
            .find('#shipping_details_modal')
            .filter(':visible:first')
            .focus()
            .select();
    });

    $(document).on('shown.bs.modal', '.row_edit_product_price_model', function() {
        $('.row_edit_product_price_model')
            .find('input')
            .filter(':visible:first')
            .focus()
            .select();
    });

    //Update Order tax
    $('button#posEditOrderTaxModalUpdate').click(function() {
        //Close modal
        $('div#posEditOrderTaxModal').modal('hide');

        var tax_obj = $('select#order_tax_modal');
        var tax_id = tax_obj.val();
        var tax_rate = tax_obj.find(':selected').data('rate');

        $('input#tax_rate_id').val(tax_id);

        __write_number($('input#tax_calculation_amount'), tax_rate);
        pos_total_row();
    });


    function submitQuickContactForm(form) {
        var data = $(form).serialize();
        $.ajax({
            method: 'POST',
            url: $(form).attr('action'),
            dataType: 'json',
            data: data,
            beforeSend: function(xhr) {
                __disable_submit_button($(form).find('button[type="submit"]'));
            },
            success: function(result) {
                if (result.success && result.data) {
                    let name = result.data.name;
                    if (result.data.supplier_business_name) {
                        name += ' ' + result.data.supplier_business_name; // added space for readability
                    }
                    $('select#customer_id').append($('<option>', { value: result.data.id, text: name }));
                    $('select#customer_id').val(result.data.id).trigger('change');
                    $('div.contact_modal').modal('hide');
                    toastr.success(result.msg);
                } else {
                    toastr.error('Error: Expected data fields are missing.');
                }
            },                                  
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                toastr.error('Error: ' + error);
            }            
        });
    }    

    //Updates for add sell
    $('select#discount_type, input#discount_amount, input#shipping_charges, \
        input#rp_redeemed_amount').change(function() {
        pos_total_row();
    });
    $('select#tax_rate_id').change(function() {
        var tax_rate = $(this)
            .find(':selected')
            .data('rate');
        __write_number($('input#tax_calculation_amount'), tax_rate);
        pos_total_row();
    });
    //Datetime picker
    $('#transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
        useCurrent: false,
        disabledDates: getDisabledDates()
    });

    //Direct sell submit
    sell_form = $('form#add_sell_form');
    if ($('form#edit_sell_form').length) {
        sell_form = $('form#edit_sell_form');
        pos_total_row();
    }
    sell_form_validator = sell_form.validate();

    $('button#submit-sell, button#save-and-print').click(function(e) {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_msp_valid = true;
        //Validate minimum selling price if hidden
        $('.pos_unit_price_inc_tax').each( function(){
            if (!$(this).is(":visible") && $(this).data('rule-min-value')) {
                var val = __read_number($(this));
                var error_msg_td = $(this).closest('tr').find('.pos_line_total_text').closest('td');
                if (val > $(this).data('rule-min-value')) {
                    is_msp_valid = false;
                    error_msg_td.append( '<label class="error">' + $(this).data('msg-min-value') + '</label>');
                } else {
                    error_msg_td.find('label.error').remove();
                }
            }
        });

        if (!is_msp_valid) {
            return false;
        }

        if ($(this).attr('id') == 'save-and-print') {
            $('#is_save_and_print').val(1);           
        } else {
            $('#is_save_and_print').val(0);
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
            }
        }

        if ($('.enable_cash_denomination_for_payment_methods').length) {
            var payment_row = $('.enable_cash_denomination_for_payment_methods').closest('.payment_row');
            var is_valid = true;
            var payment_type = payment_row.find('.payment_types_dropdown').val();
            var denomination_for_payment_types = JSON.parse($('.enable_cash_denomination_for_payment_methods').val());
            if (denomination_for_payment_types.includes(payment_type) && payment_row.find('.is_strict').length && payment_row.find('.is_strict').val() === '1' ) {
                var payment_amount = __read_number(payment_row.find('.payment-amount'));
                var total_denomination = payment_row.find('input.denomination_total_amount').val();
                if (payment_amount != total_denomination ) {
                    is_valid = false;
                }
            }

            if (!is_valid) {
                payment_row.find('.cash_denomination_error').removeClass('hide');
                toastr.error(payment_row.find('.cash_denomination_error').text());
                e.preventDefault();
                return false;
            } else {
                payment_row.find('.cash_denomination_error').addClass('hide');
            }
        }

        if (sell_form.valid()) {
            window.onbeforeunload = null;
            $(this).attr('disabled', true);
            sell_form.submit();
        }
    });

    //REPAIR MODULE:check if repair module field is present send data to filter product
    var is_enabled_stock = null;
    if ($("#is_enabled_stock").length) {
        is_enabled_stock = $("#is_enabled_stock").val();
    }

    var device_model_id = null;
    if ($("#repair_model_id").length) {
        device_model_id = $("#repair_model_id").val();
    }

    //Show product list.
    get_product_suggestion_list(
        $('select#product_category').val(),
        $('select#product_brand').val(),
        $('input#location_id').val(),
        null,
        is_enabled_stock,
        device_model_id
    );
    $('select#product_category, select#product_brand, select#select_location_id').on('change', function(e) {
        $('input#suggestion_page').val(1);
        var location_id = $('input#location_id').val();
        if (location_id != '' || location_id != undefined) {
            get_product_suggestion_list(
                $('select#product_category').val(),
                $('select#product_brand').val(),
                $('input#location_id').val(),
                null
            );
        }

        get_featured_products();
    });

    $(document).on('click', 'div.product_box', function() {
        //Check if location is not set then show error message.
        if ($('input#location_id').val() == '') {
            toastr.warning(LANG.select_location);
        } else {
            pos_product_row($(this).data('variation_id'));
        }
    });

    $(document).on('shown.bs.modal', '.row_description_modal', function() {
        $(this)
            .find('textarea')
            .first()
            .focus();
    });

    //Press enter on search product to jump into last quantty and vice-versa
    $('#search_product').keydown(function(e) {
        var key = e.which;
        if (key == 9) {
            // the tab key code
            e.preventDefault();
            if ($('#pos_table tbody tr').length > 0) {
                $('#pos_table tbody tr:last')
                    .find('input.pos_quantity')
                    .focus()
                    .select();
            }
        }
    });
    $('#pos_table').on('keypress', 'input.pos_quantity', function(e) {
        var key = e.which;
        if (key == 13) {
            // the enter key code
            $('#search_product').focus();
        }
    });

    $('#exchange_rate').change(function() {
        var curr_exchange_rate = 1;
        if ($(this).val()) {
            curr_exchange_rate = __read_number($(this));
        }
        var total_payable = __read_number($('input#final_total_input'));
        var shown_total = total_payable * curr_exchange_rate;
        $('span#total_payable').text(__currency_trans_from_en(shown_total, false));
    });

    $('select#price_group').change(function() {
        $('input#hidden_price_group').val($(this).val());
    });

    //Quick add product
    $(document).on('click', 'button.pos_add_quick_product', function() {
        var url = $(this).data('href');
        var container = $(this).data('container');
        $.ajax({
            url: url + '?product_for=pos',
            dataType: 'html',
            success: function(result) {
                $(container)
                    .html(result)
                    .modal('show');
                $('.os_exp_date').datepicker({
                    autoclose: true,
                    format: 'dd-mm-yyyy',
                    clearBtn: true,
                });
            },
        });
    });

    $(document).on('change', 'form#quick_add_product_form input#single_dpp', function() {
        var unit_price = __read_number($(this));
        $('table#quick_product_opening_stock_table tbody tr').each(function() {
            var input = $(this).find('input.unit_price');
            __write_number(input, unit_price);
            input.change();
        });
    });

    $(document).on('quickProductAdded', function(e) {
        //Check if location is not set then show error message.
        if ($('input#location_id').val() == '') {
            toastr.warning(LANG.select_location);
        } else {
            pos_product_row(e.variation.id);
        }
    });

    $('div.view_modal').on('show.bs.modal', function() {
        __currency_convert_recursively($(this));
    });

    $('table#pos_table').on('change', 'select.sub_unit', function() {
        var tr = $(this).closest('tr');
        var base_unit_selling_price = tr.find('input.hidden_base_unit_sell_price').val();

        var selected_option = $(this).find(':selected');

        var multiplier = parseFloat(selected_option.data('multiplier'));

        var allow_decimal = parseInt(selected_option.data('allow_decimal'));

        tr.find('input.base_unit_multiplier').val(multiplier);

        var unit_sp = base_unit_selling_price * multiplier;

        var sp_element = tr.find('input.pos_unit_price');
        __write_number(sp_element, unit_sp);

        sp_element.change();

        var qty_element = tr.find('input.pos_quantity');
        var base_max_avlbl = qty_element.data('qty_available');
        var error_msg_line = 'pos_max_qty_error';

        if (tr.find('select.lot_number').length > 0) {
            var lot_select = tr.find('select.lot_number');
            if (lot_select.val()) {
                base_max_avlbl = lot_select.find(':selected').data('qty_available');
                error_msg_line = 'lot_max_qty_error';
            }
        }

        qty_element.attr('data-decimal', allow_decimal);
        var abs_digit = true;
        if (allow_decimal) {
            abs_digit = false;
        }
        qty_element.rules('add', {
            abs_digit: abs_digit,
        });

        if (base_max_avlbl) {
            var max_avlbl = parseFloat(base_max_avlbl) / multiplier;
            var formated_max_avlbl = __number_f(max_avlbl);
            var unit_name = selected_option.data('unit_name');
            var max_err_msg = __translate(error_msg_line, {
                max_val: formated_max_avlbl,
                unit_name: unit_name,
            });
            qty_element.attr('data-rule-max-value', max_avlbl);
            qty_element.attr('data-msg-max-value', max_err_msg);
            qty_element.rules('add', {
                'max-value': max_avlbl,
                messages: {
                    'max-value': max_err_msg,
                },
            });
            qty_element.trigger('change');
        }
        adjustComboQty(tr);
    });

    //Confirmation before page load.
    window.onbeforeunload = function() {
        if($('form#edit_pos_sell_form').length == 0){
            if($('table#pos_table tbody tr').length > 0) {
                return LANG.sure;
            } else {
                return null;
            }
        }
    }
    $(window).resize(function() {
        var win_height = $(window).height();
        div_height = __calculate_amount('percentage', 63, win_height);
        $('div.pos_product_div').css('min-height', div_height + 'px');
        $('div.pos_product_div').css('max-height', div_height + 'px');
    });

    //Used for weighing scale barcode
    $('#weighing_scale_modal').on('shown.bs.modal', function (e) {

        //Attach the scan event
        onScan.attachTo(document, {
            suffixKeyCodes: [13], // enter-key expected at the end of a scan
            reactToPaste: true, // Compatibility to built-in scanners in paste-mode (as opposed to keyboard-mode)
            onScan: function(sCode, iQty) {
                console.log('Scanned: ' + iQty + 'x ' + sCode); 
                $('input#weighing_scale_barcode').val(sCode);
                $('button#weighing_scale_submit').trigger('click');
            },
            onScanError: function(oDebug) {
                console.log(oDebug); 
            },
            minLength: 2
            // onKeyDetect: function(iKeyCode){ // output all potentially relevant key events - great for debugging!
            //     console.log('Pressed: ' + iKeyCode);
            // }
        });

        $('input#weighing_scale_barcode').focus();
    });

    $('#weighing_scale_modal').on('hide.bs.modal', function (e) {
        //Detach from the document once modal is closed.
        onScan.detachFrom(document);
    });

    $('button#weighing_scale_submit').click(function(){

        var price_group = '';
        if ($('#price_group').length > 0) {
            price_group = $('#price_group').val();
        }

        if($('#weighing_scale_barcode').val().length > 0){
            pos_product_row(null, null, $('#weighing_scale_barcode').val());
            $('#weighing_scale_modal').modal('hide');
            $('input#weighing_scale_barcode').val('');
        } else{
            $('input#weighing_scale_barcode').focus();
        }
    });

    $('#show_featured_products').click( function(){
        if (!$('#featured_products_box').is(':visible')) {
            $('#featured_products_box').fadeIn();
        } else {
            $('#featured_products_box').fadeOut();
        }
    });
    validate_discount_field();
    set_payment_type_dropdown();
    if ($('#__is_mobile').length) {
        $('.pos_form_totals').css('margin-bottom', $('.pos-form-actions').height() - 30);
    }

    setInterval(function () {
        if ($('span.curr_datetime').length) {
            $('span.curr_datetime').html(__current_datetime());
        }
    }, 60000);

    set_search_fields();
});

function getDisabledDates() {
    // ✅ CHECK: If sales_order type, return empty array (no date restrictions)
    if ($('#sale_type').length && $('#sale_type').val() === 'sales_order') {
        return [];
    }
    
    // ✅ ONLY APPLY FOR SELL TYPE: Apply transaction_back_date restriction
    var transaction_back_date = parseInt($('input#transaction_back_date').val()) || 0;
    var disabled_dates = [];
    
    if (transaction_back_date > 0) {
        var base_date;
        
        // Check if original_transaction_date exists (EDIT mode)
        var original_transaction_date = $('input#original_transaction_date').val();
        
        if (original_transaction_date) {
            // EDIT MODE: Use transaction_date as base
            base_date = moment(original_transaction_date, moment_date_format);
            if (!base_date.isValid()) {
                console.error('Invalid transaction date format');
                return disabled_dates;
            }
        } else {
            // CREATE MODE: Use today as base
            base_date = moment();
        }
        
        // Calculate the earliest allowed date: base_date - transaction_back_date days
        var cutoff_date = base_date.clone().subtract(transaction_back_date, 'days');
        
        // Generate array of all dates BEFORE cutoff_date (these will be disabled)
        for (var i = 1; i <= 365; i++) {
            var check_date = base_date.clone().subtract(i, 'days');
            
            // If this date is before our cutoff, add it to disabled dates
            if (check_date.isBefore(cutoff_date, 'day')) {
                disabled_dates.push(check_date.format('MM/DD/YYYY'));
            }
        }
    }
    
    return disabled_dates;
}

function set_payment_type_dropdown() {
    var payment_settings = $('#location_id').data('default_payment_accounts');
    payment_settings = payment_settings ? payment_settings : [];
    enabled_payment_types = [];
    for (var key in payment_settings) {
        if (payment_settings[key] && payment_settings[key]['is_enabled']) {
            enabled_payment_types.push(key);
        }
    }
    if (enabled_payment_types.length) {
        $(".payment_types_dropdown > option").each(function() {
            //skip if advance
            if ($(this).val() && $(this).val() != 'advance') {
                if (enabled_payment_types.indexOf($(this).val()) != -1) {
                    $(this).removeClass('hide');
                } else {
                    $(this).addClass('hide');
                }
            }
        });
    }
}

function get_featured_products() {
    var location_id = $('#location_id').val();
    if (location_id && $('#featured_products_box').length > 0) {
        $.ajax({
            method: 'GET',
            url: '/sells/pos/get-featured-products/' + location_id,
            dataType: 'html',
            success: function(result) {
                if (result) {
                    $('#feature_product_div').removeClass('hide');
                    $('#featured_products_box').html(result);
                } else {
                    $('#feature_product_div').addClass('hide');
                    $('#featured_products_box').html('');
                }
            },
        });
    } else {
        $('#feature_product_div').addClass('hide');
        $('#featured_products_box').html('');
    }
}

function get_product_suggestion_list(category_id, brand_id, location_id, url = null, is_enabled_stock = null, repair_model_id = null) {
    if($('div#product_list_body').length == 0) {
        return false;
    }

    if (url == null) {
        url = '/sells/pos/get-product-suggestion';
    }
    $('#suggestion_page_loader').fadeIn(700);
    var page = $('input#suggestion_page').val();
    if (page == 1) {
        $('div#product_list_body').html('');
    }
    if ($('div#product_list_body').find('input#no_products_found').length > 0) {
        $('#suggestion_page_loader').fadeOut(700);
        return false;
    }
    $.ajax({
        method: 'GET',
        url: url,
        data: {
            category_id: category_id,
            brand_id: brand_id,
            location_id: location_id,
            page: page,
            is_enabled_stock: is_enabled_stock,
            repair_model_id: repair_model_id
        },
        dataType: 'html',
        success: function(result) {
            $('div#product_list_body').append(result);
            $('#suggestion_page_loader').fadeOut(700);
        },
    });
}

//Get recent transactions
function get_recent_transactions(status, element_obj) {
    if (element_obj.length == 0) {
        return false;
    }
    var transaction_sub_type = $("#transaction_sub_type").val();
    $.ajax({
        method: 'GET',
        url: '/sells/pos/get-recent-transactions',
        data: { status: status , transaction_sub_type: transaction_sub_type},
        dataType: 'html',
        success: function(result) {
            element_obj.html(result);
            __currency_convert_recursively(element_obj);
        },
    });
}

// ===== REMOVE ALL EXISTING QUANTITY HANDLERS FIRST =====
$(document).off('click', '.quantity-up');
$(document).off('click', '.quantity-down');  
$('table#pos_table tbody').off('change', 'input.pos_quantity');
$('table#pos_table tbody').off('input', 'input.pos_quantity'); // Remove input handler too

// ===== GLOBAL VARIABLE FOR TIMEOUT =====
window.quantityChangeTimeout = null;

// ===== SHARED FUNCTION TO UPDATE QUANTITY =====
function handleQuantityUpdate(qtyInput, shouldValidate = true) {
    if (shouldValidate) {
        if (sell_form_validator) {
            sell_form.valid();
        }
        if (pos_form_validator) {
            pos_form_validator.element(qtyInput);
        }
    }
    
    var entered_qty = __read_number(qtyInput);
    var tr = qtyInput.closest('tr');
    
    console.log('=== Quantity updated ===', {
        entered_qty: entered_qty,
        input_val: qtyInput.val(),
        event_type: shouldValidate ? 'change/input' : 'button_click'
    });
    
    // Clear any existing timeout
    if (window.quantityChangeTimeout) {
        clearTimeout(window.quantityChangeTimeout);
    }
    
    // Set a new timeout to debounce rapid changes
    window.quantityChangeTimeout = setTimeout(function() {
        updateCustomerGroupPrice(tr);
    }, shouldValidate ? 300 : 100); // Shorter timeout for button clicks
    
    // Update modifier quantities
    tr.find('.modifier_qty_text').each(function(){
        $(this).text(__currency_trans_from_en(entered_qty, false));
    });
    tr.find('.modifiers_quantity').each(function(){
        $(this).val(entered_qty);
    });

    adjustComboQty(tr);
}

// ===== QUANTITY INPUT - REAL-TIME UPDATE (while typing) =====
$('table#pos_table tbody').on('input', 'input.pos_quantity', function() {
    handleQuantityUpdate($(this), false); // No validation during typing for better performance
});

// ===== QUANTITY INPUT - FINAL UPDATE (when done typing/leaving field) =====
$('table#pos_table tbody').on('change', 'input.pos_quantity', function() {
    if (sell_form_validator) {
        sell_form.valid();
    }
    if (pos_form_validator) {
        pos_form_validator.element($(this));
    }
    
    var entered_qty = __read_number($(this));
    var tr = $(this).parents('tr');

    var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));
    var line_total = entered_qty * unit_price_inc_tax;

    __write_number(tr.find('input.pos_line_total'), line_total, false, 2);
    tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));

    //Change modifier quantity
    tr.find('.modifier_qty_text').each( function(){
        $(this).text(__currency_trans_from_en(entered_qty, false));
    });
    tr.find('.modifiers_quantity').each( function(){
        $(this).val(entered_qty);
    });

    pos_total_row();

    // IMPORTANT: Make sure this line is present and called AFTER pos_total_row()
    adjustComboQty(tr);
});

// ===== QUANTITY UP BUTTON =====
// $(document).on('click', '.quantity-up', function(e) {
//     e.preventDefault();
//     var qtyInput = $(this).closest('.input-group').find('.pos_quantity');
//     var currentQty = __read_number(qtyInput);
//     var newQty = currentQty + 1;
    
//     __write_number(qtyInput, newQty);
//     qtyInput.trigger('change'); // This triggers adjustComboQty
    
//     $('input#search_product').focus().select();
// });

// $(document).on('click', '.quantity-down', function(e) {
//     e.preventDefault();
//     var qtyInput = $(this).closest('.input-group').find('.pos_quantity');
//     var currentQty = __read_number(qtyInput);
//     var newQty = Math.max(1, currentQty - 1);
    
//     __write_number(qtyInput, newQty);
//     qtyInput.trigger('change'); // This triggers adjustComboQty
    
//     $('input#search_product').focus().select();
// });

// ===== DISCOUNT INPUT - REAL-TIME UPDATE (while typing) =====
$('table#pos_table tbody').on('input', 'input.row_discount_amount', function() {
    var row = $(this).closest('tr');
    pos_each_row(row);
    calculateLineTotal(row);
});

// ===== DISCOUNT INPUT - FINAL UPDATE (when done typing/leaving field) =====
$(document).on('change', 'table#pos_table select.tax_id, table#pos_table input.row_discount_amount', function() {
    var row = $(this).closest('tr');
    row.data('price-needs-recalc', true);
    pos_each_row(row);
    calculateLineTotal(row);
});

// ===== UNIT PRICE INPUT - REAL-TIME UPDATE (while typing) =====
$('table#pos_table tbody').on('input', 'input.pos_unit_price', function() {
    var row = $(this).closest('tr');
    // If your system has logic to update pos_unit_price_inc_tax based on pos_unit_price, call it here (e.g., update_price_inc_tax(row))
    pos_each_row(row); // Assuming this handles any necessary recalcs
    calculateLineTotal(row);
});

// ===== UNIT PRICE INC TAX INPUT - REAL-TIME UPDATE (while typing) =====
$('table#pos_table tbody').on('input', 'input.pos_unit_price_inc_tax', function() {
    var row = $(this).closest('tr');
    // If your system has logic to update pos_unit_price based on pos_unit_price_inc_tax, call it here (e.g., update_price_before_tax(row))
    pos_each_row(row); // Assuming this handles any necessary recalcs
    calculateLineTotal(row);
});

// ===== UNIT PRICE INPUT - FINAL UPDATE (when done typing/leaving field) =====
$(document).on('change', 'table#pos_table input.pos_unit_price, table#pos_table input.pos_unit_price_inc_tax', function() {
    var row = $(this).closest('tr');
    row.data('price-needs-recalc', true);
    pos_each_row(row);
    calculateLineTotal(row);
});

// Updated handleQuantityUpdate function
function handleQuantityUpdate(qtyInput, validateQty = false) {
    var row = qtyInput.closest('tr');
    var quantity = parseFloat(qtyInput.val()) || 1;
    
    console.log('=== handleQuantityUpdate called ===', {
        quantity: quantity,
        validateQty: validateQty,
        isEditMode: isEditMode() // Add this check
    });
    
    // Only update customer group pricing for new products, not during edit
    if (!isEditMode()) {
        updateCustomerGroupPrice(row);
    } else {
        // For edit mode, just calculate line total with existing prices
        calculateLineTotal(row);
    }
}

// Helper function to check if we're in edit mode
function isEditMode() {
    // Check if we're on the edit page or if there's an edit form
    return window.location.pathname.includes('/edit') || 
           $('#edit_sell_form').length > 0 ||
           $('input[name="_method"]').val() === 'put';
}

// Function to calculate line total without changing prices
function calculateLineTotal(row) {
    var quantity = parseFloat(row.find('.pos_quantity').val()) || 1;
    var unitPriceIncTax = __read_number(row.find('input.pos_unit_price_inc_tax'));
    var lineTotal = quantity * unitPriceIncTax;

    __write_number(row.find('input.pos_line_total'), lineTotal, false, 2);
    row.find('span.pos_line_total_text').text(__currency_trans_from_en(lineTotal, true));
    pos_total_row();
    
    console.log('=== Line Total Calculated (Edit Mode) ===', {
        quantity: quantity,
        unitPriceIncTax: unitPriceIncTax,
        lineTotal: lineTotal
    });
}

function updateCustomerGroupPrice(row) {
    // Skip if in edit mode
    if (isEditMode()) {
        console.log('=== Skipping updateCustomerGroupPrice (Edit Mode) ===');
        calculateLineTotal(row);
        return;
    }
    
    var customerId = $('#customer_id').val();
    var quantityInput = row.find('.pos_quantity');
    var quantity = parseFloat(quantityInput.val()) || 1;
    var variationId = row.find('.row_variation_id').val();
    var productId = row.find('.product_id').val();
    var locationId = $('#location_id').val() || 1;
    
    console.log('=== updateCustomerGroupPrice called (Create Mode) ===', {
        quantity: quantity,
        customerId: customerId,
        productId: productId,
        variationId: variationId,
        locationId: locationId,
        input_value: quantityInput.val()
    });
    
    if (!customerId || !variationId || !productId) {
        console.log('Missing required data, calculating line total normally');
        calculateLineTotal(row);
        return;
    }
    
    // Cancel any previous AJAX request for this row to prevent conflicts
    if (row.data('ajax-request')) {
        row.data('ajax-request').abort();
    }
    
    console.log('Making AJAX call with quantity:', quantity);
    
    // Make AJAX call to get updated price
    var ajaxRequest = $.ajax({
        url: '/sells/pos/get_customer_group_price',
        method: 'POST',
        data: {
            customer_id: customerId,
            product_id: productId,
            variation_id: variationId,
            quantity: quantity,
            location_id: locationId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('=== AJAX Response ===', {
                success: response.success,
                custom_price: response.custom_price,
                price_inc_tax: response.price_inc_tax,
                requested_quantity: quantity,
                group_product: response.group_product || 'unknown',
                is_original_price: response.is_original_price || false,
                is_fallback_price: response.is_fallback_price || false,
                is_custom_price: response.is_custom_price || false,
                is_variation_group_price: response.is_variation_group_price || false,
                full_response: response
            });
            
            if (response.success && response.custom_price !== null && response.custom_price !== undefined) {
                var priceType = 'unknown';
                var productType = 'unknown';
                
                // Determine product type
                if (response.group_product == 1) {
                    productType = 'normal';
                } else if (response.group_product == 2) {
                    productType = 'wholesale';
                }
                
                // Determine price type
                if (response.is_original_price) {
                    priceType = 'original (no customer group selling price)';
                } else if (response.is_fallback_price) {
                    if (response.group_product == 2) {
                        priceType = 'fallback (wholesale quantity outside price ranges)';
                    } else {
                        priceType = 'fallback (normal product no variation group price)';
                    }
                } else if (response.is_custom_price) {
                    priceType = 'custom (wholesale quantity-based pricing)';
                } else if (response.is_variation_group_price) {
                    priceType = 'variation group (normal product pricing)';
                }
                
                console.log('Updating prices from', {
                    old_unit_price: __read_number(row.find('.pos_unit_price')),
                    old_unit_price_inc_tax: __read_number(row.find('.pos_unit_price_inc_tax'))
                }, 'to', {
                    new_unit_price: response.custom_price,
                    new_unit_price_inc_tax: response.price_inc_tax,
                    price_type: priceType,
                    product_type: productType
                });
                
                // Update the unit prices
                __write_number(row.find('.pos_unit_price'), response.custom_price);
                __write_number(row.find('.pos_unit_price_inc_tax'), response.price_inc_tax);
                
                // Also update unit_price_before_discount if it exists
                var unitPriceBeforeDiscountInput = row.find('input[name*="[unit_price]"]');
                if (unitPriceBeforeDiscountInput.length) {
                    __write_number(unitPriceBeforeDiscountInput, response.custom_price);
                }
                
                console.log('=== Prices Updated Successfully ===', {
                    price_type: priceType,
                    product_type: productType,
                    quantity: quantity,
                    group_product: response.group_product
                });
            } else {
                console.log('Invalid response or null price, keeping original price');
            }
            
            // Calculate line total using the current quantity (re-read to be safe)
            calculateLineTotal(row);
            
            console.log('=== Final Calculation ===', {
                quantity: quantity,
                unit_price_inc_tax: __read_number(row.find('input.pos_unit_price_inc_tax')),
                product_type: response.group_product == 1 ? 'normal' : 'wholesale'
            });
        },
        error: function(xhr, status, error) {
            if (status !== 'abort') {
                console.error('Error updating customer group price:', error);
                console.error('Response:', xhr.responseText);
                
                // Calculate line total normally on error
                calculateLineTotal(row);
            }
        },
        complete: function() {
            row.removeData('ajax-request');
        }
    });
    
    row.data('ajax-request', ajaxRequest);
}

// Updated pos_product_row function
function pos_product_row(variation_id = null, purchase_line_id = null, weighing_scale_barcode = null, quantity = 1) {

    //Get item addition method
    var item_addtn_method = 0;
    var add_via_ajax = true;

    if (variation_id != null && $('#item_addition_method').length) {
        item_addtn_method = $('#item_addition_method').val();
    }

    if (item_addtn_method == 0) {
        add_via_ajax = true;
    } else {
        var is_added = false;

        //Search for variation id in each row of pos table
        $('#pos_table tbody')
            .find('tr')
            .each(function() {
                var row_v_id = $(this)
                    .find('.row_variation_id')
                    .val();
                var enable_sr_no = $(this)
                    .find('.enable_sr_no')
                    .val();
                var modifiers_exist = false;
                if ($(this).find('input.modifiers_exist').length > 0) {
                    modifiers_exist = true;
                }

                if (
                    row_v_id == variation_id &&
                    enable_sr_no !== '1' &&
                    !modifiers_exist &&
                    !is_added
                ) {
                    add_via_ajax = false;
                    is_added = true;

                    //Increment product quantity
                    qty_element = $(this).find('.pos_quantity');
                    var qty = __read_number(qty_element);
                    __write_number(qty_element, qty + 1);
                    
                    // Use handleQuantityUpdate instead of direct change
                    handleQuantityUpdate(qty_element, false);

                    round_row_to_iraqi_dinnar($(this));

                    $('input#search_product')
                        .focus()
                        .select();
                }
        });
    }

    if (add_via_ajax) {
        var product_row = $('input#product_row_count').val();
        var location_id = $('input#location_id').val();
        var customer_id = $('select#customer_id').val();
        var is_direct_sell = false;
        if (
            $('input[name="is_direct_sale"]').length > 0 &&
            $('input[name="is_direct_sale"]').val() == 1
        ) {
            is_direct_sell = true;
        }

        var disable_qty_alert = false;

        if ($('#disable_qty_alert').length) {
            disable_qty_alert = true;
        }

        var is_sales_order = $('#sale_type').length && $('#sale_type').val() == 'sales_order' ? true : false;

        var price_group = '';
        if ($('#price_group').length > 0) {
            price_group = parseInt($('#price_group').val());
        }

        //If default price group present
        if ($('#default_price_group').length > 0 && 
            price_group === '') {
            price_group = $('#default_price_group').val();
        }

        //If types of service selected give more priority
        if ($('#types_of_service_price_group').length > 0 && 
            $('#types_of_service_price_group').val()) {
            price_group = $('#types_of_service_price_group').val();
        }

        var is_draft=false;
        if($('input#status') && ($('input#status').val()=='quotation' || 
        $('input#status').val()=='draft')) {
            is_draft=true;
        }
        
        $.ajax({
            method: 'GET',
            url: '/sells/pos/get_product_row/' + variation_id + '/' + location_id,
            async: false,
            data: {
                product_row: product_row,
                customer_id: customer_id,
                is_direct_sell: is_direct_sell,
                price_group: price_group,
                purchase_line_id: purchase_line_id,
                weighing_scale_barcode: weighing_scale_barcode,
                quantity: quantity,
                is_sales_order: is_sales_order,
                disable_qty_alert: disable_qty_alert,
                is_draft: is_draft
            },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    $('table#pos_table tbody')
                        .append(result.html_content)
                        .find('input.pos_quantity');
                    //increment row count
                    $('input#product_row_count').val(parseInt(product_row) + 1);
                    var this_row = $('table#pos_table tbody')
                        .find('tr')
                        .last();
                    pos_each_row(this_row);
                    
                    // Only apply customer group pricing for new products (not in edit mode)
                    if (!isEditMode()) {
                        updateCustomerGroupPrice(this_row);
                    }
                    
                    //For initial discount if present
                    var line_total = __read_number(this_row.find('input.pos_line_total'));
                    this_row.find('span.pos_line_total_text').text(line_total);

                    pos_total_row();

                    //Check if multipler is present then multiply it when a new row is added.
                    if(__getUnitMultiplier(this_row) > 1){
                        this_row.find('select.sub_unit').trigger('change');
                    }

                    if (result.enable_sr_no == '1') {
                        var new_row = $('table#pos_table tbody')
                            .find('tr')
                            .last();
                        new_row.find('.row_edit_product_price_model').modal('show');
                    }

                    round_row_to_iraqi_dinnar(this_row);
                    __currency_convert_recursively(this_row);

                    $('input#search_product')
                        .focus()
                        .select();

                    //Used in restaurant module
                    if (result.html_modifier) {
                        $('table#pos_table tbody')
                            .find('tr')
                            .last()
                            .find('td:first')
                            .append(result.html_modifier);
                    }

                    //scroll bottom of items list
                    $(".pos_product_div").animate({ scrollTop: $('.pos_product_div').prop("scrollHeight")}, 1000);
                } else {
                    toastr.error(result.msg);
                    $('input#search_product')
                        .focus()
                        .select();
                }
            },
        });
    }
}

//Update values for each row
function pos_each_row(row_obj) {
    var unit_price = __read_number(row_obj.find('input.pos_unit_price'));

    var discounted_unit_price = calculate_discounted_unit_price(row_obj);
    var tax_rate = row_obj
        .find('select.tax_id')
        .find(':selected')
        .data('rate');

    var unit_price_inc_tax =
        discounted_unit_price + __calculate_amount('percentage', tax_rate, discounted_unit_price);
    __write_number(row_obj.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);

    var discount = __read_number(row_obj.find('input.row_discount_amount'));

    if (discount > 0) {
        var qty = __read_number(row_obj.find('input.pos_quantity'));
        var line_total = qty * unit_price_inc_tax;
        __write_number(row_obj.find('input.pos_line_total'), line_total);
    }

    //var unit_price_inc_tax = __read_number(row_obj.find('input.pos_unit_price_inc_tax'));

    __write_number(row_obj.find('input.item_tax'), unit_price_inc_tax - discounted_unit_price);
}

function pos_total_row() {
    var total_quantity = 0;
    var price_total = get_subtotal();
    $('table#pos_table tbody tr').each(function() {
        total_quantity = total_quantity + __read_number($(this).find('input.pos_quantity'));
    });

    //updating shipping charges
    $('span#shipping_charges_amount').text(
        __currency_trans_from_en(__read_number($('input#shipping_charges_modal')), false)
    );

    $('span.total_quantity').each(function() {
        $(this).html(__number_f(total_quantity));
    });

    //$('span.unit_price_total').html(unit_price_total);
    $('span.price_total').html(__currency_trans_from_en(price_total, false));
    calculate_billing_details(price_total);
}

function get_subtotal() {
    var price_total = 0;

    $('table#pos_table tbody tr').each(function() {
        price_total = price_total + __read_number($(this).find('input.pos_line_total'));
    });

    //Go through the modifier prices.
    $('input.modifiers_price').each(function() {
        var modifier_price = __read_number($(this));
        var modifier_quantity = $(this).closest('.product_modifier').find('.modifiers_quantity').val();
        var modifier_subtotal = modifier_price * modifier_quantity;
        price_total = price_total + modifier_subtotal;
    });

    return price_total;
}

function calculate_billing_details(price_total) {
    var discount = pos_discount(price_total);
    if ($('#reward_point_enabled').length) {
        total_customer_reward = $('#rp_redeemed_amount').val();
        discount = parseFloat(discount) + parseFloat(total_customer_reward);

        if ($('input[name="is_direct_sale"]').length <= 0) {
            $('span#total_discount').text(__currency_trans_from_en(discount, false));
        }
    }

    var order_tax = pos_order_tax(price_total, discount);

    //Add shipping charges.
    var shipping_charges = __read_number($('input#shipping_charges'));

    var additional_expense = 0;
    //calculate additional expenses
    if ($('input#additional_expense_value_1').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_1'));
    }
    if ($('input#additional_expense_value_2').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_2'))
    }
    if ($('input#additional_expense_value_3').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_3'))
    }
    if ($('input#additional_expense_value_4').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_4'))
    }

    //Add packaging charge
    var packing_charge = 0;
    if ($('#types_of_service_id').length > 0 && 
            $('#types_of_service_id').val()) {
        packing_charge = __calculate_amount($('#packing_charge_type').val(), 
            __read_number($('input#packing_charge')), price_total);

        $('#packing_charge_text').text(__currency_trans_from_en(packing_charge, false));
    }

    var total_payable = price_total + order_tax - discount + shipping_charges + packing_charge + additional_expense;

    var rounding_multiple = $('#amount_rounding_method').val() ? parseFloat($('#amount_rounding_method').val()) : 0;
    var round_off_data = __round(total_payable, rounding_multiple);
    var total_payable_rounded = round_off_data.number;

    var round_off_amount = round_off_data.diff;
    if (round_off_amount != 0) {
        $('span#round_off_text').text(__currency_trans_from_en(round_off_amount, false));
    } else {
        $('span#round_off_text').text(0);
    }
    $('input#round_off_amount').val(round_off_amount);

    __write_number($('input#final_total_input'), total_payable_rounded);
    var curr_exchange_rate = 1;
    if ($('#exchange_rate').length > 0 && $('#exchange_rate').val()) {
        curr_exchange_rate = __read_number($('#exchange_rate'));
    }
    var shown_total = total_payable_rounded * curr_exchange_rate;
    $('span#total_payable').text(__currency_trans_from_en(shown_total, false));
    $('span#price_totalss').text(__currency_trans_from_en(total_payable, false));

    $('span.total_payable_span').text(__currency_trans_from_en(total_payable_rounded, true));

    //Check if edit form then don't update price.
    if ($('form#edit_pos_sell_form').length == 0 && $('form#edit_sell_form').length == 0) {
        __write_number($('.payment-amount').first(), total_payable_rounded);
    }

    $(document).trigger('invoice_total_calculated');

    calculate_balance_due();
}

function pos_discount(total_amount) {
    var calculation_type = $('#discount_type').val();
    var calculation_amount = __read_number($('#discount_amount'));

    var discount = __calculate_amount(calculation_type, calculation_amount, total_amount);

    $('span#total_discount').text(__currency_trans_from_en(discount, false));

    return discount;
}

function pos_order_tax(price_total, discount) {
    var tax_rate_id = $('#tax_rate_id').val();
    var calculation_type = 'percentage';
    var calculation_amount = __read_number($('#tax_calculation_amount'));
    var total_amount = price_total - discount;

    if (tax_rate_id) {
        var order_tax = __calculate_amount(calculation_type, calculation_amount, total_amount);
    } else {
        var order_tax = 0;
    }

    $('span#order_tax').text(__currency_trans_from_en(order_tax, false));

    return order_tax;
}


// Function to validate cash ring percentage and update finalize payment button
function updateFinalizePaymentButton() {
    var isValid = true;
    
    // Check all payment rows for cash_ring_percentage method
    $('#payment_rows_div .payment_row').each(function() {
        var paymentRow = $(this);
        var paymentMethod = paymentRow.find('.payment_types_dropdown').val();
        
        if (paymentMethod === 'cash_ring_percentage') {
            var rowIndex = paymentRow.find('.payment_row_index').val();
            var percentageInput = $('#cash_ring_percentage_' + rowIndex);
            var percentageValue = percentageInput.val();
            
            // Check if percentage is empty, null, or zero
            if (!percentageValue || percentageValue.trim() === '' || parseFloat(percentageValue) <= 0) {
                isValid = false;
            }
        }
    });
    
    // Update finalize payment button state
    var finalizeButton = $('#pos-save');
    if (!isValid) {
        finalizeButton.prop('disabled', true);
        finalizeButton.addClass('btn-disabled');
        finalizeButton.attr('title', 'Please enter percentage for Cash Ring Percentage payment method');
    } else {
        finalizeButton.prop('disabled', false);
        finalizeButton.removeClass('btn-disabled');
        finalizeButton.removeAttr('title');
    }
}

function autoLoadSalesOrderLines() {
    var autoLoadSalesOrderId = $('#auto_load_sales_order_id').val();
    var autoLoadSalesOrderInvoice = $('#auto_load_sales_order_invoice').val();
    
    if (autoLoadSalesOrderId) {
        console.log('Auto-loading sales order lines for ID:', autoLoadSalesOrderId);
        
        var product_row = $('input#product_row_count').val() || 0;
        
        // Add sales order to dropdown
        if (autoLoadSalesOrderInvoice && $('#sales_order_ids').length) {
            var existingOption = $('#sales_order_ids option[value="' + autoLoadSalesOrderId + '"]');
            if (existingOption.length === 0) {
                var newOption = new Option(autoLoadSalesOrderInvoice, autoLoadSalesOrderId, true, true);
                $('#sales_order_ids').append(newOption);
            } else {
                $('#sales_order_ids').val(autoLoadSalesOrderId);
            }
            $('#sales_order_ids').trigger('change');
        }
        
        // Load the sales order lines
        $.ajax({
            method: 'GET',
            url: '/get-sales-order-lines',
            async: false,
            data: {
                product_row: product_row,
                sales_order_id: autoLoadSalesOrderId
            },
            dataType: 'json',
            success: function(result) {
                if (result.html) {
                    var html = result.html;
                    
                    // Add visible main products to the table
                    $(html).find('tr').each(function(){
                        var $row = $(this);
                        
                        $('table#pos_table tbody').append($row);
                        
                        var this_row = $('table#pos_table tbody').find('tr').last();
                        adjustComboQty(this_row);
                        // Check if this row has exact combo data
                        var exactComboData = this_row.data('exact-combo');
                        if (exactComboData) {
                            console.log('Found exact combo data:', exactComboData);
                            
                            // Override combo quantities with exact values
                            this_row.find('input.combo_product_qty').each(function(index) {
                                if (exactComboData[index]) {
                                    var exactQty = exactComboData[index].quantity;
                                    $(this).val(exactQty);
                                    console.log('Set combo quantity to exact value:', exactQty);
                                }
                            });
                        }
                        
                        pos_each_row(this_row);
                        product_row = parseInt(product_row) + 1;

                        var line_total = __read_number(this_row.find('input.pos_line_total'));
                        this_row.find('span.pos_line_total_text').text(line_total);

                        if(__getUnitMultiplier(this_row) > 1){
                            this_row.find('select.sub_unit').trigger('change');
                        }

                        round_row_to_iraqi_dinnar(this_row);
                        __currency_convert_recursively(this_row);
                    });

                    // Store ALL sell lines data with EXACT quantities
                    if (result.all_sell_lines && result.all_sell_lines.length > 0) {
                        var allSellLinesContainer = $('#all_sell_lines_container');
                        if (allSellLinesContainer.length === 0) {
                            $('form').append('<div id="all_sell_lines_container" style="display: none;"></div>');
                            allSellLinesContainer = $('#all_sell_lines_container');
                        }
                        
                        allSellLinesContainer.empty();
                        
                        // Add ALL sell lines as hidden inputs with EXACT quantities
                        $.each(result.all_sell_lines, function(index, sellLine) {
                            var hiddenInputs = `
                                <input type="hidden" name="sales_order_lines[${index}][id]" value="${sellLine.id}">
                                <input type="hidden" name="sales_order_lines[${index}][variation_id]" value="${sellLine.variation_id}">
                                <input type="hidden" name="sales_order_lines[${index}][quantity]" value="${sellLine.quantity}">
                                <input type="hidden" name="sales_order_lines[${index}][unit_price]" value="${sellLine.unit_price || 0}">
                                <input type="hidden" name="sales_order_lines[${index}][unit_price_inc_tax]" value="${sellLine.unit_price_inc_tax || 0}">
                                <input type="hidden" name="sales_order_lines[${index}][line_discount_type]" value="${sellLine.line_discount_type || 'fixed'}">
                                <input type="hidden" name="sales_order_lines[${index}][line_discount_amount]" value="${sellLine.line_discount_amount || 0}">
                                <input type="hidden" name="sales_order_lines[${index}][parent_sell_line_id]" value="${sellLine.parent_sell_line_id || ''}">
                                <input type="hidden" name="sales_order_lines[${index}][children_type]" value="${sellLine.children_type || ''}">
                                <input type="hidden" name="sales_order_lines[${index}][tax_id]" value="${sellLine.tax_id || ''}">
                                <input type="hidden" name="sales_order_lines[${index}][item_tax]" value="${sellLine.item_tax || 0}">
                                <input type="hidden" name="sales_order_lines[${index}][sell_line_note]" value="${sellLine.sell_line_note || ''}">
                                <input type="hidden" name="sales_order_lines[${index}][sub_unit_id]" value="${sellLine.sub_unit_id || ''}">
                            `;
                            allSellLinesContainer.append(hiddenInputs);
                        });
                        
                        console.log('Stored ALL sell lines with exact quantities:', result.all_sell_lines);
                    }

                    // Set sales order values
                    if (result.sales_order) {
                        set_so_values(result.sales_order);
                    }

                    $('input#product_row_count').val(product_row);
                    pos_total_row();
                
                } else {
                    if (result.msg) {
                        toastr.error(result.msg);
                    }
                    $('input#search_product').focus().select();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error auto-loading sales order lines:', error);
                toastr.error('Error loading sales order lines: ' + error);
            }
        });
        
        // Remove the trigger inputs after loading
        $('#auto_load_sales_order_id').remove();
        $('#auto_load_sales_order_invoice').remove();
    }
}

// Event listeners for cash ring percentage validation
$(document).on('keyup change blur', '.cash-ring-percentage', function() {
    updateFinalizePaymentButton();
});

$(document).on('change', '.payment_types_dropdown', function() {
    setTimeout(function() {
        updateFinalizePaymentButton();
    }, 100);
});

// Update button when modal is shown
$('#modal_payment').on('shown.bs.modal', function() {
    updateFinalizePaymentButton();
});

// Update button when payment rows are added or removed
$(document).on('click', '#add-payment-row', function() {
    setTimeout(function() {
        updateFinalizePaymentButton();
    }, 500);
});

$(document).on('click', '.remove_payment_row', function() {
    setTimeout(function() {
        updateFinalizePaymentButton();
    }, 100);
});

$(document).ready(function() {
    // Function to calculate cash ring final amount without changing payment amount
    function calculateCashRingFinalAmount(rowIndex) {
        var percentageInput = $('#cash_ring_percentage_' + rowIndex);
        var finalAmountInput = $('#cash_ring_final_amount_' + rowIndex);
        var amountInput = $('input[name="payment[' + rowIndex + '][amount]"]');
        
        if (percentageInput.length && finalAmountInput.length && amountInput.length) {
            var percentage = parseFloat(percentageInput.val()) || 0;
            var amount = parseFloat(amountInput.val()) || 0;
            
            // Calculate: (percentage * amount / 100) + amount
            var percentageAmount = (percentage * amount) / 100;
            var finalAmount = amount + percentageAmount;
            
            // Update only the final amount display field, keep payment amount unchanged
            finalAmountInput.val(finalAmount.toFixed(2));
            
            // Trigger balance calculation using the original payment amount
            calculate_balance_due();
        }
    }
    
    // Event listener for percentage input changes (keyup for immediate response)
    $(document).on('keyup input', '.cash-ring-percentage', function() {
        var rowIndex = $(this).attr('id').split('_').pop();
        calculateCashRingFinalAmount(rowIndex);
    });
    
    // Event listener for amount changes
    $(document).on('keyup input', '.payment-amount', function() {
        var inputName = $(this).attr('name');
        if (inputName && inputName.includes('[amount]')) {
            var match = inputName.match(/payment\[(\d+)\]\[amount\]/);
            if (match) {
                var rowIndex = match[1];
                var percentageInput = $('#cash_ring_percentage_' + rowIndex);
                
                // Recalculate final amount if percentage exists
                if (percentageInput.length && percentageInput.val()) {
                    calculateCashRingFinalAmount(rowIndex);
                } else {
                    // If no percentage, just trigger normal balance calculation
                    calculate_balance_due();
                }
            }
        }
    });
    
    // Also trigger calculation when payment method changes
    $(document).on('change', '.payment_types_dropdown', function() {
        var rowIndex = $(this).closest('.payment_row').find('.payment_row_index').val();
        var paymentMethod = $(this).val();
        
        // If switching to cash ring percentage, calculate final amount
        if (paymentMethod === 'cash_ring_percentage') {
            var percentageInput = $('#cash_ring_percentage_' + rowIndex);
            if (percentageInput.length && percentageInput.val()) {
                calculateCashRingFinalAmount(rowIndex);
            }
        } else {
            // If switching away from cash ring percentage, reset final amount
            var finalAmountInput = $('#cash_ring_final_amount_' + rowIndex);
            if (finalAmountInput.length) {
                finalAmountInput.val('');
                calculate_balance_due();
            }
        }
        
        // Always recalculate balance
        calculate_balance_due();
    });
});

// Vanilla JavaScript version for backup
document.addEventListener('DOMContentLoaded', function() {
    
    function calculateCashRingFinalAmount(rowIndex) {
        const percentageInput = document.getElementById('cash_ring_percentage_' + rowIndex);
        const finalAmountInput = document.getElementById('cash_ring_final_amount_' + rowIndex);
        const amountInput = document.querySelector(`input[name="payment[${rowIndex}][amount]"]`);
        
        if (percentageInput && finalAmountInput && amountInput) {
            const percentage = parseFloat(percentageInput.value) || 0;
            const amount = parseFloat(amountInput.value) || 0;
            
            const percentageAmount = (percentage * amount) / 100;
            const finalAmount = percentageAmount + amount;
            
            finalAmountInput.value = finalAmount.toFixed(2);
            
            // Trigger balance calculation
            if (typeof calculate_balance_due === 'function') {
                calculate_balance_due();
            }
        }
    }
    
    // Event listeners for keyup events
    document.addEventListener('keyup', function(e) {
        if (e.target.classList.contains('cash-ring-percentage')) {
            const rowIndex = e.target.id.split('_').pop();
            calculateCashRingFinalAmount(rowIndex);
        }
    });
    
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('cash-ring-percentage')) {
            const rowIndex = e.target.id.split('_').pop();
            calculateCashRingFinalAmount(rowIndex);
        }
    });
});


function calculate_balance_due() {
    var total_payable = __read_number($('#final_total_input'));
    var total_paying = 0;
    
    $('#payment_rows_div')
        .find('.payment-amount')
        .each(function() {
            var paymentAmount = parseFloat($(this).val()) || 0;
            if (paymentAmount > 0) {
                // Get the row index to check for cash ring final amount
                var inputName = $(this).attr('name');
                var match = inputName.match(/payment\[(\d+)\]\[amount\]/);
                
                if (match) {
                    var rowIndex = match[1];
                    var finalAmountInput = $('#cash_ring_final_amount_' + rowIndex);
                    
                    // If there's a cash ring final amount, use that instead of the base amount
                    if (finalAmountInput.length && finalAmountInput.val()) {
                        var finalAmount = parseFloat(finalAmountInput.val()) || 0;
                        total_paying += finalAmount;
                    } else {
                        // Otherwise use the regular payment amount
                        total_paying += paymentAmount;
                    }
                } else {
                    // Fallback to regular payment amount if we can't parse the row index
                    total_paying += paymentAmount;
                }
            }
        });
    
    var bal_due = total_payable - total_paying;
    var change_return = 0;

    //change_return
    if (bal_due < 0 || Math.abs(bal_due) < 0.05) {
        __write_number($('input#change_return'), bal_due * -1);
        $('span.change_return_span').text(__currency_trans_from_en(bal_due * -1, true));
        change_return = bal_due * -1;
        bal_due = 0;
    } else {
        __write_number($('input#change_return'), 0);
        $('span.change_return_span').text(__currency_trans_from_en(0, true));
        change_return = 0;
    }

    if (change_return !== 0) {
        $('#change_return_payment_data').removeClass('hide');
    } else {
        $('#change_return_payment_data').addClass('hide');
    }

    __write_number($('input#total_paying_input'), total_paying);
    $('span.total_paying').text(__currency_trans_from_en(total_paying, true));

    __write_number($('input#in_balance_due'), bal_due);
    $('span.balance_due').text(__currency_trans_from_en(bal_due, true));

    __highlight(bal_due * -1, $('span.balance_due'));
    __highlight(change_return * -1, $('span.change_return_span'));
}


function isValidPosForm() {
    flag = true;
    $('span.error').remove();

    if ($('select#customer_id').val() == null) {
        flag = false;
        error = '<span class="error">' + LANG.required + '</span>';
        $(error).insertAfter($('select#customer_id').parent('div'));
    }

    if ($('tr.product_row').length == 0) {
        flag = false;
        error = '<span class="error">' + LANG.no_products + '</span>';
        $(error).insertAfter($('input#search_product').parent('div'));
    }

    return flag;
}

//Set the location and initialize printer
function set_location() {
    if ($('select#select_location_id').length == 1) {
        $('input#location_id').val($('select#select_location_id').val());
        $('input#location_id').data(
            'receipt_printer_type',
            $('select#select_location_id')
                .find(':selected')
                .data('receipt_printer_type')
        );
        $('input#location_id').data(
            'default_payment_accounts',
            $('select#select_location_id')
                .find(':selected')
                .data('default_payment_accounts')
        );

        $('input#location_id').attr(
            'data-default_price_group',
            $('select#select_location_id')
                .find(':selected')
                .data('default_price_group')
        );
    }

    if ($('input#location_id').val()) {
        $('input#search_product')
            .prop('disabled', false)
            .focus();
    } else {
        $('input#search_product').prop('disabled', true);
    }

    initialize_printer();
}

function initialize_printer() {
    if ($('input#location_id').data('receipt_printer_type') == 'printer') {
        initializeSocket();
    }
}

$('body').on('click', 'label', function(e) {
    var field_id = $(this).attr('for');
    if (field_id) {
        if ($('#' + field_id).hasClass('select2')) {
            $('#' + field_id).select2('open');
            return false;
        }
    }
});

$('body').on('focus', 'select', function(e) {
    var field_id = $(this).attr('id');
    if (field_id) {
        if ($('#' + field_id).hasClass('select2')) {
            $('#' + field_id).select2('open');
            return false;
        }
    }
});

function round_row_to_iraqi_dinnar(row) {
    if (iraqi_selling_price_adjustment) {
        var element = row.find('input.pos_unit_price_inc_tax');
        var unit_price = round_to_iraqi_dinnar(__read_number(element));
        __write_number(element, unit_price);
        element.change();
    }
}

function pos_print(receipt) {
    //If printer type then connect with websocket
    if (receipt.print_type == 'printer') {
        var content = receipt;
        content.type = 'print-receipt';

        //Check if ready or not, then print.
        if (socket != null && socket.readyState == 1) {
            socket.send(JSON.stringify(content));
        } else {
            initializeSocket();
            setTimeout(function() {
                socket.send(JSON.stringify(content));
            }, 700);
        }

    } else if (receipt.html_content != '') {
        var title = document.title;
        if (typeof receipt.print_title != 'undefined') {
            document.title = receipt.print_title;
        }

        //If printer type browser then print content
        $('#receipt_section').html(receipt.html_content);
        __currency_convert_recursively($('#receipt_section'));
        __print_receipt('receipt_section');

        setTimeout(function() {
            document.title = title;
        }, 1200);
    }
}

function calculate_discounted_unit_price(row) {
    var this_unit_price = __read_number(row.find('input.pos_unit_price'));
    var row_discounted_unit_price = this_unit_price;
    var row_discount_type = row.find('select.row_discount_type').val();
    var row_discount_amount = __read_number(row.find('input.row_discount_amount'));
    if (row_discount_amount) {
        if (row_discount_type == 'fixed') {
            row_discounted_unit_price = this_unit_price - row_discount_amount;
        } else {
            row_discounted_unit_price = __substract_percent(this_unit_price, row_discount_amount);
        }
    }

    return row_discounted_unit_price;
}

function get_unit_price_from_discounted_unit_price(row, discounted_unit_price) {
    var this_unit_price = discounted_unit_price;
    var row_discount_type = row.find('select.row_discount_type').val();
    var row_discount_amount = __read_number(row.find('input.row_discount_amount'));
    if (row_discount_amount) {
        if (row_discount_type == 'fixed') {
            this_unit_price = discounted_unit_price + row_discount_amount;
        } else {
            this_unit_price = __get_principle(discounted_unit_price, row_discount_amount, true);
        }
    }

    return this_unit_price;
}

//Update quantity if line subtotal changes
$('table#pos_table tbody').on('change', 'input.pos_line_total', function() {
    var subtotal = __read_number($(this));
    var tr = $(this).parents('tr');
    var quantity_element = tr.find('input.pos_quantity');
    var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));
    var quantity = subtotal / unit_price_inc_tax;
    __write_number(quantity_element, quantity);

    if (sell_form_validator) {
        sell_form_validator.element(quantity_element);
    }
    if (pos_form_validator) {
        pos_form_validator.element(quantity_element);
    }
    tr.find('span.pos_line_total_text').text(__currency_trans_from_en(subtotal, true));

    pos_total_row();
});

$('div#product_list_body').on('scroll', function() {
    if ($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight) {
        var page = parseInt($('#suggestion_page').val());
        page += 1;
        $('#suggestion_page').val(page);
        var location_id = $('input#location_id').val();
        var category_id = $('select#product_category').val();
        var brand_id = $('select#product_brand').val();

        var is_enabled_stock = null;
        if ($("#is_enabled_stock").length) {
            is_enabled_stock = $("#is_enabled_stock").val();
        }

        var device_model_id = null;
        if ($("#repair_model_id").length) {
            device_model_id = $("#repair_model_id").val();
        }

        get_product_suggestion_list(category_id, brand_id, location_id, null, is_enabled_stock, device_model_id);
    }
});

$(document).on('ifChecked', '#is_recurring', function() {
    $('#recurringInvoiceModal').modal('show');
});

$(document).on('shown.bs.modal', '#recurringInvoiceModal', function() {
    $('input#recur_interval').focus();
});

$(document).on('click', '#select_all_service_staff', function() {
    var val = $('#res_waiter_id').val();
    $('#pos_table tbody')
        .find('select.order_line_service_staff')
        .each(function() {
            $(this)
                .val(val)
                .change();
        });
});

$(document).on('click', '.print-invoice-link', function(e) {
    e.preventDefault();
    $.ajax({
        url: $(this).attr('href') + "?check_location=true",
        dataType: 'json',
        success: function(result) {
            if (result.success == 1) {
                //Check if enabled or not
                if (result.receipt.is_enabled) {
                    pos_print(result.receipt);
                }
            } else {
                toastr.error(result.msg);
            }

        },
    });
});


function getCustomerRewardPoints() {
    if ($('#reward_point_enabled').length <= 0) {
        return false;
    }
    var is_edit = $('form#edit_sell_form').length || 
    $('form#edit_pos_sell_form').length ? true : false;
    if (is_edit && !customer_set) {
        return false;
    }

    var customer_id = $('#customer_id').val();

    $.ajax({
        method: 'POST',
        url: '/sells/pos/get-reward-details',
        data: { 
            customer_id: customer_id
        },
        dataType: 'json',
        success: function(result) {
            $('#available_rp').text(result.points);
            $('#rp_redeemed_modal').data('max_points', result.points);
            updateRedeemedAmount();
            $('#rp_redeemed_amount').change()
        },
    });
}

function updateRedeemedAmount(argument) {
    var points = $('#rp_redeemed_modal').val().trim();
    points = points == '' ? 0 : parseInt(points);
    var amount_per_unit_point = parseFloat($('#rp_redeemed_modal').data('amount_per_unit_point'));
    var redeemed_amount = points * amount_per_unit_point;
    $('#rp_redeemed_amount_text').text(__currency_trans_from_en(redeemed_amount, true));
    $('#rp_redeemed').val(points);
    $('#rp_redeemed_amount').val(redeemed_amount);
}

$(document).on('change', 'select#customer_id', function(){
    var default_customer_id = $('#default_customer_id').val();
    if ($(this).val() == default_customer_id) {
        //Disable reward points for walkin customers
        if ($('#rp_redeemed_modal').length) {
            $('#rp_redeemed_modal').val('');
            $('#rp_redeemed_modal').change();
            $('#rp_redeemed_modal').attr('disabled', true);
            $('#available_rp').text('');
            updateRedeemedAmount();
            pos_total_row();
        }
    } else {
        if ($('#rp_redeemed_modal').length) {
            $('#rp_redeemed_modal').removeAttr('disabled');
        }
        getCustomerRewardPoints();
    }

    // UPDATE ALL EXISTING ROWS WITH NEW CUSTOMER GROUP PRICING
    $('#pos_table tbody tr.product_row').each(function() {
        updateCustomerGroupPrice($(this));
    });

    get_sales_orders();
});

$(document).on('change', '#rp_redeemed_modal', function(){
    var points = $(this).val().trim();
    points = points == '' ? 0 : parseInt(points);
    var amount_per_unit_point = parseFloat($(this).data('amount_per_unit_point'));
    var redeemed_amount = points * amount_per_unit_point;
    $('#rp_redeemed_amount_text').text(__currency_trans_from_en(redeemed_amount, true));
    var reward_validation = isValidatRewardPoint();
    if (!reward_validation['is_valid']) {
        toastr.error(reward_validation['msg']);
        $('#rp_redeemed_modal').select();
    }
});

$(document).on('change', '.direct_sell_rp_input', function(){
    updateRedeemedAmount();
    pos_total_row();
});

function isValidatRewardPoint() {
    var element = $('#rp_redeemed_modal');
    var points = element.val().trim();
    points = points == '' ? 0 : parseInt(points);

    var max_points = parseInt(element.data('max_points'));
    var is_valid = true;
    var msg = '';

    if (points == 0) {
        return {
            is_valid: is_valid,
            msg: msg
        }
    }

    var rp_name = $('input#rp_name').val();
    if (points > max_points) {
        is_valid = false;
        msg = __translate('max_rp_reached_error', {max_points: max_points, rp_name: rp_name});
    }

    var min_order_total_required = parseFloat(element.data('min_order_total'));

    var order_total = __read_number($('#final_total_input'));

    if (order_total < min_order_total_required) {
        is_valid = false;
        msg = __translate('min_order_total_error', {min_order: __currency_trans_from_en(min_order_total_required, true), rp_name: rp_name});
    }

    var output = {
        is_valid: is_valid,
        msg: msg,
    }

    return output;
}

function adjustComboQty(tr) {
    var product_type = tr.find('input.product_type').val();
    
    if (product_type == 'combo' || product_type == 'combo_single') {
        // Get the parent quantity and multiplier
        var qty = __read_number(tr.find('input.pos_quantity'));
        var multiplier = __getUnitMultiplier(tr);

        console.log('=== adjustComboQty Debug ===', {
            product_type: product_type,
            parent_qty: qty,
            multiplier: multiplier
        });

        tr.find('input.combo_product_qty').each(function() {
            // Get the unit quantity (qty required per main product)
            var unit_quantity = parseFloat($(this).data('unit_quantity')) || 1;
            
            // Calculate: unit_quantity * parent_qty * multiplier
            var new_quantity = unit_quantity * qty * multiplier;

            console.log('=== Combo Product Calculation ===', {
                unit_quantity: unit_quantity,
                parent_qty: qty,
                multiplier: multiplier,
                calculated_quantity: new_quantity,
                old_value: $(this).val()
            });

            // Set the new quantity
            $(this).val(new_quantity);
        });
    }
}

$(document).on('change', '#types_of_service_id', function(){
    var types_of_service_id = $(this).val();
    var location_id = $('#location_id').val();

    if(types_of_service_id) {
        $.ajax({
            method: 'POST',
            url: '/sells/pos/get-types-of-service-details',
            data: { 
                types_of_service_id: types_of_service_id,
                location_id: location_id
            },
            dataType: 'json',
            success: function(result) {
                //reset form if price group is changed
                var prev_price_group = $('#types_of_service_price_group').val();
                if(result.price_group_id) {
                    $('#types_of_service_price_group').val(result.price_group_id);
                    $('#price_group_text').removeClass('hide');
                    $('#price_group_text span').text(result.price_group_name);
                } else {
                    $('#types_of_service_price_group').val('');
                    $('#price_group_text').addClass('hide');
                    $('#price_group_text span').text('');
                }
                $('#types_of_service_id').val(types_of_service_id);
                $('.types_of_service_modal').html(result.modal_html);
                
                if (prev_price_group != result.price_group_id) {
                    if ($('form#edit_pos_sell_form').length > 0) {
                        $('table#pos_table tbody').html('');
                        pos_total_row();
                    } else {
                        reset_pos_form();
                    }
                } else {
                    pos_total_row();
                }

                $('.types_of_service_modal').modal('show');
            },
        });
    } else {
        $('.types_of_service_modal').html('');
        $('#types_of_service_price_group').val('');
        $('#price_group_text').addClass('hide');
        $('#price_group_text span').text('');
        $('#packing_charge_text').text('');
        if ($('form#edit_pos_sell_form').length > 0) {
            $('table#pos_table tbody').html('');
            pos_total_row();
        } else {
            reset_pos_form();
        }
    }
});

$(document).on('change', 'input#packing_charge, #additional_expense_value_1, #additional_expense_value_2, \
        #additional_expense_value_3, #additional_expense_value_4', function() {
    pos_total_row();
});

$(document).on('click', '.service_modal_btn', function(e) {
    if ($('#types_of_service_id').val()) {
        $('.types_of_service_modal').modal('show');
    }
});

$(document).on('change', '.payment_types_dropdown', function(e) {
    var default_accounts = $('select#select_location_id').length ? 
                $('select#select_location_id')
                .find(':selected')
                .data('default_payment_accounts') : $('#location_id').data('default_payment_accounts');
    var payment_type = $(this).val();
    var payment_row = $(this).closest('.payment_row');
    if (payment_type && payment_type != 'advance') {
        var default_account = default_accounts && default_accounts[payment_type]['account'] ? 
            default_accounts[payment_type]['account'] : '';
        var row_index = payment_row.find('.payment_row_index').val();

        var account_dropdown = payment_row.find('select#account_' + row_index);
        if (account_dropdown.length && default_accounts) {
            account_dropdown.val(default_account);
            account_dropdown.change();
        }
    }

    //Validate max amount and disable account if advance 
    amount_element = payment_row.find('.payment-amount');
    account_dropdown = payment_row.find('.account-dropdown');
    if (payment_type == 'advance') {
        max_value = $('#advance_balance').val();
        msg = $('#advance_balance').data('error-msg');
        amount_element.rules('add', {
            'max-value': max_value,
            messages: {
                'max-value': msg,
            },
        });
        if (account_dropdown) {
            account_dropdown.prop('disabled', true);
            account_dropdown.closest('.form-group').addClass('hide');
        }
    } else {
        amount_element.rules("remove", "max-value");
        if (account_dropdown) {
            account_dropdown.prop('disabled', false); 
            account_dropdown.closest('.form-group').removeClass('hide');
        }    
    }
});

$(document).on('show.bs.modal', '#recent_transactions_modal', function () {
    get_recent_transactions('final', $('div#tab_final'));
});
$(document).on('shown.bs.tab', 'a[href="#tab_quotation"]', function () {
    get_recent_transactions('quotation', $('div#tab_quotation'));
});
$(document).on('shown.bs.tab', 'a[href="#tab_draft"]', function () {
    get_recent_transactions('draft', $('div#tab_draft'));
});

function disable_pos_form_actions(){
    if (!window.navigator.onLine) {
        return false;
    }

    $('div.pos-processing').show();
    $('#pos-save').attr('disabled', 'true');
    $('div.pos-form-actions').find('button').attr('disabled', 'true');
}

function enable_pos_form_actions(){
    $('div.pos-processing').hide();
    $('#pos-save').removeAttr('disabled');
    $('div.pos-form-actions').find('button').removeAttr('disabled');
}

$(document).on('change', '#recur_interval_type', function() {
    if ($(this).val() == 'months') {
        $('.subscription_repeat_on_div').removeClass('hide');
    } else {
        $('.subscription_repeat_on_div').addClass('hide');
    }
});

function validate_discount_field() {
    discount_element = $('#discount_amount_modal');
    discount_type_element = $('#discount_type_modal');

    if ($('#add_sell_form').length || $('#edit_sell_form').length) {
        discount_element = $('#discount_amount');
        discount_type_element = $('#discount_type');
    }
    var max_value = parseFloat(discount_element.data('max-discount'));
    if (discount_element.val() != '' && !isNaN(max_value)) {
        if (discount_type_element.val() == 'fixed') {
            var subtotal = get_subtotal();
            //get max discount amount
            max_value = __calculate_amount('percentage', max_value, subtotal)
        }

        discount_element.rules('add', {
            'max-value': max_value,
            messages: {
                'max-value': discount_element.data('max-discount-error_msg'),
            },
        });
    } else {
        discount_element.rules("remove", "max-value");      
    }
    discount_element.trigger('change');
}

$(document).on('change', '#discount_type_modal, #discount_type', function() {
    validate_discount_field();
});

function update_shipping_address(data) {
    if ($('#shipping_address_div').length) {
        var shipping_address = '';
        if (data.supplier_business_name) {
            shipping_address += data.supplier_business_name;
        }
        if (data.name) {
            shipping_address += ',<br>' + data.name;
        }
        if (data.text) {
            shipping_address += ',<br>' + data.text;
        }
        shipping_address += ',<br>' + data.shipping_address ;
        $('#shipping_address_div').html(shipping_address);
    }
    if ($('#billing_address_div').length) {
        var address = [];
        if (data.supplier_business_name) {
            address.push(data.supplier_business_name);
        }
        if (data.name) {
            address.push('<br>' + data.name);
        }
        if (data.text) {
            address.push('<br>' + data.text);
        }
        if (data.address_line_1) {
            address.push('<br>' + data.address_line_1);
        }
        if (data.address_line_2) {
            address.push('<br>' + data.address_line_2);
        }
        if (data.city) {
            address.push('<br>' + data.city);
        }
        if (data.state) {
            address.push(data.state);
        }
        if (data.country) {
            address.push(data.country);
        }
        if (data.zip_code) {
            address.push('<br>' + data.zip_code);
        }
        var billing_address = address.join(', ');
        $('#billing_address_div').html(billing_address);
    }

    if ($('#shipping_custom_field_1').length) {
        let shipping_custom_field_1 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_1 : '';
        $('#shipping_custom_field_1').val(shipping_custom_field_1);
    }

    if ($('#shipping_custom_field_2').length) {
        let shipping_custom_field_2 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_2 : '';
        $('#shipping_custom_field_2').val(shipping_custom_field_2);
    }

    if ($('#shipping_custom_field_3').length) {
        let shipping_custom_field_3 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_3 : '';
        $('#shipping_custom_field_3').val(shipping_custom_field_3);
    }

    if ($('#shipping_custom_field_4').length) {
        let shipping_custom_field_4 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_4 : '';
        $('#shipping_custom_field_4').val(shipping_custom_field_4);
    }

    if ($('#shipping_custom_field_5').length) {
        let shipping_custom_field_5 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_5 : '';
        $('#shipping_custom_field_5').val(shipping_custom_field_5);
    }
    
    //update export fields
    if (data.is_export) {
        $('#is_export').prop('checked', true);
        $('div.export_div').show();
        if ($('#export_custom_field_1').length) {
            $('#export_custom_field_1').val(data.export_custom_field_1);
        }
        if ($('#export_custom_field_2').length) {
            $('#export_custom_field_2').val(data.export_custom_field_2);
        }
        if ($('#export_custom_field_3').length) {
            $('#export_custom_field_3').val(data.export_custom_field_3);
        }
        if ($('#export_custom_field_4').length) {
            $('#export_custom_field_4').val(data.export_custom_field_4);
        }
        if ($('#export_custom_field_5').length) {
            $('#export_custom_field_5').val(data.export_custom_field_5);
        }
        if ($('#export_custom_field_6').length) {
            $('#export_custom_field_6').val(data.export_custom_field_6);
        }
    } else {
        $('#export_custom_field_1, #export_custom_field_2, #export_custom_field_3, #export_custom_field_4, #export_custom_field_5, #export_custom_field_6').val('');
        $('#is_export').prop('checked', false);
        $('div.export_div').hide();
    }
    
    $('#shipping_address_modal').val(data.shipping_address);
    $('#shipping_address').val(data.shipping_address);
}

function get_sales_orders() {
    if ($('#sales_order_ids').length) {
        if ($('#sales_order_ids').hasClass('not_loaded')) {
            $('#sales_order_ids').removeClass('not_loaded');
            return false;
        }
        var customer_id = $('select#customer_id').val();
        var location_id = $('input#location_id').val();
        $.ajax({
            url: '/get-sales-orders/' + customer_id + '?location_id=' + location_id,
            dataType: 'json',
            success: function(data) {
                $('#sales_order_ids').select2('destroy').empty().select2({data: data});
                $('table#pos_table tbody').find('tr').each( function(){
                    if (typeof($(this).data('so_id')) !== 'undefined') {
                        $(this).remove();
                    }
                });
                pos_total_row();
            },
        });
    }
}

$("#sales_order_ids").on("select2:select", function (e) {
    var sales_order_id = e.params.data.id;
    var product_row = $('input#product_row_count').val();
    var location_id = $('input#location_id').val();
    $.ajax({
        method: 'GET',
        url: '/get-sales-order-lines',
        async: false,
        data: {
            product_row: product_row,
            sales_order_id: sales_order_id
        },
        dataType: 'json',
        success: function(result) {
            if (result.html) {
                var html = result.html;
                $(html).find('tr').each(function(){
                    $('table#pos_table tbody')
                    .append($(this))
                    .find('input.pos_quantity');
                    
                    var this_row = $('table#pos_table tbody')
                        .find('tr')
                        .last();
                    pos_each_row(this_row);

                    product_row = parseInt(product_row) + 1;

                    //For initial discount if present
                    var line_total = __read_number(this_row.find('input.pos_line_total'));
                    this_row.find('span.pos_line_total_text').text(line_total);

                    //Check if multipler is present then multiply it when a new row is added.
                    if(__getUnitMultiplier(this_row) > 1){
                        this_row.find('select.sub_unit').trigger('change');
                    }

                    round_row_to_iraqi_dinnar(this_row);
                    __currency_convert_recursively(this_row);
                });

                set_so_values(result.sales_order);

                //increment row count
                $('input#product_row_count').val(product_row);
                
                pos_total_row();
            
            } else {
                toastr.error(result.msg);
                $('input#search_product')
                    .focus()
                    .select();
            }
        },
    });
});

function set_so_values(so) {
    $('textarea[name="sale_note"]').val(so.additional_notes);
    if ($('#shipping_details').is(':visible')) {
        $('#shipping_details').val(so.shipping_details);
    }
    $('#shipping_address').val(so.shipping_address);
    $('#delivered_to').val(so.delivered_to);
    $('#shipping_charges').val( __number_f(so.shipping_charges));
    $('#shipping_status').val(so.shipping_status);
    if ($('#shipping_custom_field_1').length) {
        $('#shipping_custom_field_1').val(so.shipping_custom_field_1);
    }
    if ($('#shipping_custom_field_2').length) {
        $('#shipping_custom_field_2').val(so.shipping_custom_field_2);
    }
    if ($('#shipping_custom_field_3').length) {
        $('#shipping_custom_field_3').val(so.shipping_custom_field_3);
    }
    if ($('#shipping_custom_field_4').length) {
        $('#shipping_custom_field_4').val(so.shipping_custom_field_4);
    }
    if ($('#shipping_custom_field_5').length) {
        $('#shipping_custom_field_5').val(so.shipping_custom_field_5);
    }
}

$("#sales_order_ids").on("select2:unselect", function (e) {
    var sales_order_id = e.params.data.id;
    $('table#pos_table tbody').find('tr').each( function(){
        if (typeof($(this).data('so_id')) !== 'undefined' 
            && $(this).data('so_id') == sales_order_id) {
            $(this).remove();
        pos_total_row();
        }
    });
});

$(document).on('click', '#add_expense', function(){
    $.ajax({
        url: '/expenses/create',
        data: { 
            location_id: $('#select_location_id').val()
        },
        dataType: 'html',
        success: function(result) {
            $('#expense_modal').html(result);
            $('#expense_modal').modal('show');
        },
    });
});

$(document).on('shown.bs.modal', '#expense_modal', function(){
    $('#expense_transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });
    $('#expense_modal .paid_on').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });
    $(this).find('.select2').select2();
    $('#add_expense_modal_form').validate();
});

$(document).on('hidden.bs.modal', '#expense_modal', function(){
    $(this).html('');
});

$(document).on('submit', 'form#add_expense_modal_form', function(e) {
    e.preventDefault();
    var data = $(this).serialize();

    $.ajax({
        method: 'POST',
        url: $(this).attr('action'),
        dataType: 'json',
        data: data,
        success: function(result) {
            if (result.success == true) {
                $('#expense_modal').modal('hide');
                toastr.success(result.msg);
            } else {
                toastr.error(result.msg);
            }
        },
    });
});

function get_contact_due(id) {
    $.ajax({
        method: 'get',
        url: /get-contact-due/ + id,
        dataType: 'text',
        success: function(result) {
            if (result != '') {
                $('.contact_due_text').find('span').text(result);
                $('.contact_due_text').removeClass('hide');
            } else {
                $('.contact_due_text').find('span').text('');
                $('.contact_due_text').addClass('hide');
            }
        },
    });
}

$(document).on('click', '#send_for_sell_return', function(e) {
    var invoice_no = $('#send_for_sell_return_invoice_no').val();

    if (invoice_no) {
        $.ajax({
            method: 'get',
            url: /validate-invoice-to-return/ + encodeURI(invoice_no),
            dataType: 'json',
            success: function(result) {
                if (result.success == true) {
                    window.location = result.redirect_url ;
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    }
})

$(document).on('ifChanged', 'input[name="search_fields[]"]', function(event) {
    var search_fields = [];
    $('input[name="search_fields[]"]:checked').each(function() {
       search_fields.push($(this).val());
    });

    localStorage.setItem('pos_search_fields', search_fields);
});

function set_search_fields() {
    if ($('input[name="search_fields[]"]').length == 0) {
        return false;
    }

    var pos_search_fields = localStorage.getItem('pos_search_fields');

    if (pos_search_fields === null) {
        pos_search_fields = ['name', 'sku', 'lot'];
    }

    $('input[name="search_fields[]"]').each(function() {
        if (pos_search_fields.indexOf($(this).val()) >= 0) {
            $(this).iCheck('check');
        } else {
            $(this).iCheck('uncheck');
        }
    });
}

$(document).on('click', '#show_service_staff_availability', function(){
    loadServiceStaffAvailability();
})
$(document).on('click', '#refresh_service_staff_availability_status', function(){
    loadServiceStaffAvailability(false);
})
$(document).on('click', 'button.pause_resume_timer', function(e){
    $('.view_modal').find('.overlay').removeClass('hide');
    $.ajax({
        method: 'get',
        url: $(this).attr('data-href'),
        dataType: 'json',
        success: function(result) {
            loadServiceStaffAvailability(false);
        },
    });
})

$(document).on('click', '.mark_as_available', function(e){
    e.preventDefault()
    $('.view_modal').find('.overlay').removeClass('hide');
    $.ajax({
        method: 'get',
        url: $(this).attr('href'),
        dataType: 'json',
        success: function(result) {
            loadServiceStaffAvailability(false);
        },
    });
})
var service_staff_availability_interval = null;

function loadServiceStaffAvailability(show = true) {
    var location_id = $('[name="location_id"]').val();
    $.ajax({
        method: 'get',
        url: $('#show_service_staff_availability').attr('data-href'),
        dataType: 'html',
        data: {location_id: location_id},
        success: function(result) {
            $('.view_modal').html(result);
            if (show) {
                $('.view_modal').modal('show')

                //auto refresh service staff availabilty if modal is open
                service_staff_availability_interval = setInterval(function () {
                    loadServiceStaffAvailability(false);
                }, 60000);
            }
        },
    });
}

$(document).on('hidden.bs.modal', '.view_modal', function(){
    if (service_staff_availability_interval !== null) {
        clearInterval(service_staff_availability_interval);
    }
    service_staff_availability_interval = null;
});