@extends('layouts.app')

@section('title', __('Rewards'))

@section('content')
<section class="content-header">
    <h1>{{ __('Rewards') }}</h1>
</section>

<section class="content">  
    <div class="row">
        <div class="col-md-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ session('error') }}
                </div>
            @endif

            <ul class="nav nav-tabs">
                <li class="{{ request('type') == 'customers' || (!request('type') && !in_array(request('type'), ['suppliers', 'ring_units', 'cash_ring', 'exchange_rate'])) ? 'active' : '' }}" data-type="customers">
                    <a href="#tab_customers" data-toggle="tab" aria-expanded="true">@lang('Customers Reward')</a>
                </li>
                <li class="{{ request('type') == 'suppliers' ? 'active' : '' }}" data-type="suppliers">
                    <a href="#tab_suppliers" data-toggle="tab" aria-expanded="false">@lang('Supplier Reward')</a>
                </li>
                <li class="{{ request('type') == 'ring_units' ? 'active' : '' }}" data-type="ring_units">
                    <a href="#tab_ring_units" data-toggle="tab" aria-expanded="false">@lang('Set Up Ring Units')</a>
                </li>
                <li class="{{ request('type') == 'cash_ring' ? 'active' : '' }}" data-type="cash_ring">
                    <a href="#tab_cash_ring" data-toggle="tab" aria-expanded="false">@lang('Set Up Cash Ring')</a>
                </li>
                <li class="{{ request('type') == 'exchange_rate' ? 'active' : '' }}" data-type="exchange_rate">
                    <a href="#tab_exchange_rate" data-toggle="tab" aria-expanded="false">@lang('Set Up Exchange Rate')</a>
                </li>
            </ul>

            @component('components.widget', ['class' => 'box-primary'])
                @slot('tool')
                    <div class="box-tools">
                        <button class="btn btn-block btn-primary" id="openAddRewardModal">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </button>
                    </div>
                @endslot

                <div class="tab-content">
                    <div class="tab-pane {{ request('type') == 'customers' || (!request('type') && !in_array(request('type'), ['suppliers', 'ring_units', 'cash_ring', 'exchange_rate'])) ? 'active' : '' }}" id="tab_customers">
                        <table class="table table-bordered table-striped" id="rewards_exchange" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>@lang('messages.action')</th>
                                    <th>Product For Sales</th>
                                    <th>Exchange Product</th>
                                    <th>Exchange Quantity</th>
                                    <th>Amount</th>
                                    <th>Receive Product</th>
                                    <th>Receive Quantity</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="tab-pane {{ request('type') == 'suppliers' ? 'active' : '' }}" id="tab_suppliers">
                        <table class="table table-bordered table-striped" id="rewards_exchange_suppliers" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>@lang('messages.action')</th>
                                    <th>Product For Sales</th>
                                    <th>Exchange Product</th>
                                    <th>Exchange Quantity</th>
                                    <th>Amount</th>
                                    <th>Receive Product</th>
                                    <th>Receive Quantity</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="tab-pane {{ request('type') == 'ring_units' ? 'active' : '' }}" id="tab_ring_units">
                        <table class="table table-bordered table-striped" id="ring_units_table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>@lang('messages.action')</th>
                                    <th>Ring Name</th>
                                    <th>Values</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="tab-pane {{ request('type') == 'cash_ring' ? 'active' : '' }}" id="tab_cash_ring">
                        <table class="table table-bordered table-striped" id="cash_ring_table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>@lang('messages.action')</th>
                                    <th>Brands</th>
                                    <th>Ring Name</th>
                                    <th>Unit Value ($)</th>
                                    <th>Redemption Value ($)</th>
                                    <th>Unit Value (Riel)</th>
                                    <th>Redemption Value (Riel)</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    <div class="tab-pane {{ request('type') == 'exchange_rate' ? 'active' : '' }}" id="tab_exchange_rate">
                        <div class="row" style="padding: 20px;">
                            <div class="col-md-12">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4 style="margin-top: 0; font-weight: bold;">Exchange Rate Setting</h4>
                                        <p class="text-muted">Single global rate used for customer top ups. Example: 4,000 = USD 1</p>
                                    </div>
                                    <div id="rate_status_display" style="font-weight: bold; font-size: 16px;">
                                        <i class="fa fa-circle"></i> <span class="status-text"></span>
                                    </div>
                                </div>
                                <hr>

                                <form id="exchange_rate_form" onsubmit="return false;">
                                    <div class="form-group" style="max-width: 400px;">
                                        <label for="exchange_rate_input">KHR per 1 USD</label>
                                        <input type="number" class="form-control input-lg" id="exchange_rate_input" name="exchange_rate" 
                                               value="{{ isset($business->exchange_rate) ? number_format($business->exchange_rate, 0, '.', '') : '4000' }}" 
                                               step="1" placeholder="Enter Rate">
                                        <p class="help-block" style="margin-top: 10px;">
                                            Formula: USD = KHR / <span id="rate_formula_display" style="font-weight:bold;">4000</span> (per 1 USD)
                                        </p>
                                    </div>

                                    <div class="form-group" style="margin-top: 30px;">
                                        <button type="button" class="btn btn-primary" id="btn_save_rate" style="min-width: 100px;">Save</button>
                                        <button type="button" class="btn btn-danger" id="btn_reset_rate" style="margin-left: 10px; min-width: 100px;">Reset</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            @endcomponent
        </div>
    </div>
