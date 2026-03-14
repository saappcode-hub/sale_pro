<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Exports\ProductsExport;
use App\Media;
use App\Product;
use App\ProductPriceRange;
use App\ProductVariation;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationLocationDetails;
use App\VariationTemplate;
use App\Warranty;
use App\TransactionSellLine;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ProductsCreatedOrModified;

class ProductController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    private $barcode_types;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;

        //barcode types
        $this->barcode_types = $this->productUtil->barcode_types();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('product.view') && !auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        
        $business_id = request()->session()->get('user.business_id');
        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

        if (request()->ajax()) {
            try {
                // Add debugging
                \Log::info('Ajax request received', request()->all());
                
                // Filter by location and price group
                $location_id = request()->get('location_id', null);
                $permitted_locations = auth()->user()->permitted_locations();
                $groupPrice = request()->get('group_price', null);
                
                $query = Product::with(['media', 'product_locations'])
                    ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                    ->join('units', 'products.unit_id', '=', 'units.id')
                    ->leftJoin('units as purchase_units', 'products.purchase_unit', '=', 'purchase_units.id')
                    ->leftJoin('categories as c1', 'products.category_id', '=', 'c1.id')
                    ->leftJoin('categories as c2', 'products.sub_category_id', '=', 'c2.id')
                    ->leftJoin('tax_rates', 'products.tax', '=', 'tax_rates.id')
                    ->join('variations as v', 'v.product_id', '=', 'products.id')
                    ->leftJoin('variation_group_prices as vgp', function ($join) use ($groupPrice) {
                        $join->on('v.id', '=', 'vgp.variation_id')
                            ->whereNull('vgp.deleted_at');
                        if (!empty($groupPrice)) {
                            $join->where('vgp.price_group_id', $groupPrice);
                        }
                    })
                    ->leftJoin('selling_price_groups as spg', 'vgp.price_group_id', '=', 'spg.id')
                    ->whereNull('v.deleted_at')
                    ->where('products.business_id', $business_id)
                    ->where('products.type', '!=', 'modifier');

                // Apply location filter - Fixed logic
                if (!empty($location_id) && $location_id != 'none') {
                    if ($permitted_locations == 'all' || in_array($location_id, $permitted_locations)) {
                        $query->whereHas('product_locations', function ($q) use ($location_id) {
                            $q->where('product_locations.location_id', $location_id);
                        });
                    }
                } elseif ($location_id == 'none') {
                    $query->doesntHave('product_locations');
                } else {
                    // When no specific location is selected, filter by permitted locations
                    if ($permitted_locations != 'all') {
                        $query->whereHas('product_locations', function ($q) use ($permitted_locations) {
                            $q->whereIn('product_locations.location_id', $permitted_locations);
                        });
                    }
                }

                // Build stock calculation using a simpler approach
                $stockSelect = DB::raw('(
                    SELECT COALESCE(SUM(vld.qty_available), 0) 
                    FROM variation_location_details vld 
                    WHERE vld.variation_id = v.id'
                    . ($permitted_locations != 'all' ? ' AND vld.location_id IN (' . implode(',', array_map('intval', $permitted_locations)) . ')' : '')
                    . (!empty($location_id) && $location_id != 'none' ? ' AND vld.location_id = ' . intval($location_id) : '')
                    . ') as current_stock'
                );

                // Build the main select with stock calculation
                $products = $query->select(
                    'products.id',
                    'products.name as product',
                    'products.type',
                    'c1.name as category',
                    'c2.name as sub_category',
                    'units.actual_name as unit',
                    'brands.name as brand',
                    'tax_rates.name as tax',
                    'products.sku',
                    'products.purchase_code',
                    'products.image',
                    'products.img_path_define',
                    'products.group_product',
                    'products.product_kpi',
                    'products.enable_stock',
                    'products.is_inactive',
                    'products.not_for_selling',
                    'products.purchase_unit',
                    'purchase_units.actual_name as purchase_unit_name',
                    'purchase_units.base_unit_multiplier as base_unit_multiplier',
                    'products.product_custom_field1', 'products.product_custom_field2', 'products.product_custom_field3', 'products.product_custom_field4', 'products.product_custom_field5', 'products.product_custom_field6',
                    'products.product_custom_field7', 'products.product_custom_field8', 'products.product_custom_field9',
                    'products.product_custom_field10', 'products.product_custom_field11', 'products.product_custom_field12',
                    'products.product_custom_field13', 'products.product_custom_field14', 'products.product_custom_field15',
                    'products.product_custom_field16', 'products.product_custom_field17', 'products.product_custom_field18', 
                    'products.product_custom_field19', 'products.product_custom_field20',
                    'products.alert_quantity',
                    DB::raw('MAX(v.sell_price_inc_tax) as max_price'),
                    DB::raw('MIN(v.sell_price_inc_tax) as min_price'),
                    DB::raw('MAX(v.dpp_inc_tax) as max_purchase_price'),
                    DB::raw('MIN(v.dpp_inc_tax) as min_purchase_price'),
                    DB::raw("GROUP_CONCAT(DISTINCT spg.name SEPARATOR ', ') as group_prices"),
                    $stockSelect
                );

                if ($is_woocommerce) {
                    $products->addSelect('woocommerce_disable_sync');
                }
                
                $products->groupBy('products.id');

                // Apply additional filters
                $type = request()->get('type', null);
                if (!empty($type)) {
                    $products->where('products.type', $type);
                }

                $product_kpi = request()->get('product_kpi', null);
                if (!empty($product_kpi)) {
                    $products->where('products.product_kpi', $product_kpi);
                }

                $groupProduct = request()->get('group_product', null);
                if (!empty($groupProduct)) {
                    $products->where('products.group_product', $groupProduct);
                }

                $groupPrice = request()->get('group_price', null);
                if (!empty($groupPrice)) {
                    $products->where('vgp.price_group_id', $groupPrice);
                }

                $category_id = request()->get('category_id', null);
                if (!empty($category_id)) {
                    $products->where('products.category_id', $category_id);
                }

                $brand_id = request()->get('brand_id', null);
                if (!empty($brand_id)) {
                    $products->where('products.brand_id', $brand_id);
                }

                $unit_id = request()->get('unit_id', null);
                if (!empty($unit_id)) {
                    $products->where('products.unit_id', $unit_id);
                }

                $tax_id = request()->get('tax_id', null);
                if (!empty($tax_id)) {
                    $products->where('products.tax', $tax_id);
                }

                $active_state = request()->get('active_state', null);
                if ($active_state == 'active') {
                    $products->Active();
                }
                if ($active_state == 'inactive') {
                    $products->Inactive();
                }
                
                $not_for_selling = request()->get('not_for_selling', null);
                if ($not_for_selling == 'true') {
                    $products->ProductNotForSales();
                }

                $woocommerce_enabled = request()->get('woocommerce_enabled', 0);
                if ($woocommerce_enabled == 1) {
                    $products->where('products.woocommerce_disable_sync', 0);
                }

                if (!empty(request()->get('repair_model_id'))) {
                    $products->where('products.repair_model_id', request()->get('repair_model_id'));
                }

                // Add logging for debugging
                \Log::info('Final query SQL: ' . $products->toSql());
                \Log::info('Query bindings: ', $products->getBindings());

                return Datatables::of($products)
                    ->addColumn(
                        'product_locations',
                        function ($row) {
                            return $row->product_locations->implode('name', ', ');
                        }
                    )
                    ->addColumn('group_product', function ($row) {
                        return $row->group_product == 1 ? 'Normal' : ($row->group_product == 2 ? 'Wholesale' : '');
                    })
                    ->addColumn('product_kpi', function ($row) {
                        return $row->product_kpi == 1 ? 'No' : ($row->product_kpi == 2 ? 'Yes' : '');
                    })
                    ->addColumn('group_prices', function ($row) {
                        return $row->group_prices ? $row->group_prices : '-';
                    })
                    ->editColumn('category', '{{$category}} @if(!empty($sub_category))<br/> -- {{$sub_category}}@endif')
                    ->addColumn(
                        'action',
                        function ($row) use ($selling_price_group_count) {
                            $html =
                            '<div class="btn-group"><button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu dropdown-menu-left" role="menu"><li><a href="'.action([\App\Http\Controllers\LabelsController::class, 'show']).'?product_id='.$row->id.'" data-toggle="tooltip" title="'.__('lang_v1.label_help').'"><i class="fa fa-barcode"></i> '.__('barcode.labels').'</a></li>';

                            if (auth()->user()->can('product.view')) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'view'], [$row->id]).'" class="view-product"><i class="fa fa-eye"></i> '.__('messages.view').'</a></li>';
                            }

                            if (auth()->user()->can('product.update')) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'edit'], [$row->id]).'"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</a></li>';
                            }

                            if (auth()->user()->can('product.delete')) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'destroy'], [$row->id]).'" class="delete-product"><i class="fa fa-trash"></i> '.__('messages.delete').'</a></li>';
                            }

                            if ($row->is_inactive == 1) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'activate'], [$row->id]).'" class="activate-product"><i class="fas fa-check-circle"></i> '.__('lang_v1.reactivate').'</a></li>';
                            }

                            $html .= '<li class="divider"></li>';

                            if ($row->enable_stock == 1 && auth()->user()->can('product.opening_stock')) {
                                $html .=
                                '<li><a href="#" data-href="'.action([\App\Http\Controllers\OpeningStockController::class, 'add'], ['product_id' => $row->id]).'" class="add-opening-stock"><i class="fa fa-database"></i> '.__('lang_v1.add_edit_opening_stock').'</a></li>';
                            }

                            if (auth()->user()->can('product.view')) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'productStockHistory'], [$row->id]).'"><i class="fas fa-history"></i> '.__('lang_v1.product_stock_history').'</a></li>';
                            }

                            if (auth()->user()->can('product.create')) {
                                if ($selling_price_group_count > 0) {
                                    $html .=
                                    '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'], [$row->id]).'"><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_selling_price_group_prices').'</a></li>';
                                }

                                $html .=
                                    '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'create'], ['d' => $row->id]).'"><i class="fa fa-copy"></i> '.__('lang_v1.duplicate_product').'</a></li>';
                            }

                            if (!empty($row->media->first())) {
                                $html .=
                                    '<li><a href="'.$row->media->first()->display_url.'" download="'.$row->media->first()->display_name.'"><i class="fas fa-download"></i> '.__('lang_v1.product_brochure').'</a></li>';
                            }

                            $html .= '</ul></div>';

                            return $html;
                        }
                    )
                    ->editColumn('product', function ($row) use ($is_woocommerce) {
                        $product = $row->is_inactive == 1 ? $row->product.' <span class="label bg-gray">'.__('lang_v1.inactive').'</span>' : $row->product;

                        $product = $row->not_for_selling == 1 ? $product.' <span class="label bg-gray">'.__('lang_v1.not_for_selling').
                            '</span>' : $product;

                        if ($is_woocommerce && !$row->woocommerce_disable_sync) {
                            $product = $product.'<br><i class="fab fa-wordpress"></i>';
                        }

                        return $product;
                    })
                    ->editColumn('image', function ($row) use ($business_id) {
                        if ($row->img_path_define == 1) {
                            return '<div style="display: flex;"><img src="'.urldecode($row->image_url).'" alt="Product image" class="product-thumbnail-small"></div>';
                        } else {
                            $s3image = "https://piik-data.sgp1.digitaloceanspaces.com/piik-data/salepro/public/image/".$business_id.'/'.$row->image;
                            return '<div style="display: flex;"><img src="'.$s3image.'" alt="Product image" class="product-thumbnail-small"></div>';
                        }
                    })
                    ->editColumn('type', function ($row) {
                        if($row->type == 'combo_single'){
                            return 'Combo Single';
                        }elseif($row->type == 'combo'){
                            return 'Combo';
                        }elseif($row->type == 'single'){
                            return 'Single';
                        }elseif($row->type == 'variable'){
                            return 'Variable';
                        }else{
                            return ' ';
                        }
                    })
                    ->addColumn('mass_delete', function ($row) {
                        return  '<input type="checkbox" class="row-select" value="'.$row->id.'">';
                    })
                    ->editColumn('current_stock', function ($row) {
                        if ($row->enable_stock) {
                            $stock = $this->productUtil->num_f($row->current_stock, false, null, true);
                            return $stock.' '.$row->unit;
                        } else {
                            return '--';
                        }
                    })
                    ->addColumn('quantity', function ($row) {
                        if ($row->enable_stock) {
                            $base_unit_multiplier = $row->base_unit_multiplier ?? 1;
                            $stock = $row->current_stock ?? 0;
                            if ($row->purchase_unit !== null) {
                                if ($stock < $base_unit_multiplier && $stock > 0) {
                                    return number_format($stock, 2) . " {$row->unit}";
                                }
                                $whole_units = intdiv($stock, $base_unit_multiplier);
                                $remaining_units = $stock % $base_unit_multiplier;
                                if ($remaining_units === 0) {
                                    return "{$whole_units} {$row->purchase_unit_name}";
                                }
                                return "{$whole_units} {$row->purchase_unit_name} {$remaining_units} {$row->unit}";
                            } else {
                                return number_format($stock, 2) . " {$row->unit}";
                            }
                        } else {
                            return '--';
                        }
                    })                                                                         
                    ->addColumn(
                        'purchase_price',
                        '<div style="white-space: nowrap;">@format_currency($min_purchase_price) @if($max_purchase_price != $min_purchase_price && $type == "variable") -  @format_currency($max_purchase_price)@endif </div>'
                    )
                    ->addColumn(
                        'selling_price',
                        '<div style="white-space: nowrap;">@format_currency($min_price) @if($max_price != $min_price && $type == "variable") -  @format_currency($max_price)@endif </div>'
                    )
                    ->filterColumn('products.sku', function ($query, $keyword) {
                        $query->whereHas('variations', function ($q) use ($keyword) {
                            $q->where('sub_sku', 'like', "%{$keyword}%");
                        })
                        ->orWhere('products.sku', 'like', "%{$keyword}%");
                    })
                    ->filterColumn('products.purchase_code', function ($query, $keyword) {
                        $query->where('products.purchase_code', 'like', "%{$keyword}%");
                    })
                    ->setRowAttr([
                        'data-href' => function ($row) {
                            if (auth()->user()->can('product.view')) {
                                return  action([\App\Http\Controllers\ProductController::class, 'view'], [$row->id]);
                            } else {
                                return '';
                            }
                        }, ])
                    ->rawColumns(['action', 'image', 'mass_delete', 'product', 'selling_price', 'purchase_price', 'category', 'current_stock'])
                    ->make(true);
                    
            } catch (\Exception $e) {
                \Log::error('DataTables error: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                
                return response()->json([
                    'error' => 'An error occurred while processing the request: ' . $e->getMessage()
                ], 500);
            }
        }

        $rack_enabled = (request()->session()->get('business.enable_racks') || request()->session()->get('business.enable_row') || request()->session()->get('business.enable_position'));

        $categories = Category::forDropdown($business_id, 'product');

        $brands = Brands::forDropdown($business_id);

        $units = Unit::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, false);
        $taxes = $tax_dropdown['tax_rates'];

        $business_locations = BusinessLocation::forDropdown($business_id);
        $business_locations->prepend(__('lang_v1.none'), 'none');

        if ($this->moduleUtil->isModuleInstalled('Manufacturing') && (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            $show_manufacturing_data = true;
        } else {
            $show_manufacturing_data = false;
        }

        $pos_module_data = $this->moduleUtil->getModuleData('get_filters_for_list_product_screen');

        $is_admin = $this->productUtil->is_admin(auth()->user());

        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                ->whereNull('deleted_at')
                ->active()
                ->pluck('name', 'id');

        return view('product.index')
            ->with(compact(
                'rack_enabled',
                'categories',
                'brands',
                'units',
                'taxes',
                'business_locations',
                'show_manufacturing_data',
                'pos_module_data',
                'is_woocommerce',
                'is_admin',
                'price_groups'
            ));
    }

    /**
 * ============================================================================
 *  REPLACE productStockHistory() in ProductController.php
 * ============================================================================
 */
