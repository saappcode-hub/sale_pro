<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\CurrentStockBackup;
use App\RewardsExchange;
use App\StockRewardsExchange;
use App\StockRingBalanceCustomer;
use App\Transaction;
use App\TransactionRingBalance;
use App\TransactionSellLine;
use App\TransactionSellRingBalance;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderRewardController extends Controller
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
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');
            $location_id = $request->location_id;
            $contact_id = $request->contact_id;

            // ---------------------------------------------------------
            // 1. BUILD QUERIES
            // ---------------------------------------------------------
            
            // A. Existing Reward Exchanges
            $rewardQuery = Transaction::where('business_id', $business_id)
                ->where('type', 'reward_exchange')
                ->whereNull('deleted_at');

            // B. Pending Sells (Has Cash Ring payment but NO Reward Exchange)
            $sellQuery = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->whereIn('payment_status', ['paid', 'partial'])
                ->whereHas('payment_lines', function($q) {
                    $q->whereIn('method', ['cash_ring', 'cash_ring_percentage']);
                })
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('transactions as t2')
                      ->where('t2.type', 'reward_exchange')
                      ->whereRaw('t2.ref_sale_invoice = transactions.invoice_no')
                      ->whereNull('t2.deleted_at');
                })
                // Eager load payments immediately
                ->with(['payment_lines']); 

            // Apply Filters
            if ($location_id && $location_id != 'all') {
                $rewardQuery->where('location_id', $location_id);
                $sellQuery->where('location_id', $location_id);
            }
            if ($contact_id && $contact_id != 'all') {
                $rewardQuery->where('contact_id', $contact_id);
                $sellQuery->where('contact_id', $contact_id);
            }
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $range = [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59'];
                $rewardQuery->whereBetween('transaction_date', $range);
                $sellQuery->whereBetween('transaction_date', $range);
            }
            
            if ($request->has('status') && $request->status != '') {
                if ($request->status == 'pending') {
                    $rewardQuery->where('status', 'pending');
                } elseif ($request->status == 'completed' || $request->status == 'partial') {
                    $rewardQuery->where('status', $request->status);
                    $sellQuery->whereRaw('1 = 0'); 
                }
            }

            // ---------------------------------------------------------
            // 2. PAGINATION
            // ---------------------------------------------------------
            $countReward = $rewardQuery->count();
            $countSell = $sellQuery->count();
            $totalRecords = $countReward + $countSell;

            $start = $request->input('start', 0);
            $length = $request->input('length', 25);
            if ($length == -1) $length = $totalRecords;

            $rewardTrans = $rewardQuery->select('*')->get();
            $sellTrans = $sellQuery->select('*')->get();
            
            $allTrans = $rewardTrans->concat($sellTrans)->sortByDesc('transaction_date');
            $pagedTrans = $allTrans->slice($start, $length);

            // ---------------------------------------------------------
            // 3. PROCESS ROWS
            // ---------------------------------------------------------
            $result = [];

            foreach ($pagedTrans as $transaction) {
                $isSellType = ($transaction->type == 'sell');
                $invoiceNo = $isSellType ? $transaction->invoice_no : $transaction->ref_sale_invoice;
                
                // Get Sell Transaction with Payments
                if ($isSellType) {
                    $sellTransaction = $transaction;
                } else {
                    $sellTransaction = Transaction::where('invoice_no', $invoiceNo)
                        ->where('type', 'sell')
                        ->where('business_id', $business_id) 
                        ->with(['payment_lines']) 
                        ->first();
                }

                $ringCashReceivableTotal = 0;
                $salesOrderInvoiceNo = '-';

                if ($sellTransaction) {
                    // Calculate Ring Cash Receivable (Payment Amount or Percentage)
                    foreach ($sellTransaction->payment_lines as $payment) {
                        if ($payment->method == 'cash_ring') {
                            $ringCashReceivableTotal += $payment->amount;
                        } elseif ($payment->method == 'cash_ring_percentage') {
                            // Use original percentage amount, not calculated amount
                            $ringCashReceivableTotal += floatval($payment->cash_ring_percentage);
                        }
                    }
                    
                    if ($sellTransaction->sales_order_ids) {
                        $salesOrderIds = is_array($sellTransaction->sales_order_ids) ? $sellTransaction->sales_order_ids : json_decode($sellTransaction->sales_order_ids, true);
                        $sid = !empty($salesOrderIds) ? $salesOrderIds[0] : null;
                        if ($sid) {
                            $so = Transaction::find($sid);
                            if ($so) $salesOrderInvoiceNo = $so->invoice_no;
                        }
                    }
                }

                // --- FIX: Filter Top Up Transactions by STATUS 'completed' ---
                // We only count "Received" if the Ring Top Up is actually COMPLETED.
                $topUpTransactions = TransactionRingBalance::where('sell_ref_invoice', $invoiceNo)
                    ->where('type', 'top_up_ring_balance')
                    ->where('status', 'completed') // <--- NEW FILTER ADDED
                    ->whereNull('deleted_at')
                    ->with(['transactionSellRingBalances.cashRingBalanceDetails.cashRingBalance'])
                    ->get();

                $rowsData = [];

                // C. PROCESS PRODUCTS (For Reward Exchange Only)
                if (!$isSellType) {
                    $sellLines = $transaction->sell_lines;
                    $productIds = $sellLines->pluck('product_id')->toArray();
                    
                    $rewards = RewardsExchange::whereIn('product_for_sale', $productIds)
                        ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
                        ->whereNull('rewards_exchange.deleted_at')
                        ->select([
                            'rewards_exchange.product_for_sale as product_for_sale_id',
                            'rewards_exchange.exchange_product as exchange_product_id',
                            'receive_products.name as receive_product_name',
                            'rewards_exchange.exchange_quantity',
                        ])->get();

                    foreach ($rewards as $reward) {
                        $sellLine = $sellLines->firstWhere('product_id', $reward->product_for_sale_id);
                        $qty = $sellLine ? $sellLine->quantity : 0;
                        $ringReceivable = $qty * $reward->exchange_quantity;

                        $ringReceived = 0;
                        foreach ($topUpTransactions as $topUp) {
                            foreach ($topUp->transactionSellRingBalances as $line) {
                                if ($line->product_id == $reward->exchange_product_id && empty($line->cash_ring)) {
                                    $ringReceived += $line->quantity;
                                }
                            }
                        }

                        $rowsData[] = [
                            'product_prize' => $reward->receive_product_name,
                            'quantity' => number_format($qty, 2),
                            'ring_receivable' => number_format($ringReceivable, 2) . ' Ring',
                            'ring_cash_receivable' => '-',
                            'ring_received' => number_format($ringReceived, 2) . ' Ring',
                            'ring_cash_received' => '-',
                            'total_ring_cash' => '-',
                            'completed' => ($ringReceivable > 0 && $ringReceived >= $ringReceivable)
                        ];
                    }
                }

                // D. PROCESS CASH RING ROW
                if ($ringCashReceivableTotal > 0) {
                    $totalCashReceivedVal = 0;
                    
                    // Arrays for aggregation
                    $aggregatedDetails = []; 
                    $grandTotalQty = 0;

                    foreach ($topUpTransactions as $topUp) {
                        foreach ($topUp->transactionSellRingBalances as $line) {
                            if (!empty($line->cash_ring) && $line->cash_ring == 1) {
                                if ($line->cashRingBalanceDetails) {
                                    foreach ($line->cashRingBalanceDetails as $detail) {
                                        $qty = (int)$detail->quantity;
                                        $cb = $detail->cashRingBalance;
                                        
                                        if ($cb && $qty > 0) {
                                            $symbol = $cb->type_currency == 1 ? '$' : '៛';
                                            $key = $cb->unit_value . '(' . $symbol . ')';
                                            
                                            if (!isset($aggregatedDetails[$key])) {
                                                $aggregatedDetails[$key] = 0;
                                            }
                                            $aggregatedDetails[$key] += $qty;
                                            $grandTotalQty += $qty;

                                            $val = $qty * $cb->unit_value;
                                            $totalCashReceivedVal += ($cb->type_currency == 1 ? $val : $val / 4000);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // String: "Total: 124 , 1($)=117 , 7($)=7"
                    $displayParts = [];
                    if ($grandTotalQty > 0) {
                        $displayParts[] = "Total: " . $grandTotalQty;
                        foreach ($aggregatedDetails as $label => $q) {
                            $displayParts[] = $label . " = " . $q;
                        }
                        $finalDisplayStr = implode(' , ', $displayParts);
                    } else {
                        $finalDisplayStr = "-";
                    }

                    $rowsData[] = [
                        'product_prize' => 'Cash Ring',
                        'quantity' => '-',
                        'ring_receivable' => '-',
                        'ring_cash_receivable' => '$' . number_format($ringCashReceivableTotal, 2),
                        'ring_received' => '-',
                        'ring_cash_received' => $finalDisplayStr,
                        'total_ring_cash' => '$' . number_format($totalCashReceivedVal, 2),
                        'completed' => ($totalCashReceivedVal >= $ringCashReceivableTotal)
                    ];
                }

                // E. CALCULATE STATUS
                if ($isSellType) {
                    $status = 'pending';
                } else {
                    $status = 'pending';
                    $allComplete = true;
                    $anyReceived = false;
                    if (empty($rowsData)) $allComplete = false;
                    foreach ($rowsData as $r) {
                        if (!$r['completed']) $allComplete = false;
                        if ($r['ring_received'] !== '-' && $r['ring_received'] != '0.00 Ring') $anyReceived = true;
                        if ($r['total_ring_cash'] !== '-' && $r['total_ring_cash'] != '$0.00') $anyReceived = true;
                    }
                    if ($allComplete) $status = 'completed';
                    elseif ($anyReceived) $status = 'partial';
                }

                // Fallback
                if (empty($rowsData)) {
                    $rowsData[] = [
                        'product_prize' => '-', 'quantity' => '-', 'ring_receivable' => '-', 'ring_cash_receivable' => '-',
                        'ring_received' => '-', 'ring_cash_received' => '-', 'total_ring_cash' => '-', 'completed' => false
                    ];
                }

                // F. FLATTEN
                foreach ($rowsData as $idx => $row) {
                    $result[] = (object) [
                        'id' => $transaction->id,
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->transaction_date,
                        'sales_order_no' => $salesOrderInvoiceNo,
                        'invoice_no' => $invoiceNo,
                        'contact_name' => $transaction->contact->name ?? '',
                        'contact_mobile' => $transaction->contact->mobile ?? '',
                        'location_name' => $transaction->location->name ?? '',
                        'status' => $status,
                        'product_prize' => $row['product_prize'],
                        'quantity' => $row['quantity'],
                        'ring_receivable' => $row['ring_receivable'],
                        'ring_cash_receivable' => $row['ring_cash_receivable'],
                        'ring_received' => $row['ring_received'],
                        'ring_cash_received' => $row['ring_cash_received'],
                        'total_ring_cash_amount' => $row['total_ring_cash'],
                        'is_first_row' => ($idx === 0),
                        'product_count' => count($rowsData),
                        'is_sell_type' => $isSellType
                    ];
                }
            }

            return Datatables::of(collect($result))
                ->addColumn('action', function ($row) {
                    $invoice = $row->invoice_no;
                    $topUpUrl = route('customer-ring-balance.create', ['sales_reward_id' => $row->id, 'invoice_sell_no' => $invoice]);
                    
                    $editButton = '';
                    $deleteButton = '';
                    
                    if (!$row->is_sell_type) {
                        $editButton = '<li><a href="' . route('sales_reward.edit', $row->id) . '"><i class="fas fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a></li>';
                        $deleteButton = '<li><a href="#" class="delete-reward-exchange" data-href="'.route('sales_reward.destroy', $row->id).'" data-csrf="'.csrf_token().'"><i class="fa fa-trash"></i> '.__('messages.delete').'</a></li>';
                    }
                    
                    $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    <li><a href="' . $topUpUrl . '"><i class="fa fa-arrow-circle-up" aria-hidden="true"></i> '.__('Ring Top up').'</a></li>
                                    ' . $editButton . '
                                    ' . $deleteButton . '
                                </ul></div>';
                    return $html;
                })
                ->editColumn('date', function($row) { return date('d-m-Y H:i', strtotime($row->date)); })
                ->editColumn('status', function($row) { return $row->status; })
                ->setTotalRecords($totalRecords)
                ->setFilteredRecords($totalRecords) 
                ->skipPaging()
                ->rawColumns(['action'])
                ->make(true);
        }

        $business_id = $request->session()->get('user.business_id');
        $business_locations = BusinessLocation::where('business_id', $business_id)->pluck('name', 'id');
        $business_locations->prepend(__('lang_v1.all'), '');
        $contact = Contact::where('business_id', $business_id)->pluck('name', 'id');
        $contact->prepend(__('lang_v1.all'), '');

        return view('sales_reward.index', compact('business_locations', 'contact'));
    }

    public function show($id)
    {
        // Fetch the transaction with related details
        $transaction = Transaction::with(['contact', 'location', 'sell_lines.product'])->findOrFail($id);
    
        // Fetch related rewards based on the transaction's sell lines and product IDs
        $sellLines = $transaction->sell_lines;
    
        // Prepare product IDs for querying RewardsExchange
        $productIds = $sellLines->pluck('product_id')->toArray();
    
        // Query RewardsExchange based on the product IDs
        $rewards = RewardsExchange::whereIn('product_for_sale', $productIds)
            ->leftJoin('products as sale_products', 'rewards_exchange.product_for_sale', '=', 'sale_products.id')
            ->leftJoin('products as exchange_products', 'rewards_exchange.exchange_product', '=', 'exchange_products.id')
            ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
            ->leftJoin('variations as variation', 'rewards_exchange.product_for_sale', '=', 'variation.product_id')
            ->whereNull('rewards_exchange.deleted_at')
            ->select([
                'rewards_exchange.id',
                'sale_products.name as product_for_sale',
                'rewards_exchange.exchange_quantity',
                'rewards_exchange.amount',
                'exchange_products.name as exchange_product',
                'receive_products.name as receive_product',
                'rewards_exchange.receive_quantity',
                'rewards_exchange.product_for_sale as product_for_sale_id'
            ])
            ->get();
    
        // Match the rewards with sell lines to get the set quantity
        foreach ($rewards as $reward) {
            // Find the matching sell line for the reward based on the product_for_sale_id
            $sellLine = $sellLines->firstWhere('product_id', $reward->product_for_sale_id);
            if ($sellLine) {
                $reward->quantity = $sellLine->quantity;
                $reward->use_ring_balance = $sellLine->used_ring_balance;
            } else {
                $reward->quantity = 0; // Default to 0 if no match found
                $reward->use_ring_balance = 0;
            }
        }

        // Return the view with transaction details and rewards
        return view('sales_reward.show', compact('transaction', 'rewards'));
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
        $contact = Contact::where('business_id', $business_id)->pluck('name', 'id');
        $contact->prepend(__('lang_v1.all'), '');
        return view('sales_reward.create')
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

      public function store(Request $request)
    {
        // Retrieve data from the request
        $data = $request->all();
        $user_id = $request->session()->get('user.id');
        $business_id = $request->session()->get('user.business_id');
        $location_id = $data['select_location_id'];
        $contact_id = $data['sell_list_filter_contact_id'];
        $ref_sale_invoice = $data['sale_order'];
        $transaction_date = $data['transaction_date'] ?? now()->format('Y-m-d H:i:s');
        $status = $data['status'];
        $additional_notes = $data['note'];

        // Get last transaction for reward_no generation (type 'reward_exchange')
        $lastTransaction = Transaction::where('business_id', $business_id)
            ->where('type', 'reward_exchange')
            ->orderBy('reward_no_default', 'desc')
            ->first();

        // Generate reward_no_default (sequential number)
        if ($lastTransaction && $lastTransaction->reward_no_default) {
            $lastRewardNoDefault = $lastTransaction->reward_no_default;
            $reward_no_default = str_pad((intval($lastRewardNoDefault) + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $reward_no_default = '00001';
        }

        // Handle reward_no logic
        $inputInvoiceNo = $request->input('invoice_no');
        if (!empty($inputInvoiceNo) && $inputInvoiceNo !== null) {
            $reward_no = $inputInvoiceNo;
        } else {
            $reward_no = $reward_no_default;
        }

        // Calculate total_before_tax and final_total
        $total_before_tax = 0;
        $final_total = 0;

        foreach ($data['set_quantity'] as $index => $quantity) {
            $amount = $data['amount'][$index] ?? 0;
            $total_before_tax += $amount * $quantity;
            $final_total += $amount * $quantity;
        }

        // Create the Transaction record
        $transaction = Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'reward_exchange',
            'status' => $status,
            'contact_id' => $contact_id,
            'reward_no' => $reward_no,
            'reward_no_default' => $reward_no_default,
            'ref_sale_invoice' => $ref_sale_invoice,
            'transaction_date' => $transaction_date,
            'additional_notes' => $additional_notes,
            'total_before_tax' => $total_before_tax,
            'final_total' => $final_total,
            'created_by' => $user_id,
        ]);

        // Create SINGLE TransactionRingBalance for all items (not in loop)
        $transactionsRingBalance = null;
        if ($status === 'completed') {
            $transactionsRingBalance = TransactionRingBalance::create([
                'business_id' => $business_id,
                'location_id' => $location_id,
                'invoice_no' => $transaction->reward_no,
                'type' => 'reward_out',
                'status' => $status,
                'contact_id' => $contact_id,
                'transaction_id' => $transaction->id,
                'transaction_date' => $transaction_date,
                'created_by' => $user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Process TransactionSellLine
        foreach ($data['product_for_sale_id'] as $index => $productForSaleId) {
            $quantity = $data['set_quantity'][$index];
            $amount = $data['amount'][$index] ?? 0;
            $usedRingBalance = $data['stock_ring_balance'][$index] ?? 0;

            TransactionSellLine::create([
                'transaction_id' => $transaction->id,
                'product_id' => $productForSaleId,
                'variation_id' => $data['variation_id'][$index] ?? null,
                'quantity' => $data['set_quantity'][$index],
                'unit_price' => $data['amount'][$index],
                'used_ring_balance' => $usedRingBalance,
            ]);

            // Only process StockRewardsExchange and VariationLocationDetails if status is completed
            if ($status === 'completed' && $transactionsRingBalance) {
                // Get the exchange product for the current product for sale
                $rewardExchange = RewardsExchange::where('product_for_sale', $productForSaleId)
                    ->whereNull('deleted_at')
                    ->first();
                    
                if ($rewardExchange) {
                    $exchangeProductId = $rewardExchange->exchange_product;
                    $exchangeVariationId = Variation::where('product_id', $exchangeProductId)->pluck('id')->first();

                    // Save to transaction_sell_ring_balance
                    TransactionSellRingBalance::create([
                        'transactions_ring_balance_id' => $transactionsRingBalance->id,
                        'product_id' => $exchangeProductId,
                        'variation_id' => $exchangeVariationId ?? null,
                        'quantity' => $quantity * $rewardExchange->exchange_quantity,
                        'used_ring_balance' => $usedRingBalance,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Handle stock ring balance for the customer
                $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                    ->where('product_id', $rewardExchange->exchange_product)
                    ->where('contact_id', $contact_id)
                    ->where('business_id', $business_id)
                    ->first();

                if ($stockRingBalanceRecord) {
                    $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance - ($quantity * $rewardExchange->exchange_quantity);

                    DB::table('stock_ring_balance_customer')
                        ->where('product_id', $rewardExchange->exchange_product)
                        ->where('contact_id', $contact_id)
                        ->where('business_id', $business_id)
                        ->update(['stock_ring_balance' => $newStockRingBalance]);
                } else {
                    $newStockRingBalance = -($quantity * $rewardExchange->exchange_quantity);

                    StockRingBalanceCustomer::create([
                        'product_id' => $rewardExchange->exchange_product,
                        'contact_id' => $contact_id,
                        'business_id' => $business_id,
                        'stock_ring_balance' => $newStockRingBalance
                    ]);
                }

                // Get all rewards associated with this product_for_sale_id
                $rewardsExchanges = RewardsExchange::where('product_for_sale', $productForSaleId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($rewardsExchanges as $rewardExchange) {
                    $exchangeProductId = $rewardExchange->exchange_product;
                    $receiveProductId = $rewardExchange->receive_product;
                    $exchangeQuantity = $data['set_quantity'][$index] * $rewardExchange->exchange_quantity;
                    $receiveQuantity = $data['set_quantity'][$index] * $rewardExchange->receive_quantity;

                    // Get exchange variation
                    $exchangeVariation = Variation::where('product_id', $exchangeProductId)->first();
                    $receiveVariation = Variation::where('product_id', $receiveProductId)->first();

                    // Get last new_quantity for exchange product
                    // Track by: product_id + contact_id + type (customer balance - decreasing)
                    $lastExchangeRecord = StockRewardsExchange::where('product_id', $exchangeProductId)
                        ->where('contact_id', $contact_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    $currentExchangeBalance = $lastExchangeRecord ? $lastExchangeRecord->new_quantity : 0;
                    $newExchangeQuantity = $currentExchangeBalance - $exchangeQuantity;

                    // Create StockRewardsExchange for exchange_product (reward_exchange_out)
                    // Store positive quantity, calculate new_quantity with sign
                    StockRewardsExchange::create([
                        'transaction_id' => $transaction->id,
                        'type' => 'reward_exchange_out',
                        'contact_id' => $contact_id,
                        'product_id' => $exchangeProductId,
                        'variation_id' => $exchangeVariation ? $exchangeVariation->id : null,
                        'quantity' => $exchangeQuantity,
                        'new_quantity' => $newExchangeQuantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Store negative quantity, new_quantity = quantity (same value with sign)
                    StockRewardsExchange::create([
                        'transaction_id' => $transaction->id,
                        'type' => 'reward_exchange_in',
                        'contact_id' => null,
                        'product_id' => $receiveProductId,
                        'variation_id' => $receiveVariation ? $receiveVariation->id : null,
                        'quantity' => -$receiveQuantity,
                        'new_quantity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update VariationLocationDetails
                    $exchangeProductVariationId = $exchangeVariation ? $exchangeVariation->product_variation->id : null;
                    $receiveProductVariationId = $receiveVariation ? $receiveVariation->product_variation->id : null;

                    if ($exchangeProductVariationId && $receiveProductVariationId) {
                        // Update VariationLocationDetails for exchange product
                        $exchangeVariationLocationDetails = VariationLocationDetails::firstOrNew([
                            'variation_id' => $exchangeVariation->id,
                            'location_id' => $location_id
                        ]);

                        if ($exchangeVariationLocationDetails->exists) {
                            $exchangeVariationLocationDetails->qty_available = ($exchangeVariationLocationDetails->qty_available ?? 0) + $exchangeQuantity;
                        } else {
                            $exchangeVariationLocationDetails->product_id = $exchangeProductId;
                            $exchangeVariationLocationDetails->product_variation_id = $exchangeProductVariationId;
                            $exchangeVariationLocationDetails->qty_available = $exchangeQuantity;
                        }

                        $exchangeVariationLocationDetails->save();

                        // Update VariationLocationDetails for receive product
                        $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                            'variation_id' => $receiveVariation->id,
                            'location_id' => $location_id
                        ]);

                        if ($receiveVariationLocationDetails->exists) {
                            $receiveVariationLocationDetails->qty_available = ($receiveVariationLocationDetails->qty_available ?? 0) - $receiveQuantity;
                        } else {
                            $receiveVariationLocationDetails->product_id = $receiveProductId;
                            $receiveVariationLocationDetails->product_variation_id = $receiveProductVariationId;
                            $receiveVariationLocationDetails->qty_available = -$receiveQuantity;
                        }

                        $receiveVariationLocationDetails->save();
                    }
                }
            }
        }

        return redirect()->route('sales_reward.index')->with('success', 'Rewards exchange added successfully.');
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        // Store the original status to check if it changed
        $originalStatus = $transaction->status;
        $newStatus = $request->input('status');
        $newTransactionDate = $request->input('transaction_date');
        
        // If status is completed, only update transaction_date
        if ($originalStatus === 'completed') {
            $transaction->update([
                'transaction_date' => $newTransactionDate,
            ]);
            
            // Also update TransactionRingBalance transaction_date to match
            TransactionRingBalance::where('transaction_id', $transaction->id)
                ->update(['transaction_date' => $newTransactionDate]);
            
            return redirect()->route('sales_reward.index')->with('success', 'Transaction date updated successfully.');
        }

        // If status is pending, allow updates to status and transaction_date only
        if ($originalStatus === 'pending') {
            // Update only status and transaction_date
            $transaction->update([
                'status' => $newStatus,
                'transaction_date' => $newTransactionDate,
            ]);

            // If status changed from pending to completed, process the necessary operations
            if ($originalStatus === 'pending' && $newStatus === 'completed') {
                // Fetch necessary details
                $business_id = $transaction->business_id;
                $location_id = $transaction->location_id;
                $contact_id = $transaction->contact_id;
                $user_id = auth()->id();
                $transaction_date = $newTransactionDate;
                $reward_no = $transaction->reward_no;

                // Create TransactionRingBalance record ONCE (not in loop)
                $transactionsRingBalance = TransactionRingBalance::create([
                    'business_id' => $business_id,
                    'location_id' => $location_id,
                    'invoice_no' => $reward_no,
                    'type' => 'reward_out',
                    'status' => 'completed',
                    'contact_id' => $contact_id,
                    'transaction_id' => $transaction->id,
                    'transaction_date' => $transaction_date,
                    'created_by' => $user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Loop through sell lines
                foreach ($transaction->sell_lines as $sellLine) {
                    $productForSaleId = $sellLine->product_id;
                    $quantity = $sellLine->quantity;
                    $usedRingBalance = $sellLine->used_ring_balance;

                    // Get rewards associated with this product_for_sale_id
                    $rewardsExchanges = RewardsExchange::where('product_for_sale', $productForSaleId)
                        ->whereNull('deleted_at')
                        ->get();

                    foreach ($rewardsExchanges as $rewardExchange) {
                        $exchangeProductId = $rewardExchange->exchange_product;
                        $receiveProductId = $rewardExchange->receive_product;
                        $exchangeQuantity = $quantity * $rewardExchange->exchange_quantity;
                        $receiveQuantity = $quantity * $rewardExchange->receive_quantity;

                        // Get the exchange product variation
                        $exchangeVariationId = Variation::where('product_id', $exchangeProductId)->pluck('id')->first();

                        // Save TransactionSellRingBalance record
                        TransactionSellRingBalance::create([
                            'transactions_ring_balance_id' => $transactionsRingBalance->id,
                            'product_id' => $exchangeProductId,
                            'variation_id' => $exchangeVariationId ?? null,
                            'quantity' => $exchangeQuantity,
                            'used_ring_balance' => $usedRingBalance,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Get exchange variation
                        $exchangeVariation = Variation::where('product_id', $exchangeProductId)->first();
                        $receiveVariation = Variation::where('product_id', $receiveProductId)->first();

                        // Get last new_quantity for exchange product
                        // Track by: product_id + contact_id + type (customer balance - decreasing)
                        $lastExchangeRecord = StockRewardsExchange::where('product_id', $exchangeProductId)
                            ->where('contact_id', $contact_id)
                            ->orderBy('id', 'desc')
                            ->first();

                        $currentExchangeBalance = $lastExchangeRecord ? $lastExchangeRecord->new_quantity : 0;
                        $newExchangeQuantity = $currentExchangeBalance - $exchangeQuantity;

                        // Create StockRewardsExchange for exchange_product (reward_exchange_out)
                        // Store positive quantity, calculate new_quantity with sign
                        StockRewardsExchange::create([
                            'transaction_id' => $transaction->id,
                            'type' => 'reward_exchange_out',
                            'contact_id' => $contact_id,
                            'product_id' => $exchangeProductId,
                            'variation_id' => $exchangeVariation ? $exchangeVariation->id : null,
                            'quantity' => $exchangeQuantity,
                            'new_quantity' => $newExchangeQuantity,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Store negative quantity, new_quantity = quantity (same value with sign)
                        StockRewardsExchange::create([
                            'transaction_id' => $transaction->id,
                            'type' => 'reward_exchange_in',
                            'contact_id' => null,
                            'product_id' => $receiveProductId,
                            'variation_id' => $receiveVariation ? $receiveVariation->id : null,
                            'quantity' => -$receiveQuantity,
                            'new_quantity' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Update VariationLocationDetails for both exchange and receive products
                        if ($exchangeVariation && $receiveVariation) {
                            $exchangeVariationId = $exchangeVariation->id;
                            $receiveVariationId = $receiveVariation->id;

                            // Update VariationLocationDetails for exchange product
                            $exchangeVariationLocationDetails = VariationLocationDetails::firstOrNew([
                                'variation_id' => $exchangeVariationId,
                                'location_id' => $location_id
                            ]);

                            if ($exchangeVariationLocationDetails->exists) {
                                $exchangeVariationLocationDetails->qty_available = ($exchangeVariationLocationDetails->qty_available ?? 0) + $exchangeQuantity;
                            } else {
                                $exchangeProductVariationId = $exchangeVariation->product_variation ? $exchangeVariation->product_variation->id : null;
                                $exchangeVariationLocationDetails->product_id = $exchangeProductId;
                                $exchangeVariationLocationDetails->product_variation_id = $exchangeProductVariationId;
                                $exchangeVariationLocationDetails->qty_available = $exchangeQuantity;
                            }
                            $exchangeVariationLocationDetails->save();

                            // Update VariationLocationDetails for receive product
                            $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                                'variation_id' => $receiveVariationId,
                                'location_id' => $location_id
                            ]);

                            if ($receiveVariationLocationDetails->exists) {
                                $receiveVariationLocationDetails->qty_available = ($receiveVariationLocationDetails->qty_available ?? 0) - $receiveQuantity;
                            } else {
                                $receiveProductVariationId = $receiveVariation->product_variation ? $receiveVariation->product_variation->id : null;
                                $receiveVariationLocationDetails->product_id = $receiveProductId;
                                $receiveVariationLocationDetails->product_variation_id = $receiveProductVariationId;
                                $receiveVariationLocationDetails->qty_available = -$receiveQuantity;
                            }
                            $receiveVariationLocationDetails->save();
                        }

                        // Handle stock ring balance for the customer
                        $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                            ->where('product_id', $exchangeProductId)
                            ->where('contact_id', $contact_id)
                            ->where('business_id', $business_id)
                            ->first();

                        if ($stockRingBalanceRecord) {
                            $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance - $exchangeQuantity;

                            DB::table('stock_ring_balance_customer')
                                ->where('product_id', $exchangeProductId)
                                ->where('contact_id', $contact_id)
                                ->where('business_id', $business_id)
                                ->update(['stock_ring_balance' => $newStockRingBalance]);
                        } else {
                            $newStockRingBalance = -$exchangeQuantity;

                            StockRingBalanceCustomer::create([
                                'product_id' => $exchangeProductId,
                                'contact_id' => $contact_id,
                                'business_id' => $business_id,
                                'stock_ring_balance' => $newStockRingBalance
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->route('sales_reward.index')->with('success', 'Transaction updated successfully.');
    }

    public function destroy($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->user()->id;
            
            // Find the transaction (not deleted)
            $transaction = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->where('type', 'reward_exchange')
                ->whereNull('deleted_at')
                ->first();
                
            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found or already deleted.'], 404);
            }
            
            // Only reverse stock if the transaction was completed
            if ($transaction->status === 'completed') {
                // Find related TransactionRingBalance
                $transactionRingBalance = TransactionRingBalance::where('transaction_id', $transaction->id)
                    ->where('business_id', $business_id)
                    ->whereNull('deleted_at')
                    ->first();
                
                // Get all sell lines for this transaction
                $sellLines = TransactionSellLine::where('transaction_id', $transaction->id)->get();
                
                foreach ($sellLines as $sellLine) {
                    $productForSaleId = $sellLine->product_id;
                    $quantity = $sellLine->quantity;
                    $usedRingBalance = $sellLine->used_ring_balance;
                    
                    // Get all rewards associated with this product_for_sale_id
                    $rewardsExchanges = RewardsExchange::where('product_for_sale', $productForSaleId)
                        ->whereNull('deleted_at')
                        ->get();
                    
                    foreach ($rewardsExchanges as $rewardExchange) {
                        $exchangeProductId = $rewardExchange->exchange_product;
                        $receiveProductId = $rewardExchange->receive_product;
                        $exchangeQuantity = $quantity * $rewardExchange->exchange_quantity;
                        $receiveQuantity = $quantity * $rewardExchange->receive_quantity;
                        
                        // 1. Reverse stock_ring_balance_customer
                        $stockRingBalanceRecord = DB::table('stock_ring_balance_customer')
                            ->where('product_id', $exchangeProductId)
                            ->where('contact_id', $transaction->contact_id)
                            ->where('business_id', $business_id)
                            ->first();
                        
                        if ($stockRingBalanceRecord) {
                            // Add back the ring balance that was deducted
                            $newStockRingBalance = $stockRingBalanceRecord->stock_ring_balance + $exchangeQuantity;
                            
                            DB::table('stock_ring_balance_customer')
                                ->where('product_id', $exchangeProductId)
                                ->where('contact_id', $transaction->contact_id)
                                ->where('business_id', $business_id)
                                ->update(['stock_ring_balance' => $newStockRingBalance]);
                        }
                        
                        // 2. Reverse VariationLocationDetails for exchange product (subtract what was added)
                        $exchangeVariation = Variation::where('product_id', $exchangeProductId)->first();
                        if ($exchangeVariation) {
                            $exchangeVariationLocationDetails = VariationLocationDetails::where('variation_id', $exchangeVariation->id)
                                ->where('location_id', $transaction->location_id)
                                ->first();
                                
                            if ($exchangeVariationLocationDetails) {
                                $exchangeVariationLocationDetails->qty_available = ($exchangeVariationLocationDetails->qty_available ?? 0) - $exchangeQuantity;
                                $exchangeVariationLocationDetails->save();
                            }
                        }
                        
                        // 3. Reverse VariationLocationDetails for receive product (add back what was subtracted)
                        $receiveVariation = Variation::where('product_id', $receiveProductId)->first();
                        if ($receiveVariation) {
                            $receiveVariationLocationDetails = VariationLocationDetails::where('variation_id', $receiveVariation->id)
                                ->where('location_id', $transaction->location_id)
                                ->first();
                                
                            if ($receiveVariationLocationDetails) {
                                $receiveVariationLocationDetails->qty_available = ($receiveVariationLocationDetails->qty_available ?? 0) + $receiveQuantity;
                                $receiveVariationLocationDetails->save();
                            } else {
                                // Create new record if it doesn't exist
                                $receiveProductVariationId = $receiveVariation->product_variation ? $receiveVariation->product_variation->id : null;
                                if ($receiveProductVariationId) {
                                    VariationLocationDetails::create([
                                        'variation_id' => $receiveVariation->id,
                                        'location_id' => $transaction->location_id,
                                        'product_id' => $receiveProductId,
                                        'product_variation_id' => $receiveProductVariationId,
                                        'qty_available' => $receiveQuantity,
                                    ]);
                                }
                            }
                        }
                        
                        // 4. Create reversal StockRewardsExchange records for balance tracking
                        // Get the last new_quantity for exchange product to reverse it
                        $lastExchangeRecord = StockRewardsExchange::where('product_id', $exchangeProductId)
                            ->where('contact_id', $transaction->contact_id)
                            ->orderBy('id', 'desc')
                            ->first();
                        
                        if ($lastExchangeRecord) {
                            // Reversal: add back the quantity (opposite of deduction)
                            $reversalNewQuantity = $lastExchangeRecord->new_quantity + $exchangeQuantity;
                            
                            // ✅ REVERSAL FOR reward_exchange_out (customer side - has contact_id)
                            StockRewardsExchange::create([
                                'transaction_id' => $transaction->id,
                                'type' => 'delete_reward_exchange_out',
                                'contact_id' => $transaction->contact_id,
                                'product_id' => $exchangeProductId,
                                'variation_id' => $exchangeVariation ? $exchangeVariation->id : null,
                                'quantity' => -$exchangeQuantity,
                                'new_quantity' => $reversalNewQuantity,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        
                        // ✅ NEW ADDITION: REVERSAL FOR reward_exchange_in (shop side - contact_id IS NULL)
                        // When contact_id IS NULL, new_quantity MUST be 0 per SQL query rule
                        StockRewardsExchange::create([
                            'transaction_id' => $transaction->id,
                            'type' => 'delete_reward_exchange_in',
                            'contact_id' => null,  // ← Shop inventory has no contact
                            'product_id' => $receiveProductId,
                            'variation_id' => $receiveVariation ? $receiveVariation->id : null,
                            'quantity' => $receiveQuantity,  // ← Reverse the negative (positive value)
                            'new_quantity' => 0,  // ← MUST BE 0 when contact_id IS NULL per SQL rule
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                
                // 5. Soft delete TransactionRingBalance if exists
                if ($transactionRingBalance) {
                    $transactionRingBalance->update([
                        'deleted_by' => $user_id,
                        'deleted_at' => now()
                    ]);
                }
            }
            
            // 6. Soft delete the main Transaction (happens regardless of status)
            $transaction->update([
                'deleted_by' => $user_id,
                'deleted_at' => now()
            ]);
            
            // Different success messages based on status
            $message = $transaction->status === 'completed' 
                ? 'Reward exchange deleted successfully and stock reversed.' 
                : 'Pending reward exchange deleted successfully.';
            
            return response()->json([
                'success' => true, 
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting reward exchange transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error occurred while deleting transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        // Find transaction excluding soft deleted ones
        $transaction = Transaction::where('id', $id)
            ->whereNull('deleted_at') // Only get non-deleted transactions
            ->firstOrFail();
    
        // Fetch related data to populate the edit form (similar to create)
        $business_id = session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, false, true)['locations'];
        $contact = Contact::where('business_id', $business_id)->pluck('name', 'id');
        
        return view('sales_reward.edit')
            ->with(compact('transaction', 'business_locations', 'contact'));
    }

    public function checkTransaction(Request $request)
{
    // Retrieve the business ID from the session
    $business_id = $request->session()->get('user.business_id');

    // Validate the sale order
    $request->validate(['saleorder' => 'required|string']);

    // Get the sale order
    $saleorder = $request->saleorder;
    
    // Check only if the form_action is 'create'
    if ($request->context == 'create') {
        // Check if there's an existing reward transaction for the sale order
        $existingTransaction = Transaction::where('ref_sale_invoice', $saleorder)
            ->where('business_id', $request->session()->get('user.business_id'))
            ->whereNull('deleted_at') // Only check non-deleted transactions
            ->first();

        if ($existingTransaction) {
            return response()->json(['error' => 'This order is already existing with customer reward.'], 400);
        }

        // Check if there are deleted reward exchange transactions for this sale order
        $deletedTransaction = Transaction::where('ref_sale_invoice', $saleorder)
            ->where('business_id', $request->session()->get('user.business_id'))
            ->where('type', 'reward_exchange')
            ->whereNotNull('deleted_at') // Check for deleted transactions
            ->first();

        if ($deletedTransaction) {
            // If there's a deleted reward exchange, we can proceed to recreate it
            // Continue with the normal flow to allow recreation
        }
    }
    
    // Set different query parameters based on context
    if($request->context == 'create'){
        $invoice = 'invoice_no';
        $type = 'sell';
        $status = 'final';
    } else {
        // For edit context, we need to find the reward_exchange transaction
        $invoice = 'ref_sale_invoice';
        $type = 'reward_exchange';
        // For edit, we don't filter by status - we want to find the existing reward transaction
        $status = null; // Don't filter by status for edit
    }

    // Build the query - for edit context, we want to include deleted transactions
    $query = Transaction::where($invoice, $saleorder)
        ->where('business_id', $business_id)
        ->where('type', $type)
        ->with('contact'); // Eager load the contact relationship

    // Only add status filter if we have a status (for create context)
    if ($status !== null) {
        $query->where('status', $status);
    }

    // For edit context, don't filter by deleted_at to allow viewing deleted transactions
    if ($request->context == 'create') {
        $query->whereNull('deleted_at');
    }

    $transaction = $query->first();

    // Check if the transaction exists
    if (!$transaction) {
        return response()->json(['error' => 'Transaction not found.'], 404);
    }

    // Retrieve the associated sell_lines
    $sellLines = $transaction->sell_lines()->with('product')->get();

    // Check if sell_lines have data
    if ($sellLines->isEmpty()) {
        return response()->json(['error' => 'No products found in this transaction.'], 404);
    }

    // Mapping of product_id to quantity from sell lines
    $productToQuantity = $sellLines->mapWithKeys(function ($line) {
        return [$line->product_id => $line->quantity];
    });

    // Prepare product IDs for querying RewardsExchange
    $productIds = $sellLines->pluck('product_id')->toArray();

    // Query RewardsExchange with join to get product names and variations
    // Only get non-deleted rewards regardless of context
    $rewards = RewardsExchange::where('rewards_exchange.business_id', $business_id)
    ->whereIn('rewards_exchange.product_for_sale', $productIds)
    ->whereNull('rewards_exchange.deleted_at') // Only get non-deleted rewards
    ->leftJoin('products as sale_products', 'rewards_exchange.product_for_sale', '=', 'sale_products.id')
    ->leftJoin('products as exchange_products', 'rewards_exchange.exchange_product', '=', 'exchange_products.id')
    ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
    ->leftJoin('variations as variation', 'rewards_exchange.product_for_sale', '=', 'variation.product_id')
    ->leftJoin('stock_ring_balance_customer as stock', function ($join) use ($transaction) {
        $join->on('stock.product_id', '=', 'rewards_exchange.exchange_product')
            ->where('stock.contact_id', '=', $transaction->contact->id)
            ->where('stock.business_id', '=', $transaction->business_id);
    })
    ->select([
        'rewards_exchange.id',
        'sale_products.name as product_for_sale',
        'rewards_exchange.product_for_sale as product_for_sale_id',
        'exchange_products.name as exchange_product',
        'rewards_exchange.exchange_product as exchange_product_id',
        'rewards_exchange.exchange_quantity',
        'rewards_exchange.amount',
        'rewards_exchange.receive_product as receive_product_id',
        'rewards_exchange.receive_quantity',
        'variation.id as variation_id',
        'receive_products.name as receive_product',
        'stock.stock_ring_balance as stock_ring_balance',
    ])
    ->get()
    ->map(function ($reward) use ($productToQuantity) {
        // Add the set_quantity from the mapped product quantities
        $reward->set_quantity = $productToQuantity[$reward->product_for_sale_id] ?? 0;
        return $reward;
    });
        
    // Check if rewards are found
    if ($rewards->isEmpty()) {
        return response()->json(['error' => 'No product rewards found for these items.'], 404);
    }

    // Prepare set_quantity for the response
    $setQuantities = $rewards->pluck('set_quantity')->all(); // Collecting set_quantities

    $response = [
        'transaction' => $transaction,
        'sell_lines' => $sellLines,
        'rewards' => $rewards, // Include rewards with product names and IDs
        'set_quantity' => $setQuantities, // Assuming 'quantity' is the field for sell_lines
        'contact_name' => $transaction->contact->name ?? 'N/A',
        'contact_id' => $transaction->contact->id ?? null,
    ];

    return response()->json($response);
}
}
