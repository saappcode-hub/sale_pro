<div class="row">
    <div class="col-md-12">
        <button type="button" class="btn btn-primary pull-right shipping_address_btn" 
                data-href="{{ action([\App\Http\Controllers\ContactController::class, 'getShippingAddressForm'], [$contact->id]) }}">
            <i class="fa fa-plus"></i> @lang('messages.add')
        </button>
    </div>
</div>
<br>
<div class="row">
    <div class="col-md-12">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="shipping_address_table" width="100%">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('lang_v1.label')</th>
                        <th>@lang('contact.mobile')</th>
                        <th>@lang('business.address')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit -->
<div class="modal fade shipping_address_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>