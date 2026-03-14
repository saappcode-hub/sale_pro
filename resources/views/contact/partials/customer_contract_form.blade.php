<div class="modal-dialog modal-lg" role="document" style="width: 1150px;">
    <div class="modal-content">
        <div class="modal-header" style="background-color: #007bff; color: white; border-bottom: 1px solid #e9ecef;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1;">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title" style="font-weight: 600;">
                @if(isset($customer_contract)) Edit Customer Contract @else Add New Customer Contract @endif
            </h4>
        </div>

        <div class="modal-body" style="background-color: #fff; padding: 20px;">
            {!! Form::open([
                'url' => isset($customer_contract) 
                    ? action([\App\Http\Controllers\ContactController::class, 'updateCustomerContract'], [$customer_contract->id]) 
                    : action([\App\Http\Controllers\ContactController::class, 'storeCustomerContract']), 
                'method' => isset($customer_contract) ? 'PUT' : 'POST', 
                'id' => 'customer_contract_form'
            ]) !!}
            
            {!! Form::hidden('contact_id', $contact->id) !!}

            {{-- Contract Name & Ref No --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('contract_name', 'Contract Name *', ['style' => 'font-weight: 600; color: #555;']) !!}
                        {!! Form::text('contract_name', isset($customer_contract) ? $customer_contract->contract_name : null, [
                            'class' => 'form-control',
                            'required',
                            'placeholder' => 'Enter Contract Name',
                            'style' => 'border-radius: 4px;'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('reference_no', 'Reference No', ['style' => 'font-weight: 600; color: #555;']) !!}
                        {!! Form::text('reference_no', isset($customer_contract) ? $customer_contract->reference_no : null, [
                            'class' => 'form-control',
                            'placeholder' => 'Leave empty to auto-generate (e.g. 00001)',
                            'style' => 'border-radius: 4px;'
                        ]) !!}
                    </div>
                </div>
            </div>

            {{-- Dates --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('start_date', 'Start Date *', ['style' => 'font-weight: 600; color: #555;']) !!}
                        <div class="input-group">
                            {!! Form::text('start_date', isset($customer_contract) ? $customer_contract->start_date->format('Y-m-d') : null, [
                                'class' => 'form-control',
                                'required',
                                'placeholder' => 'YYYY-MM-DD',
                                'id' => 'start_date',
                                'style' => 'border-right: 0;'
                            ]) !!}
                            <span class="input-group-addon" style="background: white; border-left: 0;"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('end_date', 'End Date', ['style' => 'font-weight: 600; color: #555;']) !!}
                        <div class="input-group">
                            {!! Form::text('end_date', isset($customer_contract) && $customer_contract->end_date ? $customer_contract->end_date->format('Y-m-d') : null, [
                                'class' => 'form-control',
                                'placeholder' => 'dd----yyyy',
                                'id' => 'end_date',
                                'style' => 'border-right: 0;'
                            ]) !!}
                            <span class="input-group-addon" style="background: white; border-left: 0;"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="margin: 15px 0; border-top: 1px solid #eee;">

            {{-- Products Search --}}
            <div class="row">
                <div class="col-md-12">
                    <label style="font-weight: 600; color: #555;">Product Targets & Pricing</label>
                    <div class="form-group" style="position: relative;">
                        <span class="fa fa-search" style="position: absolute; left: 10px; top: 10px; color: #777; z-index: 5;"></span>
                        {!! Form::text('search_contract_product', null, [
                            'class' => 'form-control',
                            'id' => 'search_contract_product',
                            'placeholder' => 'Enter Product Name / SKU',
                            'autocomplete' => 'off',
                            'style' => 'padding-left: 30px; border-radius: 4px;',
                            'data-url' => action([\App\Http\Controllers\ContactController::class, 'getProductsForContract'])
                        ]) !!}
                    </div>
                </div>
            </div>

            {{-- Products Table --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="table-responsive" style="border: 1px solid #e9ecef; background: white; min-height: 200px;">
                        <table class="table table-bordered" id="contract_products_table" style="margin-bottom: 0;">
                            <thead style="background-color: #e9ecef; color: #495057; font-weight: bold;">
                                <tr>
                                    <th style="width: 30%;">Product Details</th>
                                    <th style="width: 15%; text-align: center;">Target Quantity</th>
                                    <th style="width: 15%; text-align: center;">Unit Price</th>
                                    <th style="width: 25%; text-align: center;">Discount</th>
                                    <th style="width: 10%; text-align: right;">Subtotal</th>
                                    <th style="width: 5%; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody id="contract_products_tbody">
                                @if(isset($customer_contract) && $customer_contract->products->count() > 0)
                                    @foreach($customer_contract->products as $index => $product)
                                        @if(empty($product->parent_sell_line_id))
                                            @php
                                                $productName = $product->product ? $product->product->name : 'N/A';
                                                $productSku = $product->product ? $product->product->sku : '';
                                                $variation_id = $product->variation_id ?? ''; 
                                            @endphp
                                            <tr class="product-row">
                                                <td style="vertical-align: middle;">
                                                    <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $product->product_id }}" class="product-id-input">
                                                    <input type="hidden" name="products[{{ $index }}][variation_id]" value="{{ $variation_id }}" class="variation-id-input">
                                                    <strong>{{ $productName }}</strong><br>
                                                    <small class="text-muted">SKU: {{ $productSku }}</small>
                                                </td>
                                                {{-- UPDATED: Centered Quantity with Fixed Width --}}
                                                <td style="vertical-align: middle; text-align: center;">
                                                    <div class="input-group input-group-sm" style="width: 120px; margin: 0 auto;">
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-default qty-minus"><i class="fa fa-minus"></i></button>
                                                        </span>
                                                        {!! Form::number('products['.$index.'][target_quantity]', $product->target_quantity, [
                                                            'class' => 'form-control text-center quantity-input',
                                                            'min' => '0'
                                                        ]) !!}
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-default qty-plus"><i class="fa fa-plus"></i></button>
                                                        </span>
                                                    </div>
                                                </td>
                                                {{-- UPDATED: Centered Unit Price --}}
                                                <td style="vertical-align: middle; text-align: center;">
                                                    {!! Form::text('products['.$index.'][unit_price]', number_format($product->unit_price, 2, '.', ''), ['class' => 'form-control price-input input-sm text-center']) !!}
                                                </td>
                                                {{-- UPDATED: Centered Discount --}}
                                                <td style="vertical-align: middle; padding: 8px 2px; text-align: center;">
                                                    <div class="input-group input-group-sm" style="width: 100%;">
                                                        {{-- Changed text-right to text-center --}}
                                                        {!! Form::text('products['.$index.'][discount]', number_format($product->discount, 2, '.', ''), ['class' => 'form-control discount-input text-center']) !!}
                                                        <span class="input-group-btn">
                                                            {!! Form::select('products['.$index.'][discount_type]', ['Fixed' => 'Fixed', 'Percentage' => '%'], $product->discount_type, ['class' => 'form-control discount-type-select']) !!}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-right" style="vertical-align: middle;">
                                                    <span class="subtotal" style="color: #d63384; font-weight: bold;">${{ number_format($product->subtotal, 2) }}</span>
                                                </td>
                                                <td class="text-center" style="vertical-align: middle;">
                                                    <button type="button" class="btn btn-link text-danger remove-product"><i class="fa fa-times"></i></button>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            {{-- Totals --}}
            <div class="row">
                <div class="col-md-5 col-md-offset-7">
                    <table class="table table-sm" style="border: 1px solid #dee2e6;">
                        <tr>
                            <td class="text-right" style="font-weight: bold;">Total Target Units:</td>
                            <td class="text-center" style="font-weight: bold;"><span id="total_units">0</span></td>
                        </tr>
                        <tr>
                            <td class="text-right" style="font-weight: bold;">Total Contract Value:</td>
                            <td class="text-center" style="color: #28a745; font-weight: bold;"><span id="total_value">$0.00</span></td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- DOCUMENTS SECTION --}}
            <div class="row">
                <div class="col-md-12">
                    <label style="font-weight: 600;"><i class="fa fa-paperclip"></i> @lang('lang_v1.documents')</label>
                    
                    {{-- 1. DROPZONE FOR NEW FILES --}}
                    <div class="form-group">
                        <div class="dropzone" id="contractUpload"></div>
                        <input type="hidden" id="contract_document_names" name="contract_document_names" value="">
                        <small class="text-muted">@lang('lang_v1.upload_docs_help_text')</small>
                    </div>

                    {{-- 2. LIST EXISTING FILES (EDIT MODE ONLY) --}}
                    @if(isset($customer_contract) && $customer_contract->media->count() > 0)
                    <div class="form-group">
                        <label>@lang('lang_v1.uploaded_documents')</label>
                        <div style="border: 1px solid #ddd; padding: 10px; background-color: #f9f9f9; display: flex; flex-wrap: wrap; gap: 15px;">
                            @foreach($customer_contract->media as $media)
                                @php
                                    $is_image = in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                                @endphp
                                <div class="media-item" style="width: 120px; text-align: center; background: #fff; padding: 10px; border: 1px solid #eee; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                    
                                    {{-- CLICKABLE THUMBNAIL (With Download Attribute) --}}
                                    <a href="{{ $media->display_url }}" download="{{ $media->display_name }}" target="_blank" title="Click to download" style="text-decoration: none;">
                                        <div style="height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 5px;">
                                            @if($is_image)
                                                <img src="{{ $media->display_url }}" alt="{{ $media->display_name }}" style="max-height: 100%; max-width: 100%;">
                                            @else
                                                <i class="fa fa-file-text-o fa-3x text-primary"></i>
                                            @endif
                                        </div>
                                    </a>

                                    {{-- CLICKABLE FILENAME (With Download Attribute) --}}
                                    <div style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 5px;" title="{{ $media->display_name }}">
                                        <a href="{{ $media->display_url }}" download="{{ $media->display_name }}" target="_blank" style="color: #333; font-weight: bold;">
                                            {{ $media->display_name }}
                                        </a>
                                    </div>
                                    
                                    {{-- REMOVE BUTTON --}}
                                    <button type="button" class="btn btn-xs btn-link text-danger delete_contract_media" 
                                        data-href="{{ url('contacts/contract-media-delete/' . $media->id) }}" 
                                        style="padding: 0; font-size: 11px;">
                                        <i class="fa fa-trash"></i> Remove file
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                </div>
            </div>

            {!! Form::close() !!}
        </div>

        <div class="modal-footer" style="background-color: #fff; border-top: 1px solid #e9ecef;">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" form="customer_contract_form">Save Contract</button>
        </div>
    </div>
</div>

<style>
    /* HIDE ARROWS inside Number Input */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield;
    }

    /* Quantity Input Styling */
    .input-group-sm > .form-control.quantity-input {
        height: 30px;
        border-radius: 0;
        text-align: center;
        border: 1px solid #ced4da;
        box-shadow: none;
        min-width: 60px;
    }
    
    .input-group-sm > .input-group-btn > .btn {
        height: 30px;
        border: 1px solid #ced4da;
        background-color: #f8f9fa;
        color: #333;
        padding-top: 4px;
        min-width: 32px; 
    }
    
    .input-group-sm > .input-group-btn > .btn:hover {
        background-color: #e2e6ea;
    }

    /* Discount Input Styling */
    .discount-input {
        height: 30px;
        border-radius: 4px 0 0 4px !important;
        border-right: none;
        min-width: 50px;
    }

    /* Discount Type Select Styling */
    .discount-type-select {
        height: 30px;
        padding: 0 5px;
        border-radius: 0 4px 4px 0 !important;
        min-width: 60px;
    }

    /* Remove X icon from default Close button */
    .modal-footer .btn-default i.fa-close {
        display: none !important;
    }
    
    /* Ensure the table looks consistent */
    #contract_products_table th {
        background-color: #e9ecef;
    }
</style>