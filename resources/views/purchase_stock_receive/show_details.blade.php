<div class="modal-header">
    <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    @php
      $title = $purchase->type == 'purchase_order' ? __('lang_v1.purchase_order_details') : __('purchase.purchase_details');
      $custom_labels = json_decode(session('business.custom_labels'), true);
    @endphp
    <h4 class="modal-title" id="modalTitle"> {{$title}} (<b>@lang('purchase.ref_no'):</b> #{{ $purchase->ref_no }})
    </h4>
</div>
<div class="modal-body">
  <div class="row">
    <div class="col-sm-12">
      <p class="pull-right"><b>@lang('messages.date'):</b> {{ @format_date($purchase->transaction_date) }}</p>
    </div>
  </div>
  <div class="row invoice-info">
    <div class="col-sm-4 invoice-col">
      @lang('purchase.supplier'):
      <address>
        {!! $purchase->contact->contact_address !!}
        @if(!empty($purchase->contact->tax_number))
          <br>@lang('contact.tax_no'): {{$purchase->contact->tax_number}}
        @endif
        @if(!empty($purchase->contact->mobile))
          <br>@lang('contact.mobile'): {{$purchase->contact->mobile}}
        @endif
        @if(!empty($purchase->contact->email))
          <br>@lang('business.email'): {{$purchase->contact->email}}
        @endif
      </address>
      @if($purchase->document_path)
        
        <a href="{{$purchase->document_path}}" 
        download="{{$purchase->document_name}}" class="btn btn-sm btn-success pull-left no-print">
          <i class="fa fa-download"></i> 
            &nbsp;{{ __('purchase.download_document') }}
        </a>
      @endif
    </div>

    <div class="col-sm-4 invoice-col">
      @lang('business.business'):
      <address>
        <strong>{{ $purchase->business->name }}</strong>
        {{ $purchase->location->name }}
        @if(!empty($purchase->location->landmark))
          <br>{{$purchase->location->landmark}}
        @endif
        @if(!empty($purchase->location->city) || !empty($purchase->location->state) || !empty($purchase->location->country))
          <br>{{implode(',', array_filter([$purchase->location->city, $purchase->location->state, $purchase->location->country]))}}
        @endif
        
        @if(!empty($purchase->business->tax_number_1))
          <br>{{$purchase->business->tax_label_1}}: {{$purchase->business->tax_number_1}}
        @endif

        @if(!empty($purchase->business->tax_number_2))
          <br>{{$purchase->business->tax_label_2}}: {{$purchase->business->tax_number_2}}
        @endif

        @if(!empty($purchase->location->mobile))
          <br>@lang('contact.mobile'): {{$purchase->location->mobile}}
        @endif
        @if(!empty($purchase->location->email))
          <br>@lang('business.email'): {{$purchase->location->email}}
        @endif
      </address>
    </div>

    <div class="col-sm-4 invoice-col">
      <b>@lang('purchase.ref_no'):</b> #{{ $purchase->ref_no }}<br/>
      <b>@lang('messages.date'):</b> {{ @format_date($purchase->transaction_date) }}<br/>
      @if(!empty($purchase->status))
        <b>@lang('purchase.purchase_status'):</b> @if($purchase->type == 'purchase_order'){{$po_statuses[$purchase->status]['label'] ?? ''}} @else {{ __('lang_v1.' . $purchase->status) }} @endif<br>
      @endif
      @if(!empty($purchase->payment_status))
      <b>@lang('purchase.payment_status'):</b> {{ __('lang_v1.' . $purchase->payment_status) }}
      @endif

      @if(!empty($custom_labels['purchase']['custom_field_1']))
        <br><strong>{{$custom_labels['purchase']['custom_field_1'] ?? ''}}: </strong> {{$purchase->custom_field_1}}
      @endif
      @if(!empty($custom_labels['purchase']['custom_field_2']))
        <br><strong>{{$custom_labels['purchase']['custom_field_2'] ?? ''}}: </strong> {{$purchase->custom_field_2}}
      @endif
      @if(!empty($custom_labels['purchase']['custom_field_3']))
        <br><strong>{{$custom_labels['purchase']['custom_field_3'] ?? ''}}: </strong> {{$purchase->custom_field_3}}
      @endif
      @if(!empty($custom_labels['purchase']['custom_field_4']))
        <br><strong>{{$custom_labels['purchase']['custom_field_4'] ?? ''}}: </strong> {{$purchase->custom_field_4}}
      @endif
      @if(!empty($purchase_order_nos))
            <strong>@lang('restaurant.order_no'):</strong>
            {{$purchase_order_nos}}
        @endif

        @if(!empty($purchase_order_dates))
            <br>
            <strong>@lang('lang_v1.order_dates'):</strong>
            {{$purchase_order_dates}}
        @endif
      @if($purchase->type == 'purchase_order')
        @php
          $custom_labels = json_decode(session('business.custom_labels'), true);
        @endphp
        <strong>@lang('sale.shipping'):</strong>
        <span class="label @if(!empty($shipping_status_colors[$purchase->shipping_status])) {{$shipping_status_colors[$purchase->shipping_status]}} @else {{'bg-gray'}} @endif">{{$shipping_statuses[$purchase->shipping_status] ?? '' }}</span><br>
        @if(!empty($purchase->shipping_address()))
          {{$purchase->shipping_address()}}
        @else
          {{$purchase->shipping_address ?? '--'}}
        @endif
        @if(!empty($purchase->delivered_to))
          <br><strong>@lang('lang_v1.delivered_to'): </strong> {{$purchase->delivered_to}}
        @endif
        @if(!empty($purchase->shipping_custom_field_1))
          <br><strong>{{$custom_labels['shipping']['custom_field_1'] ?? ''}}: </strong> {{$purchase->shipping_custom_field_1}}
        @endif
        @if(!empty($purchase->shipping_custom_field_2))
          <br><strong>{{$custom_labels['shipping']['custom_field_2'] ?? ''}}: </strong> {{$purchase->shipping_custom_field_2}}
        @endif
        @if(!empty($purchase->shipping_custom_field_3))
          <br><strong>{{$custom_labels['shipping']['custom_field_3'] ?? ''}}: </strong> {{$purchase->shipping_custom_field_3}}
        @endif
        @if(!empty($purchase->shipping_custom_field_4))
          <br><strong>{{$custom_labels['shipping']['custom_field_4'] ?? ''}}: </strong> {{$purchase->shipping_custom_field_4}}
        @endif
        @if(!empty($purchase->shipping_custom_field_5))
          <br><strong>{{$custom_labels['shipping']['custom_field_5'] ?? ''}}: </strong> {{$purchase->shipping_custom_field_5}}
        @endif
        @php
          $medias = $purchase->media->where('model_media_type', 'shipping_document')->all();
        @endphp
        @if(count($medias))
          @include('sell.partials.media_table', ['medias' => $medias])
        @endif
      @endif
    </div>
  </div>

  <br>
  <div class="row">
    <div class="col-sm-12 col-xs-12">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr class="bg-green">
                        <th>#</th>
                        <th>@lang('product.product_name')</th>
                        @if($purchase->type == 'purchase_order')
                            <th class="text-right">@lang('lang_v1.quantity_remaining')</th>
                        @endif
                        <th class="text-right">@if($purchase->type == 'purchase_order') @lang('lang_v1.order_quantity') @else @lang('purchase.purchase_quantity') @endif</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $total_before_tax = 0.00;
                    @endphp
                    @foreach($purchase->purchase_lines as $purchase_line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $purchase_line->product->name }}
                                @if($purchase_line->product->type == 'variable')
                                    - {{ $purchase_line->variations->product_variation->name}}
                                    - {{ $purchase_line->variations->name}}
                                @endif
                            </td>
                            @if($purchase->type == 'purchase_order')
                                <td class="text-right">
                                    <span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $purchase_line->po_qty_remaining_display }}</span>
                                </td>
                            @endif
                            <td class="text-right">
                                <span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $purchase_line->quantity_display }}</span>
                                @if(!empty($purchase_line->product->second_unit) && $purchase_line->secondary_unit_quantity != 0)
                                    <br>
                                    <span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $purchase_line->secondary_unit_quantity }}</span> {{$purchase_line->product->second_unit->short_name}}
                                @endif
                            </td>
                        </tr>
                        @php 
                            $total_before_tax += ($purchase_line->quantity * $purchase_line->purchase_price);
                        @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>