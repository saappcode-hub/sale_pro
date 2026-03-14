@extends('layouts.app')
@section('title', __('lang_v1.sell_payment_report'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>{{ __('lang_v1.sell_payment_report')}}</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="row">
        <div class="col-md-12">
           @component('components.filters', ['title' => __('report.filters')])
              {!! Form::open(['url' => '#', 'method' => 'get', 'id' => 'sell_payment_report_form' ]) !!}
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-user"></i>
                            </span>
                            {!! Form::select('customer_id', $customers, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'required']); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location').':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::select('location_id', $business_locations, $selected_location ?? null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'required']); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_types', __('lang_v1.payment_method').':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fas fa-money-bill-alt"></i>
                            </span>
                            {!! Form::select('payment_types', $payment_types, null, ['class' => 'form-control select2', 'placeholder' => __('messages.all'), 'required']); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('customer_group_filter', __('lang_v1.customer_group').':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-users"></i>
                            </span>
                            {!! Form::select('customer_group_filter', $customer_groups, null, ['class' => 'form-control select2']); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('spr_date_filter', __('report.date_range') . ':') !!}
                        {!! Form::text('date_range', $selected_date_range ?? null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'spr_date_filter', 'readonly']); !!}
                    </div>
                </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" 
                    id="sell_payment_report_table">
                        <thead>
                            <tr>
                                <th>&nbsp;</th>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('Invoice Create Date')</th>
                                <th>@lang('lang_v1.paid_on')</th>
                                <th>@lang('sale.sale')</th>
                                <th>@lang('contact.customer')</th>
                                <th>@lang('lang_v1.customer_group')</th>
                                <th>@lang('lang_v1.payment_method')</th>
                                <th>@lang('Total Amount')</th>
                                <th>@lang('Amount Paid')</th>
                                <th>Amount Percentage</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                     <tfoot>
                        <tr class="bg-gray font-17 footer-total text-center">
                            <td colspan="8"><strong>@lang('sale.total'):</strong></td>
                            <td><span class="display_currency" id="footer_total_invoice_amount" data-currency_symbol ="true"></span></td>
                            <td><span class="display_currency" id="footer_total_amount" data-currency_symbol ="true"></span></td>
                            <td><span class="display_currency" id="footer_total_amount_percentage" data-currency_symbol ="true"></span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->
<div class="modal fade view_register" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

@endsection

@section('javascript')
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            // Check if we have URL parameters for filtering
            var urlParams = new URLSearchParams(window.location.search);
            var paidOnDate = urlParams.get('paid_on');
            var locationId = urlParams.get('sell_list_filter_location_id');
            
            // Function to update date range picker
            function updateDateRangePicker(dateStr) {
                var targetDate = moment(dateStr);
                var dateFormat = moment_date_format || 'MM/DD/YYYY';
                var formattedDate = targetDate.format(dateFormat);
                var dateRangeValue = formattedDate + ' ~ ' + formattedDate;
                
                console.log('Updating date range to:', dateRangeValue);
                
                // Update the input value
                $('#spr_date_filter').val(dateRangeValue);
                
                // Wait for daterangepicker to be initialized, then update it
                var attempts = 0;
                var checkDatePicker = setInterval(function() {
                    attempts++;
                    if ($('#spr_date_filter').data('daterangepicker')) {
                        $('#spr_date_filter').data('daterangepicker').setStartDate(targetDate);
                        $('#spr_date_filter').data('daterangepicker').setEndDate(targetDate);
                        $('#spr_date_filter').val(dateRangeValue); // Set again after daterangepicker update
                        console.log('Date range picker updated successfully');
                        clearInterval(checkDatePicker);
                    } else if (attempts > 50) { // Stop after 5 seconds
                        console.log('Could not find daterangepicker, setting input value only');
                        clearInterval(checkDatePicker);
                    }
                }, 100);
            }
            
            // If we have parameters from daily payment report, update filters
            if (paidOnDate && locationId) {
                console.log('Setting filters - Date:', paidOnDate, 'Location:', locationId);
                
                // Set the location dropdown
                $('#location_id').val(locationId).trigger('change');
                
                // Update the date range after a short delay to ensure elements are ready
                setTimeout(function() {
                    updateDateRangePicker(paidOnDate);
                }, 1000);
                
                // Trigger the table reload to apply filters
                setTimeout(function() {
                    if (typeof sell_payment_report !== 'undefined') {
                        console.log('Reloading sell payment report table');
                        sell_payment_report.ajax.reload();
                    }
                }, 1500);
            }
        });
    </script>
@endsection