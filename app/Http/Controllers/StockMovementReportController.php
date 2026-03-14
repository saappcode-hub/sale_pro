<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementReportController extends Controller
{
    protected $transactionUtil;
    protected $businessUtil;
    protected $commonUtil;

    public function __construct(TransactionUtil $transactionUtil, BusinessUtil $businessUtil, Util $commonUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->commonUtil = $commonUtil;
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            return $this->getStockMovementData($request, $business_id);
        }

        $business_locations = BusinessLocation::where('business_id', $business_id)->pluck('name', 'id');
        $business_locations->prepend(__('lang_v1.all'), '');

        return view('stock_report_movement.index', compact('business_locations'));
    }

    /**
     * OPTIMIZED: Filter Products FIRST, then Calculate Stock for visible rows only.
     */
    private function getStockMovementData(Request $request, $business_id)
    {
        $apply_filters = $request->get('apply_filters', false);
        
        // Fast load: return empty if no filters
        if (!$apply_filters) {
            return [
                'draw' => (int) $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        $location_id = $request->get('sell_list_filter_location_id');
        $date_range = $request->get('sell_list_filter_date_range');

        if (empty($location_id) || empty($date_range)) {
            return ['draw' => (int) $request->get('draw'), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];
        }

        // Parse Dates
        $start_date = null;
        $end_date = null;
        if (!empty($date_range)) {
            try {
                $dates = explode(' - ', $date_range);
                if (count($dates) == 2) {
                    $start_date = Carbon::createFromFormat('m/d/Y', trim($dates[0]))->startOfDay();
                    $end_date = Carbon::createFromFormat('m/d/Y', trim($dates[1]))->endOfDay();
                }
            } catch (\Exception $e) { }
        }

        if (!$start_date || !$end_date) {
            return ['draw' => (int) $request->get('draw'), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];
        }

        // =======================================================
        // STEP 1: Query Products Table Only (Light & Fast)
        // =======================================================
        $productsQuery = DB::table('products as p')
            ->leftJoin('units as u1', 'p.unit_id', '=', 'u1.id')
            ->leftJoin('units as u2', 'p.purchase_unit', '=', 'u2.id')
            ->where('p.business_id', $business_id)
            ->where('p.type', '!=', 'combo')
            ->where('p.enable_stock', 1)
            ->select(
                'p.id',
                'p.sku',
                'p.name as product_name',
                'u1.actual_name as base_unit_name',
                'u2.actual_name as purchase_unit_name',
                DB::raw('COALESCE((SELECT u.base_unit_multiplier FROM units u WHERE u.id = p.purchase_unit AND u.base_unit_id = p.unit_id), 1) as base_unit_multiplier')
            );

        // Get Total Count (Clone query to avoid modifying original)
        $totalRecords = $productsQuery->clone()->count();

        // Apply Search (Product Name or SKU)
        if ($request->has('search') && !empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $productsQuery->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.sku', 'like', "%{$search}%");
            });
        }

        // Get Filtered Count
        $filteredRecords = $productsQuery->clone()->count();

        // Apply Sorting (Sort by Name/SKU is fast. Sort by Stock is disabled for speed)
        $order = $request->get('order');
        $columns = $request->get('columns');
        if (!empty($order) && isset($columns)) {
            $colIndex = $order[0]['column'];
            $colName = $columns[$colIndex]['data'];
            $dir = $order[0]['dir'];

            if ($colName == 'sku') {
                $productsQuery->orderBy('p.sku', $dir);
            } else {
                $productsQuery->orderBy('p.name', $dir != 'desc' ? 'asc' : 'desc');
            }
        } else {
            $productsQuery->orderBy('p.name', 'asc');
        }

        $limit = $request->get('length', 25);
        $start = $request->get('start', 0);

        // Only apply pagination if "Show All" (-1) is NOT selected
        if ($limit != -1) {
            $productsQuery->skip($start)->take($limit);
        }

        $pageProducts = $productsQuery->get();

        // =======================================================
        // STEP 2: Calculate Stock ONLY for these 25 Products
        // =======================================================
        
        $results = [];
        
        if ($pageProducts->isNotEmpty()) {
            // Get the list of 25 IDs to filter the heavy query
            $product_ids = $pageProducts->pluck('id')->toArray();
            $ids_string = implode(',', $product_ids);

            // Run the heavy math query, but RESTRICTED to these IDs
            $stockData = $this->getOptimizedStockQuery($business_id, $location_id, $start_date, $end_date, $ids_string);
            
            // Convert to a keyed array for easy merging
            $stockKeyed = [];
            foreach ($stockData as $item) {
                $stockKeyed[$item->product_id] = $item;
            }

            // Get Location Name
            $loc = BusinessLocation::find($location_id);
            $location_name = $loc ? $loc->name : '';

            // Merge and Format
            foreach ($pageProducts as $product) {
                $data = isset($stockKeyed[$product->id]) ? $stockKeyed[$product->id] : null;

                // Helper to safely get value
                $val = function($key) use ($data) {
                    return $data ? $data->{$key} : 0;
                };

                // Build row
                $results[] = [
                    'sku_product' => $product->product_name,
                    'sku' => $product->sku,
                    'location' => $location_name,
                    'beginning_stock_raw' => $this->formatQuantityWithUnit($val('beginning_stock'), $product),
                    'sale_raw' => $this->formatQuantityWithUnit($val('sale'), $product),
                    'sale_return_raw' => $this->formatQuantityWithUnit($val('sale_return'), $product),
                    'purchase_raw' => $this->formatQuantityWithUnit($val('purchase'), $product),
                    'purchase_return_raw' => $this->formatQuantityWithUnit($val('purchase_return'), $product),
                    'stock_transfer_in_raw' => $this->formatQuantityWithUnit($val('stock_transfer_in'), $product),
                    'stock_transfer_out_raw' => $this->formatQuantityWithUnit($val('stock_transfer_out'), $product),
                    'stock_reward_in_raw' => $this->formatQuantityWithUnit($val('stock_reward_in'), $product),
                    'stock_reward_out_raw' => $this->formatQuantityWithUnit($val('stock_reward_out'), $product),
                    'supplier_receive_raw' => $this->formatQuantityWithUnit($val('supplier_receive'), $product),
                    'supplier_exchange_raw' => $this->formatQuantityWithUnit($val('supplier_exchange'), $product),
                    'stock_adjustment_raw' => $this->formatQuantityWithUnit($val('stock_adjustment'), $product),
                    'ending_stock_raw' => $this->formatQuantityWithUnit($val('ending_stock'), $product),
                ];
            }
        }

        // Return DataTables Response
        return [
            'draw' => (int) $request->get('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $results,
        ];
    }

    /**
     * Heavy Calculation Query, optimized by filtering IDs
     */
    private function getOptimizedStockQuery($business_id, $location_id, $start_date, $end_date, $product_ids_string)
    {
        $start_date_str = $start_date->format('Y-m-d H:i:s');
        $end_date_str = $end_date->format('Y-m-d H:i:s');

        $stock_history_subquery = DB::table(DB::raw('(
            SELECT
                t.transaction_date,
                t.type as transaction_type,
                t.status,
                CASE
                    WHEN t.type = \'sell\' AND t.status = \'final\' THEN -sl.quantity
                    WHEN t.type = \'purchase\' AND t.status = \'received\' AND (pt.exchange_product_id IS NULL OR JSON_CONTAINS(pt.exchange_product_id, \'null\') OR pt.exchange_product_id = CAST(\'[null]\' AS JSON)) THEN pl.quantity
                    WHEN t.type = \'stock_adjustment\' THEN -al.quantity
                    WHEN t.type = \'opening_stock\' THEN pl.quantity
                    WHEN t.type = \'sell_transfer\' AND t.status = \'final\' THEN -sl.quantity
                    WHEN t.type = \'purchase_transfer\' AND t.status = \'received\' THEN pl.quantity
                    WHEN t.type = \'production_purchase\' THEN pl.quantity
                    WHEN t.type = \'production_sell\' AND t.status = \'final\' THEN -sl.quantity
                    WHEN t.type = \'purchase_return\' THEN -(COALESCE(pl.quantity_returned, 0) + COALESCE(rpl.quantity_returned, 0))
                    WHEN t.type = \'sell_return\' THEN rsl.quantity_returned
                    ELSE 0
                END AS quantity_change,
                COALESCE(sl.product_id, pl.product_id, al.product_id, rpl.product_id, rsl.product_id) AS product_id
            FROM transactions t
            LEFT JOIN transaction_sell_lines sl ON sl.transaction_id = t.id
            LEFT JOIN purchase_lines pl ON pl.transaction_id = t.id
            LEFT JOIN stock_adjustment_lines al ON al.transaction_id = t.id
            LEFT JOIN transactions return_txn ON t.return_parent_id = return_txn.id
            LEFT JOIN purchase_lines rpl ON rpl.transaction_id = return_txn.id
            LEFT JOIN transaction_sell_lines rsl ON rsl.transaction_id = return_txn.id
            LEFT JOIN purchase_types pt ON pl.purchase_type_id = pt.id
            WHERE t.type IN (\'sell\', \'purchase\', \'stock_adjustment\', \'opening_stock\', \'sell_transfer\', \'purchase_transfer\', \'production_purchase\', \'purchase_return\', \'sell_return\', \'production_sell\')
            AND t.deleted_at IS NULL
            AND t.business_id = ' . $business_id . '
            ' . ($location_id ? 'AND t.location_id = ' . $location_id : '') . '

            UNION ALL

            SELECT
                t.transaction_date,
                \'reward_exchange\' AS transaction_type,
                t.status,
                SUM(sre.quantity) AS quantity_change,
                v.product_id
            FROM stock_reward_exchange_new sre
            JOIN transactions t ON t.id = sre.transaction_id
            JOIN variations v ON v.id = sre.variation_id
            WHERE t.status = \'completed\'
            AND t.type = \'reward_exchange\'
            AND sre.type IN (\'reward_exchange_out\', \'reward_exchange_in\', \'edit_reward_exchange_out\', \'edit_reward_exchange_in\')
            AND t.deleted_at IS NULL
            AND t.business_id = ' . $business_id . '
            ' . ($location_id ? 'AND t.location_id = ' . $location_id : '') . '
            GROUP BY t.id, t.transaction_date, t.status, v.product_id

            UNION ALL

            SELECT
                t.transaction_date,
                \'supplier_exchange\' AS transaction_type,
                NULL AS status,
                sre.quantity AS quantity_change,
                v.product_id
            FROM stock_reward_exchange_new sre
            JOIN transactions t ON t.id = sre.transaction_id
            JOIN variations v ON v.id = sre.variation_id
            WHERE t.sub_type = \'send\'
            AND t.type = \'supplier_exchange\'
            AND sre.type = \'supplier_reward_out\'
            AND sre.contact_id IS NULL
            AND t.deleted_at IS NULL
            AND t.business_id = ' . $business_id . '
            ' . ($location_id ? 'AND t.location_id = ' . $location_id : '') . '

            UNION ALL

            SELECT
                t.transaction_date,
                \'supplier_exchange_receive\' AS transaction_type,
                NULL AS status,
                sre.quantity AS quantity_change,
                v.product_id
            FROM stock_reward_exchange_new sre
            JOIN transactions t ON t.id = sre.transaction_id
            JOIN variations v ON v.id = sre.variation_id
            WHERE t.type = \'supplier_exchange_receive\'
            AND sre.type = \'supplier_receive_in\'
            AND sre.contact_id IS NULL
            AND t.deleted_at IS NULL
            AND t.business_id = ' . $business_id . '
            ' . ($location_id ? 'AND t.location_id = ' . $location_id : '') . '

        ) as stock_history'));

        // Aggregation: Here we apply the product_id filter to make it FAST
        $aggregated = DB::table($stock_history_subquery)
            ->select('product_id')
            ->selectRaw("SUM(IF(transaction_date < '{$start_date_str}', quantity_change, 0)) as beginning_stock")
            ->selectRaw("SUM(quantity_change) as ending_stock")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'sell' AND status = 'final', ABS(quantity_change), 0)) as sale")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'sell_return', quantity_change, 0)) as sale_return")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'purchase' AND status = 'received', quantity_change, 0)) as purchase")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'purchase_return', ABS(quantity_change), 0)) as purchase_return")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'purchase_transfer' AND status = 'received', quantity_change, 0)) as stock_transfer_in")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'sell_transfer' AND status = 'final', ABS(quantity_change), 0)) as stock_transfer_out")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'reward_exchange' AND quantity_change > 0, quantity_change, 0)) as stock_reward_in")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'reward_exchange' AND quantity_change < 0, ABS(quantity_change), 0)) as stock_reward_out")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'supplier_exchange_receive', quantity_change, 0)) as supplier_receive")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'supplier_exchange', ABS(quantity_change), 0)) as supplier_exchange")
            ->selectRaw("SUM(IF(transaction_date >= '{$start_date_str}' AND transaction_type = 'stock_adjustment', ABS(quantity_change), 0)) as stock_adjustment")
            ->where('transaction_date', '<=', $end_date_str)
            // CRITICAL SPEED FIX: Only calculate for the 25 requested products
            ->whereRaw("product_id IN ($product_ids_string)")
            ->groupBy('product_id')
            ->get();

        return $aggregated;
    }

    private function formatQuantityWithUnit($quantity, $row)
    {
        $base_unit_multiplier = $row->base_unit_multiplier;
        $purchase_unit_name = $row->purchase_unit_name;
        $base_unit_name = $row->base_unit_name;

        if ($quantity == 0) {
            return '0 ' . ($purchase_unit_name ?: 'units');
        }

        $major_units = floor($quantity / $base_unit_multiplier);
        $minor_units = $quantity % $base_unit_multiplier;

        $result = $major_units . ' ' . ($purchase_unit_name ?: 'units');
        
        if ($minor_units > 0) {
            $result .= ' ' . $minor_units . ' ' . ($base_unit_name ?: 'units');
        }

        return $result;
    }
}