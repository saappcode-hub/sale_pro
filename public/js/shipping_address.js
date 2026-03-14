// Declare shipping_address_data_table in a broader scope
let shipping_address_data_table;

$(document).ready(function() {
    // Initialize the DataTable when the Shipping Address tab is shown
    $('a[href="#shipping_address_tab"]').on('shown.bs.tab', function(e) {
        if (!$.fn.DataTable.isDataTable('#shipping_address_table')) {
            initializeShippingAddressDataTable();
        }
    });

    // Show add/edit form in modal
    $(document).on('click', '.shipping_address_btn', function() {
        var url = $(this).data('href');
        $.ajax({
            method: "GET",
            dataType: "html",
            url: url,
            success: function(result) {
                $('.shipping_address_modal').html(result).modal("show");
            },
            error: function(xhr, status, error) {
                toastr.error('Failed to load the form: ' + error);
            }
        });
    });

    // Toggle new label input field
    $(document).on('click', '#add_new_label_btn', function() {
        $('#new_label_container').toggle();
        if ($('#new_label_container').is(':visible')) {
            $('#new_label_input').focus();
        } else {
            $('#new_label_input').val(''); // Clear input when hiding
        }
    });

    // Save new label via AJAX
    $(document).on('click', '#save_new_label_btn', function() {
        var newLabel = $('#new_label_input').val().trim();
        var businessId = $('#business_id').val();
        var contactId = $('#contact_id').val();

        if (!newLabel) {
            toastr.error('Please enter a label name.');
            return;
        }

        $.ajax({
            method: 'POST',
            url: '/contacts/add-shipping-label',
            dataType: 'json',
            data: {
                name: newLabel,
                business_id: businessId,
                contact_id: contactId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success) {
                    // Add the new label to the select dropdown
                    $('#label_shipping_id').append(
                        $('<option>', {
                            value: result.label.id,
                            text: result.label.name,
                            selected: true
                        })
                    );
                    // Hide the new label input and clear it
                    $('#new_label_container').hide();
                    $('#new_label_input').val('');
                    toastr.success(result.msg);
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Failed to save the new label: ' + error);
            }
        });
    });

    // Form submission for add/edit
    $(document).on('submit', 'form#shipping_address_form', function(e) {
        e.preventDefault();
        var url = $(this).attr('action');
        var method = $(this).attr('method');
        var data = $(this).serialize();
        var isFirstAddress = false;

        // Check if this is the first address being created
        if (method === 'POST') {
            var contactId = $('#contact_id').val();
            $.ajax({
                method: 'GET',
                url: '/contacts/shipping-addresses/' + contactId,
                async: false,
                success: function(response) {
                    if (response.recordsTotal === 0) {
                        isFirstAddress = true;
                    }
                }
            });
        }

        $.ajax({
            method: method,
            dataType: "json",
            url: url,
            data: data,
            success: function(result) {
                if (result.success) {
                    $('.shipping_address_modal').modal('hide');
                    toastr.success(result.msg);
                    
                    // Reload the DataTable
                    if (shipping_address_data_table && $.fn.DataTable.isDataTable('#shipping_address_table')) {
                        shipping_address_data_table.ajax.reload(function() {
                            // After DataTable reload, update map if this was the first address
                            if (isFirstAddress) {
                                setTimeout(function() {
                                    updateMapDisplay();
                                }, 500);
                            }
                        });
                    } else {
                        initializeShippingAddressDataTable();
                        if (isFirstAddress) {
                            setTimeout(function() {
                                updateMapDisplay();
                            }, 500);
                        }
                    }
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Failed to save the address: ' + error);
            }
        });
    });

    // Delete shipping address
    $(document).on('click', '.delete_shipping_address', function(e) {
        e.preventDefault();
        var url = $(this).data('href');
        swal({
            title: LANG.sure,
            icon: "warning",
            buttons: true,
            dangerMode: true
        }).then((confirmed) => {
            if (confirmed) {
                $.ajax({
                    method: 'DELETE',
                    dataType: 'json',
                    url: url,
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            // Reload DataTable
                            if (shipping_address_data_table && $.fn.DataTable.isDataTable('#shipping_address_table')) {
                                shipping_address_data_table.ajax.reload(function() {
                                    // Update map after deletion
                                    setTimeout(function() {
                                        updateMapDisplay();
                                    }, 300);
                                });
                            } else {
                                initializeShippingAddressDataTable();
                            }
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                    error: function(xhr, status, error) {
                        toastr.error('Failed to delete the address: ' + error);
                    }
                });
            }
        });
    });

    // Handle checkbox click to set default address
    $(document).on('click', '.set_default_checkbox', function() {
        var address_id = $(this).data('id');
        var url = '/contacts/shipping-address/set-default/' + address_id;

        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: url,
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success) {
                    toastr.success(result.msg);
                    // Reload the DataTable to reflect the updated default status
                    if (shipping_address_data_table && $.fn.DataTable.isDataTable('#shipping_address_table')) {
                        shipping_address_data_table.ajax.reload();
                    }
                    // Update the map display
                    updateMapDisplay();
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Failed to set the address as default: ' + error);
            }
        });
    });
});

