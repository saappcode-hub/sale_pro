@extends('layouts.app')

@section('title', __('currency'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('currency')
        <small>@lang('currency')</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#tab_1" data-toggle="tab" aria-expanded="true">@lang('currency')</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_1">
                        <div class="row">
                            <div class="col-md-12">
                                <h4>@lang('all currency') 
                                    <button type="button" class="btn btn-primary btn-modal pull-right" 
                                        data-href="{{ action([\App\Http\Controllers\CurrencyController::class, 'create']) }}" 
                                        data-container=".currency_modal">
                                        <i class="fa fa-plus"></i> @lang('messages.add')
                                    </button>
                                </h4>
                            </div>
                        </div>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="currency_table">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Currency</th>
                                        <th>Code</th>
                                        <th>Symbol</th>
                                        <th>Exchange Rate</th>
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categories as $category)
                                    <tr>
                                        <td>{{ $category['country'] }}</td>
                                        <td>{{ $category['currency'] }}</td>
                                        <td>{{ $category['code'] }}</td>
                                        <td>{{ $category['symbol'] }}</td>
                                        <td>{{ $category['exchange_rate'] ?: 0 }}</td>
                                        <td>
                                            <button type="button" data-href="{{ action([\App\Http\Controllers\CurrencyController::class, 'edit'], [$category['id']]) }}" class="btn btn-xs btn-primary btn-modal" data-container=".currency_edit_modal">
                                                <i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")
                                            </button>
                                            <button type="button" data-href="{{ action([\App\Http\Controllers\CurrencyController::class, 'destroy'], [$category['id']]) }}" class="btn btn-xs btn-danger delete_currency_button">
                                                <i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade currency_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade currency_edit_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

</section>
<!-- /.content -->
@endsection
@section('javascript')
<script type="text/javascript">
$(document).on('submit', '#currency_add_form', function(e) {
    e.preventDefault();
    var form = $(this);
    $.ajax({
        url: form.attr('action'),
        method: form.attr('method'),
        data: form.serialize(),
        success: function(response) {
            if (response.success) {
                // Check for refresh flag and reload the page
                if (response.refresh) {
                    location.reload();
                }
                // Close the modal and show success message
                $('.currency_modal').modal('hide');
                $('.currency_edit_modal').modal('hide');
                toastr.success(response.msg);
            } else {
                toastr.error(response.msg);
            }
        },
        error: function() {
            toastr.error('An error occurred.');
        }
    });
});
</script>