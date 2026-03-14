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

class AllRingBalanceController extends Controller
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
        ->leftJoin(DB::raw('(SELECT srbc.product_id, srbc.business_id, SUM(srbc.stock_ring_balance) as stock_ring_balance
                            FROM stock_ring_balance_customer srbc
                            JOIN contacts c ON srbc.contact_id = c.id
                            WHERE c.type = "customer"
                            GROUP BY srbc.product_id, srbc.business_id) as stock_summary'), function($join) {
            $join->on('products.id', '=', 'stock_summary.product_id')
                ->on('rewards_exchange.business_id', '=', 'stock_summary.business_id');
        })
        ->leftJoin(DB::raw('(SELECT srbs.product_id, srbs.business_id, SUM(srbs.stock_ring_balance) as stock_ring_balance_supplier
                            FROM stock_ring_balance_customer srbs
                            JOIN contacts c ON srbs.contact_id = c.id
                            WHERE c.type = "supplier"
                            GROUP BY srbs.product_id, srbs.business_id) as stock_summary_supplier'), function($join) {
            $join->on('products.id', '=', 'stock_summary_supplier.product_id')
                ->on('rewards_exchange.business_id', '=', 'stock_summary_supplier.business_id');
        })
        ->leftJoin(DB::raw('(SELECT product_id, SUM(qty_available) as qty_available
                            FROM variation_location_details
                            GROUP BY product_id) as variation_qty'), function($join) {
            $join->on('rewards_exchange.exchange_product', '=', 'variation_qty.product_id');
        }) // Join for available quantity from variation_location_details
        ->leftJoin('units', 'products.unit_id', '=', 'units.id') // Join with units table
        ->leftJoin(DB::raw('(SELECT product_id, SUM(quantity) AS supplier_exchange
                            FROM transaction_sell_lines
                            JOIN transactions ON transaction_sell_lines.transaction_id = transactions.id
                            WHERE transactions.type = "supplier_exchange"
                            AND transactions.sub_type = "send"
                            AND transactions.deleted_by IS NULL 
                            AND transactions.deleted_at IS NULL
                            GROUP BY product_id) as supplier_exchange_totals'), function($join) {
            $join->on('rewards_exchange.exchange_product', '=', 'supplier_exchange_totals.product_id');
        }) // Join for supplier_exchange with sub_type = send and deletion filter
        ->leftJoin(DB::raw('(SELECT product_id, SUM(quantity) AS supplier_exchange_receive
                            FROM transaction_sell_lines
                            JOIN transactions ON transaction_sell_lines.transaction_id = transactions.id
                            WHERE transactions.type = "supplier_exchange_receive"
                            AND transactions.status = "completed"
                            AND transactions.deleted_by IS NULL 
                            AND transactions.deleted_at IS NULL
                            GROUP BY product_id) as supplier_exchange_receive_totals'), function($join) {
            $join->on('rewards_exchange.exchange_product', '=', 'supplier_exchange_receive_totals.product_id');
        }) // Join for supplier_exchange_receive with deletion filter
        ->where('rewards_exchange.business_id', $business_id)
        ->where('rewards_exchange.type', 'customers') // Ensure correct type
        ->whereNull('rewards_exchange.deleted_at');

    if ($product_id) {
        $query->where('rewards_exchange.exchange_product', $product_id);
    }

    $query->select(
        'products.id as product_id',
        'products.name as product_name',
        'rewards_exchange.exchange_quantity',
        DB::raw('COALESCE(stock_summary.stock_ring_balance, 0) as stock_ring_balance'),
        DB::raw('COALESCE(stock_summary_supplier.stock_ring_balance_supplier, 0) as stock_ring_balance_supplier'),
        DB::raw('COALESCE(variation_qty.qty_available, 0) as qty_available'),
        'units.short_name as unit_name',
        DB::raw('(COALESCE(stock_summary.stock_ring_balance, 0) + COALESCE(variation_qty.qty_available, 0)) as total_stock'),
        DB::raw('COALESCE(supplier_exchange_totals.supplier_exchange, 0) as supplier_exchange'),
        DB::raw('COALESCE(supplier_exchange_receive_totals.supplier_exchange_receive, 0) as supplier_exchange_receive'),
        DB::raw('COALESCE(supplier_exchange_totals.supplier_exchange, 0) - COALESCE(supplier_exchange_receive_totals.supplier_exchange_receive, 0) as total_supplier')
    );

    // Execute the query and return the data
    $data = $query->get();
    return $data; // Return the results
}

    private function getDataForTableCashRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $request->input('product_id');
        
        $customerQuery = DB::table('cash_ring_balance')
        ->join('products', 'cash_ring_balance.product_id', '=', 'products.id')
        ->leftJoin('units', 'products.unit_id', '=', 'units.id')
        ->leftJoin('transaction_cash_ring_balance', 'cash_ring_balance.id', '=', 'transaction_cash_ring_balance.cash_ring_balance_id')
        // Additional joins as requested
        ->leftJoin('transaction_sell_ring_balance', 'transaction_cash_ring_balance.transaction_sell_ring_balance_id', '=', 'transaction_sell_ring_balance.id')
        ->leftJoin('transactions_ring_balance', 'transaction_sell_ring_balance.transactions_ring_balance_id', '=', 'transactions_ring_balance.id')
        ->where('cash_ring_balance.business_id', $business_id)
        // Add condition for deleted_at is null
        ->whereNull('transactions_ring_balance.deleted_at');

        if ($product_id) {
            $customerQuery->where('cash_ring_balance.product_id', $product_id);
        }

        $customerData = $customerQuery->select(
            'products.id as product_id',
            'products.name as product_name',
            'units.short_name as unit_name',
            
            // Customer totals from transaction_cash_ring_balance
            DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 1 OR cash_ring_balance.type_currency = 2 THEN COALESCE(transaction_cash_ring_balance.quantity, 0) ELSE 0 END) as customer_cash_ring_qty'),
            DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 1 THEN (COALESCE(transaction_cash_ring_balance.quantity, 0) * cash_ring_balance.unit_value) ELSE 0 END) as customer_cash_ring_dollar'),
            DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 2 THEN (COALESCE(transaction_cash_ring_balance.quantity, 0) * cash_ring_balance.unit_value) ELSE 0 END) as customer_cash_ring_riel')
        )
        ->groupBy('products.id', 'products.name', 'units.short_name')
        ->get();

        // Get supplier transactions separately (grouped by transaction to avoid duplicates)
        $supplierData = DB::table('transactions_supplier_cash_ring_detail')
            ->join('transactions_supplier_cash_ring', 'transactions_supplier_cash_ring_detail.transactions_supplier_cash_ring_id', '=', 'transactions_supplier_cash_ring.id')
            ->join('cash_ring_balance', 'transactions_supplier_cash_ring_detail.cash_ring_balance_id', '=', 'cash_ring_balance.id')
            ->where('transactions_supplier_cash_ring.business_id', $business_id)
            ->whereIn('transactions_supplier_cash_ring.status', ['send', 'claim'])
            ->when($product_id, function($query) use ($product_id) {
                return $query->where('transactions_supplier_cash_ring_detail.product_id', $product_id);
            })
            ->select(
                'transactions_supplier_cash_ring_detail.product_id',
                
                // Sum by transaction first, then by product to avoid double counting
                DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 1 OR cash_ring_balance.type_currency = 2 THEN transactions_supplier_cash_ring_detail.quantity ELSE 0 END) as supplier_cash_ring_qty'),
                DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 1 THEN (transactions_supplier_cash_ring_detail.quantity * cash_ring_balance.unit_value) ELSE 0 END) as supplier_cash_ring_dollar'),
                DB::raw('SUM(CASE WHEN cash_ring_balance.type_currency = 2 THEN (transactions_supplier_cash_ring_detail.quantity * cash_ring_balance.unit_value) ELSE 0 END) as supplier_cash_ring_riel')
            )
            ->groupBy('transactions_supplier_cash_ring_detail.product_id')
            ->get()
            ->keyBy('product_id');

            // Combine customer and supplier data
            $finalData = $customerData->map(function ($customer) use ($supplierData) {
            $supplier = $supplierData->get($customer->product_id);
            
            // Get supplier totals or default to 0
            $supplier_qty = $supplier ? $supplier->supplier_cash_ring_qty : 0;
            $supplier_dollar = $supplier ? $supplier->supplier_cash_ring_dollar : 0;
            $supplier_riel = $supplier ? $supplier->supplier_cash_ring_riel : 0;
            
            // Calculate final totals (customer - supplier since supplier transactions reduce the balance)
            $customer->supplier_cash_ring_qty = $supplier_qty;
            $customer->supplier_cash_ring_dollar = $supplier_dollar;
            $customer->supplier_cash_ring_riel = $supplier_riel;
            
            // Total = Customer input - Supplier output
            $customer->total_cash_ring_qty = $customer->customer_cash_ring_qty - $supplier_qty;
            $customer->total_cash_ring_dollar = $customer->customer_cash_ring_dollar - $supplier_dollar;
            $customer->total_cash_ring_riel = $customer->customer_cash_ring_riel - $supplier_riel;
            
            return $customer;
        });

        // Add products that only have supplier transactions (no customer transactions)
        $supplierOnlyProducts = $supplierData->filter(function($supplier) use ($customerData) {
            return !$customerData->contains('product_id', $supplier->product_id);
        });

        foreach ($supplierOnlyProducts as $supplier) {
            $product = DB::table('products')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->where('products.id', $supplier->product_id)
                ->select('products.id as product_id', 'products.name as product_name', 'units.short_name as unit_name')
                ->first();
                
            if ($product) {
                $product->customer_cash_ring_qty = 0;
                $product->customer_cash_ring_dollar = 0;
                $product->customer_cash_ring_riel = 0;
                $product->supplier_cash_ring_qty = $supplier->supplier_cash_ring_qty;
                $product->supplier_cash_ring_dollar = $supplier->supplier_cash_ring_dollar;
                $product->supplier_cash_ring_riel = $supplier->supplier_cash_ring_riel;
                $product->total_cash_ring_qty = 0 - $supplier->supplier_cash_ring_qty;
                $product->total_cash_ring_dollar = 0 - $supplier->supplier_cash_ring_dollar;
                $product->total_cash_ring_riel = 0 - $supplier->supplier_cash_ring_riel;
                
                $finalData->push($product);
            }
        }

        return $finalData;
    }

    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        
        if ($request->ajax()) {
            $tab = $request->input('tab', 'ring'); // Default to 'ring' tab
            
            if ($tab === 'cash_ring') {
                $data = $this->getDataForTableCashRing($request);
                
                return DataTables::of($data)
                    ->addColumn('action', function ($row) {
                        $view = '<li><a href="' . route('all-ring.showCashRing', $row->product_id) . '"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                        return '<div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">' . $view . '</ul>
                                </div>';
                    })
                    ->addColumn('product_name', function($row) {
                        return $row->product_name;
                    })
                    ->addColumn('total_cash_ring_qty', function($row) {
                        return number_format($row->total_cash_ring_qty, 0);
                    })
                    ->addColumn('total_cash_ring_dollar', function($row) {
                        return number_format($row->total_cash_ring_dollar, 0).' $';
                    })
                    ->addColumn('total_cash_ring_riel', function($row) {
                        return number_format($row->total_cash_ring_riel, 0) . ' ៛';
                    })
                    ->rawColumns(['action'])
                    ->make(true);
            } else {
                // Original ring data
                $data = $this->getDataForTableReward($request);
        
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
                        return $row->product_name;
                    })
                    ->addColumn('stock_ring_balance', function($row) {
                        return number_format($row->stock_ring_balance, 0);
                    })
                    ->addColumn('qty_available', function($row) {
                        return number_format($row->qty_available, 0);
                    })
                    ->addColumn('total_suppliers', function($row) {
                        return number_format($row->stock_ring_balance_supplier, 0);
                    })
                    // ->addColumn('total_suppliers', function($row) {
                    //     return number_format($row->total_supplier * $row->supplier_exchange_quantity, 0);
                    // })
                    ->addColumn('total_stock', function($row) {
                        return number_format($row->total_stock, 0);
                    })
                    ->rawColumns(['action'])
                    ->make(true);
            }
        }

        // Get product list for both ring and cash ring
        $ring_products = DB::table('rewards_exchange')
            ->leftJoin('products', 'rewards_exchange.exchange_product', '=', 'products.id')
            ->where('rewards_exchange.business_id', $business_id)
            ->select('products.id', 'products.name')
            ->get();

        $cash_ring_products = DB::table('cash_ring_balance')
            ->join('products', 'cash_ring_balance.product_id', '=', 'products.id')
            ->where('cash_ring_balance.business_id', $business_id)
            ->select('products.id', 'products.name')
            ->distinct()
            ->get();

        // Merge and create product lists
        $all_products = $ring_products->merge($cash_ring_products)->unique('id');
        $product_list = ['' => 'All'] + $all_products->pluck('name', 'id')->toArray();

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
            DB::raw("SUM(CASE 
                WHEN transactions_ring_balance.type = 'top_up_ring_balance' 
                AND transactions_ring_balance.status = 'completed' 
                AND transactions_ring_balance.deleted_by IS NULL 
                AND transactions_ring_balance.deleted_at IS NULL
                THEN transaction_sell_ring_balance.quantity 
                ELSE 0 
            END) AS quantities_in"),
            DB::raw("SUM(CASE 
                WHEN transactions_ring_balance.type = 'reward_out' 
                AND transactions_ring_balance.status = 'completed' 
                AND transactions_ring_balance.deleted_by IS NULL 
                AND transactions_ring_balance.deleted_at IS NULL
                THEN transaction_sell_ring_balance.used_ring_balance 
                ELSE 0 
            END) AS used_ring_balance"),
            'units.short_name as unit_short_name'
        )
        ->where('products.id', $id)
        ->where('transactions_ring_balance.business_id', $business_id)
        ->first();

    $supplier_exchange = DB::table('products')
        ->leftJoin('units', 'products.unit_id', '=', 'units.id')
        ->leftJoin('stock_reward_exchange_new', 'products.id', '=', 'stock_reward_exchange_new.product_id')
        ->leftJoin('transactions', 'stock_reward_exchange_new.transaction_id', '=', 'transactions.id')
        ->select(
            DB::raw("SUM(CASE 
                WHEN transactions.type = 'supplier_exchange' 
                AND transactions.sub_type = 'send' 
                AND transactions.deleted_by IS NULL 
                AND transactions.deleted_at IS NULL
                THEN stock_reward_exchange_new.quantity 
                ELSE 0 
            END) AS supplier_exchange_send"),
            'units.short_name as unit_short_name'
        )
        ->where('products.id', $id)
        ->where('transactions.business_id', $business_id)
        ->first();

    // Fetch Ring Unit Details
    $ring_unit_details = DB::table('ring_unit')
        ->leftJoin('transaction_sell_ring_balance_ring_units', 'ring_unit.id', '=', 'transaction_sell_ring_balance_ring_units.ring_units_id')
        ->leftJoin('transaction_sell_ring_balance', 'transaction_sell_ring_balance_ring_units.transaction_sell_ring_balance_id', '=', 'transaction_sell_ring_balance.id')
        ->leftJoin('transactions_ring_balance', 'transaction_sell_ring_balance.transactions_ring_balance_id', '=', 'transactions_ring_balance.id')
        ->where('ring_unit.business_id', $business_id)
        ->where('ring_unit.product_id', $id)
        ->whereNull('transactions_ring_balance.deleted_by')
        ->whereNull('transactions_ring_balance.deleted_at')
        ->groupBy('ring_unit.id', 'ring_unit.value')
        ->select(
            'ring_unit.value',
            DB::raw("COALESCE(SUM(CASE 
                WHEN transactions_ring_balance.type = 'top_up_ring_balance' 
                AND transactions_ring_balance.status = 'completed' 
                THEN transaction_sell_ring_balance_ring_units.quantity_ring 
                ELSE 0 
            END), 0) as total_quantity_ring")
        )
        ->orderBy('ring_unit.value', 'asc')
        ->get();

    // Handle ring_unit_details logic
    if ($ring_unit_details->isEmpty()) {
        // If ring_unit_details is empty, set a default "1 Ring" entry with quantities_in
        $ring_unit_details = collect([
            (object) [
                'value' => 1,
                'total_quantity_ring' => $product_data->quantities_in ?? 0,
            ]
        ]);
    } else {
        $quantities_in = $product_data->quantities_in ?? 0;

        // Check if only value = 1 exists
        if ($ring_unit_details->count() === 1 && $ring_unit_details->first()->value === 1) {
            // If only value = 1 exists, set total_quantity_ring to quantities_in
            $ring_unit_details->first()->total_quantity_ring = $quantities_in;
        } else {
            // Compute A = sum(value * total_quantity_ring) for all ring units except value = 1
            $A = 0;
            foreach ($ring_unit_details as $ring_unit) {
                if ($ring_unit->value != 1) {
                    $A += $ring_unit->value * $ring_unit->total_quantity_ring;
                }
            }

            // Log the intermediate values for debugging
            \Log::info("Product ID: {$id}, Quantities In: {$quantities_in}, A (sum for value != 1): {$A}");

            // Compute B = quantities_in - A
            $B = $quantities_in - $A;

            // Log the computed B
            \Log::info("Product ID: {$id}, B (quantities_in - A): {$B}");

            // Check if value = 1 exists and update it, otherwise add it
            $found = false;
            foreach ($ring_unit_details as $ring_unit) {
                if ($ring_unit->value == 1) {
                    $ring_unit->total_quantity_ring = $B;
                    $found = true;
                    break;
                }
            }

            // If value = 1 is not found, add a new "1 Ring" entry with B
            if (!$found) {
                $ring_unit_details->push((object) [
                    'value' => 1,
                    'total_quantity_ring' => $B,
                ]);
            }

            // Sort the collection by value to maintain order
            $ring_unit_details = $ring_unit_details->sortBy('value')->values();

            // Log the final ring_unit_details for debugging
            \Log::info("Product ID: {$id}, Final Ring Unit Details: " . json_encode($ring_unit_details));
        }
    }

    return view('all_ring_balance.show', compact(
        'selected_product',
        'exchange_products',
        'business_locations',
        'product_data',
        'supplier_exchange',
        'ring_unit_details'
    ));
}

