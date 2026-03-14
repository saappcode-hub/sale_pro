@php
	$common_settings = session()->get('business.common_settings');
@endphp
<div class="row">
	<div class="col-md-12">
		<h4>{{$stock_details['variation']}}</h4>
	</div>
	<div class="col-md-4 col-xs-4">
		<strong>@lang('lang_v1.quantities_in')</strong>
		<table class="table table-condensed">
			<tr>
				<th>@lang('report.total_purchase')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_purchase']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
				<th>@lang('lang_v1.opening_stock')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_opening_stock']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
				<th>@lang('lang_v1.total_sell_return')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_sell_return']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
				<th>@lang('lang_v1.stock_transfers') (@lang('lang_v1.in'))</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_purchase_transfer']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
                <th>@lang('Stock Reward In')</th>
                <td>
                    <span class="display_currency" data-is_quantity="true">{{$stock_details['total_rewards_in']}}</span> {{$stock_details['unit']}}
                </td>
            </tr>
			<tr>
                <th>@lang('Supplier Reward Receive')</th>
                <td>
                    <span class="display_currency" data-is_quantity="true">{{$stock_details['supplier_reward_exchange_receive']}}</span> {{$stock_details['unit']}}
                </td>
            </tr>
		</table>
	</div>
	<div class="col-md-4 col-xs-4">
		<strong>@lang('lang_v1.quantities_out')</strong>
		<table class="table table-condensed">
			<tr>
				<th>@lang('lang_v1.total_sold')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_sold']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
				<th>@lang('report.total_stock_adjustment')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_adjusted']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
				<th>@lang('lang_v1.total_purchase_return')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_purchase_return']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			
			<tr>
				<th>@lang('Stock_transfers') (@lang('lang_v1.out'))</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['total_sell_transfer']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
			<tr>
                <th>@lang('Stock Reward Out')</th>
                <td>
                    <span class="display_currency" data-is_quantity="true">{{$stock_details['total_rewards_out']}}</span> {{$stock_details['unit']}}
                </td>
            </tr>
			<tr>
				<th>@lang('Supplier Reward Exchange')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['supplier_reward_exchange']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
		</table>
	</div>

	<div class="col-md-4 col-xs-4">
		<strong>@lang('lang_v1.totals')</strong>
		<table class="table table-condensed">
			<tr>
				<th>@lang('report.current_stock')</th>
				<td>
					<span class="display_currency" data-is_quantity="true">{{$stock_details['current_stock']}}</span> {{$stock_details['unit']}}
				</td>
			</tr>
		</table>
	</div>
</div>
<div class="row">
	<div class="col-md-12">
		<hr>
		<table class="table table-slim" id="stock_history_table">
			<thead>
			<tr>
				<th>@lang('lang_v1.type')</th>
				<th>@lang('lang_v1.quantity_change')</th>
				@if(!empty($common_settings['enable_secondary_unit']))
					<th>@lang('lang_v1.quantity_change') (@lang('lang_v1.secondary_unit'))</th>
				@endif
				<th>@lang('lang_v1.new_quantity')</th>
				@if(!empty($common_settings['enable_secondary_unit']))
					<th>@lang('lang_v1.new_quantity') (@lang('lang_v1.secondary_unit'))</th>
				@endif
				<th>@lang('lang_v1.date')</th>
				<th>@lang('purchase.ref_no')</th>
				<th>@lang('lang_v1.customer_supplier_info')</th>
			</tr>
			</thead>
			<tbody id="stock_history_tbody">
				@forelse($stock_history as $history)
					<tr>
						<td>{{$history['type_label']}}</td>
						@if($history['quantity_change'] > 0 )
							<td class="text-success"> +<span class="display_currency" data-is_quantity="true">{{$history['quantity_change']}}</span>
							</td>
						@else
							<td class="text-danger"><span class="display_currency text-danger" data-is_quantity="true">{{$history['quantity_change']}}</span>
							</td>
						@endif

						@if(!empty($common_settings['enable_secondary_unit']))
							@if($history['quantity_change'] > 0 )
								<td class="text-success">
									@if(!empty($history['purchase_secondary_unit_quantity']))
										+<span class="display_currency" data-is_quantity="true">{{$history['purchase_secondary_unit_quantity']}}</span> {{$stock_details['second_unit']}}
									@endif
								</td>
							@else
								<td class="text-danger">
									@if(!empty($history['sell_secondary_unit_quantity']))
										-<span class="display_currency" data-is_quantity="true">{{$history['sell_secondary_unit_quantity']}}</span> {{$stock_details['second_unit']}}
									@endif
								</td>
							@endif
						@endif
						<td>
							<span class="display_currency" data-is_quantity="true">{{$history['stock']}}</span>
						</td>
						@if(!empty($common_settings['enable_secondary_unit']))
							<td>
								@if(!empty($stock_details['second_unit']))
									<span class="display_currency" data-is_quantity="true">{{$history['stock_in_second_unit']}}</span> {{$stock_details['second_unit']}}
								@endif
							</td>
						@endif
						<td>{{@format_datetime($history['date'])}}</td>
						<td>
							{{$history['ref_no']}}
						</td>
						<td>
							{{$history['contact_name'] ?? '--'}} 
							@if(!empty($history['supplier_business_name']))
								- {{$history['supplier_business_name']}}
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="text-center">
						@lang('lang_v1.no_stock_history_found')
					</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>

