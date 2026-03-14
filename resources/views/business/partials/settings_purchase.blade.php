<div class="pos-tab-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- ========================================================== --}}
    {{-- ORIGINAL SETTINGS SECTION                                  --}}
    {{-- ========================================================== --}}
    <div class="row">
        @if(!config('constants.disable_purchase_in_other_currency', true))
        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                    {!! Form::checkbox('purchase_in_diff_currency', 1, $business->purchase_in_diff_currency , 
                    [ 'class' => 'input-icheck', 'id' => 'purchase_in_diff_currency']); !!} {{ __( 'purchase.allow_purchase_different_currency' ) }}
                    </label>
                  @show_tooltip(__('tooltip.purchase_different_currency'))
                </div>
            </div>
        </div>
        <div class="col-sm-4 @if($business->purchase_in_diff_currency != 1) hide @endif" id="settings_purchase_currency_div">
            <div class="form-group">
                {!! Form::label('purchase_currency_id', __('purchase.purchase_currency') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::select('purchase_currency_id', $currencies, $business->purchase_currency_id, ['class' => 'form-control select2', 'placeholder' => __('business.currency'), 'required', 'style' => 'width:100% !important']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4 @if($business->purchase_in_diff_currency != 1) hide @endif" id="settings_currency_exchange_div">
            <div class="form-group">
                {!! Form::label('p_exchange_rate', __('purchase.p_exchange_rate') . ':') !!}
                @show_tooltip(__('tooltip.currency_exchange_factor'))
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-info"></i>
                    </span>
                    {!! Form::number('p_exchange_rate', $business->p_exchange_rate, ['class' => 'form-control', 'placeholder' => __('business.p_exchange_rate'), 'required', 'step' => '0.001']); !!}
                </div>
            </div>
        </div>
        @endif
        <div class="clearfix"></div>
        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                  <label>
                    {!! Form::checkbox('enable_editing_product_from_purchase', 1, $business->enable_editing_product_from_purchase , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_editing_product_from_purchase' ) }}
                  </label>
                  @show_tooltip(__('lang_v1.enable_updating_product_price_tooltip'))
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                    {!! Form::checkbox('enable_purchase_status', 1, $business->enable_purchase_status , [ 'class' => 'input-icheck', 'id' => 'enable_purchase_status']); !!} {{ __( 'lang_v1.enable_purchase_status' ) }}
                    </label>
                  @show_tooltip(__('lang_v1.tooltip_enable_purchase_status'))
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                    {!! Form::checkbox('enable_lot_number', 1, $business->enable_lot_number , [ 'class' => 'input-icheck', 'id' => 'enable_lot_number']); !!} {{ __( 'lang_v1.enable_lot_number' ) }}
                    </label>
                  @show_tooltip(__('lang_v1.tooltip_enable_lot_number'))
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                    {!! Form::checkbox('common_settings[enable_purchase_order]', 1, !empty($common_settings['enable_purchase_order']) , [ 'class' => 'input-icheck', 'id' => 'enable_purchase_order']); !!} {{ __( 'lang_v1.enable_purchase_order' ) }}
                    </label>
                  @show_tooltip(__('lang_v1.purchase_order_help_text'))
                </div>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                    {!! Form::checkbox('common_settings[enable_purchase_requisition]', 1, !empty($common_settings['enable_purchase_requisition']) , [ 'class' => 'input-icheck', 'id' => 'enable_purchase_requisition']); !!} {{ __( 'lang_v1.enable_purchase_requisition' ) }}
                    </label>
                  @show_tooltip(__('lang_v1.purchase_requisition_help_text'))
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('enable_purchase_type', 1, $business->enable_purchase_type , [ 'class' => 'input-icheck', 'id' => 'enable_purchase_type']); !!} Enable Purchase Type
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================== --}}
    {{-- PURCHASE TYPE MANAGEMENT SECTION                           --}}
    {{-- ========================================================== --}}
    <div id="purchase_type_container" class="@if(!$business->enable_purchase_type) hide @endif">
        <div class="col-md-12">
            <hr>
            <h5 style="margin-bottom: 10px;">Purchase Type Management</h5>
        </div>
        
        {{-- Add New Purchase Type Input --}}
        <div class="col-md-12" style="margin-bottom: 20px;">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <input type="text" class="form-control" id="new_purchase_type_name" placeholder="Enter purchase type name">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        {!! Form::select('new_purchase_type_product_id', [], null, ['class' => 'form-control select2', 'id' => 'new_purchase_type_product_id', 'placeholder' => 'Link Exchange Product (Optional)', 'style' => 'width:100%']); !!}
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary btn-block" id="btn_add_purchase_type">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>
            </div>
        </div>

        {{-- List Container --}}
        <div class="col-md-12">
            <div id="purchase_type_list" class="purchase-type-container">
                {{-- Data will be loaded here via AJAX --}}
            </div>
        </div>
    </div>
</div>

<style>
    /* Container for purchase type items */
    .purchase-type-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    /* Individual purchase type badge/pill */
    .purchase-type-item {
        display: inline-flex;
        align-items: flex-start;
        justify-content: space-between;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
        min-width: 280px;
        max-width: 350px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .purchase-type-item.editing {
        background-color: #fff;
        border-color: #0066cc;
        box-shadow: 0 2px 8px rgba(0,102,204,0.2);
        flex-direction: column;
    }

    .purchase-type-content {
        flex-grow: 1;
        width: 100%;
        margin-right: 10px;
    }

    .purchase-type-name {
        color: #333;
        font-weight: 600;
        display: block;
        word-break: break-word;
    }
    
    .purchase-type-name small {
        display: block;
        margin-top: 4px;
        font-weight: normal;
        color: #007bff;
    }

    /* Edit fields container */
    .edit-fields {
        width: 100%;
    }
    
    .edit-fields.hide {
        display: none;
    }

    .purchase-type-input {
        width: 100%;
        padding: 6px;
        border: 1px solid #ccc;
        border-radius: 3px;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .purchase-type-actions {
        display: flex;
        flex-direction: column;
        gap: 5px;
        justify-content: center;
    }
    
    .purchase-type-item.editing .purchase-type-actions {
        flex-direction: row;
        margin-top: 10px;
        width: 100%;
        justify-content: flex-end;
    }

    /* Action Buttons */
    .purchase-type-actions button {
        background: none !important;
        border: none !important;
        padding: 4px !important;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-purchase-type-edit { color: #007bff !important; }
    .btn-purchase-type-delete { color: #dc3545 !important; }
    .btn-purchase-type-save { color: #28a745 !important; }
    .btn-purchase-type-cancelss { color: #6c757d !important; }

    .purchase-type-actions button:hover {
        opacity: 0.8;
        transform: scale(1.1);
    }

    /* Show/Hide Button Logic */
    .btn-purchase-type-save, .btn-purchase-type-cancelss {
        display: none;
    }

    .purchase-type-item.editing .btn-purchase-type-save,
    .purchase-type-item.editing .btn-purchase-type-cancelss {
        display: inline-block;
    }

    .purchase-type-item.editing .btn-purchase-type-edit,
    .purchase-type-item.editing .btn-purchase-type-delete {
        display: none;
    }
</style>