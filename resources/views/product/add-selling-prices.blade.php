@extends('layouts.app')
@section('title', __('lang_v1.add_selling_price_group_prices'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('lang_v1.add_selling_price_group_prices')</h1>
</section>

<!-- Main content -->
<section class="content">
	{!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'saveSellingPrices']), 'method' => 'post', 'id' => 'selling_price_form' ]) !!}
	{!! Form::hidden('product_id', $product->id); !!}
	{!! Form::hidden('group_product', $product->group_product) !!}
	<div class="row">
		<div class="col-xs-12">
		<div class="box box-solid">
			<div class="box-header">
	            <h3 class="box-title">@lang('sale.product'): {{$product->name}} ({{$product->sku}})</h3>
	        </div>
			<div class="box-body">
				@if($product->group_product == 1)
					<div class="row">
						<div class="col-xs-12">
							<div class="table-responsive">
								<table class="table table-condensed table-bordered table-th-green text-center table-striped">
									<thead>
										<tr>
											@if($product->type == 'variable')
												<th>
													@lang('lang_v1.variation')
												</th>
											@endif
											<th>@lang('lang_v1.default_selling_price_inc_tax')</th>
											@foreach($price_groups as $price_group)
												@if(isset($variation_prices[array_key_first($variation_prices)][$price_group->id]))
													<th>{{$price_group->name}} @show_tooltip(__('lang_v1.price_group_price_type_tooltip'))</th>
												@endif
											@endforeach
										</tr>
									</thead>
									<tbody>
										@foreach($product->variations as $variation)
											<tr>
											@if($product->type == 'variable')
												<td>
													{{$variation->product_variation->name}} - {{$variation->name}} ({{$variation->sub_sku}})
												</td>
											@endif
											<td><span class="display_currency" data-currency_symbol="true">{{$variation->sell_price_inc_tax}}</span></td>
												@foreach($price_groups as $price_group)
													@if(isset($variation_prices[$variation->id][$price_group->id])) 
														<td>
															{!! Form::text('group_prices[' . $price_group->id . '][' . $variation->id . '][price]', 
																!empty($variation_prices[$variation->id][$price_group->id]['price']) ? 
																@num_format($variation_prices[$variation->id][$price_group->id]['price']) : 0, 
																['class' => 'form-control input_number input-sm'] 
															); !!}

															@php
																$price_type = !empty($variation_prices[$variation->id][$price_group->id]['price_type']) 
																	? $variation_prices[$variation->id][$price_group->id]['price_type'] 
																	: 'fixed';

																$name = 'group_prices[' . $price_group->id . '][' . $variation->id . '][price_type]';
															@endphp

															<select name="{{$name}}" class="form-control">
																<option value="fixed" @if($price_type == 'fixed') selected @endif>@lang('lang_v1.fixed')</option>
																<option value="percentage" @if($price_type == 'percentage') selected @endif>@lang('lang_v1.percentage')</option>
															</select>
														</td>
													@endif
												@endforeach
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>
				@else
				<div class="row" id="price_range_part">
						<!-- Navigation Tabs -->
						<ul class="nav nav-tabs">
							@foreach($price_groups as $index => $group)
								@if(isset($variation_prices[array_key_first($variation_prices)][$group->id])) 
									<li class="nav-item">
										<a class="nav-link {{ $index == 0 ? 'active' : '' }}" 
											id="group-tab-{{ $group->id }}" 
											data-toggle="tab" 
											href="#group-{{ $group->id }}" 
											role="tab" 
											aria-controls="group-{{ $group->id }}" 
											aria-selected="{{ $index == 0 ? 'true' : 'false' }}">
											{{ $group->name }}
										</a>
									</li>
								@endif
							@endforeach
						</ul>

						<div class="tab-content">
							@foreach($price_groups as $index => $group)
								<div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" 
									id="group-{{ $group->id }}" role="tabpanel">
									<table class="table table-bordered add-product-price-table">
										<thead>
											<tr>
												<th>Minimum Qty</th>
												<th>Maximum Qty</th>
												<th>Price Per Piece</th>
												<th style="background-color: blue; color: white; text-align: center;">
													<button type="button" class="btn btn-sm btn-success add-row" 
															data-group="{{ $group->id }}">+</button>
												</th>
											</tr>
										</thead>
										<tbody id="price_range_body_{{ $group->id }}">
											@if(isset($price_ranges[$group->id])) 
												@foreach(json_decode($price_ranges[$group->id]->price_range, true) as $index => $range)
													<tr>
														<td>
															<input type="text" name="product_price_range[{{ $group->id }}][{{ $index }}][minimum_qty]" 
																class="form-control min-qty" required 
																placeholder="Minimum Qty"
																value="{{ $range['minimum_qty'] }}"
																onkeypress="return event.charCode >= 48 && event.charCode <= 57">
														</td>
														<td>
															<input type="text" name="product_price_range[{{ $group->id }}][{{ $index }}][maximum_qty]" 
																class="form-control max-qty" required 
																placeholder="Maximum Qty"
																value="{{ $range['maximum_qty'] }}"
																onkeypress="return event.charCode >= 48 && event.charCode <= 57">
														</td>
														<td>
															<input type="text" name="product_price_range[{{ $group->id }}][{{ $index }}][price]" 
																class="form-control price" required
																value="{{ $range['price'] }}">
														</td>
														<td style="text-align: center;">
															<button type="button" class="btn btn-sm btn-danger remove-row">-</button>
														</td>
													</tr>
												@endforeach
											@else
												<!-- Default empty row -->
												<tr>
													<td>
														<input type="text" name="product_price_range[{{ $group->id }}][0][minimum_qty]" 
															class="form-control min-qty" required 
															placeholder="Minimum Qty"
															onkeypress="return event.charCode >= 48 && event.charCode <= 57">
													</td>
													<td>
														<input type="text" name="product_price_range[{{ $group->id }}][0][maximum_qty]" 
															class="form-control max-qty" required 
															placeholder="Maximum Qty"
															onkeypress="return event.charCode >= 48 && event.charCode <= 57">
													</td>
													<td>
														<input type="text" name="product_price_range[{{ $group->id }}][0][price]" 
															class="form-control price" required>
													</td>
													<td style="text-align: center;">
														<button type="button" class="btn btn-sm btn-danger remove-row" disabled>-</button>
													</td>
												</tr>
											@endif
										</tbody>
									</table>
								</div>
							@endforeach
						</div>
					</div>
				@endif
			</div>
		</div>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-12">
			{!! Form::hidden('submit_type', 'save', ['id' => 'submit_type']); !!}
			<div class="text-center">
      			<div class="btn-group">
					<button id="opening_stock_button" @if($product->enable_stock == 0) disabled @endif type="submit" value="submit_n_add_opening_stock" class="btn bg-purple submit_form btn-big">@lang('lang_v1.save_n_add_opening_stock')</button>
					<button type="submit" value="save_n_add_another" class="btn bg-maroon submit_form btn-big">@lang('lang_v1.save_n_add_another')</button>
          			<button type="submit" value="submit" class="btn btn-primary submit_form btn-big">@lang('messages.save')</button>
          		</div>
          	</div>
		</div>
	</div>

	{!! Form::close() !!}