{{-- ================================================================== --}}
{{-- Server-side pagination state (hidden) --}}
{{-- ================================================================== --}}
<input type="hidden" id="sh_page" value="1">
<input type="hidden" id="sh_last" value="{{$history_last_page ?? 1}}">
<input type="hidden" id="sh_total" value="{{$history_total ?? 0}}">
<input type="hidden" id="sh_stock" value="{{$stock_details['current_stock']}}">
<input type="hidden" id="sh_per_page" value="25">
<input type="hidden" id="sh_sec_unit" value="{{$stock_details['second_unit'] ?? ''}}">
<input type="hidden" id="sh_has_sec" value="{{!empty($common_settings['enable_secondary_unit']) ? '1' : '0'}}">
<input type="hidden" id="sh_next_page_stock" value="{{$next_page_stock ?? 0}}">

<script>
$(document).ready(function() {
    // Hide DT pagination only (keep Show entries, export buttons, info text)
    var dtW = $('#stock_history_table').closest('.dataTables_wrapper');
    dtW.find('.dataTables_paginate').hide();

    // Fix DT info to show real total
    shFixInfo();

    // Build our pagination
    shBuildPag();

    // Intercept "Show entries" dropdown — reload from server
    dtW.find('.dataTables_length select').off('change').on('change', function(e) {
        e.stopImmediatePropagation(); e.preventDefault();
        var v = $(this).val();
        $('#sh_per_page').val(v == '-1' ? parseInt($('#sh_total').val()) || 99999 : parseInt(v));
        shGoPage(1, true); // true = per_page changed, don't use page_start_stock
        return false;
    });

    // Our pagination clicks
    $(document).on('click', '#sh_pagination a.sh-pg', function(e) {
        e.preventDefault();
        var pg = $(this).data('pg');
        if (pg) shGoPage(pg, false);
    });
});

function shGoPage(page, perPageChanged) {
    var url = $('#stock_history_url').val();
    var loc = $('#location_id').val();
    var cs = parseFloat($('#sh_stock').val()) || 0;
    var pp = parseInt($('#sh_per_page').val()) || 25;
    var curPage = parseInt($('#sh_page').val()) || 1;
    var total = parseInt($('#sh_total').val()) || 0;

    if (!url) return;

    // Destroy DT
    if ($.fn.DataTable.isDataTable('#stock_history_table')) {
        $('#stock_history_table').DataTable().destroy();
    }

    $('#stock_history_tbody').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></td></tr>');

    // Build request data — send cached values so server skips heavy queries
    var reqData = {
        action: 'history',
        location_id: loc,
        page: page,
        per_page: pp,
        current_stock: cs,
        cached_total: total  // Server skips COUNT
    };

    // If going to next page and we have next_page_stock, send it
    // Server skips the "sum newer rows" query
    if (!perPageChanged && page === curPage + 1) {
        var nps = parseFloat($('#sh_next_page_stock').val());
        if (!isNaN(nps)) reqData.page_start_stock = nps;
    }

    $.ajax({
        url: url, type: 'GET', dataType: 'json', data: reqData,
        success: function(r) {
            if (!r.success) { $('#stock_history_tbody').html('<tr><td colspan="8" class="text-center text-danger">Error</td></tr>'); return; }

            shRender(r.data);
            $('#sh_page').val(r.page);
            $('#sh_last').val(r.last_page);
            $('#sh_total').val(r.total);
            $('#sh_per_page').val(r.per_page);
            if (r.next_page_stock !== undefined && r.next_page_stock !== null) {
                $('#sh_next_page_stock').val(r.next_page_stock);
            }

            shBuildPag();

            // Re-init DT (keeps Show entries + export buttons)
            try {
                $('#stock_history_table').DataTable({
                    searching: false, ordering: false,
                    pageLength: r.per_page > 10000 ? -1 : r.per_page
                });
                var dtW = $('#stock_history_table').closest('.dataTables_wrapper');
                dtW.find('.dataTables_paginate').hide();
                shFixInfo();

                // Re-attach Show entries interceptor
                dtW.find('.dataTables_length select').off('change').on('change', function(e) {
                    e.stopImmediatePropagation(); e.preventDefault();
                    var v = $(this).val();
                    $('#sh_per_page').val(v == '-1' ? parseInt($('#sh_total').val()) || 99999 : parseInt(v));
                    shGoPage(1, true);
                    return false;
                });
            } catch(ex){}

            if (typeof __currency_convert_recursively === 'function') {
                __currency_convert_recursively($('#stock_history_table'));
            }
        },
        error: function() {
            $('#stock_history_tbody').html('<tr><td colspan="8" class="text-center text-danger">Error loading data.</td></tr>');
        }
    });
}

