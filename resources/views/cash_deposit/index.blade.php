@extends('layouts.app')
@section('title', 'Cash Deposit')

@section('content')

<section class="content-header">
    <h1>Cash Deposit</h1>
</section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cd_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('cd_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cd_customer_id',  __('contact.customer') . ':') !!}
                {!! Form::select('cd_customer_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cd_status', 'Deposit Status:') !!}
                <select class="form-control select2" name="cd_status" id="cd_status" style="width:100%">
                    <option value="" selected>All</option>
                    <option value="pending">Pending</option>
                    <option value="deposited">Completed</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cd_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('cd_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#cash_invoice_tab" data-toggle="tab" aria-expanded="true">
                            <i class="fa fa-money"></i> Cash Invoice
                        </a>
                    </li>
                    <li>
                        <a href="#deposition_tab" data-toggle="tab" aria-expanded="false">
                            <i class="fa fa-history"></i> Deposit History
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="cash_invoice_tab">
                        <div style="margin-bottom: 10px;">
                            {{-- PERMISSION CHECK --}}
                            @can('cash_deposit.create')
                                <button type="button" class="btn btn-default" id="btn_deposit_selected" disabled>
                                    Deposit Selected (<span id="selected_count">0</span>)
                                </button>
                            @endcan
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="cash_invoice_table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select_all_rows"></th>
                                        <th>@lang('messages.date')</th>
                                        <th>@lang('sale.invoice_no')</th>
                                        <th>@lang('sale.customer_name')</th>
                                        <th>Payment Ref</th>
                                        <th>Payment Amount</th>
                                        <th>Deposit Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane" id="deposition_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="deposit_history_table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>@lang('messages.action')</th>
                                        <th>Payment Date</th> {{-- NEW COLUMN ADDED --}}
                                        <th>Deposit Date</th>
                                        <th>Ref No</th>
                                        <th>Bank</th>
                                        <th>Invoices</th>
                                        <th>Amount</th>
                                        <th>Slip</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Create Modal --}}