public function getRingStockHistory(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $product_id = $request->input('product_id');

    if (empty($product_id)) {
        return response()->json(['error' => 'Product ID is required'], 400);
    }

    // First, let's debug what's in the database
    \Log::info("Fetching ring stock history for product: {$product_id}, business: {$business_id}");

    // Check transactions_ring_balance records
    $ringBalanceCheck = DB::table('transactions_ring_balance')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->where('transactions_ring_balance.business_id', $business_id)
        ->where('transaction_sell_ring_balance.product_id', $product_id)
        ->where('transactions_ring_balance.status', 'completed')
        ->whereNull('transactions_ring_balance.deleted_by')
        ->whereNull('transactions_ring_balance.deleted_at')
        ->select(
            'transactions_ring_balance.*',
            'transaction_sell_ring_balance.quantity',
            'transaction_sell_ring_balance.used_ring_balance'
        )
        ->get();

    \Log::info("Ring balance records found: " . json_encode($ringBalanceCheck));

    // Fetch and sort transactions using the UNIX timestamp
    $transactions = DB::table(function ($query) use ($business_id, $product_id) {
        $query->from('transactions_ring_balance')
            ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
            ->where('transactions_ring_balance.business_id', $business_id)
            ->where('transaction_sell_ring_balance.product_id', $product_id)
            ->where('transactions_ring_balance.status', 'completed')
            ->whereNull('transactions_ring_balance.deleted_by')
            ->whereNull('transactions_ring_balance.deleted_at')
            ->select(
                'transactions_ring_balance.type',
                DB::raw("CASE 
                            WHEN transactions_ring_balance.type = 'reward_out' THEN COALESCE(transaction_sell_ring_balance.quantity, 0)
                            WHEN transactions_ring_balance.type = 'top_up_ring_balance' THEN COALESCE(transaction_sell_ring_balance.quantity, 0)
                            ELSE 0 
                        END as quantity_change"),
                DB::raw("UNIX_TIMESTAMP(transactions_ring_balance.transaction_date) as date"),
                'transactions_ring_balance.invoice_no'
            )
            ->unionAll(
                DB::table('stock_reward_exchange_new')
                    ->join('transactions', 'stock_reward_exchange_new.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('stock_reward_exchange_new.product_id', $product_id)
                    
                    // IMPORTANT: Only get shop deductions in supplier exchange
                    ->where('stock_reward_exchange_new.type', 'supplier_reward_out')
                    ->whereNull('stock_reward_exchange_new.contact_id')

                    ->where('transactions.type', 'supplier_exchange')
                    ->where('transactions.status', 'completed')
                    ->whereNull('transactions.deleted_by')
                    ->whereNull('transactions.deleted_at')

                    ->select(
                        DB::raw("'supplier_exchange' as type"),
                        DB::raw("COALESCE(stock_reward_exchange_new.quantity, 0) as quantity_change"),
                        DB::raw("UNIX_TIMESTAMP(transactions.transaction_date) as date"),
                        'transactions.ref_no as invoice_no'
                    )
            );
    }, 'combined_transactions')
    ->orderBy('date', 'asc') // Change to ASC for proper accumulation
    ->get();

    // Debug: Log the raw transactions
    \Log::info("Raw transactions for product {$product_id}: " . json_encode($transactions));

    if ($transactions->isEmpty()) {
        \Log::warning("No transactions found for product {$product_id}");
        return DataTables::of(collect())->make(true);
    }

    // Calculate running balance from oldest to newest
    $running_balance = 0;
    $results = collect();

    foreach ($transactions as $transaction) {
        // Convert quantity_change to float to ensure proper calculation
        $quantity_change = (float) $transaction->quantity_change;
        
        \Log::info("Processing transaction: type={$transaction->type}, quantity_change={$quantity_change}");
        
        // Handle different transaction types
        if ($transaction->type === 'reward_out') {
            // For reward_out, subtract from balance and show as negative
            $running_balance -= $quantity_change;
            $display_quantity = -$quantity_change;
            $type_display = 'Customer reward exchange';
        } elseif ($transaction->type === 'top_up_ring_balance') {
            // For top_up, add to balance and show as positive
            $running_balance += $quantity_change;
            $display_quantity = $quantity_change;
            $type_display = 'Top up ring balance';
        } elseif ($transaction->type === 'supplier_exchange') {
            // For supplier_exchange, add to balance and show as positive
            $running_balance += $quantity_change;
            $display_quantity = $quantity_change;
            $type_display = 'Supplier exchange';
        } else {
            // Default case
            $running_balance += $quantity_change;
            $display_quantity = $quantity_change;
            $type_display = ucfirst(str_replace('_', ' ', $transaction->type));
        }
        
        $results->push([
            'type' => $type_display,
            'quantity_change' => number_format($display_quantity, 2),
            'new_quantity' => number_format($running_balance, 2),
            'date' => date('Y-m-d H:i:s', $transaction->date),
            'invoice_no' => $transaction->invoice_no,
        ]);
    }

    // Reverse to show newest first in the table
    $results = $results->reverse()->values();

    // Debug: Log the final results
    \Log::info("Final results for product {$product_id}: " . json_encode($results));

    return DataTables::of($results)
        ->addColumn('type', fn($row) => $row['type'])
        ->addColumn('quantity_change', function ($row) {
            $quantity = (float) str_replace(',', '', $row['quantity_change']);
            $color = $quantity < 0 ? 'red' : 'green';
            return "<span style='color: $color;'>{$row['quantity_change']}</span>";
        })
        ->addColumn('new_quantity', fn($row) => $row['new_quantity'])
        ->addColumn('date', fn($row) => $row['date'])
        ->addColumn('invoice_no', fn($row) => $row['invoice_no'])
        ->rawColumns(['quantity_change'])
        ->make(true);
}

   public function showCashRing(Request $request, $id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid product ID');
        }
        
        $business_id = $request->session()->get('user.business_id');
        
        // First, check if product exists in products table
        $selected_product = DB::table('products')
            ->where('products.id', $id)
            ->select('products.*')
            ->first();
            
        if (!$selected_product) {
            abort(404, 'Product not found for ID: ' . $id);
        }
        
        // Check if this product has any cash ring stock balance
        $has_cash_ring_stock = DB::table('stock_cash_ring_balance_product')
            ->where('business_id', $business_id)
            ->where('product_id', $id)
            ->exists();
            
        // If no cash ring stock exists for this product, return error
        if (!$has_cash_ring_stock) {
            abort(404, 'No cash ring stock data found for this product in Business ID: ' . $business_id);
        }
        
        // Get all products that have cash ring stock
        $exchange_products = DB::table('stock_cash_ring_balance_product')
            ->join('products', 'stock_cash_ring_balance_product.product_id', '=', 'products.id')
            ->where('stock_cash_ring_balance_product.business_id', $business_id)
            ->distinct()
            ->pluck('products.name', 'products.id');
        
        $business_locations = DB::table('business_locations')
            ->join('product_locations', 'business_locations.id', '=', 'product_locations.location_id')
            ->where('product_locations.product_id', $id)
            ->where('business_locations.is_active', 1)
            ->pluck('business_locations.name', 'business_locations.id');
        
        // Get cash ring details from stock_cash_ring_balance_product with quantities
        $cash_ring_data = DB::table('stock_cash_ring_balance_product')
            ->join('cash_ring_balance', 'stock_cash_ring_balance_product.cash_ring_balance_id', '=', 'cash_ring_balance.id')
            ->where('stock_cash_ring_balance_product.product_id', $id)
            ->where('stock_cash_ring_balance_product.business_id', $business_id)
            ->select(
                'cash_ring_balance.type_currency',
                'cash_ring_balance.unit_value',
                DB::raw('COALESCE(SUM(stock_cash_ring_balance_product.stock_cash_ring_balance), 0) as total_quantity')
            )
            ->groupBy('cash_ring_balance.type_currency', 'cash_ring_balance.unit_value')
            ->orderBy('cash_ring_balance.type_currency')
            ->orderBy('cash_ring_balance.unit_value')
            ->get();
        
        // Separate data by currency type for easier display
        $dollar_data = $cash_ring_data->where('type_currency', 1);
        $riel_data = $cash_ring_data->where('type_currency', 2);
        
        // Calculate subtotals
        $dollar_subtotal = $dollar_data->sum('total_quantity');
        $riel_subtotal = $riel_data->sum('total_quantity');
        
        return view('all_ring_balance.show_cash_ring', compact(
            'selected_product',
            'exchange_products',
            'business_locations',
            'cash_ring_data',
            'dollar_data',
            'riel_data',
            'dollar_subtotal',
            'riel_subtotal'
        ));
    }

    public function getCashRingStockHistory(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $id; // Get from URL parameter
        
        if (empty($product_id) || !is_numeric($product_id)) {
            \Log::error('Invalid or missing Product ID in getCashRingStockHistory', ['id_parameter' => $id]);
            return response()->json(['error' => 'Valid Product ID is required'], 400);
        }
        
        try {
            // Create a collection to store all transactions
            $allTransactions = collect();
            
            // Query 1: Cash Ring Top Up and Adjustment transactions
            $cashRingTransactions = DB::table('transaction_sell_ring_balance')
                ->join('transactions_ring_balance', 'transaction_sell_ring_balance.transactions_ring_balance_id', '=', 'transactions_ring_balance.id')
                ->where('transactions_ring_balance.business_id', $business_id)
                ->where('transaction_sell_ring_balance.product_id', $product_id)
                ->where('transaction_sell_ring_balance.cash_ring', 1)
                ->where('transactions_ring_balance.status', 'completed')
                // **MODIFIED**: Fetch both 'top_up_ring_balance' and 'adjustment' types
                ->whereIn('transactions_ring_balance.type', ['top_up_ring_balance', 'adjustment']) 
                ->whereNull('transactions_ring_balance.deleted_at')
                ->select(
                    // **MODIFIED**: Use a CASE statement to set the display text for 'type'
                    DB::raw("CASE 
                        WHEN transactions_ring_balance.type = 'top_up_ring_balance' THEN 'Cash Ring Top Up'
                        WHEN transactions_ring_balance.type = 'adjustment' THEN 'Adjustment'
                        ELSE transactions_ring_balance.type
                    END as type"),
                    'transaction_sell_ring_balance.quantity as quantity_change',
                    'transactions_ring_balance.transaction_date as date',
                    'transactions_ring_balance.invoice_no',
                    DB::raw("UNIX_TIMESTAMP(transactions_ring_balance.transaction_date) as date_timestamp"),
                    'transaction_sell_ring_balance.id as unique_id',
                    DB::raw("'cash_ring' as transaction_source")
                )
                ->get();
                
            // Query 2: Supplier Cash Ring transactions
            $supplierCashRingTransactions = DB::table('transactions_supplier_cash_ring_detail')
                ->join('transactions_supplier_cash_ring', 'transactions_supplier_cash_ring_detail.transactions_supplier_cash_ring_id', '=', 'transactions_supplier_cash_ring.id')
                ->where('transactions_supplier_cash_ring.business_id', $business_id)
                ->where('transactions_supplier_cash_ring_detail.product_id', $product_id)
                ->whereIn('transactions_supplier_cash_ring.status', ['send', 'claim'])
                ->select(
                    DB::raw("'Supplier Cash Ring' as type"),
                    DB::raw('-SUM(transactions_supplier_cash_ring_detail.quantity) as quantity_change'),
                    'transactions_supplier_cash_ring.transaction_date as date',
                    'transactions_supplier_cash_ring.invoice_no',
                    DB::raw("UNIX_TIMESTAMP(transactions_supplier_cash_ring.transaction_date) as date_timestamp"),
                    'transactions_supplier_cash_ring.id as unique_id',
                    DB::raw("'supplier_cash_ring' as transaction_source")
                )
                ->groupBy(
                    'transactions_supplier_cash_ring.id',
                    'transactions_supplier_cash_ring.transaction_date',
                    'transactions_supplier_cash_ring.invoice_no'
                )
                ->get();
                
            // Merge all collections
            $allTransactions = $cashRingTransactions->merge($supplierCashRingTransactions);
            
            // Sort by date for chronological processing
            $chronologicalTransactions = $allTransactions->sortBy('date_timestamp');
            $running_quantity = 0;
            
            // Calculate running totals. This logic now correctly includes adjustments.
            $processedTransactions = $chronologicalTransactions->map(function ($transaction) use (&$running_quantity) {
                $running_quantity += $transaction->quantity_change;
                
                return [
                    'type' => $transaction->type,
                    'quantity_change' => $transaction->quantity_change,
                    'value_change' => $transaction->quantity_change, // Simple quantity as value
                    'new_quantity' => $running_quantity,
                    'date' => $transaction->date ? date('Y-m-d H:i:s', strtotime($transaction->date)) : 'N/A',
                    'invoice_no' => $transaction->invoice_no ?? 'N/A',
                ];
            });
            
            // Format the results for display
            $results = $processedTransactions->map(function ($row) {
                return [
                    'type' => $row['type'],
                    'quantity_change' => number_format(abs($row['quantity_change']), 2),
                    'original_quantity' => $row['quantity_change'], // Keep original for color logic
                    'value_change' => number_format(abs($row['value_change']), 0),
                    'new_quantity' => number_format($row['new_quantity'], 2),
                    'date' => $row['date'],
                    'invoice_no' => $row['invoice_no'],
                ];
            });
            
            return DataTables::of($results)
                ->editColumn('quantity_change', function ($row) {
                    $originalQty = $row['original_quantity'];
                    if ($originalQty >= 0) {
                        return "<span style='color: green;'>+{$row['quantity_change']}</span>";
                    } else {
                        return "<span style='color: red;'>-{$row['quantity_change']}</span>";
                    }
                })
                ->editColumn('value_change', function ($row) {
                    $originalQty = $row['original_quantity'];
                    if ($originalQty >= 0) {
                        return $row['value_change'];
                    } else {
                        return '-' . $row['value_change'];
                    }
                })
                ->rawColumns(['quantity_change'])
                ->make(true);
                
        } catch (\Exception $e) {
            \Log::error('Error in getCashRingStockHistory: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'product_id' => $product_id
            ]);
            
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }
}
