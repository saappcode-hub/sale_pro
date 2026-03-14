@extends('layouts.app')
@section('title', __('Void Sell'))

@section('content')

<section class="content-header no-print">
    <h1>@lang('Void Sell')</h1>
</section>

<section class="content no-print">
    <div class="box box-primary collapsed-box" id="filters_box">
        <div class="box-header with-border" style="cursor: pointer;" id="filters_header">
            <h3 class="box-title">@lang('report.filters')</h3>
        </div>
        <div class="box-body" style="display: none;" id="filters_content">
            <div class="row">
                @include('sell.partials.sell_list_filters')
                @if(!empty($sources))
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('sell_list_filter_source',  __('lang_v1.sources') . ':') !!}
                            {!! Form::select('sell_list_filter_source', $sources, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all') ]); !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    @component('components.widget', ['class' => 'box-primary', 'title' => __('Void Sell List')])
        <table class="table table-bordered table-striped ajax_view" id="avoid_sell_table">
            <thead>
                <tr>
                    <th>@lang('messages.action')</th>
                    <th>@lang('messages.date')</th>
                    <th>Order No</th>
                    <th>@lang('sale.invoice_no')</th>
                    <th>@lang('sale.customer_name')</th>
                    <th>@lang('lang_v1.contact_no')</th>
                    <th>@lang('sale.location')</th>
                    <th>@lang('sale.payment_status')</th>
                    <th>@lang('lang_v1.payment_method')</th>
                    <th>@lang('sale.total_amount')</th>
                    <th>@lang('sale.total_paid')</th>
                    <th>@lang('lang_v1.sell_due')</th>
                    <th>@lang('lang_v1.sell_return_due')</th>
                    <th>@lang('Sell Status')</th>
                    <th>Delivery Person</th>
                    <th>@lang('lang_v1.shipping_status')</th>
                    <th>@lang('lang_v1.total_items')</th>
                    <th>@lang('lang_v1.types_of_service')</th>
                    <th>{{ $custom_labels['types_of_service']['custom_field_1'] ?? __('lang_v1.service_custom_field_1' )}}</th>
                    <th>{{ $custom_labels['sell']['custom_field_1'] ?? '' }}</th>
                    <th>{{ $custom_labels['sell']['custom_field_2'] ?? ''}}</th>
                    <th>{{ $custom_labels['sell']['custom_field_3'] ?? ''}}</th>
                    <th>{{ $custom_labels['sell']['custom_field_4'] ?? ''}}</th>
                    <th>@lang('lang_v1.added_by')</th>
                    <th>@lang('sale.sell_note')</th>
                    <th>@lang('sale.staff_note')</th>
                    <th>@lang('sale.shipping_details')</th>
                    <th>@lang('restaurant.table')</th>
                    <th>@lang('restaurant.service_staff')</th>
                </tr>
            </thead>
            <tfoot>
                <tr class="bg-gray font-17 footer-total text-center">
                    <td colspan="7"><strong>@lang('sale.total'):</strong></td>
                    <td class="footer_payment_status_count"></td>
                    <td class="payment_method_count"></td>
                    <td class="footer_sale_total"></td>
                    <td class="footer_total_paid"></td>
                    <td class="footer_total_remaining"></td>
                    <td class="footer_total_sell_return_due"></td>
                    <td colspan="16"></td>
                </tr>
            </tfoot>
        </table>
    @endcomponent

    {{-- Modal for viewing transaction details --}}
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog"></div>

</section>
@stop

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    $('#filters_header').click(function() {
        var $filtersContent = $('#filters_content');
        var $filtersBox = $('#filters_box');
        
        if ($filtersContent.is(':visible')) {
            $filtersContent.slideUp();
            $filtersBox.addClass('collapsed-box');
        } else {
            $filtersContent.slideDown();
            $filtersBox.removeClass('collapsed-box');
        }
    });

    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            avoid_sell_table.ajax.reload();
        }
    );
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        avoid_sell_table.ajax.reload();
    });

    var avoid_sell_table = $('#avoid_sell_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        "ajax": {
            "url": "{{ action([\App\Http\Controllers\SellController::class, 'avoidSellIndex']) }}",
            "data": function (d) {
                if($('#sell_list_filter_date_range').val()) {
                    var start = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }
                d.location_id = $('#sell_list_filter_location_id').val();
                d.customer_id = $('#sell_list_filter_customer_id').val();
                d.payment_status = $('#sell_list_filter_payment_status').val();
                d.sale_status = $('#sell_list_filter_sale_status').val();
                d.created_by = $('#created_by').val();
                d.sales_cmsn_agnt = $('#sales_cmsn_agnt').val();
                d.service_staffs = $('#service_staffs').val();

                if($('#shipping_status').length) {
                    d.shipping_status = $('#shipping_status').val();
                }
                if($('#sell_list_filter_source').length) {
                    d.source = $('#sell_list_filter_source').val();
                }
                d = __datatable_ajax_callback(d);
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, "searchable": false},
            { data: 'transaction_date', name: 'transactions.transaction_date'  },
            { data: 'order_no', name: 'transactions.ref_no' },
            { data: 'invoice_no', name: 'transactions.invoice_no'},
            { data: 'conatct_name', name: 'contacts.name'},
            { data: 'mobile', name: 'contacts.mobile'},
            { data: 'business_location', name: 'bl.name'},
            { data: 'payment_status', name: 'transactions.payment_status'},
            { data: 'payment_methods', orderable: false, "searchable": false},
            { data: 'final_total', name: 'transactions.final_total'},
            { data: 'total_paid', name: 'total_paid', "searchable": false},
            { data: 'total_remaining', name: 'total_remaining'},
            { data: 'return_due', orderable: false, "searchable": false},
            { data: 'sell_status', name: 'transactions.status', orderable: false, "searchable": false},
            { data: 'delivery_person_name', name: 'dp.first_name', "searchable": false},
            { data: 'shipping_status', name: 'transactions.shipping_status'},
            { data: 'total_items', name: 'total_items', "searchable": false},
            { data: 'types_of_service_name', name: 'tos.name', @if(empty($is_types_service_enabled)) visible: false @endif},
            { data: 'service_custom_field_1', name: 'transactions.service_custom_field_1', @if(empty($is_types_service_enabled)) visible: false @endif},
            { data: 'custom_field_1', name: 'transactions.custom_field_1', @if(empty($custom_labels['sell']['custom_field_1'])) visible: false @endif},
            { data: 'custom_field_2', name: 'transactions.custom_field_2', @if(empty($custom_labels['sell']['custom_field_2'])) visible: false @endif},
            { data: 'custom_field_3', name: 'transactions.custom_field_3', @if(empty($custom_labels['sell']['custom_field_3'])) visible: false @endif},
            { data: 'custom_field_4', name: 'transactions.custom_field_4', @if(empty($custom_labels['sell']['custom_field_4'])) visible: false @endif},
            { data: 'added_by', name: 'u.first_name'},
            { data: 'additional_notes', name: 'transactions.additional_notes'},
            { data: 'staff_note', name: 'transactions.staff_note'},
            { data: 'shipping_details', name: 'transactions.shipping_details'},
            { data: 'table_name', name: 'tables.name', @if(empty($is_tables_enabled)) visible: false @endif },
            { data: 'waiter', name: 'ss.first_name', @if(empty($is_service_staff_enabled)) visible: false @endif },
        ],
        "fnDrawCallback": function (oSettings) {
            __currency_convert_recursively($('#avoid_sell_table'));
            initializeRowClickHandler(); // Re-apply click handlers on table draw
        },
        "footerCallback": function ( row, data, start, end, display ) {
            var footer_sale_total = 0, footer_total_paid = 0, footer_total_remaining = 0, footer_total_sell_return_due = 0;
            for (var r in data){
                footer_sale_total += $(data[r].final_total).data('orig-value') ? parseFloat($(data[r].final_total).data('orig-value')) : 0;
                footer_total_paid += $(data[r].total_paid).data('orig-value') ? parseFloat($(data[r].total_paid).data('orig-value')) : 0;
                footer_total_remaining += $(data[r].total_remaining).data('orig-value') ? parseFloat($(data[r].total_remaining).data('orig-value')) : 0;
                footer_total_sell_return_due += $(data[r].return_due).find('.sell_return_due').data('orig-value') ? parseFloat($(data[r].return_due).find('.sell_return_due').data('orig-value')) : 0;
            }
            $('.footer_total_sell_return_due').html(__currency_trans_from_en(footer_total_sell_return_due));
            $('.footer_total_remaining').html(__currency_trans_from_en(footer_total_remaining));
            $('.footer_total_paid').html(__currency_trans_from_en(footer_total_paid));
            $('.footer_sale_total').html(__currency_trans_from_en(footer_sale_total));
            $('.footer_payment_status_count').html(__count_status(data, 'payment_status'));
            $('.service_type_count').html(__count_status(data, 'types_of_service_name'));
            $('.payment_method_count').html(__count_status(data, 'payment_methods'));
        },
        // ## ADDED THIS TO MAKE ROWS CLICKABLE ##
        createdRow: function( row, data, dataIndex ) {
            $(row).attr('data-transaction-id', data.id);
            $(row).addClass('clickable-row');
        }
    });

    $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #sell_list_filter_payment_status, #sell_list_filter_sale_status, #created_by, #sales_cmsn_agnt, #service_staffs, #shipping_status, #sell_list_filter_source',  function() {
        avoid_sell_table.ajax.reload();
    });

    // Initialize the click handler
    initializeRowClickHandler();
});