<div class="modal fade" id="deposit_modal" tabindex="-1" role="dialog" aria-labelledby="depositModalLabel">
    <div class="modal-dialog" role="document" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2196F3; color: white; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="depositModalLabel" style="font-size: 16px; font-weight: 500;">
                    <i class="fa fa-file-text-o"></i> Deposit Details
                </h4>
            </div>
            {!! Form::open(['id' => 'deposit_form', 'method' => 'POST']) !!}
            <div class="modal-body" style="padding: 20px;">
                
                <div style="background-color: #E3F2FD; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="color: #1976D2; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Selected Invoices</div>
                            <div style="color: #1976D2; font-size: 13px;">
                                <span id="invoice_count">0</span> Invoices Selected
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #1976D2; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Total Amount</div>
                            <div style="color: #1976D2; font-size: 18px; font-weight: 700;" id="display_total_amount">$0.00</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-size: 13px; color: #555; font-weight: 500;">Amount to Deposit</label>
                            <input type="text" class="form-control" id="total_amount" name="total_amount" readonly 
                                   style="background-color: #f5f5f5; font-size: 14px; color: #333; height: 36px;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-size: 13px; color: #555; font-weight: 500;">
                                Deposit Date <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="deposit_datetime" name="deposit_datetime" required readonly
                                   style="font-size: 14px; height: 36px;">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-size: 13px; color: #555; font-weight: 500;">
                                To Bank Account <span class="text-danger">*</span>
                            </label>
                            {!! Form::select('bank_account_id', $bank_accounts, null, [
                                'class' => 'form-control select2', 
                                'style' => 'width:100%; height: 36px;', 
                                'placeholder' => 'Please Select', 
                                'required' => true, 
                                'id' => 'bank_account_id'
                            ]); !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-size: 13px; color: #555; font-weight: 500;">Reference No</label>
                            <input type="text" class="form-control" id="ref_no" name="ref_no" placeholder="Leave empty to auto-generate (00001)"
                                   style="font-size: 14px; height: 36px;">
                            <small class="text-muted" style="font-size: 11px;">
                                <i class="fa fa-info-circle"></i> Auto-generates if left empty (e.g., 00001, 00002)
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label style="font-size: 13px; color: #555; font-weight: 500;">
                                <i class="fa fa-paperclip"></i> Attach Slip / Payment Proof
                            </label>
                            <div class="dropzone" id="depositSlipUpload"></div>
                            <input type="hidden" id="deposit_slip_names" name="deposit_slip_names" value="">
                            <small class="text-muted" style="font-size: 12px;">
                                <i class="fa fa-info-circle"></i> Upload payment slip or proof (Image/PDF). You can upload multiple files.
                            </small>
                        </div>
                    </div>
                </div>

                {!! Form::hidden('payment_ids', null, ['id' => 'payment_ids']); !!}
            </div>
            <div class="modal-footer" style="padding: 12px 20px; background-color: #fafafa;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-size: 13px; padding: 6px 16px;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submit_deposit" style="background-color: #2196F3; border-color: #2196F3; font-size: 13px; padding: 6px 16px;">
                    <i class="fa fa-check"></i> Submit Deposit
                </button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

{{-- Edit Modal Container --}}
<div class="modal fade" id="edit_deposit_modal" tabindex="-1" role="dialog" aria-labelledby="editDepositModalLabel"></div>

{{-- Attachments View Modal --}}
<div class="modal fade" id="attachments_modal" tabindex="-1" role="dialog" aria-labelledby="attachmentsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="attachmentsModalLabel">
                    <i class="fa fa-paperclip"></i> Deposit Attachments - <span id="attachment_ref_no"></span>
                </h4>
            </div>
            <div class="modal-body" id="attachments_container" style="max-height: 70vh; overflow-y: auto;">
                <div class="text-center">
                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                    <p>Loading attachments...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Payment Details Modal (NEW) --}}
<div class="modal fade" id="payment_details_modal" tabindex="-1" role="dialog" aria-labelledby="paymentDetailsLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="paymentDetailsLabel">
                    <i class="fa fa-calendar"></i> Payment Dates & Details <small>(Ref: <span id="pd_deposit_ref"></span>)</small>
                </h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="payment_details_list_table">
                        <thead>
                            <tr class="bg-info">
                                <th>Payment Date</th>
                                <th>Payment Ref</th>
                                <th>Invoice Info</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Content loaded via AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade invoice_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

@endsection

