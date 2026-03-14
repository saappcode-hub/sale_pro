<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header" style="background-color: #007bff; color: white;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">View: {{ $contract->reference_no }}</h4>
        </div>

        <div class="modal-body">
            {{-- TOP INFO SECTION --}}
            <div class="row">
                <div class="col-md-6">
                    <p>
                        <strong>Reference No:</strong> {{ $contract->reference_no }}<br>
                        <strong>Contract Name:</strong> {{ $contract->contract_name }}
                    </p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Status:</strong> 
                        <span class="label {{ $contract->status_class }}">
                            {{ $contract->status_label }}
                        </span>
                        <br>
                        <strong>Period:</strong> 
                        {{ @format_date($contract->start_date) }} 
                        @if($contract->end_date)
                            to {{ @format_date($contract->end_date) }}
                        @endif
                    </p>
                </div>
            </div>

            <br>

            {{-- TABS SECTION --}}
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#tab_targets" data-toggle="tab" aria-expanded="true">
                            Product Targets & Breakdown
                        </a>
                    </li>
                    <li>
                        <a href="#tab_related_sales" data-toggle="tab" aria-expanded="false">
                            Related Sales 
                            @if(count($relatedSales) > 0)
                                <span class="label label-danger">{{ count($relatedSales) }}</span>
                            @endif
                        </a>
                    </li>
                </ul>

                <div class="tab-content" style="padding-top: 20px;">
                    
                    {{-- TAB 1: PRODUCT TARGETS & BREAKDOWN --}}
                    <div class="tab-pane active" id="tab_targets">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr class="bg-gray">
                                        <th>Product (SKU)</th>
                                        <th class="text-center">Target Qty</th>
                                        <th class="text-center">Actual Qty</th>
                                        <th class="text-center">Unit Price</th>
                                        <th class="text-center">Discount</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contract->products as $product)
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <b>{{ $product->product->name ?? '' }}</b> 
                                                <span class="text-muted">({{ $product->product->sku ?? '' }})</span>
                                            </td>
                                            <td class="text-center" style="vertical-align: middle;">
                                                {{ number_format($product->target_quantity, 0) }}
                                            </td>
                                            
                                            {{-- ACTUAL QTY --}}
                                            <td class="text-center" style="vertical-align: middle; color: #17a2b8; font-weight: bold;">
                                                {{ number_format($product->actual_qty, 0) }}
                                            </td>

                                            <td class="text-center" style="vertical-align: middle;">
                                                <span class="display_currency" data-currency_symbol="true">{{ $product->unit_price }}</span>
                                            </td>
                                            <td class="text-center" style="vertical-align: middle;">
                                                <span class="display_currency" data-currency_symbol="true">{{ $product->discount }}</span>
                                                <small>({{ $product->discount_type }})</small>
                                            </td>
                                            <td class="text-right font-weight-bold" style="vertical-align: middle;">
                                                <span class="display_currency" data-currency_symbol="true">{{ $product->subtotal }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                
                                {{-- FOOTER SECTION --}}
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-right" style="font-weight: bold;">Total Target Qty:</td>
                                        <td class="text-right" style="font-weight: bold;">
                                            {{ number_format($contract->total_target_units, 0) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right" style="font-weight: bold;">Total Contract Value:</td>
                                        <td class="text-right" style="color: #28a745; font-weight: bold; font-size: 16px;">
                                            <span class="display_currency" data-currency_symbol="true">{{ $contract->total_contract_value }}</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- TAB 2: RELATED SALES --}}
                    <div class="tab-pane" id="tab_related_sales">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr class="bg-gray">
                                        <th>Date</th>
                                        <th>Invoice No</th>
                                        {{-- ADDED HEADER --}}
                                        <th>Business Location</th> 
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($relatedSales as $sale)
                                        <tr>
                                            <td>{{ @format_datetime($sale->transaction_date) }}</td>
                                            <td>
                                                <a href="#" data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$sale->id]) }}" class="btn-modal" data-container=".view_modal">
                                                    {{ $sale->invoice_no }}
                                                </a>
                                            </td>
                                            {{-- ADDED COLUMN DATA --}}
                                            <td>{{ $sale->location_name ?? '' }}</td>
                                            
                                            <td class="text-right">
                                                <span class="display_currency" data-currency_symbol="true">
                                                   {{ number_format($sale->final_total, 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            {{-- UPDATED COLSPAN TO 4 --}}
                                            <td colspan="4" class="text-center text-muted">
                                                No related sales found for this contract period.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            {{-- END TABS SECTION --}}

            {{-- DOCUMENTS SECTION --}}
            @if($contract->media->count() > 0)
            <div class="row">
                <div class="col-md-12">
                    <h4 style="font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 20px;">
                        <i class="fa fa-paperclip"></i> @lang('lang_v1.documents')
                    </h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                        @foreach($contract->media as $media)
                            @php
                                $is_image = in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                            @endphp
                            
                            <div style="width: 100px; text-align: center; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <a href="{{ $media->display_url }}" download="{{ $media->display_name }}" target="_blank" title="Click to download" style="text-decoration: none; color: inherit;">
                                    <div style="height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 5px;">
                                        @if($is_image)
                                            <img src="{{ $media->display_url }}" alt="{{ $media->display_name }}" style="max-height: 100%; max-width: 100%;">
                                        @else
                                            <i class="fa fa-file-text-o fa-3x text-primary"></i>
                                        @endif
                                    </div>
                                    <div style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: bold;">
                                        {{ $media->display_name }}
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
</div>