<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            <h4 class="modal-title">@lang('Add New Address')</h4>
        </div>
        <div class="modal-body">
            {!! Form::open([
                'url' => isset($shipping_address) 
                    ? action([\App\Http\Controllers\ContactController::class, 'updateShippingAddress'], [$shipping_address->id]) 
                    : action([\App\Http\Controllers\ContactController::class, 'storeShippingAddress']), 
                'method' => isset($shipping_address) ? 'PUT' : 'POST', 
                'id' => 'shipping_address_form'
            ]) !!}
            
            {!! Form::hidden('contact_id', $contact->id) !!}
            {!! Form::hidden('business_id', $contact->business_id) !!}

            <div class="form-group">
                {!! Form::label('label_shipping_id', __('Label As')) !!}
                {!! Form::select('label_shipping_id', $labels, isset($shipping_address) ? $shipping_address->label_shipping_id : $default_label_id, [
                    'class' => 'form-control',
                    'required',
                    'id' => 'label_shipping_id'
                ]) !!}
                <a href="javascript:void(0)" id="add_new_label_btn" style="margin-top: 10px; display: inline-block;">
                    <i class="fa fa-plus"></i> @lang('Add New Label')
                </a>
                <div id="new_label_container" style="display: none; margin-top: 10px;">
                    {!! Form::text('new_label', null, [
                        'class' => 'form-control',
                        'id' => 'new_label_input',
                        'placeholder' => __('Enter new label')
                    ]) !!}
                    <button type="button" class="btn btn-primary" id="save_new_label_btn" style="margin-top: 10px;">
                        @lang('messages.save')
                    </button>
                </div>
            </div>

            <div class="form-group">
                {!! Form::label('mobile', __('contact.mobile')) !!}
                {!! Form::text('mobile', isset($shipping_address) ? $shipping_address->mobile : null, [
                    'class' => 'form-control',
                    'required',
                    'placeholder' => __('Enter mobile number')
                ]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('address', __('business.address')) !!}
                {!! Form::textarea('address', isset($shipping_address) ? $shipping_address->address : null, [
                    'class' => 'form-control',
                    'required',
                    'placeholder' => __('Enter Address'),
                    'rows' => 3
                ]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('map_link', __('Map Link')) !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-map-marker"></i></span>
                    {!! Form::text('map_link', isset($shipping_address) ? $shipping_address->map : null, [
                        'class' => 'form-control',
                        'placeholder' => __('Map Link')
                    ]) !!}
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="submit" class="btn btn-primary">@lang('messages.save')</button>
            </div>

            {!! Form::close() !!}
        </div>
    </div>
</div>