function initializeShippingAddressDataTable() {
    shipping_address_data_table = $('#shipping_address_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/contacts/shipping-addresses/' + $('#contact_id').val()
        },
        columns: [
            { data: 'action', name: 'action' },
            { data: 'label', name: 'label' },
            { data: 'mobile', name: 'mobile' },
            { data: 'address', name: 'address' },
            { data: 'default_action', name: 'default_action' }
        ],
        columnDefs: [
            {
                targets: [0, 4], // 'action' and 'default_action' columns are not orderable/searchable
                orderable: false,
                searchable: false
            }
        ]
    });
}

// Function to update the map when default shipping address changes
function updateMapDisplay() {
    var contactId = $('#contact_id').val();
    if (!contactId) {
        contactId = $('input[name="contact_id"]').val();
    }
    
    if (!contactId) {
        console.log('No contact ID found for map update');
        return;
    }
    
    $.ajax({
        url: '/contacts/get-default-shipping-map/' + contactId,
        method: 'GET',
        dataType: 'json',
        success: function(result) {
            if (result.success && result.coordinates) {
                // Update the map iframe
                var newSrc = 'https://www.google.com/maps?q=' + encodeURIComponent(result.coordinates) + '&hl=en;z=14&output=embed';
                
                // Check if map container exists
                if ($('#shipping_map_container').length > 0) {
                    // Update existing map
                    $('#gmap_canvas').attr('src', newSrc);
                    $('#shipping_map_container').show();
                } else {
                    // Create new map container if it doesn't exist
                    var mapHtml = '<div class="map-container" id="shipping_map_container">' +
                                 '<iframe id="gmap_canvas" src="' + newSrc + '" ' +
                                 'width="100%" height="100%" frameborder="0" style="border:0" allowfullscreen>' +
                                 '</iframe>' +
                                 '<button onclick="toggleFullscreen(this)" style="position: absolute; top: 10px; right: 10px; z-index: 10;">&#x26F6;</button>' +
                                 '</div>';
                    
                    // Replace "No map data available" message with map
                    $('.col-sm-6 p:contains("No map data available")').replaceWith(mapHtml);
                }
                
                // Hide any "No map data available" messages
                $('p:contains("No map data available")').hide();
                
                console.log('Map updated successfully with coordinates: ' + result.coordinates);
            } else {
                // Hide map and show no data message
                $('#shipping_map_container').hide();
                if ($('p:contains("No map data available")').length === 0) {
                    $('.col-sm-6').append('<p>No map data available.</p>');
                } else {
                    $('p:contains("No map data available")').show();
                }
                console.log('No map data available for contact');
            }
        },
        error: function(xhr, status, error) {
            console.log('Error updating map display: ' + error);
        }
    });
}