public function productStockHistory($id)
{
    if (!auth()->user()->can('product.view')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    if (request()->ajax()) {
        $location_id = request()->input('location_id');
        $action = request()->input('action', 'all');

        // ── Paginated history (called by JS on page click / show entries) ──
        if ($action === 'history') {
            $page     = (int) request()->input('page', 1);
            $per_page = (int) request()->input('per_page', 25);
            $current_stock = (float) request()->input('current_stock', 0);

            // These skip heavy queries on page 2+ (JS sends them)
            $cached_total     = request()->has('cached_total') ? (int) request()->input('cached_total') : null;
            $page_start_stock = request()->has('page_start_stock') ? (float) request()->input('page_start_stock') : null;

            $result = $this->productUtil->getVariationStockHistory(
                $business_id, $id, $location_id,
                $current_stock, $page, $per_page,
                $cached_total, $page_start_stock
            );

            return response()->json([
                'success'         => true,
                'data'            => $result['data'],
                'total'           => $result['total'],
                'page'            => $result['page'],
                'per_page'        => $result['per_page'],
                'last_page'       => $result['last_page'],
                'next_page_stock' => $result['next_page_stock'] ?? null,
            ]);
        }

        // ── Initial load (returns rendered blade with page 1) ────────────
        $stock_details = $this->productUtil->getVariationStockDetails(
            $business_id, $id, $location_id
        );
        if (!is_array($stock_details)) $stock_details = [];

        $calculated_current_stock = ($stock_details['total_opening_stock']??0)
            + ($stock_details['total_purchase']??0) + ($stock_details['total_purchase_transfer']??0)
            + ($stock_details['total_sell_return']??0) + ($stock_details['total_rewards_in']??0)
            - ($stock_details['total_sold']??0) - ($stock_details['total_sell_transfer']??0)
            - ($stock_details['total_purchase_return']??0) - ($stock_details['total_adjusted']??0)
            - ($stock_details['total_rewards_out']??0)
            + ($stock_details['supplier_reward_exchange']??0) + ($stock_details['supplier_reward_exchange_receive']??0);
        $stock_details['current_stock'] = $calculated_current_stock;

        $vld = VariationLocationDetails::where('variation_id', $id)->where('location_id', $location_id)->first();
        if ($vld && (float)$calculated_current_stock != (float)$vld->qty_available) {
            $vld->update(['qty_available' => $calculated_current_stock]);
        }

        $history_result = $this->productUtil->getVariationStockHistory(
            $business_id, $id, $location_id, $calculated_current_stock, 1, 25
        );
        $stock_history     = $history_result['data'] ?? [];
        $history_total     = $history_result['total'] ?? 0;
        $history_last_page = $history_result['last_page'] ?? 1;
        $next_page_stock   = $history_result['next_page_stock'] ?? 0;

        return view('product.stock_history_details')
            ->with(compact('stock_details','stock_history','history_total','history_last_page','next_page_stock'));
    }

    $product = Product::where('business_id', $business_id)
        ->with(['variations','variations.product_variation'])->findOrFail($id);
    $business_locations = BusinessLocation::forDropdown($business_id);
    return view('product.stock_history')->with(compact('product','business_locations'));
}

    /**
     * Refresh stock data by comparing calculated stock with stored stock
     */
    public function refreshStockData(Request $request)
    {
        if (!auth()->user()->can('product.view')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $business_id = $request->input('business_id') ?? request()->session()->get('user.business_id');
            $product_id = $request->input('product_id');
            $location_id = $request->input('location_id');
            
            \Log::info('Starting stock refresh', [
                'business_id' => $business_id,
                'product_id' => $product_id,
                'location_id' => $location_id,
                'user_id' => auth()->id()
            ]);
            
            if (empty($business_id)) {
                return response()->json(['success' => false, 'message' => 'Business ID is required'], 400);
            }
            
            // Get variation location details
            $query = DB::table('variation_location_details as vld')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('p.business_id', $business_id)
                ->select('vld.variation_id', 'vld.location_id', 'vld.qty_available', 'vld.product_id', 'p.name as product_name');

            if (!empty($product_id)) {
                $query->where('p.id', $product_id);
            }
            if (!empty($location_id)) {
                $query->where('vld.location_id', $location_id);
            }

            $variationLocationDetails = $query->get();
            \Log::info('Found ' . $variationLocationDetails->count() . ' variation location details to check');
            
            $totalCount = $variationLocationDetails->count();
            $processedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errors = [];
            $updatedProducts = []; // Store products that were updated for activity log

            foreach ($variationLocationDetails as $detail) {
                try {
                    // Get current qty_available from variation_location_details
                    $qty_available = (float) $detail->qty_available; // Example: 10.20
                    
                    // Get stock details and calculate current stock
                    $stockDetails = $this->productUtil->getVariationStockDetails(
                        $business_id,
                        $detail->variation_id,
                        $detail->location_id
                    );

                    // Calculate the real current stock
                    $calculated_current_stock = ($stockDetails['total_opening_stock'] ?? 0)
                        + ($stockDetails['total_purchase'] ?? 0)
                        + ($stockDetails['total_purchase_transfer'] ?? 0)
                        + ($stockDetails['total_sell_return'] ?? 0)
                        + ($stockDetails['total_rewards_in'] ?? 0)
                        - ($stockDetails['total_sold'] ?? 0)
                        - ($stockDetails['total_sell_transfer'] ?? 0)
                        - ($stockDetails['total_purchase_return'] ?? 0)
                        - ($stockDetails['total_adjusted'] ?? 0)
                        - ($stockDetails['total_rewards_out'] ?? 0)
                        + ($stockDetails['supplier_reward_exchange'] ?? 0)
                        + ($stockDetails['supplier_reward_exchange_receive'] ?? 0);
                    // Example: calculated_current_stock = 20.00

                    // Compare qty_available vs calculated_current_stock
                    if (abs($calculated_current_stock - $qty_available) > 0.0001) {
                        // NOT EQUAL: 10.20 ≠ 20.00 → UPDATE qty_available to 20.00
                        DB::table('variation_location_details')
                            ->where('variation_id', $detail->variation_id)
                            ->where('location_id', $detail->location_id)
                            ->where('product_id', $detail->product_id)
                            ->update([
                                'qty_available' => $calculated_current_stock,
                                'updated_at' => now()
                            ]);
                        
                        $updatedCount++;
                        
                        // Store updated product info for activity log (simplified format)
                        $productKey = $detail->product_id;
                        $updatedProducts[$productKey] = [
                            'old' => $qty_available,
                            'new' => $calculated_current_stock
                        ];
                        
                        \Log::info("Stock updated", [
                            'product_name' => $detail->product_name,
                            'variation_id' => $detail->variation_id,
                            'location_id' => $detail->location_id,
                            'old_qty_available' => $qty_available,
                            'calculated_current_stock' => $calculated_current_stock,
                            'difference' => $calculated_current_stock - $qty_available
                        ]);
                    } else {
                        // EQUAL: calculated_current_stock = qty_available → SKIP
                        $skippedCount++;
                    }

                    $processedCount++;
                    
                    if ($processedCount % 50 == 0) {
                        \Log::info("Progress: {$processedCount}/{$totalCount} processed, {$updatedCount} updated, {$skippedCount} skipped");
                    }

                } catch (\Exception $e) {
                    $errors[] = "Error processing variation {$detail->variation_id}: " . $e->getMessage();
                    $processedCount++;
                }
            }

            \Log::info("Stock refresh completed", [
                'total_checked' => $totalCount,
                'total_updated' => $updatedCount,
                'total_skipped' => $skippedCount,
                'errors_count' => count($errors)
            ]);

            // Store activity log only if products were updated
            if ($updatedCount > 0 && !empty($updatedProducts)) {
                // Insert activity log with simplified properties format
                DB::table('activity_log')->insert([
                    'log_name' => 'default',
                    'description' => 'refresh_stock',
                    'subject_id' => null,
                    'subject_type' => 'App\Product',
                    'event' => null,
                    'business_id' => $business_id,
                    'causer_id' => auth()->id(),
                    'causer_type' => 'App\User',
                    'properties' => json_encode($updatedProducts),
                    'batch_uuid' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                \Log::info("Activity log created for stock refresh", [
                    'updated_products_count' => count($updatedProducts),
                    'total_variations_updated' => $updatedCount,
                    'causer_id' => auth()->id()
                ]);
            }

            // Enhanced return messages
            if ($updatedCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Stock refresh completed",
                    'total_checked' => $totalCount,
                    'total_updated' => $updatedCount,
                    'total_skipped' => $skippedCount,
                    'errors_count' => count($errors)
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => "No stock refresh",
                    'total_checked' => $totalCount,
                    'total_updated' => 0,
                    'total_skipped' => $totalCount,
                    'errors_count' => count($errors)
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Stock refresh error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error during stock refresh: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock refresh progress
     */
    public function getStockRefreshProgress()
    {
        $business_id = request()->session()->get('user.business_id');
        $progress = cache()->get("stock_refresh_progress_{$business_id}", [
            'processed' => 0,
            'total' => 0,
            'percentage' => 0,
            'updated' => 0,
            'completed' => true
        ]);

        return response()->json($progress);
    }

    public function updateKpi(Request $request)
    {
        try {
            $selected_products = $request->input('selected_products');
            $product_kpi = $request->input('product_kpi');

            Product::whereIn('id', $selected_products)
                ->update(['product_kpi' => $product_kpi]);

            return response()->json([
                'success' => true,
                'msg' => __('Updated Successfully')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'msg' => $e->getMessage()
            ]);
        }
    }

    public function getUnitsByBaseId(Request $request)
    {
        $unit_id = $request->input('unit_id');
        $business_id = $request->session()->get('user.business_id');
    
        // Retrieve the selected unit
        $selected_unit = Unit::where('id', $unit_id)
            ->where('business_id', $business_id)
            ->pluck('actual_name', 'id');
    
        // Retrieve units with the selected unit as the base_unit_id
        $related_units = Unit::where('base_unit_id', $unit_id)
            ->where('business_id', $business_id)
            ->pluck('actual_name', 'id');
    
        // Combine selected unit and related units
        $units = $selected_unit->union($related_units);
    
        return response()->json($units);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for products quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('products', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('products', $business_id, action([\App\Http\Controllers\ProductController::class, 'index']));
        }

        $categories = Category::forDropdown($business_id, 'product');

        $brands = Brands::forDropdown($business_id);
        $units = Unit::forDropdown($business_id, true);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;
        $barcode_default = $this->productUtil->barcode_default();

        $default_profit_percent = request()->session()->get('business.default_profit_percent');

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);

        //Duplicate product
        $duplicate_product = null;
        $rack_details = null;

        $sub_categories = [];
        if (! empty(request()->input('d'))) {
            $duplicate_product = Product::where('business_id', $business_id)->find(request()->input('d'));
            $duplicate_product->name .= ' (copy)';

            if (! empty($duplicate_product->category_id)) {
                $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $duplicate_product->category_id)
                        ->pluck('name', 'id')
                        ->toArray();
            }

            //Rack details
            if (! empty($duplicate_product->id)) {
                $rack_details = $this->productUtil->getRackDetails($business_id, $duplicate_product->id);
            }
        }

        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');
        $product_types = $this->product_types();

        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        //product screen view from module
        $pos_module_data = $this->moduleUtil->getModuleData('get_product_screen_top_view');

        $selling_price_group_modify = SellingPriceGroup::where('business_id', $business_id)->get();

        return view('product.create')
            ->with(compact('categories', 'brands', 'units', 'taxes', 'barcode_types', 'default_profit_percent', 'tax_attributes', 'barcode_default', 'business_locations', 'duplicate_product', 'sub_categories', 'rack_details', 'selling_price_group_count', 'module_form_parts', 'product_types', 'common_settings', 'warranties', 'pos_module_data','selling_price_group_modify'));
    }

    private function product_types()
    {
        //Product types also includes modifier.
        return ['single' => __('lang_v1.single'),
            'variable' => __('lang_v1.variable'),
            'combo' => __('lang_v1.combo'),
            'combo_single' => __('Combo Single'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        try {

            $group_product = $request->input('product_type'); // Store this in group_product
            $selling_price_group = $request->input('selling_price_group', []);
            $business_id = $request->session()->get('user.business_id');
            $form_fields = ['name', 'brand_id', 'unit_id', 'purchase_unit', 'category_id', 'tax', 'type', 'barcode_type', 'sku', 'purchase_code', 'alert_quantity', 'tax_type', 'weight', 'product_description', 'sub_unit_ids', 'preparation_time_in_minutes', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20',];
            $module_form_fields = $this->moduleUtil->getModuleFormField('product_form_fields');
            if (! empty($module_form_fields)) {
                $form_fields = array_merge($form_fields, $module_form_fields);
            }

            $product_details = $request->only($form_fields);
            $product_details['business_id'] = $business_id;
            $product_details['img_path_define'] = 2;
            $product_details['created_by'] = $request->session()->get('user.id');
            // Handle kind_product (set to 0 by default, 1 if other product selected)
            $product_details['kind_product'] = $request->input('kind_product', 0);
            $product_details['enable_stock'] = (! empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) ? 1 : 0;
            $product_details['not_for_selling'] = (! empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) ? 1 : 0;
            $product_details['group_product'] = $group_product;

            if (! empty($request->input('sub_category_id'))) {
                $product_details['sub_category_id'] = $request->input('sub_category_id');
            }

            if (! empty($request->input('secondary_unit_id'))) {
                $product_details['secondary_unit_id'] = $request->input('secondary_unit_id');
            }

            if (empty($product_details['sku'])) {
                $product_details['sku'] = ' ';
            }

            if (! empty($product_details['alert_quantity'])) {
                $product_details['alert_quantity'] = $this->productUtil->num_uf($product_details['alert_quantity']);
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ! empty($expiry_enabled) && ($product_details['enable_stock'] == 1)) {
                $product_details['expiry_period_type'] = $request->input('expiry_period_type');
                $product_details['expiry_period'] = $this->productUtil->num_uf($request->input('expiry_period'));
            }

            if (! empty($request->input('enable_sr_no')) && $request->input('enable_sr_no') == 1) {
                $product_details['enable_sr_no'] = 1;
            }

            //upload document
            // $product_details['image'] = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
            // $this->productUtil->uploadImageToS3($request, 'image', 'image');
            if($request['image'] == null){
                $product_details['image'] = null;
            }else{
                $product_details['image'] = $this->productUtil->uploadImageToS3($request, 'image', 'image');
            }
            
            $common_settings = session()->get('business.common_settings');

            $product_details['warranty_id'] = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;

            DB::beginTransaction();

            $product = Product::create($product_details);

            event(new ProductsCreatedOrModified($product_details, 'added'));

            if (empty(trim($request->input('sku')))) {
                $sku = $this->productUtil->generateProductSku($product->id);
                $product->sku = $sku;
                $product->save();
            }

            //Add product locations
            $product_locations = $request->input('product_locations');
            if (! empty($product_locations)) {
                $product->product_locations()->sync($product_locations);
            }

            if ($product->type == 'single') {
                $this->productUtil->createSingleProductVariation($product->id, $product->sku, $request->input('single_dpp'), $request->input('single_dpp_inc_tax'), $request->input('profit_percent'), $request->input('single_dsp'), $request->input('single_dsp_inc_tax'));
            } elseif ($product->type == 'variable') {
                if (! empty($request->input('product_variation'))) {
                    $input_variations = $request->input('product_variation');
                    $this->productUtil->createVariableProductVariations($product->id, $input_variations);
                }

            } elseif ($product->type == 'combo') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $this->productUtil->num_uf($quantity[$key]),
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $this->productUtil->createSingleProductVariation($product->id, $product->sku, $request->input('item_level_purchase_price_total'), $request->input('purchase_price_inc_tax'), $request->input('profit_percent'), $request->input('selling_price'), $request->input('selling_price_inc_tax'), $combo_variations);
            } elseif ($product->type == 'combo_single') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $this->productUtil->num_uf($quantity[$key]),
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $this->productUtil->createSingleProductVariation($product->id, $product->sku, $request->input('item_level_purchase_price_total'), $request->input('purchase_price_inc_tax'), $request->input('profit_percent'), $request->input('selling_price'), $request->input('selling_price_inc_tax'), $combo_variations);
            }

            //Add product racks details.
            $product_racks = $request->get('product_racks', null);
            if (! empty($product_racks)) {
                $this->productUtil->addRackDetails($business_id, $product->id, $product_racks);
            }

            //Set Module fields
            if (! empty($request->input('has_module_data'))) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);

            // Determine `price_inc_tax` based on product type
            $product_type = $request->input('type'); // single, combo, combo_single
            $price_inc_tax = 0;
            if ($product_type == 'single') {
                $price_inc_tax = $request->input('single_dsp_inc_tax', 0);
            } elseif ($product_type == 'combo' || $product_type == 'combo_single') {
                $price_inc_tax = $request->input('selling_price_inc_tax', 0);
            }

            // Handle saving for variable type products
            if ($product_type == 'variable') {
                // Fetch all variations associated with the product
                $variations = Variation::where('product_id', $product->id)->get();

                if (!empty($variations) && !empty($selling_price_group)) {
                    foreach ($variations as $variation) {
                        foreach ($selling_price_group as $price_group_id) {
                            VariationGroupPrice::create([
                                'variation_id' => $variation->id,
                                'price_group_id' => $price_group_id,
                                'price_inc_tax' => $variation->default_sell_price ?? 0, // Fetch from variation
                                'price_type' => 'fixed'
                            ]);
                        }
                    }
                }
            } else {
                // Save selling price groups for non-variable types (single, combo, combo_single)
                if (!empty($selling_price_group)) {
                    foreach ($selling_price_group as $price_group_id) {
                        // Assuming only one variation exists for these types
                        $variation = Variation::where('product_id', $product->id)->first();

                        if ($variation) {
                            VariationGroupPrice::create([
                                'variation_id' => $variation->id,
                                'price_group_id' => $price_group_id,
                                'price_inc_tax' => $price_inc_tax,
                                'price_type' => 'fixed'
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            $output = ['success' => 1,
                'msg' => __('product.product_added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return redirect('products')->with('status', $output);
        }

        if ($request->input('submit_type') == 'submit_n_add_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'submit_n_add_selling_prices') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'],
                [$product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create']
            )->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $details = $this->productUtil->getRackDetails($business_id, $id, true);

        return view('product.show')->with(compact('details'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;

        $product = Product::where('business_id', $business_id)
                            ->with(['product_locations'])
                            ->where('id', $id)
                            ->firstOrFail();

        //Sub-category
        $sub_categories = [];
        $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $product->category_id)
                        ->pluck('name', 'id')
                        ->toArray();
        $sub_categories = ['' => 'None'] + $sub_categories;
        // Fetch `group_product` value
        $group_product = $product->group_product; // Fetch from the database
        $default_profit_percent = request()->session()->get('business.default_profit_percent');

        //Get units.
        $units = Unit::forDropdown($business_id, true);
        $sub_unit_data = $this->productUtil->getSubUnits($business_id, $product->unit_id, true);
        $sub_units = isset($sub_unit_data['units']) ? $sub_unit_data['units'] : [];
        $default_selected_unit = $sub_unit_data['default_selected_unit'] ?? $product->unit_id; // Use the main unit as fallback
        
        // Ensure each sub-unit has a 'multiplier' key if $sub_units is not empty
        foreach ($sub_units as $id => $unit) {
            // Ensure each sub-unit has 'multiplier' and 'name' keys.
            if (!isset($unit['multiplier'])) {
                $sub_units[$id]['multiplier'] = 1; // Default multiplier if not set
            }
            if (!isset($unit['name'])) {
                $sub_units[$id]['name'] = 'N/A'; // Default name if not set
            }
        }
        
        // Retrieve purchase_unit options: selected unit + related units
        $purchase_units = Unit::where('business_id', $business_id)
        ->where(function($query) use ($product) {
            $query->where('id', $product->unit_id)
                ->orWhere('base_unit_id', $product->unit_id);
        })
        ->pluck('actual_name', 'id');

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);
        //Rack details
        $rack_details = $this->productUtil->getRackDetails($business_id, $id);

        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');
        $product_types = $this->product_types();
        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        //product screen view from module
        $pos_module_data = $this->moduleUtil->getModuleData('get_product_screen_top_view');

        $alert_quantity = ! is_null($product->alert_quantity) ? $this->productUtil->num_f($product->alert_quantity, false, null, true) : null;

        $selling_price_groups = SellingPriceGroup::where('business_id', $business_id)->get();

        $selected_price_groups = VariationGroupPrice::whereIn('variation_id', $product->variations->pluck('id'))
            ->whereNull('deleted_at')
            ->pluck('price_group_id')
            ->toArray();

        return view('product.edit')
                ->with(compact('categories', 'brands', 'units', 'sub_units', 'taxes', 'tax_attributes', 'barcode_types', 'product', 'purchase_units', 'sub_categories', 'default_profit_percent', 'business_locations', 'rack_details', 'selling_price_group_count', 'module_form_parts', 'product_types', 'common_settings', 'warranties', 'pos_module_data', 'alert_quantity', 'default_selected_unit', 'selling_price_groups','group_product','selected_price_groups'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
           
            $business_id = $request->session()->get('user.business_id');
            $product_details = $request->only(['name', 'brand_id', 'unit_id', 'purchase_unit', 'category_id', 'tax', 'barcode_type', 'sku', 'purchase_code', 'alert_quantity', 'tax_type', 'weight', 'product_description', 'sub_unit_ids', 'preparation_time_in_minutes', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20',]);

            DB::beginTransaction();

            $product = Product::where('business_id', $business_id)
                                ->where('id', $id)
                                ->with(['product_variations'])
                                ->first();

            $module_form_fields = $this->moduleUtil->getModuleFormField('product_form_fields');
            if (! empty($module_form_fields)) {
                foreach ($module_form_fields as $column) {
                    $product->$column = $request->input($column);
                }
            }
           
            $product->name = $product_details['name'];
            $product->brand_id = $product_details['brand_id'];
            $product->unit_id = $product_details['unit_id'];
            $product->purchase_unit = $product_details['purchase_unit'];
            $product->category_id = $product_details['category_id'];
            $product->tax = $product_details['tax'];
            $product->barcode_type = $product_details['barcode_type'];
            $product->sku = $product_details['sku'];
            $product->purchase_code= $product_details['purchase_code'];
            $product->alert_quantity = ! empty($product_details['alert_quantity']) ? $this->productUtil->num_uf($product_details['alert_quantity']) : $product_details['alert_quantity'];
            $product->tax_type = $product_details['tax_type'];
            $product->weight = $product_details['weight'];
            $product->img_path_define = 2;
            $product->product_custom_field1 = $product_details['product_custom_field1'] ?? '';
            $product->product_custom_field2 = $product_details['product_custom_field2'] ?? '';
            $product->product_custom_field3 = $product_details['product_custom_field3'] ?? '';
            $product->product_custom_field4 = $product_details['product_custom_field4'] ?? '';
            $product->product_custom_field5 = $product_details['product_custom_field5'] ?? '';
            $product->product_custom_field6 = $product_details['product_custom_field6'] ?? '';
            $product->product_custom_field7 = $product_details['product_custom_field7'] ?? '';
            $product->product_custom_field8 = $product_details['product_custom_field8'] ?? '';
            $product->product_custom_field9 = $product_details['product_custom_field9'] ?? '';
            $product->product_custom_field10 = $product_details['product_custom_field10'] ?? '';
            $product->product_custom_field11 = $product_details['product_custom_field11'] ?? '';
            $product->product_custom_field12 = $product_details['product_custom_field12'] ?? '';
            $product->product_custom_field13 = $product_details['product_custom_field13'] ?? '';
            $product->product_custom_field14 = $product_details['product_custom_field14'] ?? '';
            $product->product_custom_field15 = $product_details['product_custom_field15'] ?? '';
            $product->product_custom_field16 = $product_details['product_custom_field16'] ?? '';
            $product->product_custom_field17 = $product_details['product_custom_field17'] ?? '';
            $product->product_custom_field18 = $product_details['product_custom_field18'] ?? '';
            $product->product_custom_field19 = $product_details['product_custom_field19'] ?? '';
            $product->product_custom_field20 = $product_details['product_custom_field20'] ?? '';

            $product->product_description = $product_details['product_description'];
            $product->sub_unit_ids = null;
            // $product->sub_unit_ids = ! empty($product_details['sub_unit_ids']) ? $product_details['sub_unit_ids'] : null;
            $product->preparation_time_in_minutes = $product_details['preparation_time_in_minutes'];
            $product->warranty_id = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;
            $product->secondary_unit_id = ! empty($request->input('secondary_unit_id')) ? $request->input('secondary_unit_id') : null;
       
            // Handle the kind_product field (ensure it's saved as 1 when checked, 0 otherwise)
            $product->kind_product = !empty($request->input('kind_product')) ? 1 : 0;
          
            if (! empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) {
                $product->enable_stock = 1;
            } else {
                $product->enable_stock = 0;
            }

            $product->not_for_selling = (! empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) ? 1 : 0;

            if (! empty($request->input('sub_category_id'))) {
                $product->sub_category_id = $request->input('sub_category_id');
            } else {
                $product->sub_category_id = null;
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($expiry_enabled)) {
                if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ($product->enable_stock == 1)) {
                    $product->expiry_period_type = $request->input('expiry_period_type');
                    $product->expiry_period = $this->productUtil->num_uf($request->input('expiry_period'));
                } else {
                    $product->expiry_period_type = null;
                    $product->expiry_period = null;
                }
            }

            if (! empty($request->input('enable_sr_no')) && $request->input('enable_sr_no') == 1) {
                $product->enable_sr_no = 1;
            } else {
                $product->enable_sr_no = 0;
            }

            //upload document
            // $file_name = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
            // $this->productUtil->uploadImageToS3($request, 'image', 'image');
            if($request['image'] == null){
                $file_name = null;
            }else{
                $file_name = $this->productUtil->uploadImageToS3($request, 'image', 'image');
            }
            if (! empty($file_name)) {

                //If previous image found then remove
                if (! empty($product->image_path) && file_exists($product->image_path)) {
                    unlink($product->image_path);
                }

                $product->image = $file_name;
                //If product image is updated update woocommerce media id
                if (! empty($product->woocommerce_media_id)) {
                    $product->woocommerce_media_id = null;
                }
            }

            $product->save();
            $product->touch();

            event(new ProductsCreatedOrModified($product, 'updated'));

            //Add product locations
            $product_locations = ! empty($request->input('product_locations')) ?
                                $request->input('product_locations') : [];

            $permitted_locations = auth()->user()->permitted_locations();
            //If not assigned location exists don't remove it
            if ($permitted_locations != 'all') {
                $existing_product_locations = $product->product_locations()->pluck('id');

                foreach ($existing_product_locations as $pl) {
                    if (! in_array($pl, $permitted_locations)) {
                        $product_locations[] = $pl;
                    }
                }
            }

            $product->product_locations()->sync($product_locations);
          
            if ($product->type == 'single') {
                $single_data = $request->only(['single_variation_id', 'single_dpp', 'single_dpp_inc_tax', 'single_dsp_inc_tax', 'profit_percent', 'single_dsp']);
                $variation = Variation::find($single_data['single_variation_id']);

                $variation->sub_sku = $product->sku;
                $variation->default_purchase_price = $this->productUtil->num_uf($single_data['single_dpp']);
                $variation->dpp_inc_tax = $this->productUtil->num_uf($single_data['single_dpp_inc_tax']);
                $variation->profit_percent = $this->productUtil->num_uf($single_data['profit_percent']);
                $variation->default_sell_price = $this->productUtil->num_uf($single_data['single_dsp']);
                $variation->sell_price_inc_tax = $this->productUtil->num_uf($single_data['single_dsp_inc_tax']);
                $variation->save();

                Media::uploadMedia($product->business_id, $variation, $request, 'variation_images');
            } elseif ($product->type == 'variable') {
                //Update existing variations
                $input_variations_edit = $request->get('product_variation_edit');
                if (! empty($input_variations_edit)) {
                    $this->productUtil->updateVariableProductVariations($product->id, $input_variations_edit);
                }

                //Add new variations created.
                $input_variations = $request->input('product_variation');
                if (! empty($input_variations)) {
                    $this->productUtil->createVariableProductVariations($product->id, $input_variations);
                }
            } elseif ($product->type == 'combo') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $quantity[$key],
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $variation = Variation::find($request->input('combo_variation_id'));
                $variation->sub_sku = $product->sku;
                $variation->default_purchase_price = $this->productUtil->num_uf($request->input('item_level_purchase_price_total'));
                $variation->dpp_inc_tax = $this->productUtil->num_uf($request->input('purchase_price_inc_tax'));
                $variation->profit_percent = $this->productUtil->num_uf($request->input('profit_percent'));
                $variation->default_sell_price = $this->productUtil->num_uf($request->input('selling_price'));
                $variation->sell_price_inc_tax = $this->productUtil->num_uf($request->input('selling_price_inc_tax'));
                $variation->combo_variations = $combo_variations;
                $variation->save();
            } elseif ($product->type == 'combo_single') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $quantity[$key],
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $variation = Variation::find($request->input('combo_variation_id'));
                $variation->sub_sku = $product->sku;
                $variation->default_purchase_price = $this->productUtil->num_uf($request->input('item_level_purchase_price_total'));
                $variation->dpp_inc_tax = $this->productUtil->num_uf($request->input('purchase_price_inc_tax'));
                $variation->profit_percent = $this->productUtil->num_uf($request->input('profit_percent'));
                $variation->default_sell_price = $this->productUtil->num_uf($request->input('selling_price'));
                $variation->sell_price_inc_tax = $this->productUtil->num_uf($request->input('selling_price_inc_tax'));
                $variation->combo_variations = $combo_variations;
                $variation->save();
            }

            //Add product racks details.
            $product_racks = $request->get('product_racks', null);
            if (! empty($product_racks)) {
                $this->productUtil->addRackDetails($business_id, $product->id, $product_racks);
            }

            $product_racks_update = $request->get('product_racks_update', null);
            if (! empty($product_racks_update)) {
                $this->productUtil->updateRackDetails($business_id, $product->id, $product_racks_update);
            }

            //Set Module fields
            if (! empty($request->input('has_module_data'))) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);

            // ✅ Fetch existing Selling Price Groups
            $existing_price_groups = VariationGroupPrice::whereIn('variation_id', function($query) use ($id) {
                $query->select('id')->from('variations')->where('product_id', $id);
            })->get()->keyBy(function ($item) {
                return $item->price_group_id . '-' . $item->variation_id;
            });
            
            $selected_price_groups = $request->input('selling_price_group', []);
            
            // ✅ Process Unchecked Price Groups (Soft Delete)
            foreach ($existing_price_groups as $key => $group_price) {
                [$price_group_id, $variation_id] = explode('-', $key);
                if (!in_array($price_group_id, $selected_price_groups)) {
                    $group_price->update(['deleted_at' => now()]);
                }
            }

            // ✅ Process Newly Checked Price Groups (Insert New)
            foreach ($selected_price_groups as $price_group_id) {
                if ($product->type == 'variable') {
                    // ✅ If variable product, handle multiple variations
                    $variations = Variation::where('product_id', $id)->get();
                    foreach ($variations as $variation) {
                        $existing_record_key = $price_group_id . '-' . $variation->id;
            
                        if (isset($existing_price_groups[$existing_record_key])) {
                            // ✅ If previously soft deleted, restore by nullifying deleted_at
                            $existing_price_groups[$existing_record_key]->update(['deleted_at' => null]);
                        } else {
                            // ✅ Otherwise, create new record
                            VariationGroupPrice::create([
                                'variation_id' => $variation->id,
                                'price_group_id' => $price_group_id,
                                'price_inc_tax' => $variation->default_sell_price ?? 0,
                                'price_type' => 'fixed'
                            ]);
                        }
                    }
                } else {
                    // ✅ If single/combo/combo_single, handle only the first variation
                    $variation = Variation::where('product_id', $id)->first();
            
                    if ($variation) {
                        $existing_record_key = $price_group_id . '-' . $variation->id;
                        $price_inc_tax = match ($product->type) {
                            'single' => $variation->sell_price_inc_tax ?? $product->single_dsp_inc_tax ?? 0,
                            'combo', 'combo_single' => $variation->sell_price_inc_tax ?? $product->selling_price_inc_tax ?? 0,
                            default => 0
                        };
            
                        if (isset($existing_price_groups[$existing_record_key])) {
                            // ✅ If previously soft deleted, restore by nullifying deleted_at
                            $existing_price_groups[$existing_record_key]->update(['deleted_at' => null]);
                        } else {
                            // ✅ Otherwise, create new record
                            VariationGroupPrice::create([
                                'variation_id' => $variation->id,
                                'price_group_id' => $price_group_id,
                                'price_inc_tax' => $price_inc_tax,
                                'price_type' => 'fixed'
                            ]);
                        }
                    }
                }
            }            

            DB::commit();
            $output = ['success' => 1,
                'msg' => __('product.product_updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        if ($request->input('submit_type') == 'update_n_edit_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'submit_n_add_selling_prices') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'],
                [$product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create']
            )->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $can_be_deleted = true;
                $error_msg = '';

                //Check if any purchase or transfer exists
                $count = PurchaseLine::join(
                    'transactions as T',
                    'purchase_lines.transaction_id',
                    '=',
                    'T.id'
                )
                                    ->whereIn('T.type', ['purchase'])
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->count();
                if ($count > 0) {
                    $can_be_deleted = false;
                    $error_msg = __('lang_v1.purchase_already_exist');
                } else {
                    //Check if any opening stock sold
                    $count = PurchaseLine::join(
                        'transactions as T',
                        'purchase_lines.transaction_id',
                        '=',
                        'T.id'
                     )
                                    ->where('T.type', 'opening_stock')
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_sold', '>', 0)
                                    ->count();
                    if ($count > 0) {
                        $can_be_deleted = false;
                        $error_msg = __('lang_v1.opening_stock_sold');
                    } else {
                        //Check if any stock is adjusted
                        $count = PurchaseLine::join(
                            'transactions as T',
                            'purchase_lines.transaction_id',
                            '=',
                            'T.id'
                        )
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_adjusted', '>', 0)
                                    ->count();
                        if ($count > 0) {
                            $can_be_deleted = false;
                            $error_msg = __('lang_v1.stock_adjusted');
                        }
                    }
                }

                $product = Product::where('id', $id)
                                ->where('business_id', $business_id)
                                ->with('variations')
                                ->first();

                //Check if product is added as an ingredient of any recipe
                if ($this->moduleUtil->isModuleInstalled('Manufacturing')) {
                    $variation_ids = $product->variations->pluck('id');

                    $exists_as_ingredient = \Modules\Manufacturing\Entities\MfgRecipeIngredient::whereIn('variation_id', $variation_ids)
                        ->exists();
                    if ($exists_as_ingredient) {
                        $can_be_deleted = false;
                        $error_msg = __('manufacturing::lang.added_as_ingredient');
                    }
                }

                if ($can_be_deleted) {
                    if (! empty($product)) {

                        $sales_orders = TransactionSellLine::where('product_id', $id)->get();
                        if($sales_orders->isEmpty()){

                            DB::beginTransaction();
                            //Delete variation location details
                            VariationLocationDetails::where('product_id', $id)
                                                    ->delete();
                            $product->delete();
                            event(new ProductsCreatedOrModified($product, 'deleted'));
                            DB::commit();

                            $output = ['success' => true,
                                'msg' => __('lang_v1.product_delete_success'),
                            ];
                        }else{

                           Product::where('id', $id)
                            ->update([
                                'is_inactive' => 1
                            ]);

                            $output = ['success' => true,
                                'msg' => __('Product soft delete sucess'),
                            ];
                        }
                    }
                } else {
                    $output = ['success' => false,
                        'msg' => $error_msg,
                    ];
                }
            } catch (\Exception $e) {
                print_r('11');
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Get subcategories list for a category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getSubCategories(Request $request)
    {
        if (! empty($request->input('cat_id'))) {
            $category_id = $request->input('cat_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $category_id)
                        ->select(['name', 'id'])
                        ->get();
            $html = '<option value="">None</option>';
            if (! empty($sub_categories)) {
                foreach ($sub_categories as $sub_category) {
                    $html .= '<option value="'.$sub_category->id.'">'.$sub_category->name.'</option>';
                }
            }
            echo $html;
            exit;
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationFormPart(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $action = $request->input('action');
        $type = $request->input('type'); // Extract the type from the request
        if ($request->input('action') == 'add') {
            if ($request->input('type') == 'single') {
                return view('product.partials.single_product_form_part')
                        ->with(['profit_percent' => $profit_percent]);
            } elseif ($request->input('type') == 'variable') {
                $variation_templates = VariationTemplate::where('business_id', $business_id)->pluck('name', 'id')->toArray();
                $variation_templates = ['' => __('messages.please_select')] + $variation_templates;

                return view('product.partials.variable_product_form_part')
                        ->with(compact('variation_templates', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                return view('product.partials.combo_product_form_part')
                ->with(compact('profit_percent', 'action', 'type'));
            } elseif ($request->input('type') == 'combo_single') {
                return view('product.partials.combo_product_form_part')
                ->with(compact('profit_percent', 'action', 'type'));
            }
        } elseif ($request->input('action') == 'edit' || $request->input('action') == 'duplicate') {
            $product_id = $request->input('product_id');
            $action = $request->input('action');
            if ($request->input('type') == 'single') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();

                return view('product.partials.edit_single_product_form_part')
                            ->with(compact('product_deatails', 'action'));
            } elseif ($request->input('type') == 'variable') {
                $product_variations = ProductVariation::where('product_id', $product_id)
                        ->with(['variations', 'variations.media'])
                        ->get();

                return view('product.partials.variable_product_form_part')
                        ->with(compact('product_variations', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();
                $combo_variations = $this->productUtil->__getComboProductDetails($product_deatails['variations'][0]->combo_variations, $business_id);

                $variation_id = $product_deatails['variations'][0]->id;
                $profit_percent = $product_deatails['variations'][0]->profit_percent;

                return view('product.partials.combo_product_form_part')
                ->with(compact('combo_variations', 'profit_percent', 'action', 'variation_id', 'type'));
            } elseif ($request->input('type') == 'combo_single') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();
                $combo_variations = $this->productUtil->__getComboProductDetails($product_deatails['variations'][0]->combo_variations, $business_id);

                $variation_id = $product_deatails['variations'][0]->id;
                $profit_percent = $product_deatails['variations'][0]->profit_percent;

                return view('product.partials.combo_product_form_part')
                ->with(compact('combo_variations', 'profit_percent', 'action', 'variation_id', 'type'));
            }
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getVariationValueRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_index = $request->input('variation_row_index');
        $value_index = $request->input('value_index') + 1;

        $row_type = $request->input('row_type', 'add');

        return view('product.partials.variation_value_row')
                ->with(compact('profit_percent', 'variation_index', 'value_index', 'row_type'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_templates = VariationTemplate::where('business_id', $business_id)
                                                ->pluck('name', 'id')->toArray();
        $variation_templates = ['' => __('messages.please_select')] + $variation_templates;

        $row_index = $request->input('row_index', 0);
        $action = $request->input('action');

        return view('product.partials.product_variation_row')
                    ->with(compact('variation_templates', 'row_index', 'action', 'profit_percent'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getVariationTemplate(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $template = VariationTemplate::where('id', $request->input('template_id'))
                                                ->with(['values'])
                                                ->first();
        $row_index = $request->input('row_index');

        $values = [];
        foreach ($template->values as $v) {
            $values[] = [
                'id' => $v->id,
                'text' => $v->name,
            ];
        }

        return [
            'html' => view('product.partials.product_variation_template')
                    ->with(compact('template', 'row_index', 'profit_percent'))->render(),
            'values' => $values,
        ];
    }

    /**
     * Return the view for combo product row
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getComboProductEntryRow(Request $request)
    {
        if ($request->ajax()) {
            $product_id = $request->input('product_id');
            $variation_id = $request->input('variation_id');
            $business_id = $request->session()->get('user.business_id');
    
            if (!empty($product_id)) {
                $product = Product::where('id', $product_id)
                    ->with(['unit'])
                    ->first();
    
                if (!$product) {
                    return response()->json(['error' => 'Product not found'], 404);
                }
    
                $query = Variation::where('product_id', $product_id)
                    ->with(['product_variation']);
    
                if ($variation_id !== '0') {
                    $query->where('id', $variation_id);
                }
    
                $variations = $query->get();
    
                // Fetch sub-unit data consistently
                $sub_unit_data = $this->productUtil->getSubUnits($business_id, $product->unit->id, false, $product_id);
                $sub_units = $sub_unit_data['units'] ?? [];
                $default_selected_unit = $sub_unit_data['default_selected_unit'] ?? $product->unit->id;
    
                // Verify if the sub-units are populated and apply fallback if needed
                if (empty($sub_units)) {
                    $sub_units[$product->unit->id] = [
                        'name' => $product->unit->actual_name,
                        'multiplier' => 1,
                        'allow_decimal' => $product->unit->allow_decimal,
                    ];
                }
    
                // Structure the return format to match your needs
                $sub_units = [
                    'units' => $sub_units,
                    'default_selected_unit' => $default_selected_unit,
                ];
                
                // Debugging output to confirm the structure
                \Log::info('Formatted sub_units data:', $sub_units);
    
                return view('product.partials.combo_product_entry_row')
                    ->with(compact('product', 'variations', 'sub_units', 'default_selected_unit'));
            }
        }
    }
    
    

    /**
     * Retrieves products list.
     *
     * @param  string  $q
     * @param  bool  $check_qty
     * @return JSON
     */
    public function getProducts()
    {
        if (request()->ajax()) {
            $search_term = request()->input('term', '');
            $location_id = request()->input('location_id', null);
            $check_qty = request()->input('check_qty', false);
            $price_group_id = request()->input('price_group', null);
            $business_id = request()->session()->get('user.business_id');
            $not_for_selling = request()->get('not_for_selling', null);
            $price_group_id = request()->input('price_group', '');
            $product_types = request()->get('product_types', []);

            $search_fields = request()->get('search_fields', ['name', 'sku']);
            if (in_array('sku', $search_fields)) {
                $search_fields[] = 'sub_sku';
            }

            $result = $this->productUtil->filterProduct($business_id, $search_term, $location_id, $not_for_selling, $price_group_id, $product_types, $search_fields, $check_qty);

            return json_encode($result);
        }
    }

    /**
     * Retrieves products list without variation list
     *
     * @param  string  $q
     * @param  bool  $check_qty
     * @return JSON
     */
    public function getProductsWithoutVariations()
    {
        if (request()->ajax()) {
            $term = request()->input('term', '');
            //$location_id = request()->input('location_id', '');

            //$check_qty = request()->input('check_qty', false);

            $business_id = request()->session()->get('user.business_id');

            $products = Product::join('variations', 'products.id', '=', 'variations.product_id')
                ->where('products.business_id', $business_id)
                ->where('products.type', '!=', 'modifier');

            //Include search
            if (! empty($term)) {
                $products->where(function ($query) use ($term) {
                    $query->where('products.name', 'like', '%'.$term.'%');
                    $query->orWhere('sku', 'like', '%'.$term.'%');
                    $query->orWhere('sub_sku', 'like', '%'.$term.'%');
                });
            }

            //Include check for quantity
            // if($check_qty){
            //     $products->where('VLD.qty_available', '>', 0);
            // }

            $products = $products->groupBy('products.id')
                ->select(
                    'products.id as product_id',
                    'products.name',
                    'products.type',
                    'products.enable_stock',
                    'products.sku',
                    'products.id as id',
                    DB::raw('CONCAT(products.name, " - ", products.sku) as text')
                )
                    ->orderBy('products.name')
                    ->get();

            return json_encode($products);
        }
    }

    /**
     * Checks if product sku already exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkProductSku(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $sku = $request->input('sku');
        $product_id = $request->input('product_id');

        //check in products table
        $query = Product::where('business_id', $business_id)
                        ->where('sku', $sku);
        if (! empty($product_id)) {
            $query->where('id', '!=', $product_id);
        }
        $count = $query->count();

        //check in variation table if $count = 0
        if ($count == 0) {
            $query2 = Variation::where('sub_sku', $sku)
                            ->join('products', 'variations.product_id', '=', 'products.id')
                            ->where('business_id', $business_id);

            if (! empty($product_id)) {
                $query2->where('product_id', '!=', $product_id);
            }

            if (! empty($request->input('variation_id'))) {
                $query2->where('variations.id', '!=', $request->input('variation_id'));
            }
            $count = $query2->count();
        }
        if ($count == 0) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    /**
     * Validates multiple variation skus
     */
    public function validateVaritionSkus(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $all_skus = $request->input('skus');

        $skus = [];
        foreach ($all_skus as $key => $value) {
            $skus[] = $value['sku'];
        }

        //check product table is sku present
        $product = Product::where('business_id', $business_id)
                        ->whereIn('sku', $skus)
                        ->first();

        if (! empty($product)) {
            return ['success' => 0, 'sku' => $product->sku];
        }

        foreach ($all_skus as $key => $value) {
            $query = Variation::where('sub_sku', $value['sku'])
                            ->join('products', 'variations.product_id', '=', 'products.id')
                            ->where('business_id', $business_id);

            if (! empty($value['variation_id'])) {
                $query->where('variations.id', '!=', $value['variation_id']);
            }
            $variation = $query->first();

            if (! empty($variation)) {
                return ['success' => 0, 'sku' => $variation->sub_sku];
            }
        }

        return ['success' => 1];
    }

    /**
     * Loads quick add product modal.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickAdd()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $product_name = ! empty(request()->input('product_name')) ? request()->input('product_name') : '';

        $product_for = ! empty(request()->input('product_for')) ? request()->input('product_for') : null;

        $business_id = request()->session()->get('user.business_id');
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::forDropdown($business_id, true);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;

        $default_profit_percent = Business::where('id', $business_id)->value('default_profit_percent');

        $locations = BusinessLocation::forDropdown($business_id);

        $enable_expiry = request()->session()->get('business.enable_product_expiry');
        $enable_lot = request()->session()->get('business.enable_lot_number');

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);

        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        return view('product.partials.quick_add_product')
                ->with(compact('categories', 'brands', 'units', 'taxes', 'barcode_types', 'default_profit_percent', 'tax_attributes', 'product_name', 'locations', 'product_for', 'enable_expiry', 'enable_lot', 'module_form_parts', 'business_locations', 'common_settings', 'warranties'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveQuickProduct(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $form_fields = ['name', 'brand_id', 'unit_id', 'category_id', 'tax', 'barcode_type', 'tax_type', 'sku',
                'alert_quantity', 'type', 'sub_unit_ids', 'sub_category_id', 'weight', 'product_description', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20'];

            $module_form_fields = $this->moduleUtil->getModuleData('product_form_fields');
            if (! empty($module_form_fields)) {
                foreach ($module_form_fields as $key => $value) {
                    if (! empty($value) && is_array($value)) {
                        $form_fields = array_merge($form_fields, $value);
                    }
                }
            }
            $product_details = $request->only($form_fields);

            $product_details['type'] = empty($product_details['type']) ? 'single' : $product_details['type'];
            $product_details['business_id'] = $business_id;
            $product_details['created_by'] = $request->session()->get('user.id');
            if (! empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) {
                $product_details['enable_stock'] = 1;
                //TODO: Save total qty
                //$product_details['total_qty_available'] = 0;
            }
            if (! empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) {
                $product_details['not_for_selling'] = 1;
            }
            if (empty($product_details['sku'])) {
                $product_details['sku'] = ' ';
            }

            if (! empty($product_details['alert_quantity'])) {
                $product_details['alert_quantity'] = $this->productUtil->num_uf($product_details['alert_quantity']);
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ! empty($expiry_enabled)) {
                $product_details['expiry_period_type'] = $request->input('expiry_period_type');
                $product_details['expiry_period'] = $this->productUtil->num_uf($request->input('expiry_period'));
            }

            if (! empty($request->input('enable_sr_no')) && $request->input('enable_sr_no') == 1) {
                $product_details['enable_sr_no'] = 1;
            }

            $product_details['warranty_id'] = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;

            DB::beginTransaction();

            $product = Product::create($product_details);
            event(new ProductsCreatedOrModified($product_details, 'added'));

            if (empty(trim($request->input('sku')))) {
                $sku = $this->productUtil->generateProductSku($product->id);
                $product->sku = $sku;
                $product->save();
            }

            $this->productUtil->createSingleProductVariation(
                $product->id,
                $product->sku,
                $request->input('single_dpp'),
                $request->input('single_dpp_inc_tax'),
                $request->input('profit_percent'),
                $request->input('single_dsp'),
                $request->input('single_dsp_inc_tax')
            );

            if ($product->enable_stock == 1 && ! empty($request->input('opening_stock'))) {
                $user_id = $request->session()->get('user.id');

                $transaction_date = $request->session()->get('financial_year.start');
                $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

                $this->productUtil->addSingleProductOpeningStock($business_id, $product, $request->input('opening_stock'), $transaction_date, $user_id);
            }

            //Add product locations
            $product_locations = $request->input('product_locations');
            if (! empty($product_locations)) {
                $product->product_locations()->sync($product_locations);
            }

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('product.product_added_success'),
                'product' => $product,
                'variation' => $product->variations->first(),
                'locations' => $product_locations,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function view($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $product = Product::where('business_id', $business_id)
                        ->with(['brand', 'unit', 'category', 'sub_category', 'product_tax', 'variations', 'variations.product_variation', 'variations.group_prices', 'variations.media', 'product_locations', 'warranty', 'media'])
                        ->findOrFail($id);

            $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');

            $allowed_group_prices = [];
            foreach ($price_groups as $key => $value) {
                if (auth()->user()->can('selling_price_group.'.$key)) {
                    $allowed_group_prices[$key] = $value;
                }
            }

            $group_price_details = [];
            $price_ranges = [];
    
            foreach ($product->variations as $variation) {
                foreach ($variation->group_prices as $group_price) {
                    $group_price_details[$variation->id][$group_price->price_group_id] = [
                        'price' => $group_price->price_inc_tax,
                        'price_type' => $group_price->price_type,
                        'calculated_price' => $group_price->calculated_price
                    ];
    
                    // Fetch price ranges if group pricing has ranges
                    $rangeData = ProductPriceRange::where('selling_price_group_id', $group_price->price_group_id)
                        ->where('product_id', $product->id)
                        ->value('price_range');
    
                    if (!empty($rangeData)) {
                        $price_ranges[$variation->id][$group_price->price_group_id] = json_decode($rangeData, true);
                    }
                }
            }

            $rack_details = $this->productUtil->getRackDetails($business_id, $id, true);

            $combo_variations = [];
            if ($product->type == 'combo' || $product->type == 'combo_single') {
                $combo_variations = $this->productUtil->__getComboProductDetails($product['variations'][0]->combo_variations, $business_id);
            }
            
            return view('product.view-modal')->with(compact(
                'product',
                'rack_details',
                'allowed_group_prices',
                'group_price_details',
                'combo_variations',
                'price_ranges'
            ));
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    /**
     * Mass deletes products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(Request $request)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $purchase_exist = false;

            if (! empty($request->input('selected_rows'))) {
                $business_id = $request->session()->get('user.business_id');

                $selected_rows = explode(',', $request->input('selected_rows'));

                $products = Product::where('business_id', $business_id)
                                    ->whereIn('id', $selected_rows)
                                    ->with(['purchase_lines', 'variations'])
                                    ->get();
                $deletable_products = [];

                $is_mfg_installed = $this->moduleUtil->isModuleInstalled('Manufacturing');

                DB::beginTransaction();

                foreach ($products as $product) {
                    $can_be_deleted = true;
                    //Check if product is added as an ingredient of any recipe
                    if ($is_mfg_installed) {
                        $variation_ids = $product->variations->pluck('id');

                        $exists_as_ingredient = \Modules\Manufacturing\Entities\MfgRecipeIngredient::whereIn('variation_id', $variation_ids)
                            ->exists();
                        $can_be_deleted = ! $exists_as_ingredient;
                    }

                    //Delete if no purchase found
                    if (empty($product->purchase_lines->toArray()) && $can_be_deleted) {
                        //Delete variation location details
                        // VariationLocationDetails::where('product_id', $product->id)
                        //                             ->delete();
                        // $product->delete();
                        // event(new ProductsCreatedOrModified($product, 'Deleted'));
                        // Product::where('id', $product->id)
                        // ->update([
                        //     'is_inactive' => 1
                        // ]);

                        $sales_orders = TransactionSellLine::where('product_id', $product->id)->get();
                        if($sales_orders->isEmpty()){
                            
                            DB::beginTransaction();
                            //Delete variation location details
                            VariationLocationDetails::where('product_id', $product->id)
                                                    ->delete();
                            $product->delete();
                            event(new ProductsCreatedOrModified($product, 'deleted'));
                            DB::commit();

                            $output = ['success' => true,
                                'msg' => __('lang_v1.product_delete_success'),
                            ];
                        }else{

                           Product::where('id', $product->id)
                            ->update([
                                'is_inactive' => 1
                            ]);

                            $output = ['success' => true,
                                'msg' => __('Product soft delete sucess'),
                            ];
                        }

                    } else {
                        $purchase_exist = true;
                        $output = ['success' => 1,
                            'msg' => __('lang_v1.product_delete_success'),
                        ];
                    }
                }

                DB::commit();
            }

            // if (! $purchase_exist) {
            //     $output = ['success' => 1,
            //         'msg' => __('lang_v1.product_delete_success'),
            //     ];
            // } else {
            //     $output = ['success' => 0,
            //         'msg' => __('Product soft delete sucess'),
            //     ];
            // }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Shows form to add selling price group prices for a product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addSellingPrices($id)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $product = Product::where('business_id', $business_id)
                    ->with([
                        'variations', 
                        'variations.group_prices' => function ($query) {
                            $query->whereNull('deleted_at'); // ✅ Exclude soft-deleted prices
                        },
                        'variations.product_variation'
                    ])
                    ->findOrFail($id);

        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                            ->active()
                                            ->get();
        $variation_prices = [];
        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $group_price) {
                $variation_prices[$variation->id][$group_price->price_group_id] = ['price' => $group_price->price_inc_tax, 'price_type' => $group_price->price_type];
            }
        }

        // ✅ Fetch saved price ranges from `ProductPriceRange`
        $price_ranges = ProductPriceRange::where('business_id', $business_id)
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('selling_price_group_id'); 


        return view('product.add-selling-prices')->with(compact('product', 'price_groups', 'variation_prices', 'price_ranges'));
    }

    /**
     * Saves selling price group prices for a product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveSellingPrices(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $business_id = $request->session()->get('user.business_id');
            $product = Product::where('business_id', $business_id)
                            ->with(['variations'])
                            ->findOrFail($request->input('product_id'));

            DB::beginTransaction();

            // ✅ If group_product == 1, save selling prices for variations
            if ($product->group_product == 1) {
                foreach ($product->variations as $variation) {
                    $variation_group_prices = [];
                    foreach ($request->input('group_prices', []) as $key => $value) {
                        if (isset($value[$variation->id])) {
                            $variation_group_price =
                            VariationGroupPrice::where('variation_id', $variation->id)
                                                ->where('price_group_id', $key)
                                                ->first();
                            if (empty($variation_group_price)) {
                                $variation_group_price = new VariationGroupPrice([
                                    'variation_id' => $variation->id,
                                    'price_group_id' => $key,
                                ]);
                            }

                            $variation_group_price->price_inc_tax = $this->productUtil->num_uf($value[$variation->id]['price']);
                            $variation_group_price->price_type = $value[$variation->id]['price_type'];
                            $variation_group_prices[] = $variation_group_price;
                        }
                    }

                    if (! empty($variation_group_prices)) {
                        $variation->group_prices()->saveMany($variation_group_prices);
                    }
                }
            }

            // ✅ If group_product != 1, save range-based prices in `product_price_ranges`
            else {
                foreach ($request->input('product_price_range', []) as $group_id => $ranges) {
                    $price_range_data = [];
    
                    foreach ($ranges as $range) {
                        // ✅ Ensure we're only saving non-null values
                        if (!empty($range['minimum_qty']) && !empty($range['maximum_qty']) && !empty($range['price'])) {
                            $price_range_data[] = [
                                'minimum_qty' => $range['minimum_qty'],
                                'maximum_qty' => $range['maximum_qty'],
                                'price' => $this->productUtil->num_uf($range['price'])
                            ];
                        }
                    }
    
                    // ✅ Update or insert `product_price_ranges` only if there is valid data
                    if (!empty($price_range_data)) {
                        ProductPriceRange::updateOrCreate(
                            [
                                'business_id' => $business_id,
                                'product_id' => $product->id,
                                'selling_price_group_id' => $group_id
                            ],
                            [
                                'price_range' => json_encode($price_range_data),
                                'created_by' => auth()->user()->id,
                            ]
                        );
                    } else {
                        // ✅ If no valid price range, delete existing entry (to avoid keeping outdated records)
                        ProductPriceRange::where('business_id', $business_id)
                            ->where('product_id', $product->id)
                            ->where('selling_price_group_id', $group_id)
                            ->delete();
                    }
                }
            }

            // Update product timestamp
            $product->touch();

            DB::commit();
            $output = ['success' => 1, 'msg' => __('lang_v1.updated_success')];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        if ($request->input('submit_type') == 'submit_n_add_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create'])
                ->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    public function viewGroupPrice($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $product = Product::where('business_id', $business_id)
                            ->where('id', $id)
                            ->with(['variations', 'variations.product_variation', 'variations.group_prices'])
                            ->first();

        $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');

        $allowed_group_prices = [];
        foreach ($price_groups as $key => $value) {
            if (auth()->user()->can('selling_price_group.'.$key)) {
                $allowed_group_prices[$key] = $value;
            }
        }

        $group_price_details = [];

        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $group_price) {
                $group_price_details[$variation->id][$group_price->price_group_id] = $group_price->price_inc_tax;
            }
        }

        return view('product.view-product-group-prices')->with(compact('product', 'allowed_group_prices', 'group_price_details'));
    }

    /**
     * Mass deactivates products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDeactivate(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if (! empty($request->input('selected_products'))) {
                $business_id = $request->session()->get('user.business_id');

                $selected_products = explode(',', $request->input('selected_products'));

                DB::beginTransaction();

                $products = Product::where('business_id', $business_id)
                                    ->whereIn('id', $selected_products)
                                    ->update(['is_inactive' => 1]);

                DB::commit();
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.products_deactivated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Activates the specified resource from storage.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function activate($id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $product = Product::where('id', $id)
                                ->where('business_id', $business_id)
                                ->update(['is_inactive' => 0]);

                $output = ['success' => true,
                    'msg' => __('lang_v1.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Deletes a media file from storage and database.
     *
     * @param  int  $media_id
     * @return json
     */
    public function deleteMedia($media_id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                Media::deleteMedia($business_id, $media_id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.file_deleted_successfully'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getProductsApi($id = null)
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $filter_string = request()->header('FILTERS');
            $order_by = request()->header('ORDER-BY');

            parse_str($filter_string, $filters);

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $limit = ! empty(request()->input('limit')) ? request()->input('limit') : 10;

            $location_id = $api_settings->location_id;

            $query = Product::where('business_id', $api_settings->business_id)
                            ->active()
                            ->with(['brand', 'unit', 'category', 'sub_category',
                                'product_variations', 'product_variations.variations', 'product_variations.variations.media',
                                'product_variations.variations.variation_location_details' => function ($q) use ($location_id) {
                                    $q->where('location_id', $location_id);
                                }, ]);

            if (! empty($filters['categories'])) {
                $query->whereIn('category_id', $filters['categories']);
            }

            if (! empty($filters['brands'])) {
                $query->whereIn('brand_id', $filters['brands']);
            }

            if (! empty($filters['category'])) {
                $query->where('category_id', $filters['category']);
            }

            if (! empty($filters['sub_category'])) {
                $query->where('sub_category_id', $filters['sub_category']);
            }

            if ($order_by == 'name') {
                $query->orderBy('name', 'asc');
            } elseif ($order_by == 'date') {
                $query->orderBy('created_at', 'desc');
            }

            if (empty($id)) {
                $products = $query->paginate($limit);
            } else {
                $products = $query->find($id);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($products);
    }

    public function getVariationsApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $variations_string = request()->header('VARIATIONS');

            if (is_numeric($variations_string)) {
                $variation_ids = intval($variations_string);
            } else {
                parse_str($variations_string, $variation_ids);
            }

            $api_settings = $this->moduleUtil->getApiSettings($api_token);
            $location_id = $api_settings->location_id;
            $business_id = $api_settings->business_id;

            $query = Variation::with([
                'product_variation',
                'product' => function ($q) use ($business_id) {
                    $q->where('business_id', $business_id);
                },
                'product.unit',
                'variation_location_details' => function ($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                },
            ]);

            $variations = is_array($variation_ids) ? $query->whereIn('id', $variation_ids)->get() : $query->where('id', $variation_ids)->first();
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($variations);
    }

    /**
     * Shows form to edit multiple products at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkEdit(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $selected_products_string = $request->input('selected_products');
        if (! empty($selected_products_string)) {
            $selected_products = explode(',', $selected_products_string);
            $business_id = $request->session()->get('user.business_id');

            $products = Product::where('business_id', $business_id)
                                ->whereIn('id', $selected_products)
                                ->with(['variations', 'variations.product_variation', 'variations.group_prices', 'product_locations'])
                                ->get();

            $all_categories = Category::catAndSubCategories($business_id);

            $categories = [];
            $sub_categories = [];
            foreach ($all_categories as $category) {
                $categories[$category['id']] = $category['name'];

                if (! empty($category['sub_categories'])) {
                    foreach ($category['sub_categories'] as $sub_category) {
                        $sub_categories[$category['id']][$sub_category['id']] = $sub_category['name'];
                    }
                }
            }

            $brands = Brands::forDropdown($business_id);

            $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
            $taxes = $tax_dropdown['tax_rates'];
            $tax_attributes = $tax_dropdown['attributes'];

            $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');
            $business_locations = BusinessLocation::forDropdown($business_id);

            return view('product.bulk-edit')->with(compact(
                'products',
                'categories',
                'brands',
                'taxes',
                'tax_attributes',
                'sub_categories',
                'price_groups',
                'business_locations'
            ));
        }
    }

    /**
     * Updates multiple products at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdate(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $products = $request->input('products');
            $business_id = $request->session()->get('user.business_id');

            DB::beginTransaction();
            foreach ($products as $id => $product_data) {
                $update_data = [
                    'category_id' => $product_data['category_id'],
                    'sub_category_id' => $product_data['sub_category_id'],
                    'brand_id' => $product_data['brand_id'],
                    'tax' => $product_data['tax'],
                ];

                //Update product
                $product = Product::where('business_id', $business_id)
                                ->findOrFail($id);

                $product->update($update_data);

                //Add product locations
                $product_locations = ! empty($product_data['product_locations']) ?
                                    $product_data['product_locations'] : [];
                $product->product_locations()->sync($product_locations);

                $variations_data = [];

                //Format variations data
                foreach ($product_data['variations'] as $key => $value) {
                    $variation = Variation::where('product_id', $product->id)->findOrFail($key);
                    $variation->default_purchase_price = $this->productUtil->num_uf($value['default_purchase_price']);
                    $variation->dpp_inc_tax = $this->productUtil->num_uf($value['dpp_inc_tax']);
                    $variation->profit_percent = $this->productUtil->num_uf($value['profit_percent']);
                    $variation->default_sell_price = $this->productUtil->num_uf($value['default_sell_price']);
                    $variation->sell_price_inc_tax = $this->productUtil->num_uf($value['sell_price_inc_tax']);
                    $variations_data[] = $variation;

                    //Update price groups
                    if (! empty($value['group_prices'])) {
                        foreach ($value['group_prices'] as $k => $v) {
                            VariationGroupPrice::updateOrCreate(
                                ['price_group_id' => $k, 'variation_id' => $variation->id],
                                ['price_inc_tax' => $this->productUtil->num_uf($v)]
                            );
                        }
                    }
                }
                $product->variations()->saveMany($variations_data);
            }
            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Adds product row to edit in bulk edit product form
     *
     * @param  int  $product_id
     * @return \Illuminate\Http\Response
     */
    public function getProductToEdit($product_id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $product = Product::where('business_id', $business_id)
                            ->with(['variations', 'variations.product_variation', 'variations.group_prices'])
                            ->findOrFail($product_id);
        $all_categories = Category::catAndSubCategories($business_id);

        $categories = [];
        $sub_categories = [];
        foreach ($all_categories as $category) {
            $categories[$category['id']] = $category['name'];

            if (! empty($category['sub_categories'])) {
                foreach ($category['sub_categories'] as $sub_category) {
                    $sub_categories[$category['id']][$sub_category['id']] = $sub_category['name'];
                }
            }
        }

        $brands = Brands::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');

        return view('product.partials.bulk_edit_product_row')->with(compact(
            'product',
            'categories',
            'brands',
            'taxes',
            'tax_attributes',
            'sub_categories',
            'price_groups'
        ));
    }

    /**
     * Gets the sub units for the given unit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $unit_id
     * @return \Illuminate\Http\Response
     */
    public function getSubUnits(Request $request)
    {
        if (! empty($request->input('unit_id'))) {
            $unit_id = $request->input('unit_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_units = $this->productUtil->getSubUnits($business_id, $unit_id, true);

            //$html = '<option value="">' . __('lang_v1.all') . '</option>';
            $html = '';
            if (! empty($sub_units)) {
                foreach ($sub_units as $id => $sub_unit) {
                    $html .= '<option value="'.$id.'">'.$sub_unit['name'].'</option>';
                }
            }

            return $html;
        }
    }

    public function updateProductLocation(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $selected_products = $request->input('products');
            $update_type = $request->input('update_type');
            $location_ids = $request->input('product_location');

            $business_id = $request->session()->get('user.business_id');

            $product_ids = explode(',', $selected_products);

            $products = Product::where('business_id', $business_id)
                                ->whereIn('id', $product_ids)
                                ->with(['product_locations'])
                                ->get();
            DB::beginTransaction();
            foreach ($products as $product) {
                $product_locations = $product->product_locations->pluck('id')->toArray();

                if ($update_type == 'add') {
                    $product_locations = array_unique(array_merge($location_ids, $product_locations));
                    $product->product_locations()->sync($product_locations);
                } elseif ($update_type == 'remove') {
                    foreach ($product_locations as $key => $value) {
                        if (in_array($value, $location_ids)) {
                            unset($product_locations[$key]);
                        }
                    }
                    $product->product_locations()->sync($product_locations);
                }
            }
            DB::commit();
            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Toggle WooComerce sync
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toggleWooCommerceSync(Request $request)
    {
        try {
            $selected_products = $request->input('woocommerce_products_sync');
            $woocommerce_disable_sync = $request->input('woocommerce_disable_sync');

            $business_id = $request->session()->get('user.business_id');
            $product_ids = explode(',', $selected_products);

            DB::beginTransaction();
            if ($this->moduleUtil->isModuleInstalled('Woocommerce')) {
                Product::where('business_id', $business_id)
                        ->whereIn('id', $product_ids)
                        ->update(['woocommerce_disable_sync' => $woocommerce_disable_sync]);
            }
            DB::commit();
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Function to download all products in xlsx format
     */
    public function downloadExcel()
    {
        $is_admin = $this->productUtil->is_admin(auth()->user());
        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $filename = 'products-export-'.\Carbon::now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ProductsExport, $filename);
    }
}
