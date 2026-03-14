<?php
if($product->group_product == 1){
	?>
		<br>
		<div class="row">
			<div class="col-md-12">
				<div class="table-responsive">
					<table class="table bg-gray">
						<tr class="bg-green">
							@can('view_purchase_price')
								<th>@lang('product.default_purchase_price') (@lang('product.exc_of_tax'))</th>
								<th>@lang('product.default_purchase_price') (@lang('product.inc_of_tax'))</th>
							@endcan
							@can('access_default_selling_price')
								@can('view_purchase_price')
									<th>@lang('product.profit_percent')</th>
								@endcan
								<th>@lang('product.default_selling_price') (@lang('product.exc_of_tax'))</th>
								<th>@lang('product.default_selling_price') (@lang('product.inc_of_tax'))</th>
							@endcan
							@if(!empty($allowed_group_prices))
								<th>@lang('lang_v1.group_prices')</th>
							@endif
							<th>@lang('lang_v1.variation_images')</th>
						</tr>
						@foreach($product->variations as $variation)
						<tr>
							@can('view_purchase_price')
							<td>
								<span class="display_currency" data-currency_symbol="true">{{ $variation->default_purchase_price }}</span>
							</td>
							<td>
								<span class="display_currency" data-currency_symbol="true">{{ $variation->dpp_inc_tax }}</span>
							</td>
							@endcan
							@can('access_default_selling_price')
								@can('view_purchase_price')
								<td>
									{{ @num_format($variation->profit_percent) }}
								</td>
								@endcan
								<td>
									<span class="display_currency" data-currency_symbol="true">{{ $variation->default_sell_price }}</span>
								</td>
								<td>
									<span class="display_currency" data-currency_symbol="true">{{ $variation->sell_price_inc_tax }}</span>
								</td>
							@endcan
							@if(!empty($allowed_group_prices))
								<td class="td-full-width">
									@foreach($allowed_group_prices as $key => $value)
										<strong>{{$value}}</strong> - @if(!empty($group_price_details[$variation->id][$key]))
											<span class="display_currency" data-currency_symbol="true">{{ $group_price_details[$variation->id][$key]['calculated_price'] }}</span>
										@else
											0
										@endif
										<br>
									@endforeach
								</td>
							@endif
							<td>
								@foreach($variation->media as $media)
									{!! $media->thumbnail([60, 60], 'img-thumbnail') !!}
								@endforeach
							</td>
						</tr>
						@endforeach
					</table>
				</div>
			</div>
		</div>
	<?php
}else{
	?>
		<br>
		<div class="row">
			<div class="col-md-12">
				<div class="table-responsive">
					<table class="table bg-gray">
						<tr class="bg-green">
							@can('view_purchase_price')
								<th>@lang('product.default_purchase_price') (@lang('product.exc_of_tax'))</th>
								<th>@lang('product.default_purchase_price') (@lang('product.inc_of_tax'))</th>
							@endcan
							@can('access_default_selling_price')
								@can('view_purchase_price')
									<th>@lang('product.profit_percent')</th>
								@endcan
								<th>@lang('product.default_selling_price') (@lang('product.exc_of_tax'))</th>
								<th>@lang('product.default_selling_price') (@lang('product.inc_of_tax'))</th>
							@endcan
							<th>Selling price group</th>
							<th>Quantity</th>
							<th>Price per piece</th>
						</tr>

						@foreach($product->variations as $variation)
							@php $totalRows = collect($price_ranges[$variation->id] ?? [])->reduce(function ($carry, $item) {
								return $carry + count($item);
							}, 1); @endphp
							<tr>
								@can('view_purchase_price')
									<td rowspan="{{ $totalRows }}">
										<span class="display_currency" data-currency_symbol="true">{{ $variation->default_purchase_price }}</span>
									</td>
									<td rowspan="{{ $totalRows }}">
										<span class="display_currency" data-currency_symbol="true">{{ $variation->dpp_inc_tax }}</span>
									</td>
								@endcan
								@can('access_default_selling_price')
									@can('view_purchase_price')
										<td rowspan="{{ $totalRows }}">
											{{ @num_format($variation->profit_percent) }}
										</td>
									@endcan
									<td rowspan="{{ $totalRows }}">
										<span class="display_currency" data-currency_symbol="true">{{ $variation->default_sell_price }}</span>
									</td>
									<td rowspan="{{ $totalRows }}">
										<span class="display_currency" data-currency_symbol="true">{{ $variation->sell_price_inc_tax }}</span>
									</td>
								@endcan
							</tr>
							@if(isset($price_ranges[$variation->id]))
								@foreach($price_ranges[$variation->id] as $group_id => $ranges)
									<tr style="border: none;">
										<td rowspan="{{ count($ranges) }}" style="border: none;">
											<strong>{{ $allowed_group_prices[$group_id] ?? 'N/A' }}</strong>
										</td>
										@foreach($ranges as $index => $range)
											@if($index > 0) <tr> @endif
											<td style="border: none;">{{ $range['minimum_qty'] }} - {{ $range['maximum_qty'] }}</td>
											<td style="border: none;">
												<span class="display_currency" data-currency_symbol="true">{{ $range['price'] }}</span>
											</td>
											@if($index + 1 < count($ranges)) </tr> @endif
										@endforeach
									</tr>
								@endforeach
							@endif
						@endforeach
					</table>
				</div>
			</div>
		</div>
	<?php
}
?>