function shFixInfo() {
    var total = parseInt($('#sh_total').val()) || 0;
    var pp = parseInt($('#sh_per_page').val()) || 25;
    var pg = parseInt($('#sh_page').val()) || 1;
    var s = (pg-1)*pp+1, e = Math.min(pg*pp, total);
    var txt = 'Showing ' + s + ' to ' + e + ' of ' + total.toString().replace(/\B(?=(\d{3})+(?!\d))/g,",") + ' entries';
    var dtW = $('#stock_history_table').closest('.dataTables_wrapper');
    dtW.find('.dataTables_info').text(txt);
}

function shRender(data) {
    var $tb = $('#stock_history_tbody'), su = $('#sh_sec_unit').val(), hs = $('#sh_has_sec').val()==='1', h = '';
    if (!data||!data.length) { $tb.html('<tr><td colspan="8" class="text-center">@lang("lang_v1.no_stock_history_found")</td></tr>'); return; }
    for (var i=0; i<data.length; i++) {
        var r=data[i]; h+='<tr>';
        h+='<td>'+_e(r.type_label)+'</td>';
        if(r.quantity_change>0) h+='<td class="text-success"> +<span class="display_currency" data-is_quantity="true">'+r.quantity_change+'</span></td>';
        else h+='<td class="text-danger"><span class="display_currency text-danger" data-is_quantity="true">'+r.quantity_change+'</span></td>';
        if(hs){if(r.quantity_change>0){h+='<td class="text-success">';if(r.purchase_secondary_unit_quantity)h+='+<span class="display_currency" data-is_quantity="true">'+r.purchase_secondary_unit_quantity+'</span> '+_e(su);h+='</td>';}else{h+='<td class="text-danger">';if(r.sell_secondary_unit_quantity)h+='-<span class="display_currency" data-is_quantity="true">'+r.sell_secondary_unit_quantity+'</span> '+_e(su);h+='</td>';}}
        h+='<td><span class="display_currency" data-is_quantity="true">'+r.stock+'</span></td>';
        if(hs){h+='<td>';if(su)h+='<span class="display_currency" data-is_quantity="true">'+(r.stock_in_second_unit||0)+'</span> '+_e(su);h+='</td>';}
        h+='<td>'+_e(r.date||'')+'</td>';
        h+='<td>'+_e(r.ref_no||'')+'</td>';
        h+='<td>'+_e(r.contact_name||'--');if(r.supplier_business_name)h+=' - '+_e(r.supplier_business_name);h+='</td>';
        h+='</tr>';
    }
    $tb.html(h);
}

function shBuildPag() {
    var c=parseInt($('#sh_page').val())||1,l=parseInt($('#sh_last').val())||1,$p=$('#sh_pagination');
    if(l<=1){$p.html('');return;}
    var h='';
    h+='<li class="'+(c<=1?'disabled':'')+'"><a href="#" class="sh-pg" data-pg="'+(c>1?c-1:'')+'">Previous</a></li>';
    var s=Math.max(1,c-3),e=Math.min(l,c+3);
    if(s>1){h+='<li><a href="#" class="sh-pg" data-pg="1">1</a></li>';if(s>2)h+='<li class="disabled"><span>...</span></li>';}
    for(var p=s;p<=e;p++)h+='<li class="'+(p===c?'active':'')+'"><a href="#" class="sh-pg" data-pg="'+p+'">'+p+'</a></li>';
    if(e<l){if(e<l-1)h+='<li class="disabled"><span>...</span></li>';h+='<li><a href="#" class="sh-pg" data-pg="'+l+'">'+l+'</a></li>';}
    h+='<li class="'+(c>=l?'disabled':'')+'"><a href="#" class="sh-pg" data-pg="'+(c<l?c+1:'')+'">Next</a></li>';
    $p.html(h);
}

function _e(t){if(!t)return '';var d=document.createElement('div');d.appendChild(document.createTextNode(t));return d.innerHTML;}
</script>

<div class="row" style="margin-top:5px;">
    <div class="col-md-12 text-right">
        <ul class="pagination" id="sh_pagination" style="margin:0;"></ul>
    </div>
</div>