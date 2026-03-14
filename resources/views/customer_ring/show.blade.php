@extends('layouts.app')

@section('title', __('Detail'))

@section('content')
<style>
    .status-pending {
        background-color: orange; /* Color for Pending */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-completed {
        background-color: #20c997; /* Color for Completed */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
</style>
<section class="content-header">
    <div class="row">
        <div class="col-md-12">
            <div class="col-md-6">
                <h1>{{ __('Detail') }}</h1>
            </div>
            <div class="col-md-6">
                {!! Form::select('sell_list_filter_contact_id', $contacts, $contact_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'contact_filter']) !!}
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <strong><i class="fa fa-user-tie"></i> </strong> 
                    <b id="contact-name">{{ $contact->name }} ({{ $contact->type }})</b>
                </div>
                <div class="col-md-6">
                    <strong><i class="fa fa-calendar margin-r-5"></i> Date:</strong> 
                    <span id="contact-date">{{ $contact->created_at }}</span>
                </div>
            </div>
            <div class="row" style="height: 40px;"></div>
            <div class="row">
                <div class="col-md-6">
                    <strong><i class="fa fa-map-marker margin-r-5"></i> Address:</strong> 
                    <span id="contact-address">{{ $contact->address_line_1 }}</span>
                </div>
                <div class="col-md-6">
                    <strong><i class="fa fa-phone margin-r-5"></i> Mobile:</strong> 
                    <span id="contact-mobile">{{ $contact->mobile }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="row">
        <div class="col-md-12">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#ring_balance" data-toggle="tab">@lang('Ring Balance')</a>
                    </li>
                    <li>
                        <a href="#transaction_top_up" data-toggle="tab">@lang('Transaction Top Up')</a>
                    </li>
                </ul>
            @component('components.widget', ['class' => 'box-primary'])
                @slot('tool')
                    <div class="box-tools">
                        <!-- Button to trigger the modal -->
                        <button class="btn btn-block btn-primary" id="create-ring-balance-btn">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </button>
                    </div>
                @endslot
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Ring Balance Tab -->
                    <div class="tab-pane active" id="ring_balance">
                        <div class="box-body">
                            <table style="width: 100%;" class="table table-bordered table-striped" id="ring_balance_table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Action') }}</th>
                                        <th>{{ __('Ring Name') }}</th>
                                        <th>{{ __('Ring Balance') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <!-- Transaction Top Up Tab -->
                    <div class="tab-pane" id="transaction_top_up">
                        <div class="box-body">
                            <table style="width: 100%;" class="table table-bordered table-striped" id="transaction_top_up_table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Action') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Invoice Number') }}</th>
                                        <th>Customer Name</th>
                                        <th>Customer Mobile</th>
                                        <th>Location</th>
                                        <th>Added By</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        // Initialize DataTable for Ring Balance
        var ringBalanceTable = $('#ring_balance_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('customer-ring.getRingBalances', $contact_id) }}",
                type: "GET",
                data: function (d) {
                    d.contact_id = $('#contact_filter').val();
                },
            },
            columns: [
                {
                    data: 'action',
                    name: 'action',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, row) {
                        // Dynamically include product_id and contact_id in the URL
                        return `
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">
                                    {{ __('messages.actions') }}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    <li>
                                        <a href="{{ route('customer-ring.show_ring', '') }}/${row.contact_id}?product_id=${row.product_id}" class="view-link">
                                            <i class="fas fa-eye"></i> {{ __('View Ring Balance') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>`;
                    }
                },
                { data: 'ring_name', name: 'ring_name' },
                { data: 'ring_balance', name: 'ring_balance' }
            ],
        });

        // Initialize DataTable for Transaction Top Up
        var transactionTopUpTable = $('#transaction_top_up_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('customer-ring.getTransactionTopUps', $contact_id) }}",
                type: "GET",
                data: function(d) {
                    d.contact_id = $('#contact_filter').val();
                }
            },
            columns: [
                { data: 'action', name: 'action', searchable: false, orderable: false },
                { data: 'date', name: 'date' },
                { data: 'invoice_no', name: 'invoice_no' },
                { data: 'contact_name', name: 'contact_name' },
                { data: 'contact_mobile', name: 'contact_mobile' },
                { data: 'location_name', name: 'location_name' },
                { data: 'addby', name: 'addby' },
                {
                    data: 'status',
                    name: 'status',
                    render: function(data, type, row) {
                        if (data === 'pending') {
                            return '<span class="status-pending">Pending</span>';
                        } else if (data === 'completed') {
                            return '<span class="status-completed">Completed</span>';
                        }
                        return data; // Fallback for other statuses
                    }
                }
            ]
        });

        $(document).ready(function () {
            // Update the "Create" button URL when the contact filter changes
            $('#contact_filter').on('change', function () {
                const selectedContactId = $(this).val();
                const createUrl = `{{ route('customer-ring-balance.create') }}?contact_id=${selectedContactId}`;
                $('#create-ring-balance-btn').attr('onclick', `window.location='${createUrl}'`);
            });

            // Trigger the change event on page load to ensure the button URL is set for the initial contact
            $('#contact_filter').trigger('change');
        });

        // Reload tables when a different contact is selected
        $('#contact_filter').on('change', function () {
            var contactId = $(this).val();

            // Update the DataTable's data source URL
            ringBalanceTable.ajax.url("{{ route('customer-ring.getRingBalances') }}/" + contactId).load();

            // Update the create button URL dynamically
            const createUrl = `{{ route('customer-ring-balance.create') }}?contact_id=${contactId}`;
            $('#create-ring-balance-btn').attr('onclick', `window.location='${createUrl}'`);

            // Fetch and update contact details dynamically
            if (contactId) {
                fetch(`/customer-ring/contact-details/${contactId}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('contact-name').textContent = data.name + ' (' + data.type + ')';
                        document.getElementById('contact-date').textContent = data.created_at;
                        document.getElementById('contact-address').textContent = data.address_line_1;
                        document.getElementById('contact-mobile').textContent = data.mobile;
                    })
                    .catch(error => {
                        console.error('Error fetching contact details:', error);
                    });
            } else {
                // Reset contact details if "All" is selected
                document.getElementById('contact-name').textContent = 'All Contacts';
                document.getElementById('contact-date').textContent = '';
                document.getElementById('contact-address').textContent = '';
                document.getElementById('contact-mobile').textContent = '';
            }
        });
    });
</script>
@endsection