</section>

<div class="modal fade" id="addRewardModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog no-print" role="document">
        <div class="modal-content">
            </div>
    </div>
</div>

<div class="modal fade" id="addCashRingModal" tabindex="-1" role="dialog" aria-labelledby="cashRingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg no-print" role="document" style="width: 80%; max-width: 1150px;">
        <div class="modal-content">
            </div>
    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/js/adminlte.min.js"></script>

<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap.min.js"></script>

<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

<script src="{{ asset('js/vendor.js') }}"></script>

<script type="text/javascript">
// Declare DataTable variables in global scope
var customersTable, suppliersTable, ringUnitsTable, cashRingTable;

// Move initializeDropdowns to global scope so it can be accessed from modal content
function initializeDropdowns() {
    // Handle product input for search
    $('.product-input').on('input', function() {
        var inputId = $(this).attr('id');
        var dropdownId = '#' + inputId + 'Dropdown';
        var query = $(this).val();

        if (query.length >= 2) {
            $.ajax({
                url: '{{ route("search-product") }}',
                type: 'GET',
                data: { query: query },
                success: function(data) {
                    var dropdown = $(dropdownId);
                    dropdown.empty();

                    if(data.length > 0){
                        $.each(data, function(index, product) {
                            dropdown.append('<li class="list-group-item" data-id="'+product.id+'" data-name="'+product.name+'" data-sku="'+product.sku+'">' + product.name + ' (' + product.sku + ')</li>');
                        });
                        dropdown.show();
                    } else {
                        dropdown.append('<li class="list-group-item">No results found</li>').show();
                    }
                },
                error: function() {
                    alert("Error fetching products. Please try again.");
                    $(dropdownId).hide();
                }
            });
        } else {
            $(dropdownId).hide();
        }
    });

    // Handle selection from dropdown
    $(document).on('click', '.list-group-item', function() {
        var inputId = $(this).parent().attr('id').replace('Dropdown', '');
        var selectedProduct = $(this).data('name') + ' (' + $(this).data('sku') + ')';
        var productId = $(this).data('id');

        $('#' + inputId).val(selectedProduct);
        $('#' + inputId + 'Id').val(productId);

        $(this).parent().hide();

        // Check if the product already exists in RewardsExchange (only for Product For Sale)
        if (inputId === 'productForSale') {
            checkProductExistence(productId);
        }
    });

    // Hide dropdown when clicked outside
    $(document).click(function(event) {
        if (!$(event.target).closest('.product-input').length && !$(event.target).closest('.list-group-item').length) {
            $('.list-group').hide();
        }
    });
}

// Function to check if the product exists in the RewardsExchange table
function checkProductExistence(productId) {
    $.ajax({
        url: '{{ route("rewards_exchange.check_product") }}',
        type: 'GET',
        data: { product_id: productId },
        success: function(data) {
            if (data.exists) {
                swal("Duplicate Product!", "This product is already part of an existing reward exchange!", "warning");
                // Clear the selected product in the productForSale field
                $('#productForSale').val('');
                $('#productForSaleId').val('');
            }
        },
        error: function() {
            swal("Error", "Unable to verify product existence. Try again.", "error");
        }
    });
}

// Function to clear search input and reload the table
function clearSearchAndReload(tableInstance) {
    if (tableInstance && typeof tableInstance.search === 'function') {
        tableInstance.search('').columns().search('').draw();
    }
}

