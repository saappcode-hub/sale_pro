@extends('layouts.app')
@section('title', __('lang_v1.product_stock_history'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('lang_v1.product_stock_history')</h1>
</section>

<!-- Main content -->
<section class="content">
<div class="row">
    <div class="col-md-12">
    @component('components.widget', ['title' => $product->name])
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('product_id',  __('sale.product') . ':') !!}
                {!! Form::select('product_id', [$product->id=>$product->name . ' - ' . $product->sku], $product->id, ['class' => 'form-control', 'style' => 'width:100%']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, request()->input('location_id', null), ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
            </div>
        </div>
        @if($product->type == 'variable')
            <div class="col-md-3">
                <div class="form-group">
                    <label for="variation_id">@lang('product.variations'):</label>
                    <select class="select2 form-control" name="variation_id" id="variation_id">
                        @foreach($product->variations as $variation)
                            <option value="{{$variation->id}}"
                            @if(request()->input('variation_id', null) == $variation->id)
                                selected
                            @endif
                            >{{$variation->product_variation->name}} - {{$variation->name}} ({{$variation->sub_sku}})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @else
            <input type="hidden" id="variation_id" name="variation_id" value="{{$product->variations->first()->id}}">
        @endif
    @endcomponent
    @component('components.widget')
        {{-- NEW: AJAX URL for pagination --}}
        <input type="hidden" id="stock_history_url" value="">
        <div id="product_stock_history" style="display: none;"></div>
    @endcomponent
    </div>
</div>

</section>
<!-- /.content -->
@endsection

@section('javascript')
<script type="text/javascript">
        $(document).ready( function(){
            load_stock_history($('#variation_id').val(), $('#location_id').val());

            $('#product_id').select2({
                ajax: {
                    url: '/products/list-no-variation',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            term: params.term, // search term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data,
                        };
                    },
                },
                minimumInputLength: 1,
                escapeMarkup: function(m) {
                    return m;
                },
            }).on('select2:select', function (e) {
                var data = e.params.data;
                window.location.href = "{{url('/')}}/products/stock-history/" + data.id
            });
        });

        function load_stock_history(variation_id, location_id) {
            $('#product_stock_history').fadeOut();
            
            // NEW: store AJAX URL for pagination
            $('#stock_history_url').val('/products/stock-history/' + variation_id);

            $.ajax({
                url: '/products/stock-history/' + variation_id,
                data: {
                    location_id: location_id
                },
                dataType: 'html',
                success: function(result) {
                    console.log('AJAX Success - Result length:', result.length);
                    $('#product_stock_history')
                        .html(result)
                        .fadeIn();

                    __currency_convert_recursively($('#product_stock_history'));

                    // Check if the table exists and has data rows before initializing DataTable
                    if ($('#stock_history_table').length && $('#stock_history_table tbody tr').length > 0 && $('#stock_history_table tbody tr td').text().trim() !== '@lang("lang_v1.no_stock_history_found")') {
                        try {
                            $('#stock_history_table').DataTable({
                                searching: false,
                                ordering: false
                            });
                        } catch (e) {
                            console.error('DataTable initialization error:', e);
                        }
                    } else {
                        console.log('No data available for DataTable initialization or table is empty');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $('#product_stock_history')
                        .html('<div class="alert alert-danger">Error loading stock history</div>')
                        .fadeIn();
                }
            });
        }

    $(document).on('change', '#variation_id, #location_id', function(){
        load_stock_history($('#variation_id').val(), $('#location_id').val());
    });
   </script>
@endsection