// ## ADDED THIS FUNCTION TO HANDLE ROW CLICKS ##
function initializeRowClickHandler() {
    $('#avoid_sell_table tbody').off('click', 'tr.clickable-row');
    
    $('#avoid_sell_table tbody').on('click', 'tr.clickable-row', function(e) {
        // Don't trigger if clicking on the action button itself
        if ($(e.target).closest('.btn-group, button').length) {
            return;
        }
        
        var transactionId = $(this).data('transaction-id');
        if (transactionId) {
            showTransactionDetails(transactionId);
        }
    });
}

// ## ADDED THIS FUNCTION TO SHOW THE TRANSACTION POPUP ##
function showTransactionDetails(transactionId) {
    $.ajax({
        url: '/sells/' + transactionId,
        method: 'GET',
        success: function(response) {
            $('#transactionDetailsModal').html(response).modal('show');
            __currency_convert_recursively($('#transactionDetailsModal'));
        },
        error: function(xhr) {
            var errorMessage = 'Failed to load transaction details.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            var errorContent = `
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button><h4 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> Error</h4></div><div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-danger"></i><p class="text-danger" style="margin-top: 15px;">${errorMessage}</p></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div>
                </div>`;
            $('#transactionDetailsModal').html(errorContent).modal('show');
        }
    });
}
</script>

{{-- ## ADDED THIS CSS FOR HOVER EFFECT ## --}}
<style>
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .clickable-row:hover {
        background-color: #f5f5f5 !important;
    }
</style>
@endsection