function initializeTabs() {
    var urlParams = new URLSearchParams(window.location.search);
    var urlType = urlParams.get('type');
    var storedType = localStorage.getItem('currentRewardType');
    
    // Priority: URL parameter first, then localStorage, finally default to 'customers'
    var currentType = urlType || storedType || 'customers';
    
    // Validate that the type is one of the expected values (UPDATED to include exchange_rate)
    var validTypes = ['customers', 'suppliers', 'ring_units', 'cash_ring', 'exchange_rate'];
    if (!validTypes.includes(currentType)) {
        currentType = 'customers';
    }
    
    console.log('Initializing tabs with type:', currentType); // Debug log
    
    // Manage Add Button Visibility (NEW)
    if (currentType === 'exchange_rate') {
        $('#openAddRewardModal').hide();
    } else {
        $('#openAddRewardModal').show();
    }

    // Remove active class from all tabs first
    $('ul.nav-tabs li').removeClass('active');
    $('.tab-pane').removeClass('active');
    
    // Add active class to the correct tab
    $('ul.nav-tabs li[data-type="' + currentType + '"]').addClass('active');
    $('#tab_' + currentType).addClass('active');
    
    // Update URL without refreshing if currentType is different from URL
    if (urlType !== currentType) {
        var newUrl = new URL(window.location);
        newUrl.searchParams.set('type', currentType);
        window.history.replaceState({}, '', newUrl);
    }
    
    // Load the appropriate table data with proper error handling
    setTimeout(function() {
        try {
            if (currentType === 'customers' && customersTable) {
                clearSearchAndReload(customersTable);
                customersTable.ajax.reload();
            } else if (currentType === 'suppliers' && suppliersTable) {
                clearSearchAndReload(suppliersTable);
                suppliersTable.ajax.reload();
            } else if (currentType === 'ring_units' && ringUnitsTable) {
                clearSearchAndReload(ringUnitsTable);
                ringUnitsTable.ajax.reload();
            } else if (currentType === 'cash_ring' && cashRingTable) {
                clearSearchAndReload(cashRingTable);
                cashRingTable.ajax.reload();
            }
        } catch (error) {
            console.error('Error reloading table:', error);
        }
    }, 100); // Small delay to ensure tab is shown first

    // Update localStorage to match the current tab
    localStorage.setItem('currentRewardType', currentType);
}