@section('javascript')
<script type="text/javascript">
    Dropzone.autoDiscover = false;
    
    $(document).ready(function(){
        var depositSlipDropzone = null;
        var deposit_history_table = null;
        
        $('#cd_status').val('pending').trigger('change');
        
        if($('#cd_date_range').length == 1){
            $('#cd_date_range').daterangepicker(dateRangeSettings, function(start, end) {
                $('#cd_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                cash_invoice_table.ajax.reload();
                if (deposit_history_table) deposit_history_table.ajax.reload();
            });
            $('#cd_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#cd_date_range').val('');
                cash_invoice_table.ajax.reload();
                if (deposit_history_table) deposit_history_table.ajax.reload();
            });
        }

        $('#deposit_datetime').datetimepicker({ format: moment_date_format + ' HH:mm:ss', ignoreReadonly: true });

        var cash_invoice_table = $('#cash_invoice_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cash_deposit.index') }}",
                data: function(d) {
                    d.location_id = $('#cd_location_id').val();
                    d.customer_id = $('#cd_customer_id').val();
                    d.deposit_status = $('#cd_status').val();
                    if($('#cd_date_range').val()) {
                        d.start_date = $('#cd_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#cd_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                }
            },
            columns: [
                { data: 'action', name: 'action', orderable: false, searchable: false },
                { data: 'paid_on', name: 'transaction_payments.paid_on' },
                { data: 'invoice_no', name: 't.invoice_no' },
                { data: 'customer_name', name: 'c.name' },
                { data: 'payment_ref_no', name: 'transaction_payments.payment_ref_no' },
                { data: 'amount', name: 'transaction_payments.amount' },
                { data: 'deposit_status', name: 'deposit_status', searchable: false }
            ],
            columnDefs: [{ targets: 0, className: 'text-center' }]
        });

        $(document).on('change', '#cd_location_id, #cd_customer_id, #cd_status', function() {
            cash_invoice_table.ajax.reload();
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href");
            if (target === '#cash_invoice_tab') {
                $('#cd_customer_id').prop('disabled', false);
                $('#cd_status').prop('disabled', false);
            } else if (target === '#deposition_tab') {
                $('#cd_customer_id').prop('disabled', true);
                $('#cd_status').prop('disabled', true);
            }
        });

        $('a[href="#deposition_tab"]').on('shown.bs.tab', function (e) {
            $('#cd_customer_id').prop('disabled', true);
            $('#cd_status').prop('disabled', true);
            
            if (!deposit_history_table) {
                deposit_history_table = $('#deposit_history_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('cash_deposit.history') }}",
                        data: function(d) {
                            if($('#cd_date_range').val()) {
                                d.start_date = $('#cd_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                                d.end_date = $('#cd_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                            }
                        }
                    },
                    columns: [
                        { data: 'action', name: 'action', orderable: false, searchable: false },
                        { data: 'payment_dates', name: 'payment_dates', orderable: false, searchable: false }, // New Column Added
                        { data: 'deposit_datetime', name: 'deposit_datetime' },
                        { data: 'ref_no', name: 'ref_no' },
                        { data: 'bank', name: 'bank', orderable: false },
                        { data: 'invoices', name: 'invoices', orderable: false, searchable: false },
                        { data: 'amount', name: 'amount' },
                        { data: 'slip', name: 'slip', orderable: false, searchable: false },
                        { data: 'status_label', name: 'status' }
                    ],
                    order: [[2, 'desc']] // Adjusted sort index
                });
            } else {
                deposit_history_table.ajax.reload();
            }
        });

        // Event listener for the new "Payment Date" button (Popup logic)
        $(document).on('click', '.view-payment-details', function() {
            var id = $(this).data('id');
            var container = $('#payment_details_list_table tbody');
            
            container.html('<tr><td colspan="4" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');
            $('#payment_details_modal').modal('show');

            $.ajax({
                url: '/cash-deposit/' + id + '/payment-details',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#pd_deposit_ref').text(response.deposit_ref);
                    var html = '';
                    if(response.data.length > 0) {
                        $.each(response.data, function(i, item) {
                            html += `
                                <tr>
                                    <td>${item.date}</td>
                                    <td><strong>${item.ref_no}</strong></td>
                                    <td>${item.invoice} <br> <small class="text-muted">${item.customer}</small></td>
                                    <td class="text-right">${item.amount}</td>
                                </tr>
                            `;
                        });
                    } else {
                        html = '<tr><td colspan="4" class="text-center">No payment details found</td></tr>';
                    }
                    container.html(html);
                },
                error: function() {
                    container.html('<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>');
                }
            });
        });

        $('a[href="#cash_invoice_tab"]').on('shown.bs.tab', function (e) {
            $('#cd_customer_id').prop('disabled', false);
            $('#cd_status').prop('disabled', false);
        });

        // -------------------------------------------------------------------
        // EDIT & DELETE LOGIC
        // -------------------------------------------------------------------
        $(document).on('click', '.edit_deposit_btn', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            
            if (Dropzone.instances.length > 0) {
                Dropzone.instances.forEach(function(instance) {
                    if(instance.element.id === 'editDepositSlipUpload') instance.destroy();
                });
            }

            $.ajax({
                method: 'GET',
                url: url,
                dataType: 'html',
                success: function(result) {
                    $('#edit_deposit_modal').html(result).modal('show');
                }
            });
        });

        $('#edit_deposit_modal').on('shown.bs.modal', function() {
            $('#edit_deposit_datetime').datetimepicker({
                format: moment_date_format + ' HH:mm:ss',
                ignoreReadonly: true,
            });

            $('#edit_deposit_modal .select2').select2();

            $('#search_add_payment').select2({
                dropdownParent: $('#edit_deposit_modal'),
                placeholder: "Type Payment Ref, Invoice No, or Customer Name...",
                minimumInputLength: 1,
                ajax: {
                    url: "{{ route('cash_deposit.search_payments') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            $('#search_add_payment').on('select2:select', function (e) {
                var data = e.params.data;
                var payment_id = data.id;
                var amount = parseFloat(data.amount);
                
                if ($('#row_payment_' + payment_id).length > 0) {
                    toastr.warning('This payment is already in the list.');
                    $('#search_add_payment').val(null).trigger('change');
                    return;
                }

                var newRow = `
                    <tr id="row_payment_${payment_id}">
                        <td style="vertical-align: middle;">
                            <strong style="color: #2196F3">${data.payment_ref_no}</strong><br>
                            <small class="text-muted">${data.invoice_no} - ${data.customer_name}</small>
                        </td>
                        <td class="text-right" style="vertical-align: middle;">
                            <span class="display_currency" data-currency_symbol="true">${__currency_trans_from_en(amount, true)}</span>
                        </td>
                        <td style="width: 30px; text-align: center; vertical-align: middle;">
                            <button type="button" class="btn btn-danger btn-xs remove_payment_btn" 
                                    data-id="${payment_id}" 
                                    data-amount="${amount}"
                                    style="padding: 2px 6px; font-size: 11px;"
                                    title="Remove">
                                <i class="fa fa-times" style="font-size: 16px;"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#edit_invoice_table tbody').prepend(newRow);

                var current_ids = $('#edit_payment_ids').val().split(',').filter(Boolean);
                current_ids.push(payment_id);
                $('#edit_payment_ids').val(current_ids.join(','));

                var current_total = __read_number($('#edit_display_amount'));
                var new_total = current_total + amount;
                $('#edit_display_amount').val(__currency_trans_from_en(new_total, true));

                $('#search_add_payment').val(null).trigger('change');
                toastr.success('Payment added');
            });

            var editDropzone = new Dropzone("#editDepositSlipUpload", {
                url: "{{ route('cash_deposit.upload_slip') }}",
                paramName: "file",
                uploadMultiple: false,
                maxFilesize: 5,
                acceptedFiles: "image/*,application/pdf",
                addRemoveLinks: true,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(file, response) {
                    if (response.success) {
                        file.upload_success_name = response.file_name;
                        var current = $('#edit_deposit_slip_names').val();
                        var arr = current ? current.split(',') : [];
                        arr.push(response.file_name);
                        $('#edit_deposit_slip_names').val(arr.join(','));
                    } else {
                        toastr.error(response.msg);
                        this.removeFile(file);
                    }
                },
                removedfile: function(file) {
                    if (file.upload_success_name) {
                        var current = $('#edit_deposit_slip_names').val();
                        var arr = current.split(',').filter(item => item !== file.upload_success_name);
                        $('#edit_deposit_slip_names').val(arr.join(','));
                    }
                    if (file.previewElement != null && file.previewElement.parentNode != null) {
                        file.previewElement.parentNode.removeChild(file.previewElement);
                    }
                }
            });
        });

        $(document).on('click', '.remove_payment_btn', function() {
            var row_id = $(this).data('id');
            var amount = parseFloat($(this).data('amount'));
            
            $('#row_payment_' + row_id).remove();

            var current_ids = $('#edit_payment_ids').val().split(',').filter(Boolean);
            var new_ids = current_ids.filter(function(id) { return id != row_id; });
            $('#edit_payment_ids').val(new_ids.join(','));

            var current_total = __read_number($('#edit_display_amount'));
            var new_total = current_total - amount;
            $('#edit_display_amount').val(__currency_trans_from_en(new_total, true));
        });

        $(document).on('click', '.delete_media_btn', function() {
            var url = $(this).data('href');
            var media_id = $(this).data('id');

            swal({
                title: LANG.sure,
                text: "This file will be permanently deleted.",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        method: 'DELETE',
                        url: url,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                $('#media_item_' + media_id).remove();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        });

        $(document).on('submit', '#edit_deposit_form', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = $('#update_deposit_btn');

            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                data: form.serialize(),
                beforeSend: function() {
                    btn.attr('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
                },
                success: function(result) {
                    if (result.success) {
                        $('#edit_deposit_modal').modal('hide');
                        toastr.success(result.msg);
                        if(typeof deposit_history_table !== 'undefined') deposit_history_table.ajax.reload();
                        if(typeof cash_invoice_table !== 'undefined') cash_invoice_table.ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                },
                complete: function() {
                    btn.attr('disabled', false).html('<i class="fa fa-save"></i> Update Changes');
                }
            });
        });

        $(document).on('click', '.delete_deposit_btn', function(e) {
            e.preventDefault();
            var url = $(this).data('href');

            swal({
                title: LANG.sure,
                text: "Delete this deposit? Invoices will return to Pending status.",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        method: 'DELETE',
                        url: url,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                if(typeof deposit_history_table !== 'undefined') deposit_history_table.ajax.reload();
                                if(typeof cash_invoice_table !== 'undefined') cash_invoice_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        });

        $(document).on('click', '.view-attachments', function() {
            var depositId = $(this).data('deposit-id');
            $.ajax({
                url: '/cash-deposit/' + depositId + '/attachments',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#attachment_ref_no').text(response.ref_no);
                        var html = '';
                        if (response.attachments && response.attachments.length > 0) {
                            html += '<div class="row">';
                            response.attachments.forEach(function(attachment, index) {
                                html += '<div class="col-md-6 col-sm-12" style="margin-bottom: 20px;">';
                                html += '<div class="panel panel-default">';
                                html += '<div class="panel-heading"><strong>Attachment ' + (index + 1) + '</strong></div>';
                                html += '<div class="panel-body text-center">';
                                if (attachment.is_image) {
                                    html += '<a href="' + attachment.url + '" target="_blank"><img src="' + attachment.url + '" style="max-width: 100%; max-height: 400px; border: 1px solid #ddd; border-radius: 4px;"></a>';
                                } else {
                                    html += '<div style="padding: 40px; background: #f5f5f5; border-radius: 4px;"><i class="fa fa-file-pdf-o fa-5x text-danger"></i><p style="margin-top: 10px;">' + attachment.display_name + '</p></div>';
                                }
                                html += '<div style="margin-top: 10px;"><a href="' + attachment.url + '" target="_blank" class="btn btn-primary btn-sm"><i class="fa fa-external-link"></i> Open</a></div>';
                                html += '</div></div></div>';
                            });
                            html += '</div>';
                        } else {
                            html = '<div class="alert alert-info">No attachments found.</div>';
                        }
                        $('#attachments_container').html(html);
                        $('#attachments_modal').modal('show');
                    }
                }
            });
        });

        $(document).on('click', '#select_all_rows', function() {
            var isChecked = $(this).is(':checked');
            $('.deposit_check:visible').prop('checked', isChecked);
            updateDepositButton();
        });
        $(document).on('click', '.deposit_check', function() {
            updateDepositButton();
            var total = $('.deposit_check:visible').length;
            var checked = $('.deposit_check:checked').length;
            $('#select_all_rows').prop('checked', total === checked && total > 0);
        });

        function updateDepositButton() {
            var count = $('.deposit_check:checked').length;
            $('#selected_count').text(count);
            if (count > 0) {
                $('#btn_deposit_selected').removeClass('btn-default').addClass('btn-success').prop('disabled', false);
            } else {
                $('#btn_deposit_selected').removeClass('btn-success').addClass('btn-default').prop('disabled', true);
            }
        }

        $(document).on('click', '#btn_deposit_selected', function() {
            var selectedPayments = [];
            var totalAmount = 0;
            $('.deposit_check:checked').each(function() {
                selectedPayments.push($(this).val());
                totalAmount += parseFloat($(this).data('amount'));
            });

            if (selectedPayments.length === 0) {
                toastr.error('Please select at least one payment');
                return;
            }

            $('#payment_ids').val(selectedPayments.join(','));
            $('#total_amount').val(__currency_trans_from_en(totalAmount, false));
            $('#display_total_amount').text(__currency_trans_from_en(totalAmount, true));
            $('#invoice_count').text(selectedPayments.length);
            $('#deposit_datetime').data("DateTimePicker").date(moment());
            $('#bank_account_id').val('').trigger('change');
            $('#ref_no').val('');
            if (depositSlipDropzone) depositSlipDropzone.removeAllFiles();
            $('#deposit_slip_names').val('');
            $('#deposit_modal').modal('show');
        });

        $('#deposit_modal').on('shown.bs.modal', function () {
            $('#bank_account_id').select2({ dropdownParent: $('#deposit_modal') });
            if (!depositSlipDropzone) {
                depositSlipDropzone = new Dropzone("#depositSlipUpload", {
                    url: "{{ route('cash_deposit.upload_slip') }}",
                    paramName: "file",
                    uploadMultiple: false,
                    maxFilesize: 5,
                    acceptedFiles: "image/*,application/pdf",
                    addRemoveLinks: true,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(file, response) {
                        if (response.success) {
                            file.upload_success_name = response.file_name;
                            var current_files = $('#deposit_slip_names').val();
                            var files_array = current_files ? current_files.split(',').filter(Boolean) : [];
                            files_array.push(response.file_name);
                            $('#deposit_slip_names').val(files_array.join(','));
                            toastr.success(response.msg);
                        } else {
                            toastr.error(response.msg);
                            this.removeFile(file);
                        }
                    },
                    removedfile: function(file) {
                        if (file.upload_success_name) {
                            var current_files = $('#deposit_slip_names').val();
                            var files_array = current_files.split(',').filter(Boolean);
                            var index = files_array.indexOf(file.upload_success_name);
                            if (index > -1) files_array.splice(index, 1);
                            $('#deposit_slip_names').val(files_array.join(','));
                        }
                        if (file.previewElement && file.previewElement.parentNode) {
                            file.previewElement.parentNode.removeChild(file.previewElement);
                        }
                    }
                });
            }
        });

        $(document).on('submit', '#deposit_form', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                method: 'POST',
                url: "{{ route('cash_deposit.store') }}",
                dataType: 'json',
                data: formData,
                beforeSend: function() {
                    $('#submit_deposit').attr('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
                },
                success: function(result) {
                    if (result.success) {
                        $('#deposit_modal').modal('hide');
                        toastr.success(result.msg);
                        cash_invoice_table.ajax.reload();
                        if (deposit_history_table) deposit_history_table.ajax.reload();
                        $('.deposit_check').prop('checked', false);
                        $('#select_all_rows').prop('checked', false);
                        updateDepositButton();
                    } else {
                        toastr.error(result.msg);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred');
                },
                complete: function() {
                    $('#submit_deposit').attr('disabled', false).html('<i class="fa fa-check"></i> Submit Deposit');
                }
            });
        });

        $('#deposit_modal').on('hidden.bs.modal', function () {
            $('#deposit_form')[0].reset();
            $('#bank_account_id').val('').trigger('change');
            if (depositSlipDropzone) depositSlipDropzone.removeAllFiles();
            $('#deposit_slip_names').val('');
        });
    });
</script>
@endsection