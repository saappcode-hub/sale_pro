<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\CashRingBalance;
use App\StockCashRingBalanceProduct;
use App\TransactionSupplierCashRing;
use App\TransactionSupplierCashRingDetail;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SupplierCashRingBalanceController extends Controller
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
    
    /**
     * Get data for supplier cash ring balance table
     */
    private function getDataForTableSupplierCashRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->get('location_id');
        $contact_id = $request->get('contact_id');
        $status = $request->get('status');
        $date_range = $request->get('date_range');
        
        $query = TransactionSupplierCashRing::query()
            ->join('contacts', 'transactions_supplier_cash_ring.supplier_id', '=', 'contacts.id')
            ->join('users', 'transactions_supplier_cash_ring.created_by', '=', 'users.id')
            ->leftJoin('business_locations', 'transactions_supplier_cash_ring.location_id', '=', 'business_locations.id')
            ->where('transactions_supplier_cash_ring.business_id', $business_id)
            ->where('contacts.type', 'supplier');

        // Filter by location
        if ($location_id && $location_id != '' && $location_id != 'all') {
            $query->where('transactions_supplier_cash_ring.location_id', $location_id);
        }

        // Filter by contact/supplier
        if ($contact_id && $contact_id != '' && $contact_id != 'all') {
            $query->where('transactions_supplier_cash_ring.supplier_id', $contact_id);
        }

        // Filter by status
        if ($status && $status != '') {
            $query->where('transactions_supplier_cash_ring.status', $status);
        }

        // Filter by date range
        if ($date_range && $date_range != '') {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $start_date = $dates[0] . ' 00:00:00';
                $end_date = $dates[1] . ' 23:59:59';
                $query->whereBetween('transactions_supplier_cash_ring.transaction_date', [$start_date, $end_date]);
            }
        }
        
        return $query->select([
                'transactions_supplier_cash_ring.id',
                'transactions_supplier_cash_ring.transaction_date',
                'transactions_supplier_cash_ring.invoice_no',
                'transactions_supplier_cash_ring.default_invoice',
                'transactions_supplier_cash_ring.status',
                'transactions_supplier_cash_ring.total_amount_riel',
                'transactions_supplier_cash_ring.total_amount_dollar',
                'contacts.name as supplier_name',
                'contacts.mobile as supplier_mobile',
                'business_locations.name as location_name',
                'users.first_name as added_by'
            ])
            ->orderBy('transactions_supplier_cash_ring.transaction_date', 'desc')
            ->get();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->getDataForTableSupplierCashRing($request);

            return Datatables::of($data)#
                ->addColumn('action', function ($row) {
                    $view = '<li><a href="#" data-href="'.action([\App\Http\Controllers\SupplierCashRingBalanceController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    
                    $editButton = '';
                    $updateStatusButton = '';
                    // Allow edit ONLY for pending and send status, NOT for claim
                    if ($row->status === 'pending' || $row->status === 'send') {
                        $editButton = '<li><a href="' . route('supplier-cash-ring-balance.edit', $row->id) . '"><i class="fas fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a></li>';
                        $updateStatusButton = '<li><a href="#" data-href="'.action([\App\Http\Controllers\SupplierCashRingBalanceController::class, 'showUpdateStatus'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-sync" aria-hidden="true"></i> Update Status</a></li>';
                    }

                    $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    ' . $view . '
                                    ' . $editButton . '
                                    ' . $updateStatusButton . '
                                </ul></div>';
                    
                    return $html;
                })
                ->addColumn('date', function($row) {
                    return date('d-m-Y H:i', strtotime($row->transaction_date));
                })
                ->addColumn('reference_no', function($row) {
                    return $row->invoice_no ?: $row->default_invoice;
                })
                ->addColumn('supplier_name', function($row) {
                    return $row->supplier_name;
                })
                ->addColumn('supplier_mobile', function($row) {
                    return $row->supplier_mobile ?: '-';
                })
                ->addColumn('location_name', function($row) {
                    return $row->location_name ?: '-';
                })
                ->addColumn('total_amount_riel', function($row) {
                    return number_format($row->total_amount_riel, 0);
                })
                ->addColumn('total_amount_dollar', function($row) {
                    return number_format($row->total_amount_dollar, 2);
                })
                ->addColumn('status', function($row) {
                    $statusClass = '';
                    switch($row->status) {
                        case 'pending':
                            $statusClass = 'status-pending'; // Orange
                            break;
                        case 'send':
                            $statusClass = 'status-send'; // Green  
                            break;
                        case 'claim':
                            $statusClass = 'status-claim'; // Blue
                            break;
                        default:
                            $statusClass = 'status-pending';
                    }
                    return '<span class="' . $statusClass . '">' . ucfirst($row->status) . '</span>';
                })
                ->addColumn('added_by', function($row) {
                    return $row->added_by;
                })
                ->rawColumns(['action', 'status'])
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
            ->where('type', 'supplier')
            ->select('name', 'contact_id', 'mobile', 'id')
            ->get();

        $contact = $contacts->mapWithKeys(function ($item) {
            $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
            return [$item->id => $displayText];
        })->prepend(__('lang_v1.all'), '');

        return view('supplier_cash_ring_balance.index', compact('business_locations', 'contact'));
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
    
        // Validate if the contact_id exists and belongs to the business
        if ($contact_id && !Contact::where('id', $contact_id)->where('business_id', $business_id)->exists()) {
            abort(404, __('Contact not found or does not belong to this business.'));
        }
    
        // Contacts with custom formatting
        $contacts = Contact::where('business_id', $business_id)
        ->where('type', 'supplier')
        ->select('name', 'contact_id', 'mobile', 'id')
        ->get();

        $contacts = $contacts->mapWithKeys(function ($item) {
            $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
            return [$item->id => $displayText];
        })->prepend(__('lang_v1.all'), '');
    
        return view('supplier_cash_ring_balance.create', compact('business_locations', 'contacts', 'contact_id'));
    }

    /**
     * Store function following exact requirements
     */
    public function store(Request $request)
{
    \Log::info('Store request data:', $request->all());
    
    try {
        $request->validate([
            'select_location_id' => 'required',
            'sell_list_filter_contact_id' => 'required',
            'transaction_date' => 'required',
            'status' => 'required|in:pending,send'
        ]);

        if (!$request->has('products') || empty($request->products)) {
            return redirect()->back()
                ->withInput()
                ->with('status', [
                    'success' => 0,
                    'msg' => 'No products found in request. Please add at least one product.'
                ]);
        }

        \Log::info('Products data:', $request->products);

        DB::beginTransaction();

        $business_id = session()->get('user.business_id');
        $created_by = session()->get('user.id');

        $default_invoice = $this->generateDefaultInvoice($business_id);
        $invoice_no = (!empty($request->invoice_no)) ? $request->invoice_no : $default_invoice;

        $totals = $this->calculateTotals($request->products);

        \Log::info('Calculated totals:', $totals);

        $transaction = TransactionSupplierCashRing::create([
            'business_id' => $business_id,
            'location_id' => $request->select_location_id,
            'invoice_no' => $invoice_no,
            'default_invoice' => $default_invoice,
            'type' => 'supplier_cash_ring',
            'status' => $request->status,
            'supplier_id' => $request->sell_list_filter_contact_id,
            'transaction_date' => $request->transaction_date,
            'total_amount_riel' => $totals['riel'],
            'total_amount_dollar' => $totals['dollar'],
            'note' => $request->note,
            'created_by' => $created_by,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        \Log::info('Transaction created with ID: ' . $transaction->id);

        foreach ($request->products as $uniqueId => $productData) {
            \Log::info("Processing product uniqueId: {$uniqueId}", $productData);
            
            $product_id = $this->getProductId($uniqueId, $productData);
            \Log::info("Product ID resolved: {$product_id}");
            
            if (isset($productData['cash_quantities'])) {
                foreach ($productData['cash_quantities'] as $cash_ring_balance_id => $quantity) {
                    if ($quantity > 0) {
                        \Log::info("Creating detail: product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}, quantity={$quantity}");
                        
                        TransactionSupplierCashRingDetail::create([
                            'transactions_supplier_cash_ring_id' => $transaction->id,
                            'product_id' => $product_id,
                            'cash_ring_balance_id' => $cash_ring_balance_id,
                            'quantity' => $quantity,
                            'transaction_date' => $request->transaction_date
                        ]);

                        // Update stock when status = 'send'
                        if ($request->status === 'send') {
                            \Log::info("Updating stock for product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}");
                            $this->updateOrCreateStockCashRingBalance(
                                $product_id,
                                $request->select_location_id,
                                $business_id,
                                $cash_ring_balance_id,
                                $quantity,
                                'decrease'
                            );
                        }
                    }
                }
            } else {
                \Log::warning("No cash_quantities found for product: {$uniqueId}");
            }
        }

        DB::commit();
        \Log::info('Transaction committed successfully');

        return redirect()->route('supplier-cash-ring-balance.index')
            ->with('status', [
                'success' => 1,
                'msg' => __('Supplier cash ring transaction saved successfully!')
            ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Store function error: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return redirect()->back()
            ->withInput()
            ->with('status', [
                'success' => 0,
                'msg' => 'Error saving transaction: ' . $e->getMessage()
            ]);
    }
}

    /**
     * Generate default_invoice auto from 00001
     */
    private function generateDefaultInvoice($business_id)
    {
        $lastTransaction = TransactionSupplierCashRing::where('business_id', $business_id)
            ->whereNotNull('default_invoice')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTransaction && $lastTransaction->default_invoice) {
            $lastNumber = (int) $lastTransaction->default_invoice;
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get product_id with fallback options
     */
    private function getProductId($uniqueId, $productData)
    {
        // First check if product_id is directly available
        if (isset($productData['product_id'])) {
            return $productData['product_id'];
        }

        // Extract from uniqueId if it contains product_id (format: "123_cash")
        if (strpos($uniqueId, '_cash') !== false) {
            $productId = str_replace('_cash', '', $uniqueId);
            if (is_numeric($productId)) {
                return (int) $productId;
            }
        }

        // If uniqueId is numeric, use it directly
        if (is_numeric($uniqueId)) {
            return (int) $uniqueId;
        }

        // Last resort: get from cash_ring_balance
        if (isset($productData['cash_quantities'])) {
            $cash_ring_balance_id = array_key_first($productData['cash_quantities']);
            $cashRingBalance = CashRingBalance::find($cash_ring_balance_id);
            if ($cashRingBalance) {
                return $cashRingBalance->product_id;
            }
        }

        throw new \Exception("Could not determine product_id for uniqueId: " . $uniqueId);
    }

    /**
     * Update stock_cash_ring_balance_product when status = send
     * Calculate update cash_ring_balance_id of StockCashRingBalanceProduct
     * Based on product location_id, business_id and product_id
     */
    private function updateStockCashRingBalance($product_id, $location_id, $business_id, $cash_ring_balance_id, $quantity)
    {
        // Update stock directly using where conditions (no primary key id)
        // Table uses composite primary key: (product_id, business_id, location_id, cash_ring_balance_id)
        
        $updated = DB::table('stock_cash_ring_balance_product')
            ->where('product_id', $product_id)
            ->where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('cash_ring_balance_id', $cash_ring_balance_id)
            ->update([
                'stock_cash_ring_balance' => DB::raw('GREATEST(stock_cash_ring_balance - ' . (int)$quantity . ', 0)')
            ]);

        if ($updated) {
            \Log::info("Stock updated successfully for product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}, reduced by {$quantity}");
        } else {
            \Log::warning("No stock record found to update for product_id={$product_id}, location_id={$location_id}, business_id={$business_id}, cash_ring_balance_id={$cash_ring_balance_id}");
        }
    }

    /**
     * Calculate total_amount_dollar and total_amount_riel from create.blade
     */
    private function calculateTotals($products)
    {
        $totalDollar = 0;
        $totalRiel = 0;

        foreach ($products as $productData) {
            if (isset($productData['cash_quantities'])) {
                foreach ($productData['cash_quantities'] as $cash_ring_balance_id => $quantity) {
                    if ($quantity > 0) {
                        $cashRingBalance = CashRingBalance::find($cash_ring_balance_id);
                        
                        if ($cashRingBalance) {
                            $totalValue = $quantity * $cashRingBalance->redemption_value;
                            
                            if ($cashRingBalance->type_currency == 1) { // Dollar
                                $totalDollar += $totalValue;
                            } elseif ($cashRingBalance->type_currency == 2) { // Riel
                                $totalRiel += $totalValue;
                            }
                        }
                    }
                }
            }
        }

        return [
            'dollar' => $totalDollar,
            'riel' => $totalRiel
        ];
    }

    /**
     * Search products for supplier cash ring exchange (Cash Ring Only)
     */
    public function searchProduct(Request $request)
    {
        $business_id = session()->get('user.business_id');
        $location_id = $request->input('location_id');
        $query = $request->input('query');
        $transaction_id = $request->input('transaction_id'); // For edit mode
    
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
            // Check if product exists in cash_ring_balance and get all records
            $cashRingBalances = CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $product->id)
                ->get(['id', 'unit_value', 'type_currency', 'redemption_value']);
        
            // Add Cash Ring products only
            if ($cashRingBalances->isNotEmpty()) {
                $groupedCashRings = [
                    'dollar' => [],
                    'riel' => []
                ];
            
                foreach ($cashRingBalances as $cashRingBalance) {
                    $currencySymbol = $cashRingBalance->type_currency == 1 ? '$' : '៛';
                    $currencyType = $cashRingBalance->type_currency == 1 ? 'dollar' : 'riel';
                    
                    // Get actual stock from StockCashRingBalanceProduct
                    $actualStock = 0;
                    if ($location_id) {
                        $stockRecord = StockCashRingBalanceProduct::where('product_id', $product->id)
                            ->where('business_id', $business_id)
                            ->where('location_id', $location_id)
                            ->where('cash_ring_balance_id', $cashRingBalance->id)
                            ->first();
                        
                        $actualStock = $stockRecord ? ($stockRecord->stock_cash_ring_balance ?? 0) : 0;
                    }
                    
                    // For edit mode, calculate available stock (actual + current transaction qty)
                    $availableStock = $actualStock;
                    if ($transaction_id) {
                        $currentTransactionQty = TransactionSupplierCashRingDetail::where('transactions_supplier_cash_ring_id', $transaction_id)
                            ->where('product_id', $product->id)
                            ->where('cash_ring_balance_id', $cashRingBalance->id)
                            ->first();
                        
                        if ($currentTransactionQty) {
                            $availableStock = $actualStock + $currentTransactionQty->quantity;
                        }
                    }
                    
                    $groupedCashRings[$currencyType][] = [
                        'id' => $cashRingBalance->id,
                        'unit_value' => $cashRingBalance->unit_value,
                        'currency_symbol' => $currencySymbol,
                        'redemption_value' => $cashRingBalance->redemption_value,
                        'stock_cash_ring_balance' => $availableStock,
                        'actual_stock' => $actualStock // Add actual stock for reference
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

    public function edit($id)
{
    $business_id = session()->get('user.business_id');
    
    $transaction = TransactionSupplierCashRing::where('id', $id)
        ->where('business_id', $business_id)
        ->firstOrFail();
    
    // Allow editing if status is pending or send (NOT claim)
    if ($transaction->status !== 'pending' && $transaction->status !== 'send') {
        return redirect()->route('supplier-cash-ring-balance.index')
            ->with('status', [
                'success' => 0,
                'msg' => 'Only pending or send transactions can be edited.'
            ]);
    }
    
    $business_locations = BusinessLocation::where('business_id', $business_id)
        ->where('is_active', 1)
        ->pluck('name', 'id');
    
    $contacts = Contact::where('business_id', $business_id)
        ->where('type', 'supplier')
        ->select('name', 'contact_id', 'mobile', 'id')
        ->get();

    $contacts = $contacts->mapWithKeys(function ($item) {
        $displayText = $item->name . ' (' . $item->contact_id . ')<br>Mobile: ' . ($item->mobile ?? '');
        return [$item->id => $displayText];
    });
    
    $transactionDetails = TransactionSupplierCashRingDetail::where('transactions_supplier_cash_ring_id', $id)->get();
    
    foreach ($transactionDetails as $detail) {
        $detail->product = Product::find($detail->product_id);
        $detail->cash_ring_balance = CashRingBalance::find($detail->cash_ring_balance_id);
    }
    
    $stockData = [];
    foreach ($transactionDetails as $detail) {
        $stockRecord = StockCashRingBalanceProduct::where('product_id', $detail->product_id)
            ->where('business_id', $business_id)
            ->where('location_id', $transaction->location_id)
            ->where('cash_ring_balance_id', $detail->cash_ring_balance_id)
            ->first();
        
        $stockKey = $detail->product_id . '_' . $detail->cash_ring_balance_id;
        $actualStock = $stockRecord ? $stockRecord->stock_cash_ring_balance : 0;
        
        $currentTransactionQty = $detail->quantity ?? 0;
        if ($currentTransactionQty > $actualStock) {
            $stockData[$stockKey] = $currentTransactionQty;
        } else {
            $stockData[$stockKey] = $actualStock;
        }
    }
    
    return view('supplier_cash_ring_balance.edit', compact(
        'transaction', 
        'business_locations', 
        'contacts', 
        'transactionDetails',
        'stockData'
    ));
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    \Log::info('Update request data:', $request->all());
    
    try {
        $business_id = session()->get('user.business_id');
        
        $transaction = TransactionSupplierCashRing::where('id', $id)
            ->where('business_id', $business_id)
            ->firstOrFail();
        
        // Allow updating if status is pending or send (NOT claim)
        if ($transaction->status !== 'pending' && $transaction->status !== 'send') {
            return redirect()->route('supplier-cash-ring-balance.index')
                ->with('status', [
                    'success' => 0,
                    'msg' => 'Only pending or send transactions can be updated.'
                ]);
        }
        
        $request->validate([
            'select_location_id' => 'required',
            'sell_list_filter_contact_id' => 'required',
            'transaction_date' => 'required',
            'status' => 'required|in:pending,send'
        ]);

        if (!$request->has('products') || empty($request->products)) {
            return redirect()->back()
                ->withInput()
                ->with('status', [
                    'success' => 0,
                    'msg' => 'No products found in request. Please add at least one product.'
                ]);
        }

        DB::beginTransaction();

        // Get old transaction details for stock reversal
        $oldDetails = TransactionSupplierCashRingDetail::where('transactions_supplier_cash_ring_id', $id)->get();

        // Reverse stock for old details if transaction was in 'send' status
        if ($transaction->status === 'send') {
            foreach ($oldDetails as $detail) {
                \Log::info("Reversing stock for product_id={$detail->product_id}, cash_ring_balance_id={$detail->cash_ring_balance_id}, quantity={$detail->quantity}");
                $this->updateOrCreateStockCashRingBalance(
                    $detail->product_id,
                    $transaction->location_id,
                    $business_id,
                    $detail->cash_ring_balance_id,
                    $detail->quantity,
                    'increase'
                );
            }
        }

        $totals = $this->calculateTotals($request->products);
        
        // Delete existing details
        TransactionSupplierCashRingDetail::where('transactions_supplier_cash_ring_id', $id)->delete();
        
        // Process new products
        foreach ($request->products as $uniqueId => $productData) {
            \Log::info("Processing product uniqueId: {$uniqueId}", $productData);
            
            $product_id = $this->getProductId($uniqueId, $productData);
            \Log::info("Product ID resolved: {$product_id}");
            
            if (isset($productData['cash_quantities'])) {
                foreach ($productData['cash_quantities'] as $cash_ring_balance_id => $quantity) {
                    if ($quantity > 0) {
                        \Log::info("Creating detail: product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}, quantity={$quantity}");
                        
                        TransactionSupplierCashRingDetail::create([
                            'transactions_supplier_cash_ring_id' => $transaction->id,
                            'product_id' => $product_id,
                            'cash_ring_balance_id' => $cash_ring_balance_id,
                            'quantity' => $quantity,
                            'transaction_date' => $request->transaction_date
                        ]);
                        
                        // Update stock if new status is 'send'
                        if ($request->status === 'send') {
                            \Log::info("Updating stock: deducting {$quantity} from stock for product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}");
                            $this->updateOrCreateStockCashRingBalance(
                                $product_id,
                                $request->select_location_id,
                                $business_id,
                                $cash_ring_balance_id,
                                $quantity,
                                'decrease'
                            );
                        }
                    }
                }
            }
        }
        
        // Update main transaction
        $transaction->update([
            'location_id' => $request->select_location_id,
            'invoice_no' => $request->invoice_no ?: $transaction->default_invoice,
            'status' => $request->status,
            'supplier_id' => $request->sell_list_filter_contact_id,
            'transaction_date' => $request->transaction_date,
            'total_amount_riel' => $totals['riel'],
            'total_amount_dollar' => $totals['dollar'],
            'note' => $request->note,
            'updated_at' => now()
        ]);

        DB::commit();
        \Log::info('Transaction updated successfully');

        return redirect()->route('supplier-cash-ring-balance.index')
            ->with('status', [
                'success' => 1,
                'msg' => __('Supplier cash ring transaction updated successfully!')
            ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Update function error: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return redirect()->back()
            ->withInput()
            ->with('status', [
                'success' => 0,
                'msg' => 'Error updating transaction: ' . $e->getMessage()
            ]);
    }
}

    private function updateOrCreateStockCashRingBalance($product_id, $location_id, $business_id, $cash_ring_balance_id, $quantity, $action = 'decrease')
    {
        // Check if record exists with composite key
        $record = DB::table('stock_cash_ring_balance_product')
            ->where('product_id', $product_id)
            ->where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('cash_ring_balance_id', $cash_ring_balance_id)
            ->first();

        // Calculate new stock
        if ($action === 'decrease') {
            $newStock = ($record ? $record->stock_cash_ring_balance : 0) - $quantity;
            $newStock = max(0, $newStock); // Prevent negative stock
        } else { // increase
            $newStock = ($record ? $record->stock_cash_ring_balance : 0) + $quantity;
        }

        if ($record) {
            // UPDATE existing record
            DB::table('stock_cash_ring_balance_product')
                ->where('product_id', $product_id)
                ->where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->where('cash_ring_balance_id', $cash_ring_balance_id)
                ->update([
                    'stock_cash_ring_balance' => $newStock
                ]);
            \Log::info("Stock updated for product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}, new stock={$newStock}");
        } else {
            // INSERT new record if not exists
            DB::table('stock_cash_ring_balance_product')->insert([
                'product_id' => $product_id,
                'business_id' => $business_id,
                'location_id' => $location_id,
                'cash_ring_balance_id' => $cash_ring_balance_id,
                'stock_cash_ring_balance' => $newStock
            ]);
            \Log::info("Stock created for product_id={$product_id}, cash_ring_balance_id={$cash_ring_balance_id}, stock={$newStock}");
        }
    }

    
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $business_id = session()->get('user.business_id');

            // Query with manual joins - same approach as your index method
            $transaction = DB::table('transactions_supplier_cash_ring as tscr')
                ->leftJoin('contacts as c', function($join) {
                    $join->on('tscr.supplier_id', '=', 'c.id')
                        ->where('c.type', '=', 'supplier');
                })
                ->leftJoin('business_locations as bl', 'tscr.location_id', '=', 'bl.id')
                ->select(
                    'tscr.*',
                    'c.name as supplier_name',
                    'c.mobile as supplier_mobile', 
                    'c.contact_id as supplier_contact_id',
                    'bl.name as location_name'
                )
                ->where('tscr.business_id', $business_id)
                ->where('tscr.id', $id)
                ->first();

            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            // Get transaction details with manual joins
            $transactionDetails = DB::table('transactions_supplier_cash_ring_detail as tscrd')
                ->leftJoin('products as p', 'tscrd.product_id', '=', 'p.id')
                ->leftJoin('cash_ring_balance as crb', 'tscrd.cash_ring_balance_id', '=', 'crb.id')
                ->select(
                    'tscrd.*',
                    'p.name as product_name',
                    'p.sku as product_sku',
                    'crb.unit_value',
                    'crb.redemption_value',
                    'crb.type_currency'
                )
                ->where('tscrd.transactions_supplier_cash_ring_id', $id)
                ->get();

            \Log::info("Show transaction details:", [
                'transaction_id' => $id,
                'details_count' => $transactionDetails->count(),
                'supplier_name' => $transaction->supplier_name ?? 'No supplier'
            ]);

            return view('supplier_cash_ring_balance.show', compact('transaction', 'transactionDetails'));
            
        } catch (\Exception $e) {
            \Log::error('Show function error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading transaction details: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('supplier-cash-ring-balance.index')
                ->with('status', [
                    'success' => 0,
                    'msg' => 'Error loading transaction details: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Show update status form
     */
    public function showUpdateStatus($id)
    {
        $business_id = session()->get('user.business_id');
        
        $transaction = TransactionSupplierCashRing::where('id', $id)
            ->where('business_id', $business_id)
            ->firstOrFail();
        
        // Define allowed status transitions
        $allowedStatuses = [];
        switch($transaction->status) {
            case 'pending':
                $allowedStatuses = ['send', 'claim'];
                break;
            case 'send':
                $allowedStatuses = ['claim'];
                break;
            case 'claim':
                $allowedStatuses = []; // Cannot change from claim
                break;
        }
        
        return view('supplier_cash_ring_balance.update_status', compact('transaction', 'allowedStatuses'));
    }

    /**
     * Update transaction status
     */
    public function updateStatus(Request $request, $id)
    {
        \Log::info('Update Status request:', ['id' => $id, 'status' => $request->status]);
        
        try {
            $business_id = session()->get('user.business_id');
            
            $transaction = TransactionSupplierCashRing::where('id', $id)
                ->where('business_id', $business_id)
                ->firstOrFail();
            
            $request->validate([
                'status' => 'required|in:pending,send,claim'
            ]);
            
            $currentStatus = $transaction->status;
            $newStatus = $request->status;
            
            $this->validateStatusTransition($currentStatus, $newStatus);
            
            DB::beginTransaction();
            
            // Update stock only for specific transitions (pending -> send or pending -> claim)
            if ($this->shouldUpdateStock($currentStatus, $newStatus)) {
                $this->updateStockForStatusChange($transaction, $newStatus);
            }
            
            // Update transaction status
            $transaction->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);
            
            DB::commit();
            \Log::info("Status updated from {$currentStatus} to {$newStatus} for transaction {$id}");
            
            return response()->json([
                'success' => true,
                'message' => "Status updated successfully from {$currentStatus} to {$newStatus}!"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Update status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate status transition rules
     */
    private function validateStatusTransition($currentStatus, $newStatus)
    {
        $allowedTransitions = [
            'pending' => ['send', 'claim'],
            'send' => ['claim'],
            'claim' => [] // Cannot change from claim
        ];
        
        if (!isset($allowedTransitions[$currentStatus])) {
            throw new \Exception("Invalid current status: {$currentStatus}");
        }
        
        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new \Exception("Cannot update status from {$currentStatus} to {$newStatus}");
        }
    }

    /**
     * Check if stock should be updated for this status transition
     */
    private function shouldUpdateStock($currentStatus, $newStatus)
    {
        return $currentStatus === 'pending' && in_array($newStatus, ['send', 'claim']);
    }

    /**
     * Update stock for status change
     */
    private function updateStockForStatusChange($transaction, $newStatus)
    {
        $transactionDetails = TransactionSupplierCashRingDetail::where('transactions_supplier_cash_ring_id', $transaction->id)->get();
        
        foreach ($transactionDetails as $detail) {
            \Log::info("Updating stock for status change: product_id={$detail->product_id}, cash_ring_balance_id={$detail->cash_ring_balance_id}, quantity={$detail->quantity}");
            
            $this->updateOrCreateStockCashRingBalance(
                $detail->product_id,
                $transaction->location_id,
                $transaction->business_id,
                $detail->cash_ring_balance_id,
                $detail->quantity,
                'decrease'
            );
        }
    }
}