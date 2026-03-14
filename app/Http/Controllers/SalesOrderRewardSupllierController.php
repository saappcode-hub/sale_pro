<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\CurrentStockBackup;
use App\Product;
use App\RewardsExchange;
use App\StockRewardsExchange;
use App\StockRingBalanceCustomer;
use App\Transaction;
use App\TransactionSellLine;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderRewardSupllierController extends Controller
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
            ->where('type', 'supplier_exchange')
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
                'transactions.sub_type',
                'transactions.final_total',
                'transactions.created_by',
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
                    $editButton = '';
                    if ($row->sub_type === 'pending') {
                        $editButton = '<li><a href="' . route('sale-reward-supplier.edit', $row->id) . '"><i class="fas fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a></li>';
                    }
                    $view = '<li><a href="#" data-href="'.action([\App\Http\Controllers\SalesOrderRewardSupllierController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    // $delete = '';
                    // if ($row->status == 'pending') {
                    $delete = '<li><a href="#" class="delete-reward-exchange" data-href="' . route('sale-reward-supplier.destroy', $row->id) . '" data-csrf="'.csrf_token().'"><i class="fas fa-trash" aria-hidden="true"></i> '.__('messages.delete').'</a></li>';
                    // }
                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    ' . $view . '
                                    ' . $editButton . '
                                    ' . $delete . '
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
                ->addColumn('sub_type', function($row) {
                    return $row->sub_type;
                })
                ->addColumn('final_total', function($row) {
                    return number_format($row->final_total, 2);
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

        return view('sale_reward_supllier.index', compact('business_locations', 'contact'));
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
        return view('sale_reward_supllier.create')
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
   public function searchProduct(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $search_term = $request->input('term');

    // FIX: groupBy exchange_product to return only distinct products
    // even if one product has multiple reward formulas
    $products = DB::table('rewards_exchange')
        ->join('products', 'rewards_exchange.exchange_product', '=', 'products.id')
        ->where('rewards_exchange.type', 'suppliers')
        ->where('rewards_exchange.business_id', $business_id)
        ->whereNull('rewards_exchange.deleted_at')
        ->where(function ($query) use ($search_term) {
            $query->where('products.name', 'like', '%' . $search_term . '%')
                  ->orWhere('products.sku', 'like', '%' . $search_term . '%');
        })
        ->groupBy('rewards_exchange.exchange_product', 'products.id', 'products.name', 'products.sku')
        ->select(
            'rewards_exchange.exchange_product',
            'products.id',
            'products.name',
            'products.sku'
        )
        ->get()
        ->map(function ($item) {
            return [
                'exchange_product' => $item->exchange_product,
                'product' => [
                    'id'   => $item->id,
                    'name' => $item->name,
                    'sku'  => $item->sku,
                ]
            ];
        });

    return response()->json($products);
}
    public function getProduct(Request $request)
    {
        $product_id = $request->input('id');
        $product = RewardsExchange::with(['product'])
                    ->where('exchange_product', $product_id)  // assuming 'exchange_product' is the foreign key in 'RewardsExchange
                    ->where('type', 'suppliers') // Add condition for 'type'
                    ->whereNull('deleted_at') // Ensure 'deleted_at' is NULL (not soft deleted)
                    ->first();

        if ($product && $product->product) {
            return response()->json([
                'id' => $product->product->id,
                'name' => $product->product->name,
                'sku' => $product->product->sku,
                'exchange_quantity' => $product->exchange_quantity,
                'receive_quantity' => $product->receive_quantity,
                'price' => $product->amount,
                'total' => $product->amount  // This might need adjustment based on how 'total' is calculated
            ]);
        } else {
            return response()->json(['error' => 'Product not found'], 404);
        }
    }
    
    public function store(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $user_id = $request->user()->id;
    $location_id = $request->input('select_location_id');
    $contact_id = $request->input('sell_list_filter_contact_id');
    $transaction_date = $request->input('transaction_date', now());
    $additional_notes = $request->input('note', null);
    $sub_type = $request->input('sell_list_filter_status');
    $products = json_decode($request->input('products'), true);

    if (!is_array($products) || empty($products)) {
        return redirect()->back()->with('error', 'No products provided.');
    }

    $total_before_tax = array_reduce($products, function ($carry, $product) {
        return $carry + ($product['quantity'] * $product['price']);
    }, 0);

    $lastRewardTransaction = Transaction::where('business_id', $business_id)
        ->where('type', 'supplier_exchange')
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
        'type' => 'supplier_exchange',
        'sub_type' => $sub_type,
        'contact_id' => $contact_id,
        'ref_no' => $ref_no,
        'reward_no' => $reward_no,
        'transaction_date' => $transaction_date,
        'additional_notes' => $additional_notes,
        'total_before_tax' => $total_before_tax,
        'final_total' => $total_before_tax,
        'created_by' => $user_id,
    ]);

    foreach ($products as $productData) {
        $product_id = $productData['product_id'];
        $quantity = $productData['quantity'];
        $exchange_quantity = $productData['exchange_quantity'] * $quantity;

        $variation = Variation::where('product_id', $product_id)->firstOrFail();

        TransactionSellLine::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product_id,
            'variation_id' => $variation->id,
            'quantity' => $quantity,
            'unit_price' => $productData['price'],
        ]);

        // Only process stock and exchange if sub_type is 'send'
        if ($sub_type === 'send') {
            // Get last new_quantity based on contact_id only (ignore type)
            $lastRecordShop = StockRewardsExchange::where('product_id', $product_id)
                ->whereNull('contact_id')  // Shop side
                ->orderBy('id', 'desc')
                ->first();

            $currentShopBalance = $lastRecordShop ? $lastRecordShop->new_quantity : 0;
            $newShopBalance = $currentShopBalance - $exchange_quantity;

            // ============================================
            // RECORD 1: RING_OUT (Shop side - deduction)
            // ============================================
            StockRewardsExchange::create([
                'transaction_id' => $transaction->id,
                'type' => 'supplier_reward_out',
                'contact_id' => null,
                'product_id' => $product_id,
                'variation_id' => $variation->id,
                'quantity' => -$exchange_quantity,
                'new_quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ============================================
            // RECORD 2: RING_IN (Supplier side - addition)
            // ============================================
            $lastRecordSupplier = StockRewardsExchange::where('product_id', $product_id)
                ->where('contact_id', $contact_id)
                ->orderBy('id', 'desc')
                ->first();

            $currentSupplierBalance = $lastRecordSupplier ? $lastRecordSupplier->new_quantity : 0;
            $newSupplierBalance = $currentSupplierBalance + $exchange_quantity;

            StockRewardsExchange::create([
                'transaction_id' => $transaction->id,
                'type' => 'supplier_reward_in',
                'contact_id' => $contact_id,
                'product_id' => $product_id,
                'variation_id' => $variation->id,
                'quantity' => $exchange_quantity,
                'new_quantity' => $newSupplierBalance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Update VariationLocationDetails for exchange product (deduct from shop)
            $exchangeVariationLocationDetails = VariationLocationDetails::firstOrNew([
                'variation_id' => $variation->id,
                'location_id' => $location_id
            ]);

            if ($exchangeVariationLocationDetails->exists) {
                $exchangeVariationLocationDetails->qty_available -= $exchange_quantity;
            } else {
                $exchangeVariationLocationDetails->product_id = $product_id;
                $exchangeVariationLocationDetails->product_variation_id = $variation->product_variation_id;
                $exchangeVariationLocationDetails->qty_available = -$exchange_quantity;
            }
            $exchangeVariationLocationDetails->save();

            // 3. Update StockRingBalanceCustomer (supplier stock balance)
            $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                ->where('product_id', $product_id)
                ->where('contact_id', $contact_id)
                ->where('business_id', $business_id)
                ->first();

            if ($stockRingBalanceRecord) {
                $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance + $exchange_quantity;

                DB::table('stock_ring_balance_customer')
                    ->where('product_id', $product_id)
                    ->where('contact_id', $contact_id)
                    ->where('business_id', $business_id)
                    ->update(['stock_ring_balance' => $newStockRingBalance]);
            } else {
                $newStockRingBalance = $exchange_quantity;

                StockRingBalanceCustomer::create([
                    'product_id' => $product_id,
                    'contact_id' => $contact_id,
                    'business_id' => $business_id,
                    'stock_ring_balance' => $newStockRingBalance
                ]);
            }
        }
    }

    return redirect()->route('sale-reward-supplier.index')->with('success', 'Transaction successfully recorded.');
}
        
    public function edit($id)
    {
        $transaction = Transaction::with(['sell_lines.product'])
            ->findOrFail($id);
    
        $business_id = session()->get('user.business_id');
        
        // Fetch related exchange and receive quantities for products
        $sell_lines = $transaction->sell_lines->map(function ($sell_line) use ($business_id) {
            $rewardExchange = RewardsExchange::where('exchange_product', $sell_line->product_id)
                ->where('business_id', $business_id)
                ->where('type', 'suppliers')
                ->first(['exchange_quantity', 'receive_quantity']);
            
            $sell_line->exchange_quantity = $rewardExchange->exchange_quantity ?? 0;
            $sell_line->receive_quantity = $rewardExchange->receive_quantity ?? 0;
    
            return $sell_line;
        });
    
        $business_locations = BusinessLocation::where('business_id', $business_id)->pluck('name', 'id');
        $contact = Contact::where('type', 'supplier')->where('business_id', $business_id)->pluck('name', 'id');
    
        // Pass the transaction status to the view
        return view('sale_reward_supllier.edit', compact('transaction', 'business_locations', 'contact', 'sell_lines'));
    }

    public function update(Request $request, $id)
{
    $transaction = Transaction::findOrFail($id);

    $previous_sub_type = $transaction->sub_type;
    $new_sub_type = $request->input('sub_type');
    $business_id = $transaction->business_id;

    $transaction->location_id = $request->input('select_location_id');
    $transaction->contact_id = $request->input('sell_list_filter_contact_id');
    $transaction->transaction_date = $request->input('transaction_date');
    $transaction->additional_notes = $request->input('note');
    $transaction->sub_type = $new_sub_type;
    
    $existing_sell_lines = $transaction->sell_lines->keyBy('product_id');
    $submitted_products = $request->input('products', []);

    $total_before_tax = 0;

    foreach ($submitted_products as $product) {
        if (!isset($product['price'])) {
            return redirect()->back()->with('error', __('Price is missing for one or more products.'));
        }

        $product_id = $product['product_id'];
        $quantity = $product['quantity'];
        $price = $product['price'];
        $exchange_quantity = $product['exchange_quantity'] * $quantity;
        $line_total = $quantity * $price;
        $total_before_tax += $line_total;

        $variation = Variation::where('product_id', $product_id)->first();

        if (!$variation) {
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

        // Handle stock updates if sub_type changes to 'send' and was not 'send' before
        if ($new_sub_type === 'send' && $previous_sub_type !== 'send') {
            // Get last new_quantity based on contact_id only (ignore type)
            $lastRecordShop = StockRewardsExchange::where('product_id', $product_id)
                ->whereNull('contact_id')  // Shop side
                ->orderBy('id', 'desc')
                ->first();

            $currentShopBalance = $lastRecordShop ? $lastRecordShop->new_quantity : 0;
            $newShopBalance = $currentShopBalance - $exchange_quantity;

            // ============================================
            // RECORD 1: RING_OUT (Shop side - deduction)
            // ============================================
            StockRewardsExchange::create([
                'transaction_id' => $transaction->id,
                'type' => 'supplier_reward_out',
                'contact_id' => null,
                'product_id' => $product_id,
                'variation_id' => $variation->id,
                'quantity' => -$exchange_quantity,
                'new_quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ============================================
            // RECORD 2: RING_IN (Supplier side - addition)
            // ============================================
            $lastRecordSupplier = StockRewardsExchange::where('product_id', $product_id)
                ->where('contact_id', $transaction->contact_id)
                ->orderBy('id', 'desc')
                ->first();

            $currentSupplierBalance = $lastRecordSupplier ? $lastRecordSupplier->new_quantity : 0;
            $newSupplierBalance = $currentSupplierBalance + $exchange_quantity;

            StockRewardsExchange::create([
                'transaction_id' => $transaction->id,
                'type' => 'supplier_reward_in',
                'contact_id' => $transaction->contact_id,
                'product_id' => $product_id,
                'variation_id' => $variation->id,
                'quantity' => $exchange_quantity,
                'new_quantity' => $newSupplierBalance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Update VariationLocationDetails for exchange product (deduct from shop)
            $exchangeVariationLocationDetails = VariationLocationDetails::firstOrNew([
                'variation_id' => $variation->id,
                'location_id' => $transaction->location_id
            ]);

            if ($exchangeVariationLocationDetails->exists) {
                $exchangeVariationLocationDetails->qty_available -= $exchange_quantity;
            } else {
                $exchangeVariationLocationDetails->product_id = $product_id;
                $exchangeVariationLocationDetails->product_variation_id = $variation->product_variation_id;
                $exchangeVariationLocationDetails->qty_available = -$exchange_quantity;
            }
            $exchangeVariationLocationDetails->save();

            // 3. Update StockRingBalanceCustomer (supplier stock balance)
            $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                ->where('product_id', $product_id)
                ->where('contact_id', $transaction->contact_id)
                ->where('business_id', $business_id)
                ->first();

            if ($stockRingBalanceRecord) {
                $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance + $exchange_quantity;

                DB::table('stock_ring_balance_customer')
                    ->where('product_id', $product_id)
                    ->where('contact_id', $transaction->contact_id)
                    ->where('business_id', $business_id)
                    ->update(['stock_ring_balance' => $newStockRingBalance]);
            } else {
                $newStockRingBalance = $exchange_quantity;

                StockRingBalanceCustomer::create([
                    'product_id' => $product_id,
                    'contact_id' => $transaction->contact_id,
                    'business_id' => $business_id,
                    'stock_ring_balance' => $newStockRingBalance
                ]);
            }
        }
    }

    // Delete removed sell lines
    foreach ($existing_sell_lines as $sell_line) {
        $sell_line->delete();
    }

    $transaction->total_before_tax = $total_before_tax;
    $transaction->final_total = $total_before_tax;
    $transaction->save();

    return redirect()->route('sale-reward-supplier.index')->with('success', __('Transaction updated successfully.'));
}

    public function show($id)
    {
        $business_id = session()->get('user.business_id');

        // Fetch the transaction details along with related data
        $transaction = Transaction::with(['contact', 'location', 'sell_lines.product'])
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->firstOrFail();

        return view('sale_reward_supllier.show', compact('transaction'));
    }

    public function destroy($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->user()->id;
            
            // Find the transaction (not deleted)
            $transaction = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->where('type', 'supplier_exchange')
                ->whereNull('deleted_at')
                ->first();
                
            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found or already deleted.'], 404);
            }
            
            // Only reverse stock if sub_type is 'send'
            if ($transaction->sub_type === 'send') {
                $sellLines = TransactionSellLine::where('transaction_id', $transaction->id)->get();
                
                foreach ($sellLines as $sellLine) {
                    $product_id = $sellLine->product_id;
                    $quantity = $sellLine->quantity;
                    
                    // Get rewards exchange data for this product
                    $rewardsExchange = RewardsExchange::where('exchange_product', $product_id)
                        ->where('type', 'suppliers')
                        ->where('business_id', $business_id)
                        ->whereNull('deleted_at')
                        ->first();
                    
                    if ($rewardsExchange) {
                        $exchange_quantity = $rewardsExchange->exchange_quantity * $quantity;
                        
                        // Get variation for the product
                        $variation = Variation::where('product_id', $product_id)->first();
                        
                        if ($variation) {
                            // 1. Reverse VariationLocationDetails (add back what was subtracted)
                            $variationLocationDetails = VariationLocationDetails::where('variation_id', $variation->id)
                                ->where('location_id', $transaction->location_id)
                                ->first();
                                
                            if ($variationLocationDetails) {
                                $variationLocationDetails->qty_available = ($variationLocationDetails->qty_available ?? 0) + $exchange_quantity;
                                $variationLocationDetails->save();
                            } else {
                                // Create new record if it doesn't exist
                                $productVariationId = $variation->product_variation_id ?? null;
                                if ($productVariationId) {
                                    VariationLocationDetails::create([
                                        'variation_id' => $variation->id,
                                        'location_id' => $transaction->location_id,
                                        'product_id' => $product_id,
                                        'product_variation_id' => $productVariationId,
                                        'qty_available' => $exchange_quantity,
                                    ]);
                                }
                            }
                            
                            // 2. Create reversal records for both RING_OUT and RING_IN
                            
                            // Reverse RING_OUT (Shop)
                            $lastShopRecord = StockRewardsExchange::where('product_id', $product_id)
                                ->whereNull('contact_id')
                                ->orderBy('id', 'desc')
                                ->first();
                            
                            if ($lastShopRecord) {
                                $reversalShopBalance = $lastShopRecord->new_quantity + $exchange_quantity;
                                
                                StockRewardsExchange::create([
                                    'transaction_id' => $transaction->id,
                                    'type' => 'delete_supplier_reward_out',
                                    'contact_id' => null,
                                    'product_id' => $product_id,
                                    'variation_id' => $variation->id,
                                    'quantity' => $exchange_quantity,
                                    'new_quantity' => 0,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                            
                            // Reverse RING_IN (Supplier)
                            $lastSupplierRecord = StockRewardsExchange::where('product_id', $product_id)
                                ->where('contact_id', $transaction->contact_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            
                            if ($lastSupplierRecord) {
                                $reversalSupplierBalance = $lastSupplierRecord->new_quantity - $exchange_quantity;
                                
                                StockRewardsExchange::create([
                                    'transaction_id' => $transaction->id,
                                    'type' => 'delete_supplier_reward_in',
                                    'contact_id' => $transaction->contact_id,
                                    'product_id' => $product_id,
                                    'variation_id' => $variation->id,
                                    'quantity' => -$exchange_quantity,
                                    'new_quantity' => $reversalSupplierBalance,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                            
                            // 3. Reverse StockRingBalanceCustomer (subtract back the added balance)
                            $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                                ->where('product_id', $product_id)
                                ->where('contact_id', $transaction->contact_id)
                                ->where('business_id', $business_id)
                                ->first();
                            
                            if ($stockRingBalanceRecord) {
                                $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance - $exchange_quantity;
                                
                                DB::table('stock_ring_balance_customer')
                                    ->where('product_id', $product_id)
                                    ->where('contact_id', $transaction->contact_id)
                                    ->where('business_id', $business_id)
                                    ->update(['stock_ring_balance' => $newStockRingBalance]);
                            }
                        }
                    }
                }
            }
            
            // Soft delete the supplier_exchange transaction
            $transaction->update([
                'deleted_by' => $user_id,
                'deleted_at' => now()
            ]);
            
            $message = $transaction->sub_type === 'send' 
                ? 'Supplier exchange deleted successfully and stock reversed.' 
                : 'Pending supplier exchange deleted successfully.';
            
            return response()->json([
                'success' => true, 
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting supplier exchange transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error occurred while deleting transaction: ' . $e->getMessage()
            ], 500);
        }
    }
}
