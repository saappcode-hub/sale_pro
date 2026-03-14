<?php

namespace App\Http\Controllers;

use App\CompetitorProduct;
use App\User;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;

class ReportSaleTrackingController extends Controller
{
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
        $users = User::where('business_id', $business_id)->pluck('username', 'id');
        $users->prepend(__('lang_v1.all'), '');
        
        return view('report_sale_tracking.index', compact('users'));
    }

    public function SaleTrackingReport(Request $request)
    {
        if (!request()->ajax()) {
            return response()->json(['success' => false, 'msg' => 'Invalid request']);
        }

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->input('user_id');
        $date_range = $request->input('date_range');

        $query = User::where('users.business_id', $business_id);

        // Apply user filter to the main query if provided
        if (!empty($user_id)) {
            $query->where('users.id', $user_id);
        }

        // Validate and convert date range format from MM/DD/YYYY to YYYY-MM-DD, then add time
        $date_range_array = !empty($date_range) ? explode(' ~ ', $date_range) : [];
        $has_valid_date_range = count($date_range_array) === 2;
        
        $start_date = null;
        $end_date = null;
        if ($has_valid_date_range) {
            // Convert MM/DD/YYYY to YYYY-MM-DD
            $start_parts = explode('/', $date_range_array[0]);
            $end_parts = explode('/', $date_range_array[1]);
            
            if (count($start_parts) === 3 && count($end_parts) === 3) {
                $start_date = sprintf('%s-%s-%s 00:00:01', $start_parts[2], $start_parts[0], $start_parts[1]);
                $end_date = sprintf('%s-%s-%s 23:59:59', $end_parts[2], $end_parts[0], $end_parts[1]);
            }
        }

        $query->select(
            'users.id',
            'users.username as sale_rep',
            \DB::raw('COUNT(DISTINCT transactions_visit.id) as total_visits'),
            \DB::raw('SUM(CASE WHEN transactions_visit.visit_status = "completed" THEN 1 ELSE 0 END) as success_visits'),
            \DB::raw('SUM(CASE WHEN transactions_visit.visit_status = "missed" THEN 1 ELSE 0 END) as missed_visits'),
            \DB::raw('COUNT(DISTINCT transactions_visit.contact_id) as unique_customers')
        )
        ->selectRaw(
            '(SELECT COALESCE(SUM(tsl.quantity), 0)
            FROM transaction_sell_lines tsl
            INNER JOIN transactions t ON tsl.transaction_id = t.id
            INNER JOIN products p ON tsl.product_id = p.id
            WHERE t.created_by = users.id
            AND t.business_id = ?
            AND t.type = "sell"
            AND t.status = "final"
            AND p.product_kpi = 2
            AND tsl.parent_sell_line_id IS NULL
            AND NOT EXISTS (
                SELECT 1 
                FROM rewards_exchange re 
                WHERE re.product_for_sale = tsl.product_id 
                AND re.business_id = ?
                AND re.type = "customers"
                AND re.deleted_at IS NULL
            )' . 
            ($has_valid_date_range ? ' AND t.transaction_date BETWEEN ? AND ?' : '') .
            ') as total_products_sold',
            array_filter([
                $business_id,
                $business_id,
                $start_date,
                $end_date
            ])
        )
        ->selectRaw(
            '(SELECT COALESCE(SUM(tsl.quantity), 0)
            FROM transaction_sell_lines tsl
            INNER JOIN transactions t ON tsl.transaction_id = t.id
            INNER JOIN products p ON tsl.product_id = p.id
            WHERE t.created_by = users.id
            AND t.business_id = ?
            AND t.type = "reward_exchange"
            AND t.status = "completed"
            AND p.product_kpi = 2
            AND tsl.parent_sell_line_id IS NULL' .
            ($has_valid_date_range ? ' AND t.transaction_date BETWEEN ? AND ?' : '') .
            ') as total_ring_exchange',
            array_filter([
                $business_id,
                $start_date,
                $end_date
            ])
        )
        ->leftJoin('transactions_visit', function ($join) use ($business_id, $user_id, $start_date, $end_date, $has_valid_date_range) {
            $join->on('users.id', '=', 'transactions_visit.create_by')
                ->where('transactions_visit.business_id', '=', $business_id);
            
            // Apply user filter to transactions_visit if provided
            if (!empty($user_id)) {
                $join->where('transactions_visit.create_by', $user_id);
            }
            
            // Apply date range filter to transactions_visit if provided and valid
            if ($has_valid_date_range) {
                $join->whereBetween('transactions_visit.transaction_date', [$start_date, $end_date]);
            }
        })
        ->groupBy('users.id', 'users.username');

        $sales_data = $query->get();

        return DataTables::of($sales_data)
            ->addColumn('sale_rep', function ($row) {
                return $row->sale_rep ?? 'Unknown';
            })
            ->addColumn('total_visits', function ($row) {
                return $row->total_visits;
            })
            ->addColumn('missed_visits', function ($row) {
                return $row->missed_visits;
            })
            ->addColumn('success_visits', function ($row) {
                return $row->success_visits;
            })
            ->addColumn('unique_customers', function ($row) {
                return $row->unique_customers;
            })
            ->addColumn('total_products_sold', function ($row) {
                return number_format($row->total_products_sold, 0, '', '');
            })
            ->addColumn('total_ring_exchange', function ($row) {
                return number_format($row->total_ring_exchange, 0, '', '');
            })
            ->make(true);
    }

    public function CompetitorReport(Request $request)
    {
        if (!request()->ajax()) {
            return response()->json(['success' => false, 'msg' => 'Invalid request']);
        }

        $business_id = $request->session()->get('user.business_id');
        $date_range = $request->input('date_range');

        // Validate and convert date range format from MM/DD/YYYY to YYYY-MM-DD
        $date_range_array = !empty($date_range) ? explode(' ~ ', $date_range) : [];
        $has_valid_date_range = count($date_range_array) === 2;
        
        $start_date = null;
        $end_date = null;
        if ($has_valid_date_range) {
            $start_parts = explode('/', $date_range_array[0]);
            $end_parts = explode('/', $date_range_array[1]);
            
            if (count($start_parts) === 3 && count($end_parts) === 3) {
                $start_date = sprintf('%s-%s-%s 00:00:01', $start_parts[2], $start_parts[0], $start_parts[1]);
                $end_date = sprintf('%s-%s-%s 23:59:59', $end_parts[2], $end_parts[0], $end_parts[1]);
            }
        }

        $query = CompetitorProduct::where('competitor_product.business_id', $business_id)
            ->select(
                'competitor_product.id',
                'own_product.sku as sku',
                'own_product.name as own_product',
                \DB::raw('COALESCE((
                    SELECT SUM(tslv.quantity)
                    FROM transaction_sell_lines_visit tslv
                    INNER JOIN transactions_visit tv ON tslv.transaction_id = tv.id
                    INNER JOIN products p ON tslv.product_id = p.id
                    WHERE p.sku = competitor_product.own_product_sku
                    AND tv.business_id = ?' .
                    ($has_valid_date_range ? ' AND tv.transaction_date BETWEEN ? AND ?' : '') .
                '), 0) as own_product_qty'),
                'comp1_product.name as comp1',
                \DB::raw('COALESCE((
                    SELECT SUM(tslv.quantity)
                    FROM transaction_sell_lines_visit tslv
                    INNER JOIN transactions_visit tv ON tslv.transaction_id = tv.id
                    INNER JOIN products p ON tslv.product_id = p.id
                    WHERE p.sku = competitor_product.competitor_product1_sku
                    AND tv.business_id = ?' .
                    ($has_valid_date_range ? ' AND tv.transaction_date BETWEEN ? AND ?' : '') .
                '), 0) as comp1_qty'),
                'comp2_product.name as comp2',
                \DB::raw('COALESCE((
                    SELECT SUM(tslv.quantity)
                    FROM transaction_sell_lines_visit tslv
                    INNER JOIN transactions_visit tv ON tslv.transaction_id = tv.id
                    INNER JOIN products p ON tslv.product_id = p.id
                    WHERE p.sku = competitor_product.competitor_product2_sku
                    AND tv.business_id = ?' .
                    ($has_valid_date_range ? ' AND tv.transaction_date BETWEEN ? AND ?' : '') .
                '), 0) as comp2_qty')
            )
            ->join('products as own_product', function ($join) {
                $join->on('competitor_product.own_product_sku', '=', \DB::raw('own_product.sku COLLATE utf8mb4_unicode_ci'));
            })
            ->leftJoin('products as comp1_product', function ($join) {
                $join->on('competitor_product.competitor_product1_sku', '=', \DB::raw('comp1_product.sku COLLATE utf8mb4_unicode_ci'));
            })
            ->leftJoin('products as comp2_product', function ($join) {
                $join->on('competitor_product.competitor_product2_sku', '=', \DB::raw('comp2_product.sku COLLATE utf8mb4_unicode_ci'));
            });

        $bindings = array_filter([
            $business_id,
            $has_valid_date_range ? $start_date : null,
            $has_valid_date_range ? $end_date : null,
            $business_id,
            $has_valid_date_range ? $start_date : null,
            $has_valid_date_range ? $end_date : null,
            $business_id,
            $has_valid_date_range ? $start_date : null,
            $has_valid_date_range ? $end_date : null,
            $business_id // For the where clause
        ]);

        $competitor_data = $query->setBindings($bindings)->get();

        return DataTables::of($competitor_data)
            ->addColumn('sku', function ($row) {
                return $row->sku;
            })
            ->addColumn('own_product', function ($row) {
                return $row->own_product;
            })
            ->addColumn('own_product_qty', function ($row) {
                return number_format($row->own_product_qty, 0, '', '');
            })
            ->addColumn('comp1', function ($row) {
                return $row->comp1 ?? 'N/A';
            })
            ->addColumn('comp1_qty', function ($row) {
                return number_format($row->comp1_qty, 0, '', '');
            })
            ->addColumn('comp2', function ($row) {
                return $row->comp2 ?? 'N/A';
            })
            ->addColumn('comp2_qty', function ($row) {
                return number_format($row->comp2_qty, 0, '', '');
            })
            ->addColumn('total_qty', function ($row) {
                // Fixed: Include own_product_qty in total_qty calculation
                $total = $row->comp1_qty + $row->comp2_qty;
                return number_format($total, 0, '', '');
            })
            ->addColumn('own_product_percent', function ($row) {
                $total = $row->own_product_qty + $row->comp1_qty + $row->comp2_qty;
                $percent = $total > 0 ? ($row->own_product_qty / $total) * 100 : 0;
                $formatted_percent = number_format($percent, 2) . '%';
                // Color logic: ≥ 50.00% green, ≤ 49.99% red
                $color_class = $percent >= 50.00 ? 'text-green' : 'text-red';
                return "<span class='{$color_class}'>{$formatted_percent}</span>";
            })
            ->addColumn('competitor_percent', function ($row) {
                $total = $row->own_product_qty + $row->comp1_qty + $row->comp2_qty;
                $competitor_qty = $row->comp1_qty + $row->comp2_qty;
                $percent = $total > 0 ? ($competitor_qty / $total) * 100 : 0;
                $formatted_percent = number_format($percent, 2) . '%';
                // Color logic: ≥ 50.00% green, ≤ 49.99% red
                $color_class = $percent >= 50.00 ? 'text-green' : 'text-red';
                return "<span class='{$color_class}'>{$formatted_percent}</span>";
            })
            ->addColumn('status', function ($row) {
                $total = $row->own_product_qty + $row->comp1_qty + $row->comp2_qty;
                $own_percent = $total > 0 ? ($row->own_product_qty / $total) * 100 : 0;
                $competitor_qty = $row->comp1_qty + $row->comp2_qty;
                $competitor_percent = $total > 0 ? ($competitor_qty / $total) * 100 : 0;
                
                // Use a small epsilon to handle floating-point comparison
                $epsilon = 0.0001;
                if (abs($own_percent - $competitor_percent) < $epsilon) {
                    $status = 'No Win No Lost';
                    $color_class = 'text-orange';
                } else {
                    $status = $own_percent > $competitor_percent ? 'Win' : 'Lost';
                    $color_class = $status === 'Win' ? 'text-green' : 'text-red';
                }
                
                return "<span class='{$color_class}'>{$status}</span>";
            })
            ->rawColumns(['own_product_percent', 'competitor_percent', 'status']) // Allow HTML in these columns
            ->make(true);
    }
}