$(document).ready(function() {
    // Initialize DataTable for Customers Reward
    customersTable = $('#rewards_exchange').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("rewards_exchange.index") }}',
            type: 'GET',
            data: function(d) {
                d.type = 'customers';
            },
            error: function(xhr, error, thrown) {
                if(xhr.status == 400){
                    alert(xhr.responseJSON.error);
                } else {
                    alert("An error occurred while fetching data: " + thrown);
                }
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'product_for_sale', name: 'product_for_sale', searchable: true },
            { data: 'exchange_product', name: 'exchange_product', searchable: true },
            { data: 'exchange_quantity', name: 'exchange_quantity', searchable: false },
            { data: 'amount', name: 'amount', searchable: false },
            { data: 'receive_product', name: 'receive_product', searchable: true },
            { data: 'receive_quantity', name: 'receive_quantity', searchable: false }
        ]
    });

    // Initialize DataTable for Suppliers Reward
    suppliersTable = $('#rewards_exchange_suppliers').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("rewards_exchange.index") }}',
            type: 'GET',
            data: function(d) {
                d.type = 'suppliers';
            },
            error: function(xhr, error, thrown) {
                if(xhr.status == 400){
                    alert(xhr.responseJSON.error);
                } else {
                    alert("An error occurred while fetching data: " + thrown);
                }
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'product_for_sale', name: 'product_for_sale', searchable: true, visible: false }, // Hidden for suppliers
            { data: 'exchange_product', name: 'exchange_product', searchable: true },
            { data: 'exchange_quantity', name: 'exchange_quantity', searchable: false },
            { data: 'amount', name: 'amount', searchable: false },
            { data: 'receive_product', name: 'receive_product', searchable: true },
            { data: 'receive_quantity', name: 'receive_quantity', searchable: false }
        ]
    });

    // Initialize DataTable for Ring Units
    ringUnitsTable = $('#ring_units_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("ring-units.index") }}',
            type: 'GET',
            error: function(xhr, error, thrown) {
                alert("An error occurred while fetching ring units data: " + thrown);
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'ring_name', name: 'ring_name', searchable: true },
            { data: 'values', name: 'values', searchable: false }
        ]
    });

    // Initialize DataTable for Cash Ring - Fixed initialization
    cashRingTable = $('#cash_ring_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("cash-ring.index") }}', // Make sure this route exists
            type: 'GET',
            error: function(xhr, error, thrown) {
                console.log('Cash Ring AJAX Error: ', xhr.responseText);
                alert("An error occurred while fetching cash ring data: " + thrown);
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'brand_name', name: 'brand_name', searchable: true },
            { data: 'ring_name', name: 'ring_name', searchable: true },
            { data: 'unit_value_usd', name: 'unit_value_usd', searchable: false },
            { data: 'redemption_value_usd', name: 'redemption_value_usd', searchable: false },
            { data: 'unit_value_riel', name: 'unit_value_riel', searchable: false },
            { data: 'redemption_value_riel', name: 'redemption_value_riel', searchable: false }
        ],
        autoWidth: false,
        scrollX: true
    });

    // ----------------------------------------------------------------
    // LOGIC FOR EXCHANGE RATE TAB (NEW)
    // ----------------------------------------------------------------
    
    function updateRateStatus() {
        var val = $('#exchange_rate_input').val();
        
        // Update formula text
        $('#rate_formula_display').text(val ? val : '4000');

        if (val == 4000 || !val) {
            // Inactive State
            $('#rate_status_display .fa-circle').css('color', 'red');
            $('#rate_status_display .status-text').text('Not Active');
            $('#rate_status_display').css('color', 'red');
        } else {
            // Active State
            $('#rate_status_display .fa-circle').css('color', 'green');
            $('#rate_status_display .status-text').text('Active - 1 USD = ' + val + ' KHR');
            $('#rate_status_display').css('color', 'green');
        }
    }

    // Initialize status on load
    updateRateStatus();

    // Listen for changes
    $('#exchange_rate_input').on('input', function() {
        updateRateStatus();
    });

    // Reset Button
    $('#btn_reset_rate').click(function() {
        // 1. Set the visual value to 4000
        $('#exchange_rate_input').val(4000);
        updateRateStatus();

        // 2. Automatically Save "4000" to the database via AJAX
        $.ajax({
            url: '{{ route("rewards_exchange.save_rate") }}',
            type: 'POST',
            data: {
                exchange_rate: 4000, // Force send 4000
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function() {
                $('#btn_reset_rate').text('Resetting...').attr('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    swal("Success", "Exchange rate reset to default (Inactive).", "success");
                } else {
                    swal("Error", response.msg, "error");
                }
            },
            error: function(xhr) {
                var msg = "Something went wrong";
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                swal("Error", msg, "error");
            },
            complete: function() {
                $('#btn_reset_rate').text('Reset').attr('disabled', false);
                updateRateStatus();
            }
        });
    });

    // Save Button
    $('#btn_save_rate').click(function() {
        var rate = $('#exchange_rate_input').val();
        
        // Basic validation
        if(!rate || rate < 0) {
            swal("Invalid Rate", "Please enter a valid positive number.", "warning");
            return;
        }

        $.ajax({
            url: '{{ route("rewards_exchange.save_rate") }}', 
            type: 'POST',
            data: {
                exchange_rate: rate,
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function() {
                $('#btn_save_rate').text('Saving...').attr('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    swal("Success", response.msg, "success");
                } else {
                    swal("Error", response.msg, "error");
                }
            },
            error: function(xhr) {
                var msg = "Something went wrong";
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                swal("Error", msg, "error");
            },
            complete: function() {
                $('#btn_save_rate').text('Save').attr('disabled', false);
                updateRateStatus();
            }
        });
    });
    // ----------------------------------------------------------------
    // END NEW LOGIC
    // ----------------------------------------------------------------

    // Enhanced tab click handler
    $('.nav-tabs a').on('click', function() {
        var activeType = $(this).parent().data('type');
        var previousType = localStorage.getItem('currentRewardType') || 'customers';

        // Manage Add Button Visibility (NEW)
        if (activeType === 'exchange_rate') {
            $('#openAddRewardModal').hide();
        } else {
            $('#openAddRewardModal').show();
        }

        // Update URL parameter
        var newUrl = new URL(window.location);
        newUrl.searchParams.set('type', activeType);
        window.history.replaceState({}, '', newUrl);

        // Only reload table if switching to a different tab
        if (activeType !== previousType) {
            setTimeout(function() {
                try {
                    if (activeType === 'customers' && customersTable) {
                        clearSearchAndReload(customersTable);
                        customersTable.ajax.reload();
                    } else if (activeType === 'suppliers' && suppliersTable) {
                        clearSearchAndReload(suppliersTable);
                        suppliersTable.ajax.reload();
                    } else if (activeType === 'ring_units' && ringUnitsTable) {
                        clearSearchAndReload(ringUnitsTable);
                        ringUnitsTable.ajax.reload();
                    } else if (activeType === 'cash_ring' && cashRingTable) {
                        clearSearchAndReload(cashRingTable);
                        cashRingTable.ajax.reload();
                    }
                } catch (error) {
                    console.error('Error reloading table on tab click:', error);
                }
            }, 100);
        }

        // Update localStorage
        localStorage.setItem('currentRewardType', activeType);
    });

    // Handle browser back/forward navigation
    window.addEventListener('popstate', function(event) {
        initializeTabs();
    });

    // Handle the Add Reward button click
    $('#openAddRewardModal').click(function() {
        var type = $('ul.nav-tabs li.active').data('type') || 'customers';

        // Do nothing if we are on exchange rate tab
        if(type === 'exchange_rate') return;

        // Use addCashRingModal for cash_ring tab
        if (type === 'cash_ring') {
            $('#addCashRingModal .modal-content').html('<div class="modal-body"><p>Loading...</p></div>');
            $('#addCashRingModal').modal('show');

            $.ajax({
                url: '{{ route("cash-ring.create") }}', // Make sure this route exists
                method: 'GET',
                success: function(response) {
                    $('#addCashRingModal .modal-content').html(response);
                    // Call initializeDropdowns after the modal content is loaded
                    initializeDropdowns();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading cash ring create form:', xhr.responseText);
                    $('#addCashRingModal .modal-content').html(`
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <p>Error loading the form. Please try again later.</p>
                                <p>Error details: ${error}</p>
                            </div>
                        </div>
                    `);
                    setTimeout(function() {
                        $('#addCashRingModal').modal('hide');
                    }, 3000);
                }
            });
        } else {
            // Use addRewardModal for other tabs (customers, suppliers, ring_units)
            $('#addRewardModal .modal-content').html('<div class="modal-body"><p>Loading...</p></div>');
            $('#addRewardModal').modal('show');

            if (type === 'ring_units') {
                $.ajax({
                    url: '{{ route("ring-units.create") }}',
                    method: 'GET',
                    success: function(response) {
                        $('#addRewardModal .modal-content').html(response);
                        initializeDropdowns();
                    },
                    error: function(xhr, status, error) {
                        $('#addRewardModal .modal-content').html(`
                            <div class="modal-body">
                                <div class="alert alert-danger">
                                    <p>Error loading the form. Please try again later.</p>
                                </div>
                            </div>
                        `);
                        setTimeout(function() {
                            $('#addRewardModal').modal('hide');
                        }, 3000);
                    }
                });
            } else {
                $.ajax({
                    url: '{{ route("reward-exchange.create") }}',
                    method: 'GET',
                    data: { type: type },
                    success: function(response) {
                        $('#addRewardModal .modal-content').html(response);
                        initializeDropdowns();
                    },
                    error: function(xhr, status, error) {
                        $('#addRewardModal .modal-content').html(`
                            <div class="modal-body">
                                <div class="alert alert-danger">
                                    <p>Error loading the form. Please try again later.</p>
                                </div>
                            </div>
                        `);
                        setTimeout(function() {
                            $('#addRewardModal').modal('hide');
                        }, 3000);
                    }
                });
            }
        }
    });

    // Handle edit ring unit - FIXED
    $(document).on('click', '.edit-ring-unit', function(e) {
        e.preventDefault();

        var editUrl = $(this).attr('href');

        // Show loading message in addRewardModal (not addCashRingModal)
        $('#addRewardModal .modal-content').html('<div class="modal-body"><p>Loading...</p></div>');
        $('#addRewardModal').modal('show');

        // Make AJAX call to get form data
        $.ajax({
            url: editUrl,
            type: 'GET',
            success: function(response) {
                $('#addRewardModal .modal-content').html(response);
                initializeDropdowns();
            },
            error: function(xhr, status, error) {
                console.error('Error loading ring unit edit form:', xhr.responseText);
                $('#addRewardModal .modal-content').html(`
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <p>Error loading the edit form. Please try again later.</p>
                            <p>Error details: ${error}</p>
                        </div>
                    </div>
                `);
                setTimeout(function() {
                    $('#addRewardModal').modal('hide');
                }, 3000);
            }
        });
    });

    // Handle edit ring unit
    $(document).on('click', '.edit-cash-ring', function(e) {
        e.preventDefault();

        var editUrl = $(this).attr('href');

        // Show loading message
        $('#addCashRingModal .modal-content').html('<div class="modal-body"><p>Loading...</p></div>');
        $('#addCashRingModal').modal('show');

        // Make AJAX call to get form data
        $.ajax({
            url: editUrl,
            type: 'GET',
            success: function(response) {
                $('#addCashRingModal .modal-content').html(response);
                initializeDropdowns();
            },
            error: function(xhr, status, error) {
                console.error('Error loading cash ring edit form:', xhr.responseText);
                $('#addCashRingModal .modal-content').html(`
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <p>Error loading the edit form. Please try again later.</p>
                            <p>Error details: ${error}</p>
                        </div>
                    </div>
                `);
                setTimeout(function() {
                    $('#addCashRingModal').modal('hide');
                }, 3000);
            }
        });
    });

    // Handle opening the edit modal for rewards exchange
    $(document).on('click', '.edit-reward', function(e) {
        e.preventDefault();

        var editUrl = $(this).attr('href');

        // Show loading message
        $('#addRewardModal .modal-content').html('<div class="modal-body"><p>Loading...</p></div>');
        $('#addRewardModal').modal('show');

        // Make AJAX call to get form data
        $.ajax({
            url: editUrl,
            type: 'GET',
            success: function(response) {
                $('#addRewardModal .modal-content').html(response);
                initializeDropdowns();
            },
            error: function() {
                $('#addRewardModal .modal-content').html('<div class="modal-body"><p>Error loading the edit form.</p></div>');
            }
        });
    });

    $(document).on('click', '.delete-cash-ring', function(e) {
        e.preventDefault();

        var url = $(this).data('href');
        var csrfToken = '{{ csrf_token() }}';

        swal({
            title: "Are you sure?",
            text: "This will delete the cash ring and all associated values for this product.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        "_token": csrfToken
                    },
                    success: function(result) {
                        if (result.success) {
                            swal("Deleted!", result.message, "success");
                            if (cashRingTable) {
                                cashRingTable.ajax.reload();
                            }
                        } else {
                            swal("Failed!", result.message, "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        swal("Error", "Failed to delete cash ring.", "error");
                    }
                });
            }
        });
    });

    // Handle delete ring unit with SweetAlert confirmation
    $(document).on('click', '.delete-ring-unit', function(e) {
        e.preventDefault();

        var url = $(this).data('href');
        var csrfToken = '{{ csrf_token() }}';

        swal({
            title: "Are you sure?",
            text: "This will delete the ring unit and all associated values for this product.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        "_token": csrfToken
                    },
                    success: function(result) {
                        if (result.success) {
                            swal("Deleted!", result.message, "success");
                            if (ringUnitsTable) {
                                ringUnitsTable.ajax.reload();
                            }
                        } else {
                            swal("Failed!", result.message, "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        swal("Error", "Failed to delete ring unit.", "error");
                    }
                });
            }
        });
    });

    // Handle delete with SweetAlert confirmation for RewardsExchange
    $(document).on('click', '.delete-reward-exchange', function(e) {
        e.preventDefault();

        var url = $(this).data('href');
        var csrfToken = '{{ csrf_token() }}';

        swal({
            title: "Are you sure?",
            text: "This will delete the reward exchange.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        "_token": csrfToken
                    },
                    success: function(result) {
                        if (result.success) {
                            swal("Deleted!", result.message, "success");
                            // Reload the appropriate table based on the active tab
                            var activeType = localStorage.getItem('currentRewardType') || 'customers';
                            if (activeType === 'customers' && customersTable) {
                                customersTable.ajax.reload();
                            } else if (activeType === 'suppliers' && suppliersTable) {
                                suppliersTable.ajax.reload();
                            }
                        } else {
                            swal("Failed!", result.message, "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        swal("Error", "Failed to delete reward exchange.", "error");
                    }
                });
            }
        });
    });

    // Initialize tabs after all DataTables are set up
    setTimeout(function() {
        initializeTabs();
    }, 500);

    // Initialize dropdowns on page load
    initializeDropdowns();
});
</script>