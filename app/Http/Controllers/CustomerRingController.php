<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\RewardsExchange;
use App\StockRingBalanceCustomer;
use App\TransactionRingBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CustomerRingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Modify the query to accept filter parameters for business_location_id and contact_id
    private function getDataForTableCustomerRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        
        $query = DB::table('contacts')
            ->leftJoin('stock_ring_balance_customer', 'contacts.id', '=', 'stock_ring_balance_customer.contact_id')
            ->leftJoin('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->leftJoin('business', 'contacts.business_id', '=', 'business.id')
            ->leftJoin('business_locations', 'business.id', '=', 'business_locations.business_id')
            ->leftJoin('stock_cash_ring_balance_customer', function($join) use ($business_id) {
                $join->on('contacts.id', '=', 'stock_cash_ring_balance_customer.contact_id')
                    ->where('stock_cash_ring_balance_customer.business_id', '=', $business_id);
            })
            ->where('contacts.type', 'customer')
            ->where('contacts.business_id', $business_id)
            ->whereNull('contacts.deleted_at') // FIX: exclude soft-deleted contacts
            ->select(
                'contacts.id as contact_id',
                'contacts.name as contact_name',
                'business_locations.name as business_name',
                'contacts.mobile as contact_mobile',
                'products.id as product_id',
                'products.name as product_name',
                'units.short_name as unit_name',
                'stock_ring_balance_customer.stock_ring_balance',
                'stock_cash_ring_balance_customer.total_cuurency_dollar'
            );

        if ($business_location_id = $request->input('business_location_id')) {
            $query->where('business_locations.id', $business_location_id);
        }
        
        if ($contact_id = $request->input('contact_id')) {
            $query->where('contacts.id', $contact_id);
        }

        return $query->get();
    }

    // Display the list of customer rings
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->getDataForTableCustomerRing($request);

            $summedData = $data->groupBy('contact_id')->map(function ($items) {
                $firstItem = $items->first();
                $firstItem->total_ring_balance = $items->unique('product_id')->sum('stock_ring_balance');
                $firstItem->total_cash_ring_balance = $firstItem->total_cuurency_dollar ?? 0;
                return $firstItem;
            })->values();

            return DataTables::of($summedData)
                ->addColumn('action', function ($row) {
                    $view = '<li><a href="' . route('customer-ring.show', $row->contact_id) . '">
                                <i class="fas fa-eye" aria-hidden="true"></i> ' . __('messages.view') . '
                             </a></li>';

                    // ── Adjust Stock button ──────────────────────────────────
                    $adjustStock = '<li>
                        <a href="#" class="btn-adjust-stock"
                            data-contact-id="' . $row->contact_id . '"
                            data-contact-name="' . e($row->contact_name) . '">
                            <i class="fas fa-sliders-h" aria-hidden="true"></i> Adjust Stock
                        </a>
                    </li>';

                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs"
                                    data-toggle="dropdown" aria-expanded="false">' .
                                    __('messages.actions') .
                                    '<span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">'
                                    . $view
                                    . $adjustStock .
                                '</ul>
                            </div>';
                })
                ->addColumn('contact_id', fn($row) => $row->contact_id)
                ->addColumn('business_name', fn($row) => $row->business_name)
                ->addColumn('contact_name', fn($row) => $row->contact_name)
                ->addColumn('contact_mobile', fn($row) => $row->contact_mobile)
                ->addColumn('total_ring_balance', fn($row) =>
                    number_format($row->total_ring_balance, 0) . ' ' . $row->unit_name
                )
                ->addColumn('total_cash_ring_balance', fn($row) => $row->total_cash_ring_balance)
                ->rawColumns(['action'])
                ->make(true);
        }

        $business_id = $request->session()->get('user.business_id');

        $business_locations = BusinessLocation::where('business_id', $business_id)
            ->where('is_active', 1)
            ->pluck('name', 'id');
        $business_locations->prepend(__('lang_v1.all'), '');

        $contact = Contact::where('business_id', $business_id)->where('type', 'customer')->pluck('name', 'id');
        $contact->prepend(__('lang_v1.all'), '');

        return view('customer_ring.index', compact('business_locations', 'contact'));
    }

    // ── NEW: Return products for a contact (for the adjust modal pre-load) ──
    public function getAdjustStockProducts(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $contact_id  = $request->input('contact_id');

        $products = DB::table('stock_ring_balance_customer')
            ->join('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
            ->where('stock_ring_balance_customer.business_id', $business_id)
            ->where('stock_ring_balance_customer.contact_id', $contact_id)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'stock_ring_balance_customer.stock_ring_balance'
            )
            ->get();

        return response()->json(['products' => $products]);
    }

    // ── NEW: Product search autocomplete (for the adjust modal search box) ──
    public function searchProducts(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $q           = $request->input('q', '');
        $contact_id  = $request->input('contact_id');

        $products = DB::table('stock_ring_balance_customer')
            ->join('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
            ->where('stock_ring_balance_customer.business_id', $business_id)
            ->where('stock_ring_balance_customer.contact_id', $contact_id)
            ->where('products.name', 'LIKE', "%{$q}%")
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'stock_ring_balance_customer.stock_ring_balance'
            )
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    // ── Save the stock adjustment ────────────────────────────────────────────
    // Supports back-dated adjustments: inserts the row at the correct position
    // in the timeline (by created_at = adjustment_date) then FULLY recalculates
    // new_quantity for every record of that contact+product ordered by
    // (created_at ASC, id ASC) from scratch, so the running balance is always correct.
    public function adjustStock(Request $request)
    {
        $request->validate([
            'contact_id'            => 'required|integer|exists:contacts,id',
            'products'              => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.type'       => 'required|in:add,subtract',
            'products.*.quantity'   => 'required|numeric|min:0.01',
        ]);

        $business_id     = $request->session()->get('user.business_id');
        $location_id     = BusinessLocation::where('business_id', $business_id)->where('is_active', 1)->value('id');
        $user_id         = $request->session()->get('user.id');
        $contact_id      = (int) $request->input('contact_id');
        $adjustment_date = $request->input('adjustment_date')
            ? date('Y-m-d H:i:s', strtotime($request->input('adjustment_date')))
            : now()->toDateTimeString();
        $noted    = $request->input('note');
        $products = $request->input('products');

        DB::beginTransaction();
        try {
            // ── 1. Create transaction header ─────────────────────────────
            $trans_id = DB::table('transactions_ring_balance')->insertGetId([
                'business_id'      => $business_id,
                'location_id'      => $location_id,
                'type'             => 'adjustment',
                'status'           => 'completed',
                'contact_id'       => $contact_id,
                'transaction_date' => $adjustment_date,
                'noted'            => $noted,
                'created_by'       => $user_id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($products as $item) {
                $product_id = (int) $item['product_id'];
                $type       = $item['type'];   // 'add' | 'subtract'
                $qty        = (float) $item['quantity'];
                $signed_qty = ($type === 'subtract') ? -$qty : $qty;

                // ── 2. Get variation_id ──────────────────────────────────
                $variation_id = DB::table('variations')
                    ->where('product_id', $product_id)
                    ->value('id');

                // ── 3. Insert line item into transaction_sell_ring_balance
                //       Use adjustment_date so the record is stamped correctly.
                DB::table('transaction_sell_ring_balance')->insert([
                    'transactions_ring_balance_id' => $trans_id,
                    'product_id'                   => $product_id,
                    'variation_id'                 => $variation_id,
                    'quantity'                     => $signed_qty,
                    'created_at'                   => $adjustment_date,
                    'updated_at'                   => $adjustment_date,
                ]);

                // ── 4. Insert the adjustment row into stock_reward_exchange_new
                //       with created_at = adjustment_date (back-dated correctly).
                //       new_quantity is set to 0 temporarily; step 5 will fix it.
                DB::table('stock_reward_exchange_new')->insert([
                    'transaction_id' => $trans_id,
                    'type'           => 'adjustment',
                    'contact_id'     => $contact_id,
                    'product_id'     => $product_id,
                    'variation_id'   => $variation_id,
                    'quantity'       => $signed_qty,
                    'new_quantity'   => 0,              // ← placeholder, recalculated below
                    'created_at'     => $adjustment_date,
                    'updated_at'     => $adjustment_date,
                ]);

                // ── 5. FULL RECALCULATION ────────────────────────────────
                //       Fetch ALL records for this contact+product ordered by
                //       (created_at ASC, id ASC). Walk them in order and
                //       recompute new_quantity for every single row.
                //
                //       SIGN RULES (matches the original INSERT SQL logic):
                //
                //       The `quantity` column in stock_reward_exchange_new is
                //       stored per type as:
                //
                //         reward_exchange_out  → stored POSITIVE, but means DECREASE
                //                                (customer spends/gives ring → balance goes down)
                //         top_up_ring_balance  → stored POSITIVE → INCREASE
                //         adjustment           → stored already signed (+/-) → use as-is
                //         supplier_reward_in   → stored POSITIVE → INCREASE
                //         supplier_receive_out → stored NEGATIVE → DECREASE (already negative)
                //
                //       So only `reward_exchange_out` needs its sign flipped on recalc.
                //       All other types use their stored quantity directly.
                $allRecords = DB::table('stock_reward_exchange_new')
                    ->where('contact_id', $contact_id)
                    ->where('product_id', $product_id)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                $running_balance = 0;
                foreach ($allRecords as $rec) {
                    $storedQty = (float) $rec->quantity;

                    // Determine the effective change to the running balance per type:
                    //
                    // TYPE                          | STORED  | EFFECTIVE
                    // ------------------------------|---------|----------
                    // reward_exchange_out           | +pos    | DECREASE  → -abs(stored)
                    // delete_reward_exchange_out    | -neg    | INCREASE  → +abs(stored)  ← key fix
                    // top_up_ring_balance           | +pos    | INCREASE  → stored
                    // delete_top_up_ring_balance    | -neg    | DECREASE  → stored
                    // supplier_reward_in            | +pos    | INCREASE  → stored
                    // delete_supplier_reward_in     | -neg    | DECREASE  → stored
                    // supplier_receive_out          | -neg    | DECREASE  → stored
                    // delete_supplier_receive_out   | +pos    | INCREASE  → stored
                    // adjustment                    | ±signed | as-is     → stored
                    switch ($rec->type) {
                        case 'reward_exchange_out':
                            $effectiveQty = -abs($storedQty);
                            break;
                        case 'delete_reward_exchange_out':
                            // Stored as negative (reversal entry), but it restores the balance
                            $effectiveQty = abs($storedQty);
                            break;
                        default:
                            // All other types are already correctly signed
                            $effectiveQty = $storedQty;
                    }

                    $running_balance += $effectiveQty;

                    DB::table('stock_reward_exchange_new')
                        ->where('id', $rec->id)
                        ->update([
                            'new_quantity' => $running_balance,
                            'updated_at'   => now(),
                        ]);
                }

                // ── 6. Update stock_ring_balance_customer to the final
                //       balance (last row in the recalculated chain) ───────
                $final_balance = $running_balance;

                $affected = DB::table('stock_ring_balance_customer')
                    ->where('business_id', $business_id)
                    ->where('contact_id',  $contact_id)
                    ->where('product_id',  $product_id)
                    ->update(['stock_ring_balance' => $final_balance]);

                if ($affected === 0) {
                    DB::table('stock_ring_balance_customer')->insert([
                        'business_id'        => $business_id,
                        'contact_id'         => $contact_id,
                        'product_id'         => $product_id,
                        'stock_ring_balance' => $final_balance,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment saved successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save adjustment: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Show details for a specific contact
    public function show($contact_id)
    {
        $contact = Contact::findOrFail($contact_id);
        
        $contacts = Contact::where('business_id', $contact->business_id)->pluck('name', 'id');
        $contacts->prepend(__('lang_v1.all'), '');

        $ring_balances = StockRingBalanceCustomer::where('contact_id', $contact_id)
                                ->leftJoin('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
                                ->select('products.name as product_name', 'stock_ring_balance_customer.stock_ring_balance')
                                ->get();
        
        return view('customer_ring.show', compact('contact', 'ring_balances', 'contacts', 'contact_id'));
    }

    public function getContactDetails($contact_id)
    {
        $contact = Contact::findOrFail($contact_id);

        $ring_balances = StockRingBalanceCustomer::where('contact_id', $contact_id)
            ->leftJoin('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
            ->select('products.name as product_name', 'stock_ring_balance_customer.stock_ring_balance')
            ->get();

        return response()->json([
            'name'         => $contact->name,
            'type'         => $contact->type,
            'created_at'   => $contact->created_at->format('Y-m-d'),
            'address_line_1' => $contact->address_line_1,
            'mobile'       => $contact->mobile,
            'ring_balances' => $ring_balances,
        ]);
    }

    public function getRingBalances(Request $request, $contact_id = null)
    {
        $business_id = $request->session()->get('user.business_id');
    
        $ring_balances = DB::table('stock_ring_balance_customer')
            ->join('products', 'stock_ring_balance_customer.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('contacts', 'stock_ring_balance_customer.contact_id', '=', 'contacts.id')
            ->where('contacts.business_id', $business_id)
            ->when($contact_id, function ($query, $contact_id) {
                return $query->where('stock_ring_balance_customer.contact_id', $contact_id);
            })
            ->select(
                'stock_ring_balance_customer.contact_id',
                'products.id as product_id',
                'products.name as product_name',
                'units.short_name as short_name',
                'stock_ring_balance_customer.stock_ring_balance'
            );

        return DataTables::of($ring_balances)
            ->addColumn('action', function ($row) {
                $view = '<li><a href="#"><i class="fas fa-eye" aria-hidden="true"></i> View</a></li>';
                return '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">
                                Actions <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu">' . $view . '</ul>
                        </div>';
            })
            ->addColumn('ring_name', fn($row) => $row->product_name)
            ->addColumn('ring_balance', fn($row) => number_format($row->stock_ring_balance) . ' ' . $row->short_name)
            ->rawColumns(['action'])
            ->make(true);
    }
    
    public function getTransactionTopUps(Request $request, $contact_id = null)
    {
        $business_id = $request->session()->get('user.business_id');
        
        $transaction_top_ups = DB::table('transactions_ring_balance')
            ->join('contacts', 'transactions_ring_balance.contact_id', '=', 'contacts.id')
            ->join('business_locations', 'transactions_ring_balance.location_id', '=', 'business_locations.id')
            ->join('users', 'transactions_ring_balance.created_by', '=', 'users.id')
            ->where('transactions_ring_balance.business_id', $business_id)
            ->where('transactions_ring_balance.type', 'top_up_ring_balance')
            ->when($contact_id, function ($query, $contact_id) {
                return $query->where('transactions_ring_balance.contact_id', $contact_id);
            })
            ->select(
                'transactions_ring_balance.id',
                'transactions_ring_balance.transaction_date as date',
                'transactions_ring_balance.invoice_no as invoice_no',
                'contacts.name as contact_name',
                'contacts.mobile as contact_mobile',
                'business_locations.name as location_name',
                'users.username as addby',
                'transactions_ring_balance.status as status'
            );
        
        return DataTables::of($transaction_top_ups)
            ->addColumn('action', function ($row) {
                $view = '<li><a href="#" data-href="' . action([\App\Http\Controllers\CustomerRingController::class, 'showTransactionTopUp'], [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> ' . __('messages.view') . '</a></li>';
                return '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">
                                Actions <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">' . $view . '</ul>
                        </div>';
            })
            ->addColumn('date', fn($row) => $row->date)
            ->addColumn('invoice_no', fn($row) => $row->invoice_no)
            ->addColumn('contact_name', fn($row) => $row->contact_name)
            ->addColumn('contact_mobile', fn($row) => $row->contact_mobile)
            ->addColumn('location_name', fn($row) => $row->location_name)
            ->addColumn('addby', fn($row) => $row->addby)
            ->addColumn('status', fn($row) => $row->status)
            ->rawColumns(['action'])
            ->make(true);
    }

    public function show_ring(Request $request, $contact_id)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id  = $request->input('product_id');
        
        $selected_product = Product::findOrFail($product_id);
        
        $exchange_products = RewardsExchange::where('rewards_exchange.business_id', $business_id)
            ->join('products', 'rewards_exchange.exchange_product', '=', 'products.id')
            ->pluck('products.name', 'products.id');
        
        $business_locations = DB::table('business_locations')
            ->join('product_locations', 'business_locations.id', '=', 'product_locations.location_id')
            ->where('product_locations.product_id', $product_id)
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
                            AND transactions_ring_balance.deleted_at IS NULL 
                            AND transactions_ring_balance.deleted_by IS NULL 
                            THEN transaction_sell_ring_balance.quantity 
                            ELSE 0 
                        END) AS quantities_in"),
                DB::raw("SUM(CASE 
                            WHEN transactions_ring_balance.type = 'reward_out'
                             AND transactions_ring_balance.status = 'completed' 
                            AND transactions_ring_balance.deleted_at IS NULL 
                            AND transactions_ring_balance.deleted_by IS NULL 
                            THEN transaction_sell_ring_balance.quantity 
                            ELSE 0 
                        END) AS used_ring_balance"),
                'units.short_name as unit_short_name'
            )
            ->where('products.id', $product_id)
            ->where('transactions_ring_balance.business_id', $business_id)
            ->where('transactions_ring_balance.contact_id', $contact_id)
            ->first();

        return view('customer_ring.show_ring_balance', compact(
            'selected_product',
            'exchange_products',
            'business_locations',
            'contact_id',
            'product_data'
        ));
    }
    
    public function getRingStockHistory(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id  = $request->input('product_id');
        $contact_id  = $request->input('contact_id');

        if (empty($product_id)) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        // ── Strategy ──────────────────────────────────────────────────────────
        // Base table: stock_reward_exchange_new (sren) — contains every movement.
        //
        // display_date per type:
        //   adjustment          → sren.created_at
        //   top_up_ring_balance → trb_direct.transaction_date
        //   reward_exchange_out → trb_reward.transaction_date (linked via tx)
        //
        // new_quantity is ALWAYS recalculated in PHP by walking rows ASC (oldest→newest)
        // to guarantee correctness regardless of what is stored in sren.new_quantity.
        //
        // Edge case: if sren.new_quantity = 0 for an adjustment AND stock_ring_balance_customer
        // also shows 0, it means the recalc hasn't been done yet (placeholder). We still
        // recalculate from scratch in PHP so it doesn't matter — it will be correct.
        //
        // Sign rules for effective quantity (what changes the running balance):
        //   reward_exchange_out → -ABS(quantity)  (stored positive, means OUT)
        //   top_up_ring_balance → +quantity        (stored positive, means IN)
        //   adjustment          → quantity as-is   (already signed +/-)

        $rows = DB::table('stock_reward_exchange_new as sren')
            ->join('contacts', 'sren.contact_id', '=', 'contacts.id')
            // adjustment / top_up_ring_balance: sren.transaction_id = trb.id
            ->leftJoin('transactions_ring_balance as trb_direct', function ($join) {
                $join->on('trb_direct.id', '=', 'sren.transaction_id')
                     ->whereIn('trb_direct.type', ['top_up_ring_balance', 'adjustment']);
            })
            // reward_exchange_out: sren.transaction_id = transactions.id (sell tx)
            ->leftJoin('transactions as tx', function ($join) {
                $join->on('tx.id', '=', 'sren.transaction_id')
                     ->where('tx.type', '=', 'reward_exchange');
            })
            // link reward_out trb via contact_id + matching transaction_date
            ->leftJoin('transactions_ring_balance as trb_reward', function ($join) {
                $join->on('trb_reward.contact_id', '=', 'tx.contact_id')
                     ->on('trb_reward.transaction_date', '=', 'tx.transaction_date')
                     ->where('trb_reward.type', '=', 'reward_out')
                     ->whereNull('trb_reward.deleted_at')
                     ->whereNull('trb_reward.deleted_by');
            })
            ->where('sren.product_id', $product_id)
            ->when($contact_id, fn($q) => $q->where('sren.contact_id', $contact_id))
            ->whereIn('sren.type', [
                // Top up
                'top_up_ring_balance',
                'delete_top_up_ring_balance',
                // Reward exchange (customer spends ring)
                'reward_exchange_out',
                'delete_reward_exchange_out',
                'edit_reward_exchange_out',
                // Supplier reward (ring received from supplier)
                'supplier_reward_in',
                'delete_supplier_reward_in',
                // Supplier receive (ring sent to supplier)
                'supplier_receive_out',
                'delete_supplier_receive_out',
                // Manual adjustment
                'adjustment',
            ])
            ->select(
                'sren.id         as sren_id',
                'sren.type       as sren_type',
                'sren.quantity   as sren_quantity',
                'sren.created_at as sren_created_at',
                'contacts.name   as customer_name',
                DB::raw('COALESCE(trb_direct.invoice_no, trb_reward.invoice_no) as invoice_no'),
                // display_date — drives the sort order shown to the user
                DB::raw("CASE
                    WHEN sren.type = 'adjustment'
                        THEN sren.created_at
                    WHEN sren.type = 'top_up_ring_balance'
                        THEN COALESCE(trb_direct.transaction_date, sren.created_at)
                    WHEN sren.type IN ('reward_exchange_out', 'delete_reward_exchange_out', 'edit_reward_exchange_out')
                        THEN COALESCE(trb_reward.transaction_date, tx.transaction_date, sren.created_at)
                    ELSE sren.created_at
                END as display_date"),
                // effective_qty — signed amount that affects running balance
                // SIGN RULES (must match adjustStock recalc):
                //   reward_exchange_out         → stored +pos → DECREASE → -ABS
                //   delete_reward_exchange_out  → stored -neg → INCREASE → +ABS
                //   everything else             → already correctly signed → as-is
                DB::raw("CASE
                    WHEN sren.type = 'reward_exchange_out'        THEN -ABS(sren.quantity)
                    WHEN sren.type = 'delete_reward_exchange_out' THEN  ABS(sren.quantity)
                    ELSE sren.quantity
                END as effective_qty")
            )
            // Fetch ASC first so we can do a running-balance pass oldest → newest
            ->orderBy('display_date', 'asc')
            ->orderBy('sren.id', 'asc')
            ->get();

        // ── Recalculate new_quantity as a running balance (oldest → newest) ──
        $running_balance = 0;
        $rows_with_balance = $rows->map(function ($row) use (&$running_balance) {
            $running_balance += (float) $row->effective_qty;
            $row->computed_new_quantity = $running_balance;
            return $row;
        });

        // ── Reverse to show newest first in the table ────────────────────────
        $rows_desc = $rows_with_balance->reverse()->values();

        $results = $rows_desc->map(function ($row) {
            $type_display = match($row->sren_type) {
                'top_up_ring_balance'         => 'Top Up',
                'delete_top_up_ring_balance'  => 'Delete Top Up',
                'reward_exchange_out'         => 'Reward Out',
                'delete_reward_exchange_out'  => 'Delete Reward Out',
                'edit_reward_exchange_out'    => 'Edit Reward Out',
                'supplier_reward_in'          => 'Supplier Reward In',
                'delete_supplier_reward_in'   => 'Delete Supplier Reward In',
                'supplier_receive_out'        => 'Supplier Receive Out',
                'delete_supplier_receive_out' => 'Delete Supplier Receive Out',
                'adjustment'                  => 'Adjustment',
                default                       => ucfirst(str_replace('_', ' ', $row->sren_type)),
            };

            $effective_qty = (float) $row->effective_qty;

            return [
                'type'            => $type_display,
                'quantity_change' => number_format($effective_qty, 2),
                'new_quantity'    => number_format($row->computed_new_quantity, 2),
                'date'            => date('d-m-Y H:i:s', strtotime($row->display_date)),
                'invoice_no'      => $row->invoice_no ?? '',
                'customer'        => $row->customer_name ?? '',
            ];
        })->values();

        return DataTables::of($results)
            ->addColumn('type', fn($row) => $row['type'])
            ->addColumn('quantity_change', function ($row) {
                $qty   = (float) str_replace(',', '', $row['quantity_change']);
                $color = $qty < 0 ? 'red' : 'green';
                return "<span style='color:{$color};'>{$row['quantity_change']}</span>";
            })
            ->addColumn('new_quantity', fn($row) => $row['new_quantity'])
            ->addColumn('date', fn($row) => $row['date'])
            ->addColumn('invoice_no', fn($row) => $row['invoice_no'])
            ->addColumn('customer', fn($row) => $row['customer'])
            ->rawColumns(['quantity_change'])
            ->make(true);
    }

    public function showTransactionTopUp($id)
    {
        $business_id = session()->get('user.business_id');
        
        $transaction = TransactionRingBalance::with(['contact', 'transactionSellRingBalances.product'])
            ->where('business_id', $business_id)
            ->findOrFail($id);
            
        return view('customer_ring.show_transaction_top_up', compact('transaction'));
    }
}