</section>
@stop
@section('javascript')
	<script type="text/javascript">
		$(document).ready(function(){

			// Validate when user inputs values (checks after the user stops typing)
			 // ✅ Validate only after the user inputs Maximum Qty
			 $(document).on('input', '.max-qty', function () {
				var groupId = $(this).closest('tbody').attr('id').replace('price_range_body_', '');
				validatePriceRange(groupId);
			});

			// ✅ Validate Minimum Qty only after the user enters a value
			$(document).on('input', '.min-qty', function () {
				var groupId = $(this).closest('tbody').attr('id').replace('price_range_body_', '');
				validatePriceRange(groupId);
			});

			$('button.submit_form').click(function (e) {
				e.preventDefault();
				var isValid = true;

				$('.tab-pane.active tbody').each(function () {
					var groupId = $(this).attr('id').replace('price_range_body_', '');
					if (!validatePriceRange(groupId)) {
						isValid = false;
					}
				});

				if (isValid) {
					$('input#submit_type').val($(this).attr('value'));
					if ($("form#selling_price_form").valid()) {
						$("form#selling_price_form").submit();
					}
				}
			});
			
			// Initial setup: Activate the first tab and corresponding price range table
			$('.nav-tabs .nav-link:first').trigger('click');

			// Handle tab switching and activation
			$('.nav-tabs .nav-link').on('click', function () {
				$('.nav-tabs .nav-link, .tab-content .tab-pane').removeClass('active show');
				$(this).addClass('active');
				$($(this).attr('href')).addClass('show active');
			});

			function updateAddRemoveButtons(groupId) {
				var tableBody = $('#price_range_body_' + groupId);
				var rowCount = tableBody.find('tr').length;
				var addButton = $('button.add-row[data-group="' + groupId + '"]');

				addButton.prop('disabled', rowCount >= 3);
				tableBody.find('.remove-row').prop('disabled', rowCount <= 1);
			}

			function validatePriceRange(groupId) {
				var isValid = true;
				var previousMax = null; // Store previous row's Maximum Qty

				$('#price_range_body_' + groupId + ' tr').each(function (index) {
					var minInput = $(this).find('.min-qty');
					var maxInput = $(this).find('.max-qty');

					var minQty = parseInt(minInput.val()) || 0;
					var maxQty = parseInt(maxInput.val()) || 0;

					// Remove any existing error messages
					$(this).find('.error-message').remove();

					// ✅ Condition 1: Maximum Qty must be greater than Minimum Qty (Only check after user enters maxQty)
					if (maxInput.val() !== "" && maxQty <= minQty) {
						isValid = false;
						maxInput.after('<div class="error-message" style="color: red;">Maximum Qty must be greater than Minimum Qty.</div>');
					}

					// ✅ Condition 2: The next row’s Minimum Qty should be greater than the previous row’s Maximum Qty
					if (previousMax !== null && minQty <= previousMax) {
						isValid = false;
						minInput.after(`<div class="error-message" style="color: red;">Minimum Qty in row ${index + 1} must be greater than Maximum Qty of row ${index}.</div>`);
					}

					previousMax = maxQty; // Update previousMax for the next iteration
				});

				return isValid;
			}

			$(document).on('click', '.add-row', function () {
				var groupId = $(this).data('group');
				var tableBody = $('#price_range_body_' + groupId);
				var rowCount = tableBody.find('tr').length;

				if (rowCount < 3) {
					var newRow = `
						<tr>
							<td>
								<input type="text" name="product_price_range[${groupId}][${rowCount}][minimum_qty]" 
									class="form-control min-qty" required placeholder="Minimum Qty"
									onkeypress="return event.charCode >= 48 && event.charCode <= 57">
							</td>
							<td>
								<input type="text" name="product_price_range[${groupId}][${rowCount}][maximum_qty]" 
									class="form-control max-qty" required placeholder="Maximum Qty"
									onkeypress="return event.charCode >= 48 && event.charCode <= 57">
							</td>
							<td>
								<input type="text" name="product_price_range[${groupId}][${rowCount}][price]" 
									class="form-control price" required>
							</td>
							<td style="text-align: center;">
								<button type="button" class="btn btn-sm btn-danger remove-row">-</button>
							</td>
						</tr>
					`;
					tableBody.append(newRow);
				}

				updateAddRemoveButtons(groupId);
			});

			$(document).on('click', '.remove-row', function() {
				var groupId = $(this).closest('tbody').attr('id').replace('price_range_body_', '');
				$(this).closest('tr').remove();
				updateAddRemoveButtons(groupId);
			});

			@foreach($price_groups as $group)
				updateAddRemoveButtons({{ $group->id }});
			@endforeach

		});
	</script>
@endsection
