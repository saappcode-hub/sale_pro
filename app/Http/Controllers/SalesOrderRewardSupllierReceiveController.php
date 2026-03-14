<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\CurrentStockBackup;
use App\RewardsExchange;
use App\StockRewardsExchange;
use App\StockRingBalanceCustomer;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderRewardSupllierReceiveController extends Controller
{
    protected $transactionUtil;
    protected $businessUtil;
    protected $commonUtil;
    protected $moduleUtil;
    public function __construct(TransactionUtil $transactionUtil, BusinessUtil $businessUtil, Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
        $this->middleware('auth');
    }
    private function getDataForTableReward(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->location_id;
        $contact_id = $request->contact_id;
    
        $query = Transaction::query()
            ->with([
                'contact',
                'location',
                'sales_person'
            ])
            ->where('business_id', $business_id)
            ->where('type', 'supplier_exchange_receive')
            ->whereNull('deleted_at');
    
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }
    
        if ($location_id && $location_id != 'all') {
            $query->where('location_id', $location_id);
        }
    
        if ($contact_id && $contact_id != 'all') {
            $query->where('contact_id', $contact_id);
        }
    
        // Apply date filter only if start_date and end_date are provided
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('transaction_date', [$request->start_date . " 00:00:01", $request->end_date . " 23:59:59"]);
        }
    
        return $query->select([
                'transactions.id',
                'transactions.ref_no',
                'transactions.transaction_date as date',
                'transactions.contact_id',
                'transactions.location_id',
                'transactions.status',
                'transactions.final_total',
                'transactions.created_by',
                'transactions.payment_status',
            ])
            ->orderBy('date', 'desc')
            ->get();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->getDataForTableReward($request);
        
            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $view = '<li><a href="#" data-href="'.action([\App\Http\Controllers\SalesOrderRewardSupllierReceiveController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    $payment = '<li><a href="#" data-href="'.action([\App\Http\Controllers\SalesOrderRewardSupllierReceiveController::class, 'payment'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-money-bill-alt" aria-hidden="true"></i> '.__('Add Payment').'</a></li>';
                    
                    // Conditionally include Edit and Update Status buttons if status is not completed
                    $edit = $row->status !== 'completed' ? '<li><a href="' . route('sale-reward-supplier-receive.edit', $row->id) . '"><i class="fas fa-edit"></i> '.__('Edit').'</a></li>' : '';
                    $update_status = $row->status !== 'completed' ? '<li><a href="#" data-href="' . action([\App\Http\Controllers\SalesOrderRewardSupllierReceiveController::class, 'status'], [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-edit"></i> '.__('Update Status').'</a></li>' : '';
                    $deleteButton = '<li><a href="#" class="delete-supplier-receive" data-href="'.route('sale-reward-supplier-receive.destroy', $row->id).'" data-csrf="'.csrf_token().'"><i class="fa fa-trash"></i> '.__('messages.delete').'</a></li>';
                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    ' . $view . '
                                    ' . $edit . '
                                    ' . $payment . '
                                    ' . $update_status . '
                                    ' . $deleteButton . '
                                </ul></div>';
                })
                ->addColumn('date', function($row) {
                    return date('d-m-Y H:i', strtotime($row->date));
                })
                ->addColumn('ref_no', function($row) {
                    return $row->ref_no;
                })
                ->addColumn('contact_name', function($row) {
                    return $row->contact ? $row->contact->name : '';
                })
                ->addColumn('contact_mobile', function($row) {
                    return $row->contact ? $row->contact->mobile : '';
                })
                ->addColumn('location_name', function($row) {
                    return $row->location ? $row->location->name : '';
                })
                ->addColumn('status', function($row) {
                    return $row->status;
                })
                ->addColumn('final_total', function($row) {
                    return number_format($row->final_total, 2);
                })
                ->addColumn('payment_status', function($row) {
                    return $row->payment_status;
                })
                ->addColumn('added_by', function($row) {
                    return $row->sales_person ? $row->sales_person->last_name." ".$row->sales_person->first_name : '';
                })
                ->rawColumns(['action'])
                ->make(true); 
        }

        $business_id = $request->session()->get('user.business_id');

        // Business locations
        $business_locations = BusinessLocation::where('business_id', $business_id)->pluck('name', 'id');
        $business_locations->prepend(__('lang_v1.all'), '');

        // Contacts
        $contact = Contact::where('type', 'supplier')
            ->where('business_id', $business_id)
            ->pluck('name', 'id');
        $contact->prepend(__('lang_v1.all'), '');

        return view('sale_reward_supllier_receive.index', compact('business_locations', 'contact'));
    }

    public function create()
    {
        $sale_type = request()->get('sale_type', '');
        $business_id = request()->session()->get('user.business_id');
        $business_details = $this->businessUtil->getDetails($business_id);
        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];
        $default_location = null;
        foreach ($business_locations as $id => $name) {
            $default_location = BusinessLocation::findOrFail($id);
            break;
        }
        $default_datetime = $this->businessUtil->format_date('now', true);
        // Contacts
        $contact = Contact::where('type', 'supplier')
                    ->where('business_id', $business_id)
                    ->pluck('name', 'id');
        $contact->prepend(__('lang_v1.all'), '');
        return view('sale_reward_supllier_receive.create')
            ->with(compact(
                'business_details',
                'business_locations',
                'bl_attributes',
                'default_location',
                'default_datetime',
                'sale_type',
                'contact',
            ));
    }

    public function fetchExchangeData(Request $request)
{
    $refNo = $request->ref_no;
    $business_id = $request->session()->get('user.business_id');

    // Fetch the main transaction based on the ref_no
    $transaction = Transaction::where('ref_no', $refNo)
        ->where('type', 'supplier_exchange')
        ->where('business_id', $business_id)
        ->whereNull('deleted_at') // Add condition to exclude soft-deleted supplier_exchange
        ->first();

    // If no transaction is found, return a 404
    if (!$transaction) {
        return response()->json([], 404);
    }

    // Get suppliers_exchange_ids from the current transaction
    $suppliersExchangeIds = $transaction->id;

    // Find supplier_exchange_receive transactions where suppliers_exchange_ids match
    // IMPORTANT: Only include non-deleted supplier_exchange_receive transactions
    $receivedTransactions = Transaction::where('suppliers_exchange_ids', $suppliersExchangeIds)
        ->where('type', 'supplier_exchange_receive')
        ->whereNull('deleted_at') // Add condition to exclude soft-deleted receive transactions
        ->get();
       
    // Initialize an array to store the total received quantities
    $receivedQuantities = [];

    // Loop through the received transactions to calculate the sum of received quantities
    foreach ($receivedTransactions as $receivedTransaction) {
        $receivedLines = TransactionSellLine::where('transaction_id', $receivedTransaction->id)
            ->get();

        // Sum the quantity for each product in the transaction
        foreach ($receivedLines as $line) {
            if (isset($receivedQuantities[$line->product_id])) {
                // Add the quantity received from the current transaction
                $receivedQuantities[$line->product_id] += $line->quantity;
            } else {
                // Initialize the quantity for the product
                $receivedQuantities[$line->product_id] = $line->quantity;
            }
        }
    }
  
    // Now, we need to fetch the exchange data for this reference number
    $data = Transaction::join('transaction_sell_lines', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
        ->join('rewards_exchange', 'transaction_sell_lines.product_id', '=', 'rewards_exchange.exchange_product')
        ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
        ->where('transactions.ref_no', $refNo)
        ->where('transactions.type', 'supplier_exchange')
        ->where('transactions.business_id', $business_id)
        ->where('rewards_exchange.type', 'suppliers')
        ->whereNull('transactions.deleted_at') // Add condition to ensure main transaction is not deleted
        ->get([
            'transactions.id as transaction_id',
            'transactions.contact_id as supplier_id',
            'products.id as product_id',
            'products.name as product_name',
            'rewards_exchange.exchange_quantity',
            'rewards_exchange.receive_quantity',
            'transaction_sell_lines.quantity',
            'transaction_sell_lines.unit_price as price'
        ]);

    // Adjust the quantity dynamically based on the previously received data
    $data = $data->filter(function ($item) use ($receivedQuantities) {
        if (isset($receivedQuantities[$item->product_id])) {
            $item->quantity = max(0, $item->quantity - $receivedQuantities[$item->product_id]);
        }
        return $item->quantity > 0; // Filter out items with 0 quantity
    });

    // Check if all items have been fully received
    $allReceived = true;
    foreach ($data as $item) {
        if ($item->quantity > 0) {
            $allReceived = false;
            break;
        }
    }

    return response()->json([
        'data' => $data,
        'allReceived' => $allReceived
    ]);
}
    /**
     * Update the status of the original supplier_exchange transaction
     * based on remaining supplier_exchange_receive transactions
     */
    private function updateOriginalExchangeStatus($originalExchangeId, $business_id)
    {
        try {
            // Get the original supplier_exchange transaction
            $originalTransaction = Transaction::where('id', $originalExchangeId)
                ->where('business_id', $business_id)
                ->where('type', 'supplier_exchange')
                ->whereNull('deleted_at')
                ->first();
                
            if (!$originalTransaction) {
                return;
            }
            
            // Get all remaining (non-deleted) supplier_exchange_receive transactions with completed status
            $completedReceiveTransactions = Transaction::where('suppliers_exchange_ids', $originalExchangeId)
                ->where('type', 'supplier_exchange_receive')
                ->where('status', 'completed')
                ->whereNull('deleted_at') // IMPORTANT: Only non-deleted transactions
                ->get();
                
            // Calculate total received quantities from completed transactions only
            $receivedQuantities = [];
            foreach ($completedReceiveTransactions as $receiveTransaction) {
                $sellLines = TransactionSellLine::where('transaction_id', $receiveTransaction->id)->get();
                foreach ($sellLines as $line) {
                    if (isset($receivedQuantities[$line->product_id])) {
                        $receivedQuantities[$line->product_id] += $line->quantity;
                    } else {
                        $receivedQuantities[$line->product_id] = $line->quantity;
                    }
                }
            }
            
            // Get original exchange quantities
            $originalSellLines = TransactionSellLine::where('transaction_id', $originalExchangeId)->get();
            
            // Check if all quantities are fully received
            $allCompleted = true;
            $anyReceived = false;
            
            foreach ($originalSellLines as $line) {
                $originalQuantity = $line->quantity;
                $receivedQuantity = $receivedQuantities[$line->product_id] ?? 0;
                
                if ($receivedQuantity > 0) {
                    $anyReceived = true;
                }
                
                if ($receivedQuantity < $originalQuantity) {
                    $allCompleted = false;
                }
            }
            
            // Determine new status based on actual received quantities
            if (!$anyReceived) {
                $newStatus = 'pending';
            } elseif ($allCompleted) {
                $newStatus = 'completed';
            } else {
                $newStatus = 'partial';
            }
            
            // Only update if status actually changed
            if ($originalTransaction->status !== $newStatus) {
                $originalTransaction->update(['status' => $newStatus]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error updating original exchange status: ' . $e->getMessage());
        }
    }

    public function status($id)
    {
        $transaction = Transaction::findOrFail($id);

        return view('sale_reward_supllier_receive.update_status', compact('transaction'));
    }

   public function update_status(Request $request, $id)
{
    DB::beginTransaction();

    try {
        $transaction = Transaction::with('sell_lines')->findOrFail($id);
        $previous_status = $transaction->status;
        $business_id = $transaction->business_id;

        $transaction->status = $request->input('status');
        $transaction->save();

        $stock_lines = [];

        if ($previous_status != 'completed' && $transaction->status == 'completed') {
            foreach ($transaction->sell_lines as $line) {
                $exchange_product_id = $line->product_id;
                $exchange_quantity = $line->quantity;

                $rewardsExchange = RewardsExchange::where('exchange_product', $exchange_product_id)
                    ->where('type', 'suppliers')
                    ->where('business_id', $business_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($rewardsExchange) {
                    $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
                    $receive_product_id = $rewardsExchange->receive_product;
                    $receive_quantity = $rewardsExchange->receive_quantity * $exchange_quantity;

                    $exchange_variation = Variation::where('product_id', $exchange_product_id)->firstOrFail();
                    $receive_variation = Variation::where('product_id', $receive_product_id)->firstOrFail();

                    // ===== RING_OUT (Supplier receives ring back) =====
                    $lastSupplierRingOut = StockRewardsExchange::where('product_id', $exchange_product_id)
                        ->where('contact_id', $transaction->contact_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    $currentSupplierRingBalance = $lastSupplierRingOut ? $lastSupplierRingOut->new_quantity : 0;
                    $newSupplierRingBalance = $currentSupplierRingBalance - $ring_quantity_sent;

                    $stock_lines[] = new StockRewardsExchange([
                        'transaction_id' => $transaction->id,
                        'type' => 'supplier_receive_out',
                        'contact_id' => $transaction->contact_id,
                        'product_id' => $exchange_product_id,
                        'variation_id' => $exchange_variation->id,
                        'quantity' => -$ring_quantity_sent,
                        'new_quantity' => $newSupplierRingBalance,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update StockRingBalanceCustomer for supplier
                    // ✅ FIX: Added ELSE clause for INSERT
                    $supplierStockRingRecord = DB::table('stock_ring_balance_customer')
                        ->where('product_id', $exchange_product_id)
                        ->where('contact_id', $transaction->contact_id)
                        ->where('business_id', $business_id)
                        ->first();

                    if ($supplierStockRingRecord) {
                        $newSupplierStockBalance = $supplierStockRingRecord->stock_ring_balance - $ring_quantity_sent;
                        DB::table('stock_ring_balance_customer')
                            ->where('product_id', $exchange_product_id)
                            ->where('contact_id', $transaction->contact_id)
                            ->where('business_id', $business_id)
                            ->update(['stock_ring_balance' => $newSupplierStockBalance]);
                    } else {
                        // ✅ INSERT if record doesn't exist
                        DB::table('stock_ring_balance_customer')->insert([
                            'product_id' => $exchange_product_id,
                            'contact_id' => $transaction->contact_id,
                            'business_id' => $business_id,
                            'stock_ring_balance' => -$ring_quantity_sent,
                        ]);
                    }

                    // ===== RING_IN (Shop receives products) =====
                    $lastShopRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                        ->whereNull('contact_id')
                        ->orderBy('id', 'desc')
                        ->first();

                    $currentShopRingBalance = $lastShopRingIn ? $lastShopRingIn->new_quantity : 0;
                    $newShopRingBalance = $currentShopRingBalance + $receive_quantity;

                    $stock_lines[] = new StockRewardsExchange([
                        'transaction_id' => $transaction->id,
                        'type' => 'supplier_receive_in',
                        'contact_id' => null,
                        'product_id' => $receive_product_id,
                        'variation_id' => $receive_variation->id,
                        'quantity' => $receive_quantity,
                        'new_quantity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update VariationLocationDetails for receive product
                    $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                        'variation_id' => $receive_variation->id,
                        'location_id' => $transaction->location_id
                    ]);

                    if ($receiveVariationLocationDetails->exists) {
                        $receiveVariationLocationDetails->qty_available += $receive_quantity;
                    } else {
                        $receiveVariationLocationDetails->product_id = $receive_product_id;
                        $receiveVariationLocationDetails->product_variation_id = $receive_variation->product_variation_id;
                        $receiveVariationLocationDetails->qty_available = $receive_quantity;
                    }
                    $receiveVariationLocationDetails->save();
                }
            }
        }

        if (!empty($stock_lines)) {
            foreach ($stock_lines as $line) {
                $line->save();
            }
        }

        DB::commit();
        return response()->json(['success' => true, 'message' => __('Status updated successfully.')]);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error updating status: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

public function show($id)
{
    $business_id = session()->get('user.business_id');

    $transaction = Transaction::with(['contact', 'location', 'sell_lines.product'])
        ->where('id', $id)
        ->where('business_id', $business_id)
        ->firstOrFail();

    // Get received products ordered by id asc (same order as sell_lines were created)
    $received_products = DB::table('stock_reward_exchange_new')
        ->join('products', 'stock_reward_exchange_new.product_id', '=', 'products.id')
        ->where('stock_reward_exchange_new.transaction_id', $id)
        ->where('stock_reward_exchange_new.type', 'supplier_receive_in')
        ->select(
            'stock_reward_exchange_new.id',
            'stock_reward_exchange_new.product_id',
            'products.name as receive_product_name'
        )
        ->orderBy('stock_reward_exchange_new.id', 'asc')
        ->get()
        ->values(); // re-index 0,1,2...

    return view('sale_reward_supllier_receive.show', compact(
        'transaction', 'received_products'
    ));
}

    public function payment($id)
    {
        $business_id = session()->get('user.business_id');
            
        $transaction = Transaction::where('business_id', $business_id)
                                    ->with(['contact', 'location'])
                                    ->findOrFail($id);

        $show_advance = in_array($transaction->type, ['sell']) ? true : false;
        $payment_types = $this->transactionUtil->payment_types($transaction->location, $show_advance);

        $paid_amount = $this->transactionUtil->getTotalPaid($id);
        $amount = $transaction->final_total - $paid_amount;
        if ($amount < 0) {
            $amount = 0;
        }

        $amount_formated = $this->transactionUtil->num_f($amount);

        $payment_line = new TransactionPayment();
        $payment_line->amount = $amount;
        $payment_line->method = 'cash';
        $payment_line->paid_on = \Carbon::now()->toDateTimeString();

        //Accounts
        $accounts = $this->moduleUtil->accountsDropdown($business_id, true, false, true);

        // Add row_index variable for the payment form
        $row_index = 0;

        return $view = view('sale_reward_supllier_receive.payment')
        ->with(compact('transaction', 'payment_types', 'payment_line', 'amount_formated', 'accounts', 'row_index'))->render();
    }

    public function edit($id)
    {
        $business_id = session()->get('user.business_id');
    
        // Fetch the transaction along with related products and sell lines
        $transaction = Transaction::with(['sell_lines.product'])->findOrFail($id);
    
        // Fetch suppliers (contacts)
        $contact = Contact::where('type', 'supplier')
            ->where('business_id', $business_id)
            ->pluck('name', 'id');
    
        // Fetch rewards exchange data for each product
        $rewards_exchange = DB::table('rewards_exchange')
            ->where('business_id', $business_id)
            ->where('type', 'suppliers')
            ->whereIn('exchange_product', $transaction->sell_lines->pluck('product_id'))
            ->get()
            ->keyBy('exchange_product');
    
        return view('sale_reward_supllier_receive.edit', compact('transaction', 'contact', 'rewards_exchange'));
    }
    
public function searchProduct(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $search_term = $request->input('term');

    $products = RewardsExchange::where('type', 'suppliers')
        ->where('business_id', $business_id)
        ->whereNull('deleted_at')
        ->whereHas('product', function ($query) use ($search_term) {
            $query->where('name', 'like', '%' . $search_term . '%')
                ->orWhere('sku', 'like', '%' . $search_term . '%');
        })
        ->with(['product' => function ($query) {
            $query->select('id', 'name', 'sku');
        }])
        ->get();

    return response()->json($products);
}

public function getProduct(Request $request)
{
    $product_id = $request->input('id');
    $business_id = $request->session()->get('user.business_id');

    $product = RewardsExchange::with(['product'])
                ->where('exchange_product', $product_id)
                ->where('type', 'suppliers')
                ->where('business_id', $business_id)
                ->whereNull('deleted_at')
                ->first();

    if ($product && $product->product) {
        return response()->json([
            'id' => $product->product->id,
            'name' => $product->product->name,
            'sku' => $product->product->sku,
            'exchange_quantity' => $product->exchange_quantity,
            'receive_quantity' => $product->receive_quantity,
            'price' => $product->amount,
            'total' => $product->amount
        ]);
    } else {
        return response()->json(['error' => 'Product not found'], 404);
    }
}

// ============================================
// UPDATED STORE METHOD (INDEPENDENT FLOW)
// ============================================
public function store(Request $request)
{
    DB::beginTransaction();

    try {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->user()->id;
        $location_id = $request->input('select_location_id');
        $contact_id = $request->input('sell_list_filter_contact_id');
        $transaction_date = $request->input('transaction_date', now());
        $additional_notes = $request->input('note', null);
        $status = $request->input('status');
        $products = json_decode($request->input('products'), true);

        if (!is_array($products) || empty($products)) {
            DB::rollBack();
            return redirect()->back()->with('error', 'No products provided.');
        }

        $total_before_tax = array_reduce($products, function ($carry, $product) {
            return $carry + ($product['quantity'] * $product['price']);
        }, 0);

        $lastRewardTransaction = Transaction::where('business_id', $business_id)
            ->where('type', 'supplier_exchange_receive')
            ->whereNotNull('reward_no')
            ->orderBy('id', 'desc')
            ->first();

        $reward_no = $lastRewardTransaction
            ? str_pad((int) $lastRewardTransaction->reward_no + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        $invoice_no = $request->input('invoice_no');
        $ref_no = $invoice_no ?? $reward_no;

        $transaction = Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'supplier_exchange_receive',
            'status' => $status,
            'contact_id' => $contact_id,
            'ref_no' => $ref_no,
            'reward_no' => $reward_no,
            'transaction_date' => $transaction_date,
            'additional_notes' => $additional_notes,
            'total_before_tax' => $total_before_tax,
            'final_total' => $total_before_tax,
            'created_by' => $user_id,
        ]);

        $stock_rewards_lines = [];

        foreach ($products as $productData) {
            $rewardsExchange = RewardsExchange::where('exchange_product', $productData['product_id'])
                ->where('type', 'suppliers')
                ->where('business_id', $business_id)
                ->whereNull('deleted_at')
                ->first();
            
            if (!$rewardsExchange) {
                DB::rollBack();
                return redirect()->back()->with('error', 'No rewards exchange found for product ID ' . $productData['product_id']);
            }

            $exchange_product_id = $productData['product_id'];
            $exchange_quantity = $productData['quantity'];
            
            $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
            $receive_product_id = $rewardsExchange->receive_product;
            $receive_quantity = $rewardsExchange->receive_quantity * $exchange_quantity;

            $exchange_variation = Variation::where('product_id', $exchange_product_id)->firstOrFail();
            $receive_variation = Variation::where('product_id', $receive_product_id)->firstOrFail();

            TransactionSellLine::create([
                'transaction_id' => $transaction->id,
                'product_id' => $exchange_product_id,
                'variation_id' => $exchange_variation->id,
                'quantity' => $exchange_quantity,
                'unit_price' => $productData['price'],
            ]);

            if ($status === 'completed') {
                // RING_OUT
                $lastSupplierRingOut = StockRewardsExchange::where('product_id', $exchange_product_id)
                    ->where('contact_id', $contact_id)
                    ->orderBy('id', 'desc')
                    ->first();

                $currentSupplierRingBalance = $lastSupplierRingOut ? $lastSupplierRingOut->new_quantity : 0;
                $newSupplierRingBalance = $currentSupplierRingBalance - $ring_quantity_sent;

                $stock_rewards_lines[] = new StockRewardsExchange([
                    'transaction_id' => $transaction->id,
                    'type' => 'supplier_receive_out',
                    'contact_id' => $contact_id,
                    'product_id' => $exchange_product_id,
                    'variation_id' => $exchange_variation->id,
                    'quantity' => -$ring_quantity_sent,
                    'new_quantity' => $newSupplierRingBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update StockRingBalanceCustomer
                // ✅ FIX: Added ELSE clause for INSERT
                $supplierStockRingRecord = DB::table('stock_ring_balance_customer')
                    ->where('product_id', $exchange_product_id)
                    ->where('contact_id', $contact_id)
                    ->where('business_id', $business_id)
                    ->first();

                if ($supplierStockRingRecord) {
                    $newSupplierStockBalance = $supplierStockRingRecord->stock_ring_balance - $ring_quantity_sent;
                    DB::table('stock_ring_balance_customer')
                        ->where('product_id', $exchange_product_id)
                        ->where('contact_id', $contact_id)
                        ->where('business_id', $business_id)
                        ->update(['stock_ring_balance' => $newSupplierStockBalance]);
                } else {
                    // ✅ INSERT if record doesn't exist
                    DB::table('stock_ring_balance_customer')->insert([
                        'product_id' => $exchange_product_id,
                        'contact_id' => $contact_id,
                        'business_id' => $business_id,
                        'stock_ring_balance' => -$ring_quantity_sent,
                    ]);
                }

                // RING_IN
                $lastShopRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                    ->whereNull('contact_id')
                    ->orderBy('id', 'desc')
                    ->first();

                $currentShopRingBalance = $lastShopRingIn ? $lastShopRingIn->new_quantity : 0;
                $newShopRingBalance = $currentShopRingBalance + $receive_quantity;

                $stock_rewards_lines[] = new StockRewardsExchange([
                    'transaction_id' => $transaction->id,
                    'type' => 'supplier_receive_in',
                    'contact_id' => null,
                    'product_id' => $receive_product_id,
                    'variation_id' => $receive_variation->id,
                    'quantity' => $receive_quantity,
                    'new_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                    'variation_id' => $receive_variation->id,
                    'location_id' => $location_id
                ]);

                if ($receiveVariationLocationDetails->exists) {
                    $receiveVariationLocationDetails->qty_available += $receive_quantity;
                } else {
                    $receiveVariationLocationDetails->product_id = $receive_product_id;
                    $receiveVariationLocationDetails->product_variation_id = $receive_variation->product_variation_id;
                    $receiveVariationLocationDetails->qty_available = $receive_quantity;
                }
                $receiveVariationLocationDetails->save();
            }
        }

        if (!empty($stock_rewards_lines)) {
            foreach ($stock_rewards_lines as $line) {
                $line->save();
            }
        }

        DB::commit();
        return redirect()->route('sale-reward-supplier-receive.index')->with('success', 'Transaction successfully recorded.');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error storing supplier receive: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
    }
}

// ============================================
// UPDATED UPDATE METHOD (INDEPENDENT FLOW)
// ============================================
public function update(Request $request, $id)
{
    DB::beginTransaction();

    try {
        $transaction = Transaction::with('sell_lines')->findOrFail($id);
        $business_id = $transaction->business_id;
        $previous_status = $transaction->status;
        
        $location_id = $request->input('select_location_id');
        $contact_id = $request->input('sell_list_filter_contact_id');
        $transaction_date = $request->input('transaction_date');
        $additional_notes = $request->input('note');
        $new_status = $request->input('status');
        $products = json_decode($request->input('products'), true);

        if (!is_array($products) || empty($products)) {
            DB::rollBack();
            return redirect()->back()->with('error', 'No products provided.');
        }

        $transaction->location_id = $location_id;
        $transaction->contact_id = $contact_id;
        $transaction->transaction_date = $transaction_date;
        $transaction->additional_notes = $additional_notes;
        $transaction->status = $new_status;

        $total_before_tax = array_reduce($products, function ($carry, $product) {
            return $carry + ($product['quantity'] * $product['price']);
        }, 0);

        $transaction->total_before_tax = $total_before_tax;
        $transaction->final_total = $total_before_tax;
        $transaction->save();

        $existing_sell_lines = $transaction->sell_lines->keyBy('product_id');
        $stock_rewards_lines = [];

        foreach ($products as $product) {
            $product_id = $product['product_id'];
            $quantity = $product['quantity'];
            $price = $product['price'];

            $variation = Variation::where('product_id', $product_id)->first();

            if (!$variation) {
                DB::rollBack();
                return redirect()->back()->with('error', __('Invalid product variation.'));
            }

            if ($existing_sell_lines->has($product_id)) {
                $sell_line = $existing_sell_lines->get($product_id);
                $sell_line->quantity = $quantity;
                $sell_line->unit_price = $price;
                $sell_line->variation_id = $variation->id;
                $sell_line->save();

                $existing_sell_lines->forget($product_id);
            } else {
                TransactionSellLine::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product_id,
                    'variation_id' => $variation->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                ]);
            }
        }

        foreach ($existing_sell_lines as $sell_line) {
            $sell_line->delete();
        }

        if ($previous_status !== 'completed' && $new_status === 'completed') {
            foreach ($products as $product) {
                $product_id = $product['product_id'];
                $exchange_quantity = $product['quantity'];

                $rewardsExchange = RewardsExchange::where('exchange_product', $product_id)
                    ->where('type', 'suppliers')
                    ->where('business_id', $business_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($rewardsExchange) {
                    $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
                    $receive_product_id = $rewardsExchange->receive_product;
                    $receive_quantity = $rewardsExchange->receive_quantity * $exchange_quantity;

                    $exchange_variation = Variation::where('product_id', $product_id)->firstOrFail();
                    $receive_variation = Variation::where('product_id', $receive_product_id)->firstOrFail();

                    // RING_OUT
                    $lastSupplierRingOut = StockRewardsExchange::where('product_id', $product_id)
                        ->where('contact_id', $contact_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    $currentSupplierRingBalance = $lastSupplierRingOut ? $lastSupplierRingOut->new_quantity : 0;
                    $newSupplierRingBalance = $currentSupplierRingBalance - $ring_quantity_sent;

                    $stock_rewards_lines[] = new StockRewardsExchange([
                        'transaction_id' => $transaction->id,
                        'type' => 'supplier_receive_out',
                        'contact_id' => $contact_id,
                        'product_id' => $product_id,
                        'variation_id' => $exchange_variation->id,
                        'quantity' => -$ring_quantity_sent,
                        'new_quantity' => $newSupplierRingBalance,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update StockRingBalanceCustomer
                    // ✅ FIX: Added ELSE clause for INSERT
                    $supplierStockRingRecord = DB::table('stock_ring_balance_customer')
                        ->where('product_id', $product_id)
                        ->where('contact_id', $contact_id)
                        ->where('business_id', $business_id)
                        ->first();

                    if ($supplierStockRingRecord) {
                        $newSupplierStockBalance = $supplierStockRingRecord->stock_ring_balance - $ring_quantity_sent;
                        DB::table('stock_ring_balance_customer')
                            ->where('product_id', $product_id)
                            ->where('contact_id', $contact_id)
                            ->where('business_id', $business_id)
                            ->update(['stock_ring_balance' => $newSupplierStockBalance]);
                    } else {
                        // ✅ INSERT if record doesn't exist
                        DB::table('stock_ring_balance_customer')->insert([
                            'product_id' => $product_id,
                            'contact_id' => $contact_id,
                            'business_id' => $business_id,
                            'stock_ring_balance' => -$ring_quantity_sent,
                        ]);
                    }

                    // RING_IN
                    $lastShopRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                        ->whereNull('contact_id')
                        ->orderBy('id', 'desc')
                        ->first();

                    $currentShopRingBalance = $lastShopRingIn ? $lastShopRingIn->new_quantity : 0;
                    $newShopRingBalance = $currentShopRingBalance + $receive_quantity;

                    $stock_rewards_lines[] = new StockRewardsExchange([
                        'transaction_id' => $transaction->id,
                        'type' => 'supplier_receive_in',
                        'contact_id' => null,
                        'product_id' => $receive_product_id,
                        'variation_id' => $receive_variation->id,
                        'quantity' => $receive_quantity,
                        'new_quantity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                        'variation_id' => $receive_variation->id,
                        'location_id' => $location_id
                    ]);

                    if ($receiveVariationLocationDetails->exists) {
                        $receiveVariationLocationDetails->qty_available += $receive_quantity;
                    } else {
                        $receiveVariationLocationDetails->product_id = $receive_product_id;
                        $receiveVariationLocationDetails->product_variation_id = $receive_variation->product_variation_id;
                        $receiveVariationLocationDetails->qty_available = $receive_quantity;
                    }
                    $receiveVariationLocationDetails->save();
                }
            }
        }

        if (!empty($stock_rewards_lines)) {
            foreach ($stock_rewards_lines as $line) {
                $line->save();
            }
        }

        DB::commit();
        return redirect()->route('sale-reward-supplier-receive.index')
                        ->with('success', 'Transaction updated successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error updating supplier receive: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
    }
}

// ============================================
// UPDATED DESTROY METHOD (SIMPLIFIED)
// ============================================
public function destroy($id, Request $request)
{
    DB::beginTransaction();

    try {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->user()->id;
        
        $transaction = Transaction::where('business_id', $business_id)
            ->where('id', $id)
            ->where('type', 'supplier_exchange_receive')
            ->whereNull('deleted_at')
            ->first();
            
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found or already deleted.'], 404);
        }
        
        if ($transaction->status === 'completed') {
            $sellLines = TransactionSellLine::where('transaction_id', $transaction->id)->get();
            
            foreach ($sellLines as $sellLine) {
                $exchange_product_id = $sellLine->product_id;
                $exchange_quantity = $sellLine->quantity;
                
                $rewardsExchange = RewardsExchange::where('exchange_product', $exchange_product_id)
                    ->where('type', 'suppliers')
                    ->where('business_id', $business_id)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($rewardsExchange) {
                    $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
                    $receive_product_id = $rewardsExchange->receive_product;
                    $receive_quantity = $rewardsExchange->receive_quantity * $exchange_quantity;
                    
                    $exchange_variation = Variation::where('product_id', $exchange_product_id)->first();
                    $receive_variation = Variation::where('product_id', $receive_product_id)->first();
                    
                    if ($exchange_variation && $receive_variation) {
                        // Reverse RING_OUT
                        $lastSupplierRingOut = StockRewardsExchange::where('product_id', $exchange_product_id)
                            ->where('contact_id', $transaction->contact_id)
                            ->orderBy('id', 'desc')
                            ->first();
                        
                        if ($lastSupplierRingOut) {
                            $reversalSupplierRingBalance = $lastSupplierRingOut->new_quantity + $ring_quantity_sent;
                            
                            StockRewardsExchange::create([
                                'transaction_id' => $transaction->id,
                                'type' => 'delete_supplier_receive_out',
                                'contact_id' => $transaction->contact_id,
                                'product_id' => $exchange_product_id,
                                'variation_id' => $exchange_variation->id,
                                'quantity' => $ring_quantity_sent,
                                'new_quantity' => $reversalSupplierRingBalance,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // Update StockRingBalanceCustomer (reversal)
                        // ✅ FIX: Added ELSE clause for INSERT
                        $supplierStockRingRecord = DB::table('stock_ring_balance_customer')
                            ->where('product_id', $exchange_product_id)
                            ->where('contact_id', $transaction->contact_id)
                            ->where('business_id', $business_id)
                            ->first();

                        if ($supplierStockRingRecord) {
                            $newSupplierStockBalance = $supplierStockRingRecord->stock_ring_balance + $ring_quantity_sent;
                            DB::table('stock_ring_balance_customer')
                                ->where('product_id', $exchange_product_id)
                                ->where('contact_id', $transaction->contact_id)
                                ->where('business_id', $business_id)
                                ->update(['stock_ring_balance' => $newSupplierStockBalance]);
                        } else {
                            // ✅ INSERT if record doesn't exist (reversal creates positive balance)
                            DB::table('stock_ring_balance_customer')->insert([
                                'product_id' => $exchange_product_id,
                                'contact_id' => $transaction->contact_id,
                                'business_id' => $business_id,
                                'stock_ring_balance' => $ring_quantity_sent,
                            ]);
                        }

                        // Reverse RING_IN
                        $receiveVariationLocationDetails = VariationLocationDetails::where('variation_id', $receive_variation->id)
                            ->where('location_id', $transaction->location_id)
                            ->first();
                            
                        if ($receiveVariationLocationDetails) {
                            $receiveVariationLocationDetails->qty_available = max(0, ($receiveVariationLocationDetails->qty_available ?? 0) - $receive_quantity);
                            $receiveVariationLocationDetails->save();
                        }

                        $lastShopRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                            ->whereNull('contact_id')
                            ->orderBy('id', 'desc')
                            ->first();
                        
                        if ($lastShopRingIn) {
                            StockRewardsExchange::create([
                                'transaction_id' => $transaction->id,
                                'type' => 'delete_supplier_receive_in',
                                'contact_id' => null,
                                'product_id' => $receive_product_id,
                                'variation_id' => $receive_variation->id,
                                'quantity' => -$receive_quantity,
                                'new_quantity' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
        
        $transaction->update([
            'deleted_by' => $user_id,
            'deleted_at' => now()
        ]);
        
        DB::commit();
        
        $message = $transaction->status === 'completed' 
            ? 'Supplier receive deleted successfully and stock reversed.' 
            : 'Pending supplier receive deleted successfully.';
        
        return response()->json([
            'success' => true, 
            'message' => $message
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error deleting supplier receive transaction: ' . $e->getMessage());
        return response()->json([
            'success' => false, 
            'message' => 'Error occurred while deleting transaction: ' . $e->getMessage()
        ], 500);
    }
}
}
