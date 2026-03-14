<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\CashRingBalance;
use App\Contact;
use App\Product;
use App\RewardsExchange;
use App\RingUnit;
use App\StockCashRingBalanceCustomer;
use App\StockCashRingBalanceProduct;
use App\StockRingBalanceCustomer;
use App\Transaction;
use App\TransactionCashRingBalance;
use App\TransactionPayment;
use App\TransactionRingBalance;
use App\TransactionSellRingBalance;
use App\TransactionSellRingBalanceRingUnits;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CustomerRingBalanceController extends Controller
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
        $start = $request->start_date;
        $end = $request->end_date;
    
        $query = TransactionRingBalance::query()
            ->with([
                'contact',
                'location',
                'sales_person'
            ])
            ->where('business_id', $business_id)
            ->where('type', 'top_up_ring_balance')
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
    
        // Only apply the date filter if start_date and end_date are provided
        if (!empty($start) && !empty($end)) {
            $query->whereDate('transaction_date', '>=', $start)
                  ->whereDate('transaction_date', '<=', $end);
        }
    
        return $query->select([
                'transactions_ring_balance.id',
                'transactions_ring_balance.transaction_date as date',
                'transactions_ring_balance.invoice_no',
                'transactions_ring_balance.contact_id',
                'transactions_ring_balance.location_id',
                'transactions_ring_balance.status',
                'transactions_ring_balance.sell_ref_invoice'
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
                    if ($row->status === 'pending') {
                        // Correct route reference
                        $editButton = '<li><a href="' . route('customer-ring-balance.edit', $row->id) . '"><i class="fas fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a></li>';
                    }
                    $view = '<li><a href="#" data-href="'.action([\App\Http\Controllers\CustomerRingBalanceController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';

                    // Add delete button for all records
                    $deleteButton = '<li><a href="#" class="delete-ring-balance" data-href="'.route('customer-ring-balance.destroy', $row->id).'"><i class="fas fa-trash" aria-hidden="true"></i> '.__('messages.delete').'</a></li>';

                    $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    ' . $view . '
                                    ' . $editButton . '
                                    ' . $deleteButton . '
                                </ul></div>';
                    
                    return $html;
                })
                ->addColumn('date', function($row) {
                    return date('d-m-Y H:i', strtotime($row->date));
                })
                ->addColumn('sell_ref_invoice', function($row) {
                    return $row->sell_ref_invoice ? $row->sell_ref_invoice : 'N/A';
                })
                ->addColumn('invoice_no', function($row) {
                    return $row->invoice_no;
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
                ->rawColumns(['action'])
                ->make(true);
        }
    
        $business_id = $request->session()->get('user.business_id');

        // Business locations
        $business_locations = BusinessLocation::where('business_id', $business_id)
                ->where('is_active', 1)
                ->pluck('name', 'id');
        $business_locations->prepend(__('lang_v1.all'), '');

        // Contacts with custom formatting
        $contacts = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->select('name', 'contact_id', 'mobile', 'id')
            ->get();

        $contact = $contacts->mapWithKeys(function ($item) {
            $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
            return [$item->id => $displayText];
        })->prepend(__('lang_v1.all'), '');

        return view('customer_ring_balance.index', compact('business_locations', 'contact'));
    }

    public function create(Request $request)
    {
        $business_id = session()->get('user.business_id');

        // Fetch business locations
        $business_locations = BusinessLocation::where('business_id', $business_id)
                            ->where('is_active', 1)
                            ->pluck('name', 'id');

        // Get the preselected contact_id from the query string
        $contact_id = $request->query('contact_id');

        // --- NEW LOGIC: Check for Sales Reward ID to Auto-fill ---
        $prefillData = null;
        if ($request->has('sales_reward_id')) {
            $salesRewardId = $request->query('sales_reward_id');
            // Find the transaction (Reward Exchange)
            $rewardTransaction = Transaction::with(['sell_lines', 'contact'])
                ->where('business_id', $business_id)
                ->find($salesRewardId);

            if ($rewardTransaction) {
                // 1. Auto get Contact
                $contact_id = $rewardTransaction->contact_id;
                
                // 2. Auto get Invoice Sell No (from Sales Reward Ref)
                $invoice_sell_no = $rewardTransaction->ref_sale_invoice;

                // 3. Auto get Products (Exchange Products from Reward Config)
                // Get products that were sold in this transaction
                $productIds = $rewardTransaction->sell_lines->pluck('product_id')->toArray();
                
                // Find rewards configured for these products
                $rewards = RewardsExchange::whereIn('product_for_sale', $productIds)
                    ->where('business_id', $business_id)
                    ->whereNull('deleted_at')
                    ->get();

                $prefillProducts = [];
                foreach($rewards as $reward) {
                    $prod = Product::find($reward->exchange_product);
                    if($prod) {
                        // Check logic similar to searchProduct to determine type (Customer/Cash)
                        $hasRewardExchange = RewardsExchange::where('business_id', $business_id)
                            ->where('exchange_product', $prod->id)
                            ->exists();
                        
                        $cashRingBalances = CashRingBalance::where('business_id', $business_id)
                            ->where('product_id', $prod->id)
                            ->get(['id', 'unit_value', 'type_currency', 'redemption_value']);

                        // Add as Customer Ring
                        if ($hasRewardExchange) {
                            $prefillProducts[] = [
                                'id' => $prod->id . '_customer',
                                'productId' => $prod->id,
                                'displayName' => $prod->name . ' (Customer Ring)',
                                'name' => $prod->name,
                                'sku' => $prod->sku,
                                'type' => 'customer',
                                'cashRingValues' => null // No cash values needed for customer type
                            ];
                        }
                        
                        // --- FIX: DISABLED CASH RING AUTO-ADD FOR REWARD EXCHANGE FLOW ---
                        // We comment this out so "Cash Ring" does NOT appear when clicking "Ring Top Up"
                        /*
                        if ($cashRingBalances->isNotEmpty()) {
                            $groupedCashRings = ['dollar' => [], 'riel' => []];
                            foreach ($cashRingBalances as $cashRingBalance) {
                                $currencyType = $cashRingBalance->type_currency == 1 ? 'dollar' : 'riel';
                                $currencySymbol = $cashRingBalance->type_currency == 1 ? '$' : '៛';
                                $groupedCashRings[$currencyType][] = [
                                    'id' => $cashRingBalance->id,
                                    'unit_value' => $cashRingBalance->unit_value,
                                    'currency_symbol' => $currencySymbol,
                                    'redemption_value' => $cashRingBalance->redemption_value
                                ];
                            }
                            
                            $prefillProducts[] = [
                                'id' => $prod->id . '_cash',
                                'productId' => $prod->id,
                                'displayName' => $prod->name . ' (Cash Ring)',
                                'name' => $prod->name,
                                'sku' => $prod->sku,
                                'type' => 'cash',
                                'cashRingValues' => $groupedCashRings
                            ];
                        }
                        */
                        // -------------------------------------------------------------
                    }
                }

                $prefillData = [
                    'contact_id' => $contact_id,
                    'invoice_sell_no' => $invoice_sell_no,
                    'products' => $prefillProducts
                ];
            }
        }
        // ---------------------------------------------------------

        // Validate if the contact_id exists and belongs to the business
        if ($contact_id && !Contact::where('id', $contact_id)->where('business_id', $business_id)->exists()) {
            abort(404, __('Contact not found or does not belong to this business.'));
        }

        // UPDATED: Fetch CUSTOMER contacts
        $customerContacts = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->select('name', 'contact_id', 'mobile', 'id')
            ->get();

        $contacts = $customerContacts->mapWithKeys(function ($item) {
            $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
            return [$item->id => $displayText];
        })->prepend(__('lang_v1.all'), '');

        // NEW: Fetch SUPPLIER contacts
        $supplierContactsList = Contact::where('business_id', $business_id)
            ->where('type', 'supplier')
            ->select('name', 'contact_id', 'mobile', 'id')
            ->get();

        $suppliers = $supplierContactsList->mapWithKeys(function ($item) {
            $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
            return [$item->id => $displayText];
        })->prepend(__('lang_v1.all'), '');

        return view('customer_ring_balance.create', compact('business_locations', 'contacts', 'suppliers', 'contact_id', 'prefillData'));
    }

    public function checkInvoiceSell(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $invoice_no = $request->input('invoice_no');
        
        // Query to find the transaction and join with transaction_payments
        $transaction = Transaction::where('business_id', $business_id)
            ->where('invoice_no', $invoice_no)
            ->where('type', '=', 'sell')
            ->first();
            
        if (!$transaction) {
            return response()->json([
                'has_payment' => false,
                'total_cash_ring' => 0,
                'contact_id' => null,
                'message' => 'Invoice not found'
            ]);
        }
        
        // Get payments for this transaction
        $payments = TransactionPayment::where('transaction_id', $transaction->id)
            ->whereIn('method', ['cash_ring', 'cash_ring_percentage'])
            ->get();
            
        if ($payments->isEmpty()) {
            return response()->json([
                'has_payment' => false,
                'total_cash_ring' => 0,
                'contact_id' => $transaction->contact_id, // Return contact_id even if no payments
                'message' => 'No cash ring payments found'
            ]);
        }
        
        $totalCashRing = 0;
        $cashRingAmount = 0;
        $cashRingPercentageAmount = 0;
        $hasCashRing = false;
        $hasCashRingPercentage = false;
        
        foreach ($payments as $payment) {
            if ($payment->method === 'cash_ring') {
                $cashRingAmount += floatval($payment->amount);
                $hasCashRing = true;
            } elseif ($payment->method === 'cash_ring_percentage') {
                $cashRingPercentageAmount += floatval($payment->cash_ring_percentage);
                $hasCashRingPercentage = true;
            }
        }
        
        // Sum both cash ring and cash ring percentage
        $totalCashRing = $cashRingAmount + $cashRingPercentageAmount;
        
        // Determine the display message based on what types of payments exist
        $paymentTypes = [];
        if ($hasCashRing) {
            $paymentTypes[] = "Cash Ring: $" . number_format($cashRingAmount, 2);
        }
        if ($hasCashRingPercentage) {
            $paymentTypes[] = "Cash Ring %: $" . number_format($cashRingPercentageAmount, 2);
        }
        
        $detailMessage = '';
        if (count($paymentTypes) > 1) {
            $detailMessage = ' (' . implode(' + ', $paymentTypes) . ')';
        }
        
        return response()->json([
            'has_payment' => true,
            'total_cash_ring' => $totalCashRing,
            'cash_ring_amount' => $cashRingAmount,
            'cash_ring_percentage_amount' => $cashRingPercentageAmount,
            'has_cash_ring' => $hasCashRing,
            'has_cash_ring_percentage' => $hasCashRingPercentage,
            'has_both' => $hasCashRing && $hasCashRingPercentage,
            'detail_message' => $detailMessage,
            'contact_id' => $transaction->contact_id, // Add contact_id to response
            'message' => 'Cash ring amount calculated successfully'
        ]);
    }

    public function store(Request $request)
    {
        // Collect all the request data
        $data = $request->all();
        
        // Get business_id and location_id
        $businessId = $request->session()->get('user.business_id');
        $locationId = $data['select_location_id'] ?? null;

        // Get business and check for ring_topup prefix
        $business = Business::find($businessId);
        $ringTopUpPrefix = '';

        if ($business && !empty($business->ref_no_prefixes)) {
            $refNoPrefixes = is_string($business->ref_no_prefixes) 
                ? json_decode($business->ref_no_prefixes, true) 
                : $business->ref_no_prefixes;
                
            if (isset($refNoPrefixes['ring_topup']) && !empty($refNoPrefixes['ring_topup'])) {
                $ringTopUpPrefix = $refNoPrefixes['ring_topup'];
            }
        }

        $lastTransaction = TransactionRingBalance::where('business_id', $businessId)
            ->where('type', 'top_up_ring_balance')
            ->orderBy('id', 'desc')
            ->first();

        // Generate default invoice number
        if ($lastTransaction && $lastTransaction->default_invoice) {
            $lastDefaultInvoice = $lastTransaction->default_invoice;
            $default_invoice = str_pad((intval($lastDefaultInvoice) + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $default_invoice = '00001';
        }

        // Generate invoice number with prefix, default_invoice stays numeric only
        $invoice_no = !empty($ringTopUpPrefix) ? $ringTopUpPrefix .$default_invoice : $default_invoice;
        // Note: default_invoice remains numeric (e.g., "00001"), invoice_no includes prefix (e.g., "RINV00001")

        $current_timestamp = Carbon::now();

        // Create the TransactionRingBalance record
        $transactionRingBalance = new TransactionRingBalance([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'invoice_no' => $invoice_no,
            'default_invoice' => $default_invoice,
            'type' => 'top_up_ring_balance',
            'status' => $data['status'],
            'contact_id' => $data['sell_list_filter_contact_id'] ?? null,
            'transaction_id' => null,
            'sell_ref_invoice' => $data['invoice_sell_no'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'noted' => $data['noted'] ?? null, // ADD THIS LINE
            'created_by' => $request->user()->id,
            'created_at' => $current_timestamp,
            'updated_at' => $current_timestamp,
        ]);

        $transactionRingBalance->save();

        // Loop through products and create TransactionSellRingBalance entries
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $uniqueId => $productDetails) {
                // Extract actual product_id from uniqueId (remove '_customer' or '_cash' suffix)
                $productId = null;
                $productType = null;
                
                if (strpos($uniqueId, '_customer') !== false) {
                    $productId = str_replace('_customer', '', $uniqueId);
                    $productType = 'customer';
                } elseif (strpos($uniqueId, '_cash') !== false) {
                    $productId = str_replace('_cash', '', $uniqueId);
                    $productType = 'cash';
                }

                if (!$productId) continue;

                $variation = Variation::where('product_id', $productId)->first();

                // Handle Customer Ring quantities (existing code)
                if ($variation && isset($productDetails['quantities']) && $productType === 'customer') {
                    // Calculate total quantity in small units (1 ring)
                    $totalSmallUnits = 0;
                    foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                        $totalSmallUnits += $ringValue * $quantity;
                    }

                    if ($totalSmallUnits > 0) {
                        // Save to transaction_sell_ring_balance (cash_ring = null for customer ring)
                        $transactionSellRingBalance = new TransactionSellRingBalance([
                            'transactions_ring_balance_id' => $transactionRingBalance->id,
                            'product_id' => $productId,
                            'variation_id' => $variation->id,
                            'quantity' => $totalSmallUnits, // Total in small units
                            'cash_ring' => null, // Customer ring
                            'created_at' => $current_timestamp,
                            'updated_at' => $current_timestamp,
                        ]);
                        $transactionSellRingBalance->save();

                        // Save to transaction_sell_ring_balance_ring_units
                        foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                            if ($quantity > 0) {
                                // Find the RingUnit record for this product and ring value
                                $ringUnit = RingUnit::where('business_id', $businessId)
                                    ->where('product_id', $productId)
                                    ->where('value', $ringValue)
                                    ->first();

                                if ($ringUnit) {
                                    TransactionSellRingBalanceRingUnits::create([
                                        'transaction_sell_ring_balance_id' => $transactionSellRingBalance->id,
                                        'product_id' => $productId,
                                        'ring_units_id' => $ringUnit->id,
                                        'quantity_ring' => $quantity,
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Handle Cash Ring quantities (updated for single currency)
                if ($variation && isset($productDetails['cash_quantities']) && $productType === 'cash') {
                    // Calculate total quantity for cash rings
                    $totalCashQuantity = 0;
                    
                    // FIXED: Handle cash_quantities as an associative array with cash_ring_balance_id as keys
                    foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                        $totalCashQuantity += (int)$quantity;
                    }
                    
                    if ($totalCashQuantity > 0) {
                        // Save to transaction_sell_ring_balance (cash_ring = 1 for cash ring)
                        $transactionSellRingBalance = new TransactionSellRingBalance([
                            'transactions_ring_balance_id' => $transactionRingBalance->id,
                            'product_id' => $productId,
                            'variation_id' => $variation->id,
                            'quantity' => $totalCashQuantity,
                            'cash_ring' => 1, // Cash ring
                            'created_at' => $current_timestamp,
                            'updated_at' => $current_timestamp,
                        ]);
                        $transactionSellRingBalance->save();
                        
                        // Save to transaction_cash_ring_balance with new_qty calculation
                        foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                            if ((int)$quantity > 0) {
                                // Find the CashRingBalance record by ID
                                $cashRingBalance = CashRingBalance::where('business_id', $businessId)
                                    ->where('id', $cashRingBalanceId)
                                    ->first();
                                    
                                if ($cashRingBalance) {
                                    // Get the last record for this product_id and cash_ring_balance_id
                                    $lastRecord = \DB::table('transaction_cash_ring_balance')
                                        ->where('product_id', $productId)
                                        ->where('cash_ring_balance_id', $cashRingBalance->id)
                                        ->orderBy('transaction_date', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    // Calculate new_qty: current quantity + last new_qty (if exists and not null, otherwise 0)
                                    $lastNewQty = ($lastRecord && $lastRecord->new_qty !== null) ? $lastRecord->new_qty : 0;
                                    $newQty = (int)$quantity + $lastNewQty;

                                    TransactionCashRingBalance::create([
                                        'transaction_sell_ring_balance_id' => $transactionSellRingBalance->id,
                                        'product_id' => $productId,
                                        'cash_ring_balance_id' => $cashRingBalance->id,
                                        'quantity' => (int)$quantity,
                                        'new_qty' => $newQty,
                                        'transaction_date' => $data['transaction_date'],
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Only create or update stock balances if status is 'completed'
        if ($data['status'] === 'completed') {
            foreach ($data['products'] as $uniqueId => $productDetails) {
                // Extract actual product_id from uniqueId
                $productId = null;
                $productType = null;
                
                if (strpos($uniqueId, '_customer') !== false) {
                    $productId = str_replace('_customer', '', $uniqueId);
                    $productType = 'customer';
                } elseif (strpos($uniqueId, '_cash') !== false) {
                    $productId = str_replace('_cash', '', $uniqueId);
                    $productType = 'cash';
                }

                if (!$productId) continue;

                // Handle Customer Ring stock updates (existing code)
                if (isset($productDetails['quantities']) && $productType === 'customer') {
                    $totalSmallUnits = 0;
                    foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                        $totalSmallUnits += $ringValue * $quantity;
                    }

                    if ($totalSmallUnits > 0) {
                        $contactId = $data['sell_list_filter_contact_id'] ?? null;
                        
                        // Update StockRingBalanceCustomer
                        $stockEntry = StockRingBalanceCustomer::where('product_id', $productId)
                            ->where('contact_id', $contactId)
                            ->where('business_id', $businessId)
                            ->first();

                        if ($stockEntry) {
                            StockRingBalanceCustomer::where('product_id', $productId)
                                ->where('contact_id', $contactId)
                                ->where('business_id', $businessId)
                                ->update([
                                    'stock_ring_balance' => $stockEntry->stock_ring_balance + $totalSmallUnits
                                ]);
                        } else {
                            $newStockEntry = new StockRingBalanceCustomer([
                                'product_id' => $productId,
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'stock_ring_balance' => $totalSmallUnits,
                            ]);
                            $newStockEntry->save();
                        }

                        // Get the LAST record for this product + contact combination
                        $lastRecord = \App\StockRewardsExchange::where('product_id', $productId)
                            ->where('contact_id', $contactId)
                            ->orderBy('id', 'desc')
                            ->first();

                        $currentBalance = $lastRecord ? $lastRecord->new_quantity : 0;
                        $newQuantity = $currentBalance + $totalSmallUnits;

                        \App\StockRewardsExchange::create([
                            'transaction_id' => $transactionRingBalance->id,
                            'type' => 'top_up_ring_balance',
                            'contact_id' => $contactId,
                            'product_id' => $productId,
                            'variation_id' => $variation->id,
                            'quantity' => $totalSmallUnits,
                            'new_quantity' => $newQuantity,
                            'created_at' => $current_timestamp,
                            'updated_at' => $current_timestamp,
                        ]);
                    }
                }

                if (isset($productDetails['cash_quantities']) && $productType === 'cash') {
                    $totalAmountInDollar = 0; // Store everything as USD for customer balance

                    // Process each cash ring item
                    foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                        if ((int)$quantity > 0) {
                            $cashRingBalance = CashRingBalance::where('business_id', $businessId)
                                ->where('id', $cashRingBalanceId)
                                ->first();

                            if ($cashRingBalance) {
                                // FIXED: Use DB::table for composite primary key operations
                                $existingStock = \DB::table('stock_cash_ring_balance_product')
                                    ->where('product_id', $productId)
                                    ->where('business_id', $businessId)
                                    ->where('location_id', $locationId)
                                    ->where('cash_ring_balance_id', $cashRingBalance->id)
                                    ->first();

                                if ($existingStock) {
                                    // Update existing record - add quantity using DB::table
                                    \DB::table('stock_cash_ring_balance_product')
                                        ->where('product_id', $productId)
                                        ->where('business_id', $businessId)
                                        ->where('location_id', $locationId)
                                        ->where('cash_ring_balance_id', $cashRingBalance->id)
                                        ->update([
                                            'stock_cash_ring_balance' => \DB::raw('stock_cash_ring_balance + ' . (int)$quantity)
                                        ]);
                                } else {
                                    // Create new record using DB::table
                                    \DB::table('stock_cash_ring_balance_product')->insert([
                                        'product_id' => $productId,
                                        'business_id' => $businessId,
                                        'location_id' => $locationId,
                                        'cash_ring_balance_id' => $cashRingBalance->id,
                                        'stock_cash_ring_balance' => (int)$quantity
                                    ]);
                                }

                                // Calculate dollar amount only for customer total balance
                                $redemptionValue = $cashRingBalance->redemption_value * (int)$quantity;
                                if ($cashRingBalance->type_currency == 1) {
                                    $totalAmountInDollar += $redemptionValue;
                                } else {
                                    // Riel: convert to dollar (divide by 4000)
                                    $totalAmountInDollar += ($redemptionValue / 4000);
                                }
                            }
                        }
                    }

                    // Update customer total balance in dollars
                    if ($totalAmountInDollar > 0) {
                        $contactId = $data['sell_list_filter_contact_id'];
                        
                        $stockCashEntry = StockCashRingBalanceCustomer::where('contact_id', $contactId)
                            ->where('business_id', $businessId)
                            ->first();

                        if ($stockCashEntry) {
                            // Update existing record - add to current dollar amount
                            \DB::table('stock_cash_ring_balance_customer')
                                ->where('contact_id', $contactId)
                                ->where('business_id', $businessId)
                                ->update([
                                    'total_cuurency_dollar' => \DB::raw('total_cuurency_dollar + ' . $totalAmountInDollar),
                                ]);
                                
                            \Log::info('Updated existing cash ring balance:', [
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'amount_added' => $totalAmountInDollar,
                                'new_total' => $stockCashEntry->total_cuurency_dollar + $totalAmountInDollar
                            ]);
                        } else {
                            // Insert new record
                            \DB::table('stock_cash_ring_balance_customer')->insert([
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'total_cuurency_dollar' => $totalAmountInDollar,
                            ]);
                            
                            \Log::info('Created new cash ring balance:', [
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'initial_amount' => $totalAmountInDollar
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->route('customer-ring-balance.index')->with('success', 'Transaction saved successfully.');
    }

    public function edit($id)
{
    $business_id = session()->get('user.business_id');

    // FIXED: Use consistent relationship names that match the model definitions
    $transaction = TransactionRingBalance::with([
        'transactionSellRingBalances.product',
        'transactionSellRingBalances.ringUnits.ringUnit',
        'transactionSellRingBalances.cashRings.cashRingBalance',
        'contact' // Load the contact relationship to detect type
    ])
        ->where('business_id', $business_id)
        ->findOrFail($id);

    // Get business locations
    $business_locations = BusinessLocation::where('business_id', $business_id)
        ->where('is_active', 1)
        ->pluck('name', 'id');

    // Determine if transaction contact is customer or supplier
    $transactionContactType = $transaction->contact ? $transaction->contact->type : 'customer';

    // UPDATED: Fetch CUSTOMER contacts
    $customerContacts = Contact::where('business_id', $business_id)
        ->where('type', 'customer')
        ->select('name', 'contact_id', 'mobile', 'id')
        ->get();

    $contact = $customerContacts->mapWithKeys(function ($item) {
        $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
        return [$item->id => $displayText];
    })->prepend(__('lang_v1.all'), '');

    // NEW: Fetch SUPPLIER contacts
    $supplierContactsList = Contact::where('business_id', $business_id)
        ->where('type', 'supplier')
        ->select('name', 'contact_id', 'mobile', 'id')
        ->get();

    $suppliers = $supplierContactsList->mapWithKeys(function ($item) {
        $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
        return [$item->id => $displayText];
    })->prepend(__('lang_v1.all'), '');

    // Pass contact type to view so it can set correct toggle and populate correct list
    $contactType = $transactionContactType; // 'customer' or 'supplier'

    return view('customer_ring_balance.edit', compact(
        'transaction',
        'business_locations',
        'contact',
        'suppliers',
        'contactType'
    ));
}
    public function update(Request $request, $id)
    {
        // Collect all the request data
        $data = $request->all();
        
        // Get business_id and location_id
        $businessId = $request->session()->get('user.business_id');
        $locationId = $data['select_location_id'] ?? null;

        // Find the existing TransactionRingBalance record
        $transactionRingBalance = TransactionRingBalance::findOrFail($id);

        // Update the TransactionRingBalance record
        $transactionRingBalance->update([
            'location_id' => $locationId,
            'status' => $data['status'],
            'contact_id' => $data['sell_list_filter_contact_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'sell_ref_invoice' => $data['invoice_sell_no'] ?? null,
            'noted' => $data['noted'] ?? null, // ADD THIS LINE
            'updated_at' => Carbon::now(),
        ]);

        // Get the existing TransactionSellRingBalance entries for this transaction
        $existingEntries = TransactionSellRingBalance::where('transactions_ring_balance_id', $transactionRingBalance->id)
            ->get()
            ->keyBy(function($item) {
                // Create a key based on product_id and cash_ring type
                return $item->product_id . '_' . ($item->cash_ring ? 'cash' : 'customer');
            });

        // Process the submitted products
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $uniqueId => $productDetails) {
                // Extract actual product_id from uniqueId
                $productId = null;
                $productType = null;
                
                if (strpos($uniqueId, '_customer') !== false) {
                    $productId = str_replace('_customer', '', $uniqueId);
                    $productType = 'customer';
                } elseif (strpos($uniqueId, '_cash') !== false) {
                    $productId = str_replace('_cash', '', $uniqueId);
                    $productType = 'cash';
                }

                if (!$productId) continue;

                $variation = Variation::where('product_id', $productId)->first();

                // Handle Customer Ring quantities
                if ($variation && isset($productDetails['quantities']) && $productType === 'customer') {
                    // Calculate total quantity in small units (1 ring)
                    $totalSmallUnits = 0;
                    foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                        $totalSmallUnits += $ringValue * (int)$quantity;
                    }

                    if ($totalSmallUnits > 0) {
                        $entryKey = $productId . '_customer';
                        
                        // Check if a TransactionSellRingBalance entry exists for this customer product
                        if (isset($existingEntries[$entryKey])) {
                            $transactionSellEntry = $existingEntries[$entryKey];

                            // Update the existing TransactionSellRingBalance entry
                            $transactionSellEntry->update([
                                'quantity' => $totalSmallUnits,
                                'cash_ring' => null, // Customer ring
                                'updated_at' => Carbon::now(),
                            ]);

                            // Delete existing ring unit entries
                            TransactionSellRingBalanceRingUnits::where('transaction_sell_ring_balance_id', $transactionSellEntry->id)->delete();
                        } else {
                            // Create a new TransactionSellRingBalance entry
                            $transactionSellEntry = new TransactionSellRingBalance([
                                'transactions_ring_balance_id' => $transactionRingBalance->id,
                                'product_id' => $productId,
                                'variation_id' => $variation->id,
                                'quantity' => $totalSmallUnits,
                                'cash_ring' => null, // Customer ring
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $transactionSellEntry->save();
                        }

                        // Save new ring unit entries
                        foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                            if ((int)$quantity > 0) {
                                $ringUnit = RingUnit::where('business_id', $businessId)
                                    ->where('product_id', $productId)
                                    ->where('value', $ringValue)
                                    ->first();

                                if ($ringUnit) {
                                    TransactionSellRingBalanceRingUnits::create([
                                        'transaction_sell_ring_balance_id' => $transactionSellEntry->id,
                                        'product_id' => $productId,
                                        'ring_units_id' => $ringUnit->id,
                                        'quantity_ring' => (int)$quantity,
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Handle Cash Ring quantities
                if ($variation && isset($productDetails['cash_quantities']) && $productType === 'cash') {
                    // Calculate total quantity for cash rings
                    $totalCashQuantity = 0;
                    
                    // FIXED: Handle cash_quantities as an associative array with cash_ring_balance_id as keys
                    foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                        $totalCashQuantity += (int)$quantity;
                    }
                    
                    if ($totalCashQuantity > 0) {
                        $entryKey = $productId . '_cash';
                    
                        // Check if a TransactionSellRingBalance entry exists for this cash product
                        if (isset($existingEntries[$entryKey])) {
                            $transactionSellEntry = $existingEntries[$entryKey];
                            // Update the existing TransactionSellRingBalance entry
                            $transactionSellEntry->update([
                                'quantity' => $totalCashQuantity,
                                'cash_ring' => 1, // Cash ring
                                'updated_at' => Carbon::now(),
                            ]);
                            // Delete existing cash ring entries
                            TransactionCashRingBalance::where('transaction_sell_ring_balance_id', $transactionSellEntry->id)->delete();
                        } else {
                            // Create a new TransactionSellRingBalance entry
                            $transactionSellEntry = new TransactionSellRingBalance([
                                'transactions_ring_balance_id' => $transactionRingBalance->id,
                                'product_id' => $productId,
                                'variation_id' => $variation->id,
                                'quantity' => $totalCashQuantity,
                                'cash_ring' => 1, // Cash ring
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $transactionSellEntry->save();
                        }
                        
                        // Save new cash ring entries with new_qty calculation
                        foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                            if ((int)$quantity > 0) {
                                // Find the CashRingBalance record by ID
                                $cashRingBalance = CashRingBalance::where('business_id', $businessId)
                                    ->where('id', $cashRingBalanceId)
                                    ->first();
                                    
                                if ($cashRingBalance) {
                                    // Get the last record BEFORE this transaction for new_qty calculation
                                    $lastRecord = \DB::table('transaction_cash_ring_balance')
                                        ->where('product_id', $productId)
                                        ->where('cash_ring_balance_id', $cashRingBalance->id)
                                        ->where('transaction_date', '<', $data['transaction_date'])
                                        ->orderBy('transaction_date', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    // Calculate new_qty: current quantity + last new_qty (if exists and not null, otherwise 0)
                                    $lastNewQty = ($lastRecord && $lastRecord->new_qty !== null) ? $lastRecord->new_qty : 0;
                                    $newQty = (int)$quantity + $lastNewQty;

                                    TransactionCashRingBalance::create([
                                        'transaction_sell_ring_balance_id' => $transactionSellEntry->id,
                                        'product_id' => $productId,
                                        'cash_ring_balance_id' => $cashRingBalance->id,
                                        'quantity' => (int)$quantity,
                                        'new_qty' => $newQty,
                                        'transaction_date' => $data['transaction_date'],
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Delete TransactionSellRingBalance entries for products that were removed
        $submittedProductKeys = [];
        foreach ($data['products'] ?? [] as $uniqueId => $productDetails) {
            if (strpos($uniqueId, '_customer') !== false) {
                $submittedProductKeys[] = str_replace('_customer', '', $uniqueId) . '_customer';
            } elseif (strpos($uniqueId, '_cash') !== false) {
                $submittedProductKeys[] = str_replace('_cash', '', $uniqueId) . '_cash';
            }
        }

        foreach ($existingEntries as $entryKey => $entry) {
            if (!in_array($entryKey, $submittedProductKeys)) {
                // Delete related records based on type
                if ($entry->cash_ring) {
                    TransactionCashRingBalance::where('transaction_sell_ring_balance_id', $entry->id)->delete();
                } else {
                    TransactionSellRingBalanceRingUnits::where('transaction_sell_ring_balance_id', $entry->id)->delete();
                }
                $entry->delete();
            }
        }

        // Only update stock balances if the status is 'completed'
        if ($data['status'] === 'completed') {
            foreach ($data['products'] as $uniqueId => $productDetails) {
                // Extract actual product_id from uniqueId
                $productId = null;
                $productType = null;
                
                if (strpos($uniqueId, '_customer') !== false) {
                    $productId = str_replace('_customer', '', $uniqueId);
                    $productType = 'customer';
                } elseif (strpos($uniqueId, '_cash') !== false) {
                    $productId = str_replace('_cash', '', $uniqueId);
                    $productType = 'cash';
                }

                if (!$productId) continue;

                // Handle Customer Ring stock updates
                if (isset($productDetails['quantities']) && $productType === 'customer') {
                    $totalSmallUnits = 0;
                    foreach ($productDetails['quantities'] as $ringValue => $quantity) {
                        $totalSmallUnits += $ringValue * (int)$quantity;
                    }

                    if ($totalSmallUnits > 0) {
                        $contactId = $data['sell_list_filter_contact_id'] ?? null;
                        
                        $stockEntry = StockRingBalanceCustomer::where('product_id', $productId)
                            ->where('contact_id', $contactId)
                            ->where('business_id', $businessId)
                            ->first();

                        if ($stockEntry) {
                            StockRingBalanceCustomer::where('product_id', $productId)
                                ->where('contact_id', $contactId)
                                ->where('business_id', $businessId)
                                ->update([
                                    'stock_ring_balance' => $stockEntry->stock_ring_balance + $totalSmallUnits
                                ]);
                        } else {
                            $newStockEntry = new StockRingBalanceCustomer([
                                'product_id' => $productId,
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'stock_ring_balance' => $totalSmallUnits,
                            ]);
                            $newStockEntry->save();
                        }

                        // Get the LAST record for this product + contact combination
                        $lastRecord = \App\StockRewardsExchange::where('product_id', $productId)
                            ->where('contact_id', $contactId)
                            ->orderBy('id', 'desc')
                            ->first();

                        $currentBalance = $lastRecord ? $lastRecord->new_quantity : 0;
                        $newQuantity = $currentBalance + $totalSmallUnits;

                        \App\StockRewardsExchange::create([
                            'transaction_id' => $transactionRingBalance->id,
                            'type' => 'top_up_ring_balance',
                            'contact_id' => $contactId,
                            'product_id' => $productId,
                            'variation_id' => $variation->id,
                            'quantity' => $totalSmallUnits,
                            'new_quantity' => $newQuantity,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }

                // Handle Cash Ring stock updates - SAVE StockCashRingBalanceProduct
                if (isset($productDetails['cash_quantities']) && $productType === 'cash') {
                    $totalAmountInDollar = 0; // Store everything as USD for customer balance

                    // Process each cash ring item
                    foreach ($productDetails['cash_quantities'] as $cashRingBalanceId => $quantity) {
                        if ((int)$quantity > 0) {
                            $cashRingBalance = CashRingBalance::where('business_id', $businessId)
                                ->where('id', $cashRingBalanceId)
                                ->first();

                            if ($cashRingBalance) {
                                // FIXED: Use DB::table for composite primary key operations
                                $existingStock = \DB::table('stock_cash_ring_balance_product')
                                    ->where('product_id', $productId)
                                    ->where('business_id', $businessId)
                                    ->where('location_id', $locationId)
                                    ->where('cash_ring_balance_id', $cashRingBalance->id)
                                    ->first();

                                if ($existingStock) {
                                    // Update using DB::table with composite key conditions
                                    \DB::table('stock_cash_ring_balance_product')
                                        ->where('product_id', $productId)
                                        ->where('business_id', $businessId)
                                        ->where('location_id', $locationId)
                                        ->where('cash_ring_balance_id', $cashRingBalance->id)
                                        ->update([
                                            'stock_cash_ring_balance' => \DB::raw('stock_cash_ring_balance + ' . (int)$quantity)
                                        ]);
                                } else {
                                    // Insert new record using DB::table
                                    \DB::table('stock_cash_ring_balance_product')->insert([
                                        'product_id' => $productId,
                                        'business_id' => $businessId,
                                        'location_id' => $locationId,
                                        'cash_ring_balance_id' => $cashRingBalance->id,
                                        'stock_cash_ring_balance' => (int)$quantity
                                    ]);
                                }

                                // Calculate dollar amount only for customer total balance
                                $redemptionValue = $cashRingBalance->redemption_value * (int)$quantity;
                                if ($cashRingBalance->type_currency == 1) {
                                    $totalAmountInDollar += $redemptionValue;
                                } else {
                                    // Riel: convert to dollar (divide by 4000)
                                    $totalAmountInDollar += ($redemptionValue / 4000);
                                }
                            }
                        }
                    }

                    // Update customer total balance in dollars
                    if ($totalAmountInDollar > 0) {
                        $contactId = $data['sell_list_filter_contact_id'];
                        
                        $stockCashEntry = StockCashRingBalanceCustomer::where('contact_id', $contactId)
                            ->where('business_id', $businessId)
                            ->first();

                        if ($stockCashEntry) {
                            // Update existing record - add to current dollar amount
                            \DB::table('stock_cash_ring_balance_customer')
                                ->where('contact_id', $contactId)
                                ->where('business_id', $businessId)
                                ->update([
                                    'total_cuurency_dollar' => \DB::raw('total_cuurency_dollar + ' . $totalAmountInDollar),
                                ]);
                        } else {
                            // Insert new record
                            \DB::table('stock_cash_ring_balance_customer')->insert([
                                'contact_id' => $contactId,
                                'business_id' => $businessId,
                                'total_cuurency_dollar' => $totalAmountInDollar,
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->route('customer-ring-balance.index')->with('success', 'Transaction updated successfully.');
    }

    public function show($id)
    {
        $business_id = session()->get('user.business_id');

        // FIXED: Use the alternative relationship names for show method
        $transaction = TransactionRingBalance::with([
            'contact', 
            'transactionSellRingBalances.product',
            'transactionSellRingBalances.cashRingBalanceDetails.cashRingBalance',
            'transactionSellRingBalances.ringUnitDetails.ringUnit'
        ])->where('business_id', $business_id)->findOrFail($id);

        return view('customer_ring_balance.show', compact('transaction'));
    }

   public function destroy($id, Request $request)
{
    try {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->user()->id;
        
        // Find the transaction (not deleted)
        $transaction = TransactionRingBalance::where('business_id', $business_id)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
            
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found or already deleted.'], 404);
        }
        
        // Only reverse stock if the transaction was completed
        if ($transaction->status === 'completed') {
            // Get all related transaction sell ring balances
            $transactionSellRingBalances = TransactionSellRingBalance::where('transactions_ring_balance_id', $transaction->id)->get();
            
            // Reverse stock balances for each product
            foreach ($transactionSellRingBalances as $sellRingBalance) {
                
                // Handle Customer Ring reversal (cash_ring is null or empty)
                if (is_null($sellRingBalance->cash_ring) || $sellRingBalance->cash_ring === '') {
                    // Customer Ring - subtract from StockRingBalanceCustomer using composite key
                    $existingStock = \DB::table('stock_ring_balance_customer')
                        ->where('product_id', $sellRingBalance->product_id)
                        ->where('contact_id', $transaction->contact_id)
                        ->where('business_id', $business_id)
                        ->first();
                        
                    if ($existingStock) {
                        // Always update, allow negative values
                        $newBalance = $existingStock->stock_ring_balance - $sellRingBalance->quantity;
                        \DB::table('stock_ring_balance_customer')
                            ->where('product_id', $sellRingBalance->product_id)
                            ->where('contact_id', $transaction->contact_id)
                            ->where('business_id', $business_id)
                            ->update(['stock_ring_balance' => $newBalance]);
                    } else {
                        // Create new record with negative balance if doesn't exist
                        \DB::table('stock_ring_balance_customer')->insert([
                            'product_id' => $sellRingBalance->product_id,
                            'contact_id' => $transaction->contact_id,
                            'business_id' => $business_id,
                            'stock_ring_balance' => -$sellRingBalance->quantity,
                        ]);
                    }

                    // Get the LAST record for this product + contact (any type)
                    $lastRecord = \App\StockRewardsExchange::where('product_id', $sellRingBalance->product_id)
                        ->where('contact_id', $transaction->contact_id)
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    if ($lastRecord) {
                        // Get variation_id from variations table
                        $variation = Variation::where('product_id', $sellRingBalance->product_id)
                            ->pluck('id')
                            ->first();

                        // Reversal: subtract the quantity (opposite of addition)
                        $reversalNewQuantity = $lastRecord->new_quantity - $sellRingBalance->quantity;
                        
                        \App\StockRewardsExchange::create([
                            'transaction_id' => $transaction->id,
                            'type' => 'delete_top_up_ring_balance',
                            'contact_id' => $transaction->contact_id,
                            'product_id' => $sellRingBalance->product_id,
                            'variation_id' => $variation,
                            'quantity' => -$sellRingBalance->quantity,
                            'new_quantity' => $reversalNewQuantity,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } 
                // Handle Cash Ring reversal (cash_ring is not null and not empty)
                else {
                    // Cash Ring - subtract from both product stock and customer total
                    
                    // 1. Get all cash ring balance transactions for this sell ring balance
                    $cashRingBalances = TransactionCashRingBalance::where('transaction_sell_ring_balance_id', $sellRingBalance->id)->get();
                    
                    $totalAmountToSubtract = 0; // For customer total balance
                    
                    foreach ($cashRingBalances as $cashRingBalance) {
                        // Reverse product stock using DB::table for composite key
                        $existingStock = \DB::table('stock_cash_ring_balance_product')
                            ->where('product_id', $sellRingBalance->product_id)
                            ->where('business_id', $business_id)
                            ->where('location_id', $transaction->location_id)
                            ->where('cash_ring_balance_id', $cashRingBalance->cash_ring_balance_id)
                            ->first();
                            
                        if ($existingStock) {
                            // Always update, allow negative values
                            $newStockBalance = $existingStock->stock_cash_ring_balance - $cashRingBalance->quantity;
                            \DB::table('stock_cash_ring_balance_product')
                                ->where('product_id', $sellRingBalance->product_id)
                                ->where('business_id', $business_id)
                                ->where('location_id', $transaction->location_id)
                                ->where('cash_ring_balance_id', $cashRingBalance->cash_ring_balance_id)
                                ->update(['stock_cash_ring_balance' => $newStockBalance]);
                        } else {
                            // Create new record with negative balance if doesn't exist
                            \DB::table('stock_cash_ring_balance_product')->insert([
                                'product_id' => $sellRingBalance->product_id,
                                'business_id' => $business_id,
                                'location_id' => $transaction->location_id,
                                'cash_ring_balance_id' => $cashRingBalance->cash_ring_balance_id,
                                'stock_cash_ring_balance' => -$cashRingBalance->quantity
                            ]);
                        }
                        
                        // Calculate amount to subtract from customer total balance
                        $cashRingBalanceRecord = CashRingBalance::find($cashRingBalance->cash_ring_balance_id);
                        
                        if ($cashRingBalanceRecord) {
                            $redemptionValue = $cashRingBalanceRecord->redemption_value * $cashRingBalance->quantity;
                            
                            if ($cashRingBalanceRecord->type_currency == 1) {
                                // Dollar
                                $totalAmountToSubtract += $redemptionValue;
                            } else {
                                // Riel: convert to dollar (divide by 4000)
                                $totalAmountToSubtract += ($redemptionValue / 4000);
                            }
                        }
                    }
                    
                    // 2. Update customer total balance in dollars using composite key
                    if ($totalAmountToSubtract > 0) {
                        $existingCashStock = \DB::table('stock_cash_ring_balance_customer')
                            ->where('contact_id', $transaction->contact_id)
                            ->where('business_id', $business_id)
                            ->first();
                            
                        if ($existingCashStock) {
                            // Always update, allow negative values
                            $newTotalBalance = $existingCashStock->total_cuurency_dollar - $totalAmountToSubtract;
                            \DB::table('stock_cash_ring_balance_customer')
                                ->where('contact_id', $transaction->contact_id)
                                ->where('business_id', $business_id)
                                ->update(['total_cuurency_dollar' => $newTotalBalance]);
                        } else {
                            // Create new record with negative balance if doesn't exist
                            \DB::table('stock_cash_ring_balance_customer')->insert([
                                'contact_id' => $transaction->contact_id,
                                'business_id' => $business_id,
                                'total_cuurency_dollar' => -$totalAmountToSubtract,
                            ]);
                        }
                    }
                }
            }
        }
        
        // Mark transaction as deleted (soft delete) - Keep all transaction history
        $transaction->update([
            'deleted_by' => $user_id,
            'deleted_at' => Carbon::now()
        ]);
        
        return response()->json([
            'success' => true, 
            'message' => 'Transaction deleted successfully and ring balance reversed.'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error deleting ring balance transaction: ' . $e->getMessage());
        return response()->json([
            'success' => false, 
            'message' => 'Error occurred while deleting transaction: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Fetch RingUnit values for a given product_id.
     */
   // FIXED searchProduct method - ONLY ADD 'id' field, keep your existing code
    public function searchProduct(Request $request)
    {
        $business_id = session()->get('user.business_id');
        $query = $request->input('query');
        
        // Search products by name or SKU
        $products = Product::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
            ->orWhere('sku', 'LIKE', "%{$query}%");
        })
        ->where('not_for_selling', 1)
        ->where('products.business_id', $business_id)
        ->get(['products.id', 'products.name', 'products.sku']);
        
        $result = [];
        
        foreach ($products as $product) {
            // Check if product exists in reward_exchange
            $hasRewardExchange = RewardsExchange::where('business_id', $business_id)
                ->where('exchange_product', $product->id)
                ->exists();
            
            // Check if product exists in cash_ring_balance and get all records
            // FIXED: ADD 'id' field to the get() method
            $cashRingBalances = CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $product->id)
                ->get(['id', 'unit_value', 'type_currency', 'redemption_value']); // ADDED 'id' here
            
            // Add Customer Ring if has reward exchange
            if ($hasRewardExchange) {
                $result[] = [
                    'id' => $product->id . '_customer',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => 'customer',
                    'display_name' => $product->name . ' (Customer Ring)'
                ];
            }
            
            // Group cash ring values by type_currency
            if ($cashRingBalances->isNotEmpty()) {
                $groupedCashRings = [
                    'dollar' => [],
                    'riel' => []
                ];
                
                foreach ($cashRingBalances as $cashRingBalance) {
                    $currencySymbol = $cashRingBalance->type_currency == 1 ? '$' : '៛';
                    $currencyType = $cashRingBalance->type_currency == 1 ? 'dollar' : 'riel';
                    $groupedCashRings[$currencyType][] = [
                        'id' => $cashRingBalance->id, // ADDED this line
                        'unit_value' => $cashRingBalance->unit_value,
                        'currency_symbol' => $currencySymbol,
                        'redemption_value' => $cashRingBalance->redemption_value
                    ];
                }
                
                $result[] = [
                    'id' => $product->id . '_cash',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => 'cash',
                    'display_name' => $product->name . ' (Cash Ring)',
                    'cash_ring_values' => $groupedCashRings
                ];
            }
        }
        
        return response()->json(['products' => $result]);
    }

    public function getRingUnits(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $request->query('product_id');

        $ring_units = RingUnit::where('business_id', $business_id)
            ->where('product_id', $product_id)
            ->pluck('value')
            ->toArray();

        return response()->json(['ring_units' => $ring_units]);
    }
}
