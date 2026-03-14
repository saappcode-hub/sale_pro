<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        @php
            $form_id = 'contact_add_form';
            if (isset($quick_add)) {
                $form_id = 'quick_add_contact';
            }

            if (isset($store_action)) {
                $url = $store_action;
                $type = 'lead';
                $customer_groups = [];
            } else {
                $url = action([\App\Http\Controllers\ContactController::class, 'store']);
                $type = isset($selected_type) ? $selected_type : '';
            }
        @endphp

        {!! Form::open(['url' => $url, 'method' => 'post', 'id' => $form_id]) !!}

        <!-- Modal Header -->
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('contact.add_contact')</h4>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <!-- Main Fields -->
            <div class="row">
                <!-- Contact Type -->
                <div class="col-md-4 contact_type_div">
                    <div class="form-group">
                        {!! Form::label('type', __('contact.contact_type') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-user"></i>
                            </span>
                            {!! Form::select('type', $types, $type, [
                                'class' => 'form-control',
                                'id' => 'contact_type',
                                'placeholder' => __('messages.please_select'),
                                'required'
                            ]) !!}
                        </div>
                    </div>
                </div>

                <!-- Customer Group -->
                <div class="col-md-4 customer_fields">
                    <div class="form-group">
                        {!! Form::label('customer_group_id', __('lang_v1.customer_group') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-users"></i>
                            </span>
                            {!! Form::select('customer_group_id', $customer_groups, $selected_customer_group_id ?? null, [
                                'class' => 'form-control',
                                'placeholder' => __('messages.please_select')
                            ]) !!}
                        </div>
                    </div>
                </div>

                <!-- Individual/Business Radio Buttons -->
                <div class="col-md-4 mt-15">
                    <label class="radio-inline">
                        <input type="radio" name="contact_type_radio" id="inlineRadio1" value="individual" checked>
                        @lang('lang_v1.individual')
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="contact_type_radio" id="inlineRadio2" value="business">
                        @lang('business.business')
                    </label>
                </div>
            </div>
            <div class="row">
                <!-- Full Name (Individual) -->
                <div class="col-md-4 individual" style="display: none;">
                    <div class="form-group">
                        {!! Form::label('first_name', __('Full Name') . ':*') !!}
                        {!! Form::text('first_name', null, [
                            'class' => 'form-control',
                            'required',
                            'placeholder' => __('Full Name')
                        ]) !!}
                    </div>
                </div>

                <!-- Business Name (Business) -->
                <div class="col-md-4 business" style="display: none;">
                    <div class="form-group">
                        {!! Form::label('supplier_business_name', __('business.business_name') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-briefcase"></i>
                            </span>
                            {!! Form::text('supplier_business_name', null, [
                                'class' => 'form-control',
                                'placeholder' => __('business.business_name')
                            ]) !!}
                        </div>
                    </div>
                </div>

                <!-- Mobile -->
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('mobile', __('contact.mobile') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-mobile"></i>
                            </span>
                            {!! Form::text('mobile', null, [
                                'class' => 'form-control',
                                'required',
                                'placeholder' => __('contact.mobile'),
                                'id' => 'mobile'
                            ]) !!}
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('address_line_1', __('Shipping Address') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::text('address_line_1', null, [
                                'class' => 'form-control',
                                'placeholder' => __('Shipping Address')
                            ]) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Assigned To -->
                @if(config('constants.enable_contact_assign') && $type !== 'lead')
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('assigned_to_users', __('lang_v1.assigned_to') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-user"></i>
                                </span>
                                {!! Form::select('assigned_to_users[]', $users ?? [], null, [
                                    'class' => 'form-control select2',
                                    'id' => 'assigned_to_users',
                                    'multiple',
                                    'style' => 'width: 100%;'
                                ]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('contact_id', __('lang_v1.contact_id') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-id-badge"></i>
                                </span>
                                {!! Form::text('contact_id', null, ['class' => 'form-control','placeholder' => __('lang_v1.contact_id')]); !!}
                            </div>
                        </div>
                    </div>
                @endif
                <div class="clearfix"></div>
            </div>

            <!-- More Info Section -->
            <div class="row">
                <div class="col-md-12">
                    <button type="button" class="btn btn-primary center-block more_btn" data-target="#more_div">
                        @lang('lang_v1.more_info') <i class="fa fa-chevron-down"></i>
                    </button>
                </div>

                <div id="more_div" class="hide">
                    {!! Form::hidden('position', null, ['id' => 'position']) !!}
                    <div class="col-md-12"><hr/></div>

                    <!-- Telegram -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field2', 'Telegram' . ':') !!}
                            {!! Form::text('custom_field2', null, [
                                'class' => 'form-control',
                                'placeholder' => 'Telegram'
                            ]) !!}
                        </div>
                    </div>

                    <!-- Alternate Contact Number -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('alternate_number', __('contact.alternate_contact_number') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-phone"></i>
                                </span>
                                {!! Form::text('alternate_number', null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('contact.alternate_contact_number')
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field1', 'Note' . ':') !!}
                            {!! Form::text('custom_field1', null, [
                                'class' => 'form-control',
                                'placeholder' => 'Note'
                            ]) !!}
                        </div>
                    </div>

                    <!-- City/Province -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('province_id', __('City/Province') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-map-marker-alt"></i>
                                </span>
                                {!! Form::select('province_id', $provinces ?? [], null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('Select Province'),
                                    'id' => 'province_ids'
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- District -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('district_id', __('District') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-map-marker-alt"></i>
                                </span>
                                {!! Form::select('district_id', [], null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('Select District'),
                                    'id' => 'district_ids',
                                    'disabled' => true
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Commune -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('commune_id', __('Commune') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-map-marker-alt"></i>
                                </span>
                                {!! Form::select('commune_id', [], null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('Select Commune'),
                                    'id' => 'commune_ids',
                                    'disabled' => true
                                ]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12"><hr/></div>

                    <!-- Tax Number -->
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('tax_number', __('contact.tax_no') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-info"></i>
                                </span>
                                {!! Form::text('tax_number', null, [
                                    'class' => 'form-control',
                                    'placeholder' => __('contact.tax_no')
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Opening Balance -->
                    <div class="col-md-4 opening_balance">
                        <div class="form-group">
                            {!! Form::label('opening_balance', __('lang_v1.opening_balance') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fas fa-money-bill-alt"></i>
                                </span>
                                {!! Form::text('opening_balance', 0, [
                                    'class' => 'form-control input_number'
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Pay Term -->
                    <div class="col-md-4 pay_term">
                        <div class="form-group">
                            <div class="multi-input">
                                {!! Form::label('pay_term_number', __('contact.pay_term') . ':') !!}
                                @show_tooltip(__('tooltip.pay_term'))
                                <br/>
                                {!! Form::number('pay_term_number', null, [
                                    'class' => 'form-control width-40 pull-left',
                                    'placeholder' => __('contact.pay_term')
                                ]) !!}
                                {!! Form::select('pay_term_type', [
                                    'months' => __('lang_v1.months'),
                                    'days' => __('lang_v1.days')
                                ], '', [
                                    'class' => 'form-control width-60 pull-left',
                                    'placeholder' => __('messages.please_select')
                                ]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Credit Limit -->
                    @php
                        $common_settings = session()->get('business.common_settings');
                        $default_credit_limit = !empty($common_settings['default_credit_limit']) ? $common_settings['default_credit_limit'] : null;
                    @endphp
                    <div class="col-md-12 customer_fields">
                        <div class="form-group">
                            {!! Form::label('credit_limit', __('lang_v1.credit_limit') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fas fa-money-bill-alt"></i>
                                </span>
                                {!! Form::text('credit_limit', $default_credit_limit ?? null, [
                                    'class' => 'form-control input_number'
                                ]) !!}
                            </div>
                            <p class="help-block">@lang('lang_v1.credit_limit_help')</p>
                        </div>
                    </div>
                    <div class="col-md-12"><hr/></div>
                    <!-- Custom Fields -->
                    @php
                        $custom_labels = json_decode(session('business.custom_labels'), true);
                        $contact_custom_field5 = !empty($custom_labels['contact']['custom_field_5']) ? $custom_labels['contact']['custom_field_5'] : __('lang_v1.custom_field', ['number' => 5]);
                        $contact_custom_field6 = !empty($custom_labels['contact']['custom_field_6']) ? $custom_labels['contact']['custom_field_6'] : __('lang_v1.custom_field', ['number' => 6]);
                        $contact_custom_field7 = !empty($custom_labels['contact']['custom_field_7']) ? $custom_labels['contact']['custom_field_7'] : __('lang_v1.custom_field', ['number' => 7]);
                        $contact_custom_field8 = !empty($custom_labels['contact']['custom_field_8']) ? $custom_labels['contact']['custom_field_8'] : __('lang_v1.custom_field', ['number' => 8]);
                        $contact_custom_field9 = !empty($custom_labels['contact']['custom_field_9']) ? $custom_labels['contact']['custom_field_9'] : __('lang_v1.custom_field', ['number' => 9]);
                        $contact_custom_field10 = !empty($custom_labels['contact']['custom_field_10']) ? $custom_labels['contact']['custom_field_10'] : __('lang_v1.custom_field', ['number' => 10]);
                    @endphp

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field5', $contact_custom_field5 . ':') !!}
                            {!! Form::text('custom_field5', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field5
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field6', $contact_custom_field6 . ':') !!}
                            {!! Form::text('custom_field6', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field6
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field7', $contact_custom_field7 . ':') !!}
                            {!! Form::text('custom_field7', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field7
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field8', $contact_custom_field8 . ':') !!}
                            {!! Form::text('custom_field8', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field8
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field9', $contact_custom_field9 . ':') !!}
                            {!! Form::text('custom_field9', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field9
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('custom_field10', $contact_custom_field10 . ':') !!}
                            {!! Form::text('custom_field10', null, [
                                'class' => 'form-control',
                                'placeholder' => $contact_custom_field10
                            ]) !!}
                        </div>
                    </div>
                </div>
            </div>

            @include('layouts.partials.module_form_part')
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
            <button type="submit" class="btn btn-primary">@lang('messages.save')</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}
    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->

<script type="text/javascript">
$(document).ready(function() {
    // Province change handler
    $('#province_ids').change(function() {
        const provinceId = $(this).val();

        // Reset district and commune dropdowns
        resetDropdown('#district_ids', 'Select District');
        resetDropdown('#commune_ids', 'Select Commune');

        if (provinceId) {
            // Fetch districts
            $('#district_ids').append('<option>Loading...</option>').attr('disabled', true);
            $.ajax({
                url: '/get-districts/' + provinceId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    $('#district_ids').empty().append('<option selected disabled>Select District</option>');
                    $.each(data, function(key, value) {
                        $('#district_ids').append('<option value="' + key + '">' + value + '</option>');
                    });
                    $('#district_ids').removeAttr('disabled');
                },
                error: function() {
                    alert('Error fetching districts.');
                    resetDropdown('#district_ids', 'Select District');
                }
            });
        }
    });

    // District change handler
    $('#district_ids').change(function() {
        const districtId = $(this).val();

        // Reset commune dropdown
        resetDropdown('#commune_ids', 'Select Commune');

        if (districtId) {
            // Fetch communes
            $('#commune_ids').append('<option>Loading...</option>').attr('disabled', true);
            $.ajax({
                url: '/get-communes/' + districtId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    $('#commune_ids').empty().append('<option selected disabled>Select Commune</option>');
                    $.each(data, function(key, value) {
                        $('#commune_ids').append('<option value="' + key + '">' + value + '</option>');
                    });
                    $('#commune_ids').removeAttr('disabled');
                },
                error: function() {
                    alert('Error fetching communes.');
                    resetDropdown('#commune_ids', 'Select Commune');
                }
            });
        }
    });

    // Utility function to reset dropdown
    function resetDropdown(selector, placeholder) {
        $(selector).empty().append('<option selected disabled>' + placeholder + '</option>').attr('disabled', true);
    }
});
</script>