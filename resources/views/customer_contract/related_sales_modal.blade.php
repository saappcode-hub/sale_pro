<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header" style="background-color: #007bff; color: white; border-bottom: 1px solid #e9ecef;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1;">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title" style="font-weight: 600;">
                <i class="fa fa-list-alt"></i> Related Sales: {{ $contract->reference_no }}
            </h4>
        </div>

        <div class="modal-body" style="background-color: #fff; padding: 20px;">
            
            {{-- Contract Info Header --}}
            <div class="row" style="margin-bottom: 20px;">
                <div class="col-md-7">
                    <h4 style="margin-top: 0; font-weight: bold; color: #333;">{{ $contract->contract_name }}</h4>
                    <p class="text-muted" style="margin-bottom: 5px;">
                        <strong>Ref No:</strong> {{ $contract->reference_no }}
                    </p>
                </div>
                <div class="col-md-5 text-right">
                    <span class="label {{ $contract->status_class }}" style="font-size: 12px; padding: 5px 10px;">
                        {{ $contract->status_label }}
                    </span>
                    <br>
                    <small style="display: block; margin-top: 5px; color: #777;">
                        <i class="fa fa-calendar"></i> 
                        {{ @format_date($contract->start_date) }} 
                        @if($contract->end_date) 
                            <i class="fa fa-arrow-right" style="font-size: 10px;"></i> 
                            {{ @format_date($contract->end_date) }} 
                        @endif
                    </small>
                </div>
            </div>

            {{-- Sales Table --}}
            <h4 style="color: #555; margin-bottom: 10px; font-weight: 600;">Related Sales Transactions</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover" style="border: 1px solid #dee2e6;">
                    <thead style="background-color: #f1f3f5; color: #495057;">
                        <tr>
                            <th style="width: 20%;">Date</th>
                            <th style="width: 25%;">Invoice No</th>
                            <th style="width: 35%;">Business Location</th> 
                            <th class="text-right" style="width: 20%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($relatedSales as $sale)
                            <tr>
                                <td style="vertical-align: middle;">
                                    {{ @format_datetime($sale->transaction_date) }}
                                </td>
                                <td style="vertical-align: middle;">
                                    <a href="#" 
                                       data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$sale->id]) }}" 
                                       class="btn-modal" 
                                       data-container=".view_modal"
                                       style="font-weight: bold; text-decoration: underline; color: #007bff;">
                                        {{ $sale->invoice_no }}
                                    </a>
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $sale->location_name ?? '--' }}
                                </td>
                                <td class="text-right" style="vertical-align: middle; font-weight: bold;">
                                    <span class="display_currency" data-currency_symbol="true">
                                       {{ $sale->final_total }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 20px;">
                                    <i class="fa fa-info-circle"></i> No related sales found for this contract period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <hr style="border-top: 1px solid #eee; margin: 20px 0;">

            {{-- DOCUMENTS SECTION --}}
            @if($contract->media->count() > 0)
            <div class="row">
                <div class="col-md-12">
                    <h4 style="font-weight: 600; color: #333; margin-bottom: 15px;">
                        <i class="fa fa-paperclip"></i> @lang('lang_v1.documents')
                    </h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                        @foreach($contract->media as $media)
                            @php
                                $is_image = in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                            @endphp
                            
                            <div style="width: 110px; text-align: center; border: 1px solid #ddd; padding: 8px; border-radius: 4px; background-color: #f9f9f9;">
                                <a href="{{ $media->display_url }}" download="{{ $media->display_name }}" target="_blank" title="Click to download" style="text-decoration: none; color: inherit;">
                                    <div style="height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; background: #fff; border: 1px solid #eee;">
                                        @if($is_image)
                                            <img src="{{ $media->display_url }}" alt="{{ $media->display_name }}" style="max-height: 100%; max-width: 100%;">
                                        @else
                                            <i class="fa fa-file-text-o fa-3x text-primary"></i>
                                        @endif
                                    </div>
                                    <div style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: bold; color: #333;">
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

        <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e9ecef;">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
</div>