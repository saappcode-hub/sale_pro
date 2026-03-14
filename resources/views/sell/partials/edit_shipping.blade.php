<div class="modal-dialog" role="document">
    {!! Form::open(['url' => action([\App\Http\Controllers\SellController::class, 'updateShipping'], [$transaction->id]), 'method' => 'put', 'id' => 'edit_shipping_form' ]) !!}
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            <h4 class="modal-title">@lang('lang_v1.edit_shipping') - @if($transaction->type == 'purchase_order') {{$transaction->ref_no}} @else {{$transaction->invoice_no}} @endif</h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('shipping_address_select', __('Select Address') . ':*' ) !!}
                        @if($transaction->contact_id && !empty($shipping_addresses))
                            {!! Form::select('shipping_address_select', $shipping_addresses, $transaction->shipping_address_id ?? $default_address_id, [
                                'class' => 'form-control select2',
                                'placeholder' => __('messages.please_select'),
                                'id' => 'shipping_address_select',
                                'required' => true
                            ]) !!}
                        @else
                            {!! Form::select('shipping_address_select', [], null, [
                                'class' => 'form-control select2',
                                'placeholder' => __('messages.please_select'),
                                'id' => 'shipping_address_select',
                                'disabled' => true
                            ]) !!}
                            <small class="text-danger">@lang('No shipping addresses found for this contact')</small>
                        @endif
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('shipping_address', __('lang_v1.shipping_address') . ':' ) !!}
                        {!! Form::textarea('shipping_address', !empty($transaction->shipping_address) ? $transaction->shipping_address : '', [
                            'class' => 'form-control',
                            'placeholder' => __('lang_v1.shipping_address'),
                            'rows' => '4',
                            'id' => 'shipping_address_display',
                            'disabled' => true
                        ]) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('shipping_status', __('lang_v1.shipping_status') . ':' ) !!}
                        {!! Form::select('shipping_status', $shipping_statuses, !empty($transaction->shipping_status) ? $transaction->shipping_status : null, ['class' => 'form-control', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('delivered_to', __('lang_v1.delivered_to') . ':' ) !!}
                        {!! Form::text('delivered_to', !empty($transaction->delivered_to) ? $transaction->delivered_to : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.delivered_to')]) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('delivery_person', __('lang_v1.delivery_person') . ':' ) !!}
                        {!! Form::select('delivery_person', $users, !empty($transaction->delivery_person) ? $transaction->delivery_person : null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>

                @php
                    $custom_labels = json_decode(session('business.custom_labels'), true);

                    $shipping_custom_label_1 = !empty($custom_labels['shipping']['custom_field_1']) ? $custom_labels['shipping']['custom_field_1'] : '';
                    $is_shipping_custom_field_1_required = !empty($custom_labels['shipping']['is_custom_field_1_required']) && $custom_labels['shipping']['is_custom_field_1_required'] == 1 ? true : false;

                    $shipping_custom_label_2 = !empty($custom_labels['shipping']['custom_field_2']) ? $custom_labels['shipping']['custom_field_2'] : '';
                    $is_shipping_custom_field_2_required = !empty($custom_labels['shipping']['is_custom_field_2_required']) && $custom_labels['shipping']['is_custom_field_2_required'] == 1 ? true : false;

                    $shipping_custom_label_3 = !empty($custom_labels['shipping']['custom_field_3']) ? $custom_labels['shipping']['custom_field_3'] : '';
                    $is_shipping_custom_field_3_required = !empty($custom_labels['shipping']['is_custom_field_3_required']) && $custom_labels['shipping']['is_custom_field_3_required'] == 1 ? true : false;

                    $shipping_custom_label_4 = !empty($custom_labels['shipping']['custom_field_4']) ? $custom_labels['shipping']['custom_field_4'] : '';
                    $is_shipping_custom_field_4_required = !empty($custom_labels['shipping']['is_custom_field_4_required']) && $custom_labels['shipping']['is_custom_field_4_required'] == 1 ? true : false;

                    $shipping_custom_label_5 = !empty($custom_labels['shipping']['custom_field_5']) ? $custom_labels['shipping']['custom_field_5'] : '';
                    $is_shipping_custom_field_5_required = !empty($custom_labels['shipping']['is_custom_field_5_required']) && $custom_labels['shipping']['is_custom_field_5_required'] == 1 ? true : false;
                @endphp

                @if(!empty($shipping_custom_label_1))
                    @php
                        $label_1 = $shipping_custom_label_1 . ':';
                        if($is_shipping_custom_field_1_required) {
                            $label_1 .= '*';
                        }
                    @endphp
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('shipping_custom_field_1', $label_1 ) !!}
                            {!! Form::text('shipping_custom_field_1', !empty($transaction->shipping_custom_field_1) ? $transaction->shipping_custom_field_1 : null, ['class' => 'form-control', 'placeholder' => $shipping_custom_label_1, 'required' => $is_shipping_custom_field_1_required]) !!}
                        </div>
                    </div>
                @endif
                @if(!empty($shipping_custom_label_2))
                    @php
                        $label_2 = $shipping_custom_label_2 . ':';
                        if($is_shipping_custom_field_2_required) {
                            $label_2 .= '*';
                        }
                    @endphp
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('shipping_custom_field_2', $label_2 ) !!}
                            {!! Form::text('shipping_custom_field_2', !empty($transaction->shipping_custom_field_2) ? $transaction->shipping_custom_field_2 : null, ['class' => 'form-control', 'placeholder' => $shipping_custom_label_2, 'required' => $is_shipping_custom_field_2_required]) !!}
                        </div>
                    </div>
                @endif
                @if(!empty($shipping_custom_label_3))
                    @php
                        $label_3 = $shipping_custom_label_3 . ':';
                        if($is_shipping_custom_field_3_required) {
                            $label_3 .= '*';
                        }
                    @endphp
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('shipping_custom_field_3', $label_3 ) !!}
                            {!! Form::text('shipping_custom_field_3', !empty($transaction->shipping_custom_field_3) ? $transaction->shipping_custom_field_3 : null, ['class' => 'form-control', 'placeholder' => $shipping_custom_label_3, 'required' => $is_shipping_custom_field_3_required]) !!}
                        </div>
                    </div>
                @endif
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('shipping_note', __('lang_v1.shipping_note') . ':' ) !!}
                        {!! Form::textarea('shipping_note', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.shipping_note'), 'rows' => '4']) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="fileupload">
                            @lang('lang_v1.shipping_documents'):
                        </label>
                        <div class="dropzone" id="shipping_documents_dropzone"></div>
                        <input type="hidden" id="media_upload_url" value="{{route('attach.medias.to.model')}}">
                        <input type="hidden" id="model_id" value="{{$transaction->id}}">
                        <input type="hidden" id="model_type" value="App\Transaction">
                        <input type="hidden" id="model_media_type" value="shipping_document">
                    </div>
                </div>
                <div class="col-md-12">
                    @php
                        $medias = $transaction->media->where('model_media_type', 'shipping_document')->all();
                    @endphp
                    @include('sell.partials.media_table', ['medias' => $medias, 'delete' => true])
                </div>
            </div>
            @if(!empty($activities))
                <div class="row">
                    <div class="col-md-12">
                        <strong>{{ __('lang_v1.activities') }}:</strong><br>
                        @includeIf('activity_log.activities', ['activity_type' => 'sell'])
                    </div>
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-primary" id="update_shipping_btn" 
                @if($transaction->contact_id && empty($shipping_addresses)) disabled @endif>
                @lang('messages.update')
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
        </div>
    </div>
    {!! Form::close() !!}
</div><!-- /.modal-dialog -->

<script>
    // Declare variables globally to avoid scope issues
    var shippingAddresses = @json($shipping_address_data);
    var hasShippingAddresses = @json(!empty($shipping_addresses));
    var initialAddressId = @json($transaction->shipping_address_id ?? $default_address_id);
    
    function initializeShippingModal() {
        console.log('Initializing shipping modal...', {
            hasAddresses: hasShippingAddresses,
            initialId: initialAddressId,
            addressesData: shippingAddresses
        });

        // Clean up any existing Select2 instances
        if ($('#shipping_address_select').hasClass('select2-hidden-accessible')) {
            $('#shipping_address_select').select2('destroy');
        }

        // Toggle update button based on shipping addresses availability
        toggleUpdateButton();

        // Initialize Select2 only if we have addresses
        if (hasShippingAddresses && shippingAddresses.length > 0) {
            $('#shipping_address_select').select2({
                templateResult: formatOption,
                templateSelection: formatSelection,
                dropdownParent: $('.modal-content'),
                width: '100%'
            });

            // Set initial value and trigger change event
            if (initialAddressId) {
                $('#shipping_address_select').val(initialAddressId).trigger('change.select2');
                updateShippingAddress(initialAddressId);
            }
        }

        // Bind change event
        $('#shipping_address_select').off('change.shipping').on('change.shipping', function() {
            var selectedId = $(this).val();
            console.log('Address changed to:', selectedId);
            updateShippingAddress(selectedId);
        });
    }

    // Function to format the option for the dropdown list
    function formatOption(option) {
        if (!option.id) {
            return option.text;
        }
        var addressData = shippingAddresses.find(function(addr) {
            return addr.id == option.id;
        });
        if (addressData) {
            var html = '<strong>' + (addressData.label || 'Address ' + addressData.id) + '</strong>';
            if (addressData.mobile) {
                html += '<br><small>' + addressData.mobile + '</small>';
            }
            if (addressData.address) {
                html += '<br><small>' + addressData.address + '</small>';
            }
            return $(html);
        }
        return option.text;
    }

    // Function to format the selected option in the dropdown
    function formatSelection(option) {
        if (!option.id) {
            return option.text || 'Please Select';
        }
        var addressData = shippingAddresses.find(function(addr) {
            return addr.id == option.id;
        });
        return addressData ? addressData.label || ('Address ' + addressData.id) : option.text;
    }

    // Function to update the shipping address textarea
    function updateShippingAddress(addressId) {
        console.log('Updating shipping address for ID:', addressId);
        
        if (addressId && shippingAddresses.length > 0) {
            var addressData = shippingAddresses.find(function(addr) {
                return addr.id == addressId;
            });
            
            if (addressData) {
                var mobile = addressData.mobile || '';
                var address = addressData.address || '';
                var fullAddress = '';
                
                if (mobile && address) {
                    fullAddress = mobile + '\n' + address;
                } else if (mobile) {
                    fullAddress = mobile;
                } else if (address) {
                    fullAddress = address;
                }
                
                $('#shipping_address_display').val(fullAddress);
                console.log('Address updated to:', fullAddress);
            } else {
                $('#shipping_address_display').val('');
                console.log('Address data not found for ID:', addressId);
            }
        } else {
            $('#shipping_address_display').val('');
            console.log('No address ID provided or no addresses available');
        }
    }

    // Function to toggle update button based on shipping addresses availability
    function toggleUpdateButton() {
        var updateBtn = $('#update_shipping_btn');
        if (!hasShippingAddresses || shippingAddresses.length === 0) {
            updateBtn.prop('disabled', true);
            updateBtn.attr('title', 'No shipping addresses available for this contact');
        } else {
            updateBtn.prop('disabled', false);
            updateBtn.removeAttr('title');
        }
    }

    $(document).ready(function() {
        // Initialize on document ready
        initializeShippingModal();

        // Handle modal shown event
        $(document).on('shown.bs.modal', '#edit_shipping_modal', function() {
            console.log('Modal shown, reinitializing...');
            setTimeout(function() {
                initializeShippingModal();
            }, 100);
        });

        // Handle modal hidden event to clean up
        $(document).on('hidden.bs.modal', '#edit_shipping_modal', function() {
            console.log('Modal hidden, cleaning up...');
            if ($('#shipping_address_select').hasClass('select2-hidden-accessible')) {
                $('#shipping_address_select').select2('destroy');
            }
            $('#shipping_address_select').off('change.shipping');
        });

        // Handle form submission success to reload modal content if needed
        $('#edit_shipping_form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var formData = form.serialize();
            
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        swal("Success", response.msg, "success");
                        // Close modal
                        $('#edit_shipping_modal').modal('hide');
                        // Optionally reload the page or update the table
                        if (typeof location !== 'undefined') {
                            location.reload();
                        }
                    } else {
                        swal("Error", response.msg, "error");
                    }
                },
                error: function() {
                    swal("Error", "Something went wrong!", "error");
                }
            });
        });
    });
</script>