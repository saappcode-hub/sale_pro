<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\StockRingBalanceCustomer;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AllRingBackupController extends Controller
{
    public function __construct(TransactionUtil $transactionUtil, BusinessUtil $businessUtil, Util $commonUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->commonUtil = $commonUtil;
        $this->middleware('auth');
    }

    private function getDataForTableReward(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $request->input('product_id'); // Get the product_id from the request
    
        // Base query for rewards_exchange
        $query = DB::table('rewards_exchange')
            ->leftJoin('products', 'rewards_exchange.exchange_product', '=', 'products.id') // Join with products to get product details
            ->leftJoin(DB::raw('(SELECT product_id, business_id, SUM(stock_ring_balance) as stock_ring_balance
                                FROM stock_ring_balance_customer
                                GROUP BY product_id, business_id) as stock_summary'), function($join) {
                $join->on('products.id', '=', 'stock_summary.product_id')
                     ->on('rewards_exchange.business_id', '=', 'stock_summary.business_id');
            }) // Join rewards_exchange to aggregated stock_ring_balance_customer
            ->leftJoin('variation_location_details', function($join) {
                $join->on('rewards_exchange.exchange_product', '=', 'variation_location_details.product_id');
            }) // Join for available quantity
            ->leftJoin('units', 'products.unit_id', '=', 'units.id') // Join with units table
            ->leftJoin(DB::raw('(SELECT product_id, SUM(quantity) AS supplier_exchange
                                FROM transaction_sell_lines
                                JOIN transactions ON transaction_sell_lines.transaction_id = transactions.id
                                WHERE transactions.type = "supplier_exchange"
                                GROUP BY product_id) as supplier_exchange_totals'), function($join) {
                $join->on('rewards_exchange.exchange_product', '=', 'supplier_exchange_totals.product_id');
            }) // Join for supplier_exchange
            ->leftJoin(DB::raw('(SELECT product_id, SUM(quantity) AS supplier_exchange_receive
                                FROM transaction_sell_lines
                                JOIN transactions ON transaction_sell_lines.transaction_id = transactions.id
                                WHERE transactions.type = "supplier_exchange_receive"
                                GROUP BY product_id) as supplier_exchange_receive_totals'), function($join) {
                $join->on('rewards_exchange.exchange_product', '=', 'supplier_exchange_receive_totals.product_id');
            }) // Join for supplier_exchange_receive
            ->where('rewards_exchange.business_id', $business_id)
            ->where('rewards_exchange.type', 'customers') // Adjust this if it should be 'suppliers' based on your new query details
            ->whereNull('rewards_exchange.deleted_at');
    
        if ($product_id) {
            $query->where('rewards_exchange.exchange_product', $product_id);
        }
    
        $query->select(
            'products.id as product_id',
            'products.name as product_name',
            'rewards_exchange.exchange_quantity',
            DB::raw('COALESCE(stock_summary.stock_ring_balance, 0) as stock_ring_balance'),
            DB::raw('COALESCE(variation_location_details.qty_available, 0) as qty_available'),
            'units.short_name as unit_name',
            DB::raw('(COALESCE(stock_summary.stock_ring_balance, 0) + COALESCE(variation_location_details.qty_available, 0)) as total_stock'),
            DB::raw('COALESCE(supplier_exchange_totals.supplier_exchange, 0) as supplier_exchange'),
            DB::raw('COALESCE(supplier_exchange_receive_totals.supplier_exchange_receive, 0) as supplier_exchange_receive'),
            DB::raw('COALESCE(supplier_exchange_totals.supplier_exchange, 0) - COALESCE(supplier_exchange_receive_totals.supplier_exchange_receive, 0) as total_supplier')
        );
    
        // Execute the query and return the data
        $data = $query->get();
        return $data; // Return the results
    }

    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        if ($request->ajax()) {
            $data = $this->getDataForTableReward($request); // Get data using the raw SQL query
       
             // Fetch the exchange_quantity for each product in the fetched data
            $data->each(function ($row) use ($business_id) {
                $supplierExchange = DB::table('rewards_exchange')
                    ->where('business_id', $business_id)
                    ->where('type', 'suppliers')
                    ->where('exchange_product', $row->product_id)
                    ->select('exchange_product', 'exchange_quantity')
                    ->first();

                $row->supplier_exchange_quantity = $supplierExchange ? $supplierExchange->exchange_quantity : 0;
            });
            
            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $view = '<li><a href="' . route('all-ring.show', $row->product_id) . '"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">' . $view . '</ul>
                            </div>';
                })
                ->addColumn('product_name', function($row) {
                    return $row->product_name; // Display product name
                })
                ->addColumn('stock_ring_balance', function($row) {
                    return number_format($row->stock_ring_balance, 0); // Display stock ring balance
                })
                ->addColumn('qty_available', function($row) {
                    return number_format($row->qty_available, 0); // Display available quantity from VariationLocationDetails
                })
                ->addColumn('total_suppliers', function($row) {
                    return number_format($row->total_supplier * $row->supplier_exchange_quantity, 0); // Display available quantity from VariationLocationDetails
                })
                ->addColumn('total_stock', function($row) {
                    return number_format($row->total_stock, 0); // Display the total stock (sum of stock_ring_balance and qty_available)
                })
                ->rawColumns(['action']) // If you have any HTML in the action column
                ->make(true);
        }

        $business_id = $request->session()->get('user.business_id');
    
        // Get the product list for the given business_id
        $products = DB::table('rewards_exchange')
            ->leftJoin('products', 'rewards_exchange.exchange_product', '=', 'products.id')
            ->where('rewards_exchange.business_id', $business_id)
            ->select('products.id', 'products.name')
            ->get();
    
        // Convert to associative array for Form::select
        $product_list = ['' => 'All'] + $products->pluck('name', 'id')->toArray();
    
        return view('all_ring_balance.index', compact('product_list'));
    }

    public function show(Request $request, $id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid product ID');
        }

        $business_id = $request->session()->get('user.business_id');

        $selected_product = DB::table('products')
            ->join('rewards_exchange', 'products.id', '=', 'rewards_exchange.exchange_product')
            ->where('products.id', $id)
            ->where('rewards_exchange.business_id', $business_id)
            ->select('products.*')
            ->first();

        if (!$selected_product) {
            abort(404, 'Product not found for ID: ' . $id . ' and Business ID: ' . $business_id);
        }

        $exchange_products = DB::table('rewards_exchange')
            ->join('products', 'rewards_exchange.exchange_product', '=', 'products.id')
            ->where('rewards_exchange.business_id', $business_id)
            ->pluck('products.name', 'products.id');

        $business_locations = DB::table('business_locations')
            ->join('product_locations', 'business_locations.id', '=', 'product_locations.location_id')
            ->where('product_locations.product_id', $id)
            ->where('business_locations.is_active', 1)
            ->pluck('business_locations.name', 'business_locations.id');

        $product_data = DB::table('products')
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->leftJoin('transaction_sell_ring_balance', 'products.id', '=', 'transaction_sell_ring_balance.product_id')
            ->leftJoin('transactions_ring_balance', 'transaction_sell_ring_balance.transactions_ring_balance_id', '=', 'transactions_ring_balance.id')
            ->select(
                DB::raw("SUM(CASE WHEN transactions_ring_balance.type = 'top_up_ring_balance' THEN transaction_sell_ring_balance.quantity ELSE 0 END) AS quantities_in"),
                DB::raw("SUM(CASE WHEN transactions_ring_balance.type = 'reward_out' THEN transaction_sell_ring_balance.used_ring_balance ELSE 0 END) AS used_ring_balance"),
                'units.short_name as unit_short_name'
            )
            ->where('products.id', $id)
            ->where('transactions_ring_balance.business_id', $business_id)
            ->first();

        $supplier_exchange = DB::table('products')
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->leftJoin('stock_reward_exchange', 'products.id', '=', 'stock_reward_exchange.product_id')
            ->leftJoin('transactions', 'stock_reward_exchange.transaction_id', '=', 'transactions.id')
            ->select(
                DB::raw("SUM(CASE WHEN transactions.type = 'supplier_exchange' AND transactions.sub_type = 'send' THEN stock_reward_exchange.quantity ELSE 0 END) AS supplier_exchange_send"),
                'units.short_name as unit_short_name'
            )
            ->where('products.id', $id)
            ->where('transactions.business_id', $business_id)
            ->first();

        return view('all_ring_balance.show', compact(
            'selected_product',
            'exchange_products',
            'business_locations',
            'product_data',
            'supplier_exchange'
        ));
    }

    public function getRingStockHistory(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $product_id = $request->input('product_id');

    if (empty($product_id)) {
        return response()->json(['error' => 'Product ID is required'], 400);
    }

    // Fetch and sort transactions using the UNIX timestamp
    $transactions = DB::table(function ($query) use ($business_id, $product_id) {
        $query->from('transactions_ring_balance')
            ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
            ->where('transactions_ring_balance.business_id', $business_id)
            ->where('transaction_sell_ring_balance.product_id', $product_id)
            ->select(
                'transactions_ring_balance.type',
                DB::raw("CASE 
                            WHEN transactions_ring_balance.type = 'reward_out' THEN transaction_sell_ring_balance.used_ring_balance
                            WHEN transactions_ring_balance.type = 'top_up_ring_balance' THEN transaction_sell_ring_balance.quantity
                            ELSE 0 
                         END as quantity_change"),
                DB::raw("UNIX_TIMESTAMP(transactions_ring_balance.transaction_date) as date"),
                'transactions_ring_balance.invoice_no'
            )
            ->unionAll(
                DB::table('stock_reward_exchange')
                    ->join('transactions', 'stock_reward_exchange.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('stock_reward_exchange.product_id', $product_id)
                    ->where('transactions.type', 'supplier_exchange')
                    ->select(
                        DB::raw("'supplier_exchange' as type"),
                        'stock_reward_exchange.quantity as quantity_change',
                        DB::raw("UNIX_TIMESTAMP(transactions.transaction_date) as date"),
                        'transactions.ref_no'
                    )
            );
    }, 'combined_transactions')
    ->orderBy('date', 'desc')
    ->get();

    // Reverse the order for accumulation from the most recent
    $new_quantity = 0;
    $results = $transactions->reverse()->map(function ($transaction) use (&$new_quantity) {
        // Adjust new_quantity only if the transaction is not reward_out
        if ($transaction->type !== 'reward_out') {
            $new_quantity += $transaction->quantity_change;
        }
        
        return [
            'type' => $transaction->type === 'reward_out' ? 'Customer reward exchange' : ucfirst(str_replace('_', ' ', $transaction->type)),
            'quantity_change' => number_format($transaction->quantity_change, 2),
            'new_quantity' => number_format($new_quantity, 2),
            'date' => date('Y-m-d H:i:s', $transaction->date),
            'invoice_no' => $transaction->invoice_no,
        ];
    })->reverse(); // Ensure the final display is from the most recent to the oldest

    return DataTables::of($results)
        ->addColumn('type', fn($row) => $row['type'])
        ->addColumn('quantity_change', function ($row) {
            $color = $row['quantity_change'] < 0 ? 'red' : 'green';
            return "<span style='color: $color;'>{$row['quantity_change']}</span>";
        })
        ->addColumn('new_quantity', fn($row) => $row['new_quantity'])
        ->addColumn('date', fn($row) => $row['date'])
        ->addColumn('invoice_no', fn($row) => $row['invoice_no'])
        ->rawColumns(['quantity_change'])
        ->make(true);
}

}
