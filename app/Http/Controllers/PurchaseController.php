<?php

namespace App\Http\Controllers;

use App\AccountTransaction;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CurrentStockBackup;
use App\CustomerGroup;
use App\Product;
use App\PurchaseLine;
use App\PurchaseType;
use App\RewardsExchange;
use App\StockRewardsExchange;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\VariationLocationDetails;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Events\PurchaseCreatedOrModified;

class PurchaseController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create') && ! auth()->user()->can('view_own_purchase')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');
        if (request()->ajax()) {
            $purchases = $this->transactionUtil->getListPurchases($business_id);

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $purchases->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->supplier_id)) {
                $purchases->where('contacts.id', request()->supplier_id);
            }
            if (! empty(request()->location_id)) {
                $purchases->where('transactions.location_id', request()->location_id);
            }
            if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $purchases->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $purchases->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            if (! empty(request()->status)) {
                $purchases->where('transactions.status', request()->status);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $purchases->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            if (! auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
                $purchases->where('transactions.created_by', request()->session()->get('user.id'));
            }

            return Datatables::of($purchases)
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                data-toggle="dropdown" aria-expanded="false">'.
                                __('messages.actions').
                                '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                    if (auth()->user()->can('purchase.view')) {
                        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('purchase.view')) {
                        $html .= '<li><a href="#" class="print-invoice" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'printInvoice'], [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i>'.__('messages.print').'</a></li>';
                    }
                    if (auth()->user()->can('purchase.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (auth()->user()->can('purchase.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseController::class, 'destroy'], [$row->id]).'" class="delete-purchase"><i class="fas fa-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    $html .= '<li><a href="'.action([\App\Http\Controllers\LabelsController::class, 'show']).'?purchase_id='.$row->id.'" data-toggle="tooltip" title="'.__('lang_v1.label_help').'"><i class="fas fa-barcode"></i>'.__('barcode.labels').'</a></li>';

                    if (auth()->user()->can('purchase.view') && ! empty($row->document)) {
                        $document_name = ! empty(explode('_', $row->document, 2)[1]) ? explode('_', $row->document, 2)[1] : $row->document;
                        $html .= '<li><a href="'.url('uploads/documents/'.$row->document).'" download="'.$document_name.'"><i class="fas fa-download" aria-hidden="true"></i>'.__('purchase.download_document').'</a></li>';
                        if (isFileImage($document_name)) {
                            $html .= '<li><a href="#" data-href="'.url('uploads/documents/'.$row->document).'" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>'.__('lang_v1.view_document').'</a></li>';
                        }
                    }

                    if (auth()->user()->can('purchase.payments') ||
                        auth()->user()->can('edit_purchase_payment') ||
                        auth()->user()->can('delete_purchase_payment')) {
                        $html .= '<li class="divider"></li>';
                        if ($row->payment_status != 'paid') {
                            $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('purchase.add_payment').'</a></li>';
                        }

                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).
                        '" class="view_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true" ></i>'.__('purchase.view_payments').'</a></li>';
                    }

                    if (auth()->user()->can('purchase.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseReturnController::class, 'add'], [$row->id]).
                        '"><i class="fas fa-undo" aria-hidden="true" ></i>'.__('lang_v1.purchase_return').'</a></li>';
                    }

                    if (auth()->user()->can('purchase.update') || auth()->user()->can('purchase.update_status')) {
                        $html .= '<li><a href="#" data-purchase_id="'.$row->id.
                        '" data-status="'.$row->status.'" class="update_status"><i class="fas fa-edit" aria-hidden="true" ></i>'.__('lang_v1.update_status').'</a></li>';
                    }

                    if ($row->status == 'ordered') {
                        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_order']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> '.__('lang_v1.new_order_notification').'</a></li>';
                    } elseif ($row->status == 'received') {
                        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'items_received']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> '.__('lang_v1.item_received_notification').'</a></li>';
                    } elseif ($row->status == 'pending') {
                        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'items_pending']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> '.__('lang_v1.item_pending_notification').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->editColumn('ref_no', function ($row) {
                    return ! empty($row->return_exists) ? $row->ref_no.' <small class="label bg-red label-round no-print" title="'.__('lang_v1.some_qty_returned').'"><i class="fas fa-undo"></i></small>' : $row->ref_no;
                })
                ->editColumn(
                    'final_total',
                    '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
                ->editColumn(
                    'status',
                    '<a href="#" @if(auth()->user()->can("purchase.update") || auth()->user()->can("purchase.update_status")) class="update_status no-print" data-purchase_id="{{$id}}" data-status="{{$status}}" @endif><span class="label @transaction_status($status) status-label" data-status-name="{{__(\'lang_v1.\' . $status)}}" data-orig-value="{{$status}}">{{__(\'lang_v1.\' . $status)}}
                        </span></a>'
                )
                ->editColumn(
                    'payment_status',
                    function ($row) {
                        $payment_status = Transaction::getPaymentStatus($row);

                        return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id, 'for_purchase' => true]);
                    }
                )
                ->addColumn('payment_due', function ($row) {
                    $due = $row->final_total - $row->amount_paid;
                    $due_html = '<strong>'.__('lang_v1.purchase').':</strong> <span class="payment_due" data-orig-value="'.$due.'">'.$this->transactionUtil->num_f($due, true).'</span>';

                    if (! empty($row->return_exists)) {
                        $return_due = $row->amount_return - $row->return_paid;
                        $due_html .= '<br><strong>'.__('lang_v1.purchase_return').':</strong> <a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->return_transaction_id]).'" class="view_purchase_return_payment_modal"><span class="purchase_return" data-orig-value="'.$return_due.'">'.$this->transactionUtil->num_f($return_due, true).'</span></a>';
                    }

                    return $due_html;
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('purchase.view')) {
                            return  action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['final_total', 'action', 'payment_due', 'payment_status', 'status', 'ref_no', 'name'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);
        $orderStatuses = $this->productUtil->orderStatuses();

        return view('purchase.index')
            ->with(compact('business_locations', 'suppliers', 'orderStatuses'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $taxes = TaxRate::where('business_id', $business_id)
                        ->ExcludeForTaxGroup()
                        ->get();
        $orderStatuses = $this->productUtil->orderStatuses();
        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

        $default_purchase_status = null;
        if (request()->session()->get('business.enable_purchase_status') != 1) {
            $default_purchase_status = 'received';
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $business_details = $this->businessUtil->getDetails($business_id);
        $shortcuts = json_decode($business_details->keyboard_shortcuts, true);

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->productUtil->payment_types(null, true, $business_id);

        //Accounts
        $accounts = $this->moduleUtil->accountsDropdown($business_id, true);

        $common_settings = ! empty(session('business.common_settings')) ? session('business.common_settings') : [];

        return view('purchase.create')
            ->with(compact('taxes', 'orderStatuses', 'business_locations', 'currency_details', 'default_purchase_status', 'customer_groups', 'types', 'shortcuts', 'payment_line', 'payment_types', 'accounts', 'bl_attributes', 'common_settings'));
    }


    /**
     * Auto create supplier reward receive based on Purchase Type.
     * UPDATED: Filters based on the specific product being purchased (receive_product).
     */
      private function autoCreateRewardReceive($purchase_transaction, $purchase_lines, $business_id, $user_id)
    {
        $reward_products = [];
        $calculated_total = 0; 
        
        foreach ($purchase_lines as $line) {
            // Check if line has a purchase type selected
            // Handle both array (Request) and object (DB) for flexibility
            $purchase_type_id = is_array($line) ? ($line['purchase_type_id'] ?? null) : ($line->purchase_type_id ?? null);
            $product_id       = is_array($line) ? ($line['product_id'] ?? null)       : ($line->product_id ?? null);
            $raw_quantity     = is_array($line) ? ($line['quantity'] ?? 0)            : ($line->quantity ?? 0);

            if (!empty($purchase_type_id)) {
                $pType = PurchaseType::where('id', $purchase_type_id)
                                    ->where('business_id', $business_id)
                                    ->first();

                if ($pType && !empty($pType->exchange_product_id) && is_array($pType->exchange_product_id)) {
                    
                    foreach($pType->exchange_product_id as $ex_prod_id) {
                        
                        $rewardsExchange = RewardsExchange::where('exchange_product', $ex_prod_id)
                            ->where('receive_product', $product_id)
                            ->where('type', 'suppliers')
                            ->where('business_id', $business_id)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($rewardsExchange) {
                            // [FIXED]
                            // 1. The Edit Form input is "2,000.00" (Display Unit).
                            // 2. num_uf converts "2,000.00" -> 2000.00.
                            // 3. We DO NOT divide. 2000 is already the correct number for the reward.
                            $quantity = $this->productUtil->num_uf($raw_quantity);
                            
                            $price = $rewardsExchange->amount; 
                            
                            $reward_products[] = [
                                'exchange_product_id' => $ex_prod_id,
                                'quantity'            => $quantity, // Keeps 2000.
                                'price'               => $price,
                                'rule'                => $rewardsExchange
                            ];

                            $calculated_total += ($quantity * $price);
                        }
                    }
                }
            }
        }

        if (empty($reward_products)) {
            return;
        }

        // 2. Prepare Transaction Data
        $lastRewardTransaction = Transaction::where('business_id', $business_id)
            ->where('type', 'supplier_exchange_receive')
            ->whereNotNull('reward_no')
            ->orderBy('id', 'desc')
            ->first();

        $reward_no = $lastRewardTransaction
            ? str_pad((int) $lastRewardTransaction->reward_no + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        $ref_no = $purchase_transaction->ref_no ?? $reward_no;

        $transaction = Transaction::create([
            'business_id'            => $business_id,
            'location_id'            => $purchase_transaction->location_id,
            'type'                   => 'supplier_exchange_receive',
            'status'                 => 'completed',
            'contact_id'             => $purchase_transaction->contact_id,
            'ref_no'                 => $ref_no,
            'reward_no'              => $reward_no,
            'transaction_date'       => $purchase_transaction->transaction_date,
            'additional_notes'       => 'Auto-generated from Purchase Ref: ' . $purchase_transaction->ref_no,
            'total_before_tax'       => $calculated_total,
            'final_total'            => $calculated_total,
            'created_by'             => $user_id,
            'purchase_transaction_id' => $purchase_transaction->id, // Link to purchase by ID (not ref_no)
        ]);

        $stock_rewards_lines = [];

        foreach ($reward_products as $productData) {
            $exchange_product_id = $productData['exchange_product_id'];
            $exchange_quantity   = $productData['quantity'];
            $rewardsExchange     = $productData['rule'];

            $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
            $receive_product_id = $rewardsExchange->receive_product;
            $receive_quantity   = $rewardsExchange->receive_quantity * $exchange_quantity;

            $exchange_variation = Variation::where('product_id', $exchange_product_id)->first();
            $receive_variation  = Variation::where('product_id', $receive_product_id)->first();

            if (!$exchange_variation || !$receive_variation) continue;

            TransactionSellLine::create([
                'transaction_id' => $transaction->id,
                'product_id'     => $exchange_product_id,
                'variation_id'   => $exchange_variation->id,
                'quantity'       => $exchange_quantity,
                'unit_price'     => $productData['price'], 
            ]);

            // 1. RING_OUT
            $lastSupplierRingOut = StockRewardsExchange::where('product_id', $exchange_product_id)
                ->where('contact_id', $purchase_transaction->contact_id)
                ->orderBy('id', 'desc')
                ->first();

            $currentSupplierRingBalance = $lastSupplierRingOut ? $lastSupplierRingOut->new_quantity : 0;
            $newSupplierRingBalance     = $currentSupplierRingBalance - $ring_quantity_sent;

            $stock_rewards_lines[] = new StockRewardsExchange([
                'transaction_id' => $transaction->id,
                'type'           => 'supplier_receive_out',
                'contact_id'     => $purchase_transaction->contact_id,
                'product_id'     => $exchange_product_id,
                'variation_id'   => $exchange_variation->id,
                'quantity'       => -$ring_quantity_sent,
                'new_quantity'   => $newSupplierRingBalance,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 2. Update StockRingBalanceCustomer
            $supplierStockRingRecord = DB::table('stock_ring_balance_customer')
                ->where('product_id', $exchange_product_id)
                ->where('contact_id', $purchase_transaction->contact_id)
                ->where('business_id', $business_id)
                ->first();

            if ($supplierStockRingRecord) {
                $newSupplierStockBalance = $supplierStockRingRecord->stock_ring_balance - $ring_quantity_sent;
                DB::table('stock_ring_balance_customer')
                    ->where('product_id', $exchange_product_id)
                    ->where('contact_id', $purchase_transaction->contact_id)
                    ->where('business_id', $business_id)
                    ->update(['stock_ring_balance' => $newSupplierStockBalance]);
            } else {
                DB::table('stock_ring_balance_customer')->insert([
                    'product_id'         => $exchange_product_id,
                    'contact_id'         => $purchase_transaction->contact_id,
                    'business_id'        => $business_id,
                    'stock_ring_balance' => -$ring_quantity_sent,
                ]);
            }

            // 3. RING_IN
            $lastShopRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                ->whereNull('contact_id')
                ->orderBy('id', 'desc')
                ->first();

            $currentShopRingBalance = $lastShopRingIn ? $lastShopRingIn->new_quantity : 0;
            $newShopRingBalance = $currentShopRingBalance + $receive_quantity;

            $stock_rewards_lines[] = new StockRewardsExchange([
                'transaction_id' => $transaction->id,
                'type'           => 'supplier_receive_in',
                'contact_id'     => null, 
                'product_id'     => $receive_product_id,
                'variation_id'   => $receive_variation->id,
                'quantity'       => $receive_quantity,
                'new_quantity'   => 0, 
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 4. Update VariationLocationDetails
            $receiveVariationLocationDetails = VariationLocationDetails::firstOrNew([
                'variation_id' => $receive_variation->id,
                'location_id'  => $purchase_transaction->location_id
            ]);

            if ($receiveVariationLocationDetails->exists) {
                $receiveVariationLocationDetails->qty_available += $receive_quantity;
            } else {
                $receiveVariationLocationDetails->product_id           = $receive_product_id;
                $receiveVariationLocationDetails->product_variation_id = $receive_variation->product_variation_id;
                $receiveVariationLocationDetails->qty_available        = $receive_quantity;
            }
            $receiveVariationLocationDetails->save();
        }

        if (!empty($stock_rewards_lines)) {
            foreach ($stock_rewards_lines as $line) {
                $line->save();
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // METHOD 1: Sync supplier_exchange_receive when purchase is EDITED
    // Called unconditionally in update() — handles ALL status transitions internally
    // ─────────────────────────────────────────────────────────────────────────────
    private function syncSupplierReceiveOnPurchaseUpdate($purchase_transaction, $purchase_lines, $business_id, $user_id)
    {
        // Step 1: Find by purchase_transaction_id first (new data)
        // Fallback to ref_no for old data that was created before this column existed
        $linked = Transaction::where('business_id', $business_id)
            ->where('type', 'supplier_exchange_receive')
            ->where('purchase_transaction_id', $purchase_transaction->id)
            ->whereNull('deleted_at')
            ->whereNull('deleted_by')
            ->first();

        if (!$linked) {
            $linked = Transaction::where('business_id', $business_id)
                ->where('type', 'supplier_exchange_receive')
                ->where('ref_no', $purchase_transaction->ref_no)
                ->whereNull('purchase_transaction_id')
                ->whereNull('deleted_at')
                ->whereNull('deleted_by')
                ->first();
        }

        // Step 2: If exists → reverse stock + soft-delete regardless of new status
        //   This handles: received→pending, received→ordered, received→received (re-sync)
        if ($linked) {
            $this->reverseSupplierReceive($linked, $business_id);
            $linked->update([
                'deleted_by' => $user_id,
                'deleted_at' => now(),
            ]);
        }

        // Step 3: Re-create ONLY if new status is 'received'
        //   This handles: pending→received, ordered→received, received→received
        if ($purchase_transaction->status == 'received') {
            $this->autoCreateRewardReceive($purchase_transaction, $purchase_lines, $business_id, $user_id);
        }
        // If status is pending or ordered → nothing created → correct ✓
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // METHOD 2: Delete/reverse supplier_exchange_receive when purchase is DELETED
    // Called unconditionally in destroy() — safe if no linked transaction exists
    // ─────────────────────────────────────────────────────────────────────────────
    private function deleteSupplierReceiveOnPurchaseDestroy($purchase_transaction, $business_id, $user_id)
    {
        // Find by purchase_transaction_id first (new data)
        // Fallback to ref_no for old data that was created before this column existed
        $linked = Transaction::where('business_id', $business_id)
            ->where('type', 'supplier_exchange_receive')
            ->where('purchase_transaction_id', $purchase_transaction->id)
            ->whereNull('deleted_at')
            ->whereNull('deleted_by')
            ->first();

        if (!$linked) {
            $linked = Transaction::where('business_id', $business_id)
                ->where('type', 'supplier_exchange_receive')
                ->where('ref_no', $purchase_transaction->ref_no)
                ->whereNull('purchase_transaction_id')
                ->whereNull('deleted_at')
                ->whereNull('deleted_by')
                ->first();
        }

        if (!$linked) {
            return; // pending or ordered purchase → no supplier_exchange_receive → nothing to do
        }

        // Reverse stock effects + soft-delete
        $this->reverseSupplierReceive($linked, $business_id);
        $linked->update([
            'deleted_by' => $user_id,
            'deleted_at' => now(),
        ]);
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // METHOD 3 (HELPER): Reverse all stock effects of a supplier_exchange_receive
    // Mirrors SalesOrderRewardSupllierReceiveController::destroy() exactly
    // ─────────────────────────────────────────────────────────────────────────────
    private function reverseSupplierReceive($transaction, $business_id)
    {
        if ($transaction->status !== 'completed') {
            return; // Only completed transactions have stock effects to reverse
        }

        $sellLines = TransactionSellLine::where('transaction_id', $transaction->id)->get();

        foreach ($sellLines as $sellLine) {
            $exchange_product_id = $sellLine->product_id;
            $exchange_quantity   = $sellLine->quantity;

            $rewardsExchange = RewardsExchange::where('exchange_product', $exchange_product_id)
                ->where('type', 'suppliers')
                ->where('business_id', $business_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$rewardsExchange) continue;

            $ring_quantity_sent = $rewardsExchange->exchange_quantity * $exchange_quantity;
            $receive_product_id = $rewardsExchange->receive_product;
            $receive_quantity   = $rewardsExchange->receive_quantity * $exchange_quantity;

            $exchange_variation = Variation::where('product_id', $exchange_product_id)->first();
            $receive_variation  = Variation::where('product_id', $receive_product_id)->first();

            if (!$exchange_variation || !$receive_variation) continue;

            // ── Reverse RING_OUT → delete_supplier_receive_out ───────────────
            // new_quantity: last(contact_id, product_id) ORDER BY id DESC + ring_quantity_sent
            $lastRingOut = StockRewardsExchange::where('product_id', $exchange_product_id)
                ->where('contact_id', $transaction->contact_id)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastRingOut) {
                StockRewardsExchange::create([
                    'transaction_id' => $transaction->id,
                    'type'           => 'delete_supplier_receive_out',
                    'contact_id'     => $transaction->contact_id,
                    'product_id'     => $exchange_product_id,
                    'variation_id'   => $exchange_variation->id,
                    'quantity'       => $ring_quantity_sent,
                    'new_quantity'   => $lastRingOut->new_quantity + $ring_quantity_sent,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // ── Reverse stock_ring_balance_customer ───────────────────────────
            $ringBalanceRecord = DB::table('stock_ring_balance_customer')
                ->where('product_id', $exchange_product_id)
                ->where('contact_id', $transaction->contact_id)
                ->where('business_id', $business_id)
                ->first();

            if ($ringBalanceRecord) {
                DB::table('stock_ring_balance_customer')
                    ->where('product_id', $exchange_product_id)
                    ->where('contact_id', $transaction->contact_id)
                    ->where('business_id', $business_id)
                    ->update([
                        'stock_ring_balance' => $ringBalanceRecord->stock_ring_balance + $ring_quantity_sent,
                    ]);
            } else {
                DB::table('stock_ring_balance_customer')->insert([
                    'product_id'         => $exchange_product_id,
                    'contact_id'         => $transaction->contact_id,
                    'business_id'        => $business_id,
                    'stock_ring_balance' => $ring_quantity_sent,
                ]);
            }

            // ── Reverse RING_IN → delete_supplier_receive_in ─────────────────
            // new_quantity: always 0 (contact_id IS NULL rule)
            $lastRingIn = StockRewardsExchange::where('product_id', $receive_product_id)
                ->whereNull('contact_id')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastRingIn) {
                StockRewardsExchange::create([
                    'transaction_id' => $transaction->id,
                    'type'           => 'delete_supplier_receive_in',
                    'contact_id'     => null,
                    'product_id'     => $receive_product_id,
                    'variation_id'   => $receive_variation->id,
                    'quantity'       => -$receive_quantity,
                    'new_quantity'   => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // ── Reverse VariationLocationDetails qty_available ────────────────
            $locationDetail = VariationLocationDetails::where('variation_id', $receive_variation->id)
                ->where('location_id', $transaction->location_id)
                ->first();

            if ($locationDetail) {
                $locationDetail->qty_available = max(0, ($locationDetail->qty_available ?? 0) - $receive_quantity);
                $locationDetail->save();
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            // Check if subscribed or not
            if (!$this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\PurchaseController::class, 'index']));
            }

            $transaction_data = $request->only(['ref_no', 'status', 'contact_id', 'transaction_date', 'total_before_tax', 'location_id', 'discount_type', 'discount_amount', 'tax_id', 'tax_amount', 'shipping_details', 'shipping_charges', 'final_total', 'additional_notes', 'exchange_rate', 'pay_term_number', 'pay_term_type', 'purchase_order_ids']);

            $exchange_rate = $transaction_data['exchange_rate'];

            // Validate inputs
            $request->validate([
                'status' => 'required',
                'contact_id' => 'required',
                'transaction_date' => 'required',
                'total_before_tax' => 'required',
                'location_id' => 'required',
                'final_total' => 'required',
                'document' => 'file|max:'.(config('constants.document_size_limit') / 1000),
            ]);

            $user_id = $request->session()->get('user.id');
            $enable_product_editing = $request->session()->get('business.enable_editing_product_from_purchase');

            // Update business exchange rate
            Business::update_business($business_id, ['p_exchange_rate' => $transaction_data['exchange_rate']]);

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            // Unformat input values
            $transaction_data['total_before_tax'] = $this->productUtil->num_uf($transaction_data['total_before_tax'], $currency_details) * $exchange_rate;

            if ($transaction_data['discount_type'] == 'fixed') {
                $transaction_data['discount_amount'] = $this->productUtil->num_uf($transaction_data['discount_amount'], $currency_details) * $exchange_rate;
            } elseif ($transaction_data['discount_type'] == 'percentage') {
                $transaction_data['discount_amount'] = $this->productUtil->num_uf($transaction_data['discount_amount'], $currency_details);
            } else {
                $transaction_data['discount_amount'] = 0;
            }

            $transaction_data['tax_amount'] = $this->productUtil->num_uf($transaction_data['tax_amount'], $currency_details) * $exchange_rate;
            $transaction_data['shipping_charges'] = $this->productUtil->num_uf($transaction_data['shipping_charges'], $currency_details) * $exchange_rate;
            $transaction_data['final_total'] = $this->productUtil->num_uf($transaction_data['final_total'], $currency_details) * $exchange_rate;

            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'purchase';
            $transaction_data['payment_status'] = 'due';
            $transaction_data['transaction_date'] = $this->productUtil->uf_date($transaction_data['transaction_date'], true);

            // Upload document
            $transaction_data['document'] = $this->transactionUtil->uploadFile($request, 'document', 'documents');

            $transaction_data['custom_field_1'] = $request->input('custom_field_1', null);
            $transaction_data['custom_field_2'] = $request->input('custom_field_2', null);
            $transaction_data['custom_field_3'] = $request->input('custom_field_3', null);
            $transaction_data['custom_field_4'] = $request->input('custom_field_4', null);

            $transaction_data['shipping_custom_field_1'] = $request->input('shipping_custom_field_1', null);
            $transaction_data['shipping_custom_field_2'] = $request->input('shipping_custom_field_2', null);
            $transaction_data['shipping_custom_field_3'] = $request->input('shipping_custom_field_3', null);
            $transaction_data['shipping_custom_field_4'] = $request->input('shipping_custom_field_4', null);
            $transaction_data['shipping_custom_field_5'] = $request->input('shipping_custom_field_5', null);

            if ($request->input('additional_expense_value_1') != '') {
                $transaction_data['additional_expense_key_1'] = $request->input('additional_expense_key_1');
                $transaction_data['additional_expense_value_1'] = $this->productUtil->num_uf($request->input('additional_expense_value_1'), $currency_details) * $exchange_rate;
            }

            if ($request->input('additional_expense_value_2') != '') {
                $transaction_data['additional_expense_key_2'] = $request->input('additional_expense_key_2');
                $transaction_data['additional_expense_value_2'] = $this->productUtil->num_uf($request->input('additional_expense_value_2'), $currency_details) * $exchange_rate;
            }

            if ($request->input('additional_expense_value_3') != '') {
                $transaction_data['additional_expense_key_3'] = $request->input('additional_expense_key_3');
                $transaction_data['additional_expense_value_3'] = $this->productUtil->num_uf($request->input('additional_expense_value_3'), $currency_details) * $exchange_rate;
            }

            if ($request->input('additional_expense_value_4') != '') {
                $transaction_data['additional_expense_key_4'] = $request->input('additional_expense_key_4');
                $transaction_data['additional_expense_value_4'] = $this->productUtil->num_uf($request->input('additional_expense_value_4'), $currency_details) * $exchange_rate;
            }

            DB::beginTransaction();

            // Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type']);
            // Generate reference number
            if (empty($transaction_data['ref_no'])) {
                $transaction_data['ref_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count);
            }

            $transaction = Transaction::create($transaction_data);

            $purchases = $request->input('purchases');

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchases, $currency_details, $enable_product_editing);

            // Add Purchase payments
            $this->transactionUtil->createOrUpdatePaymentLines($transaction, $request->input('payment'));

            // Update payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            if (!empty($transaction->purchase_order_ids)) {
                $this->transactionUtil->updatePurchaseOrderStatus($transaction->purchase_order_ids);
            }

            // Adjust stock over selling if found
            $this->productUtil->adjustStockOverSelling($transaction);

            $this->transactionUtil->activityLog($transaction, 'added');

            PurchaseCreatedOrModified::dispatch($transaction);

           if ($transaction->status == 'received') {
                // False means "Do NOT divide", keep the 2000
             $this->autoCreateRewardReceive($transaction, $request->input('purchases'), $business_id, $request->user()->id);
            }

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('purchase.purchase_add_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('purchases')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // if (!auth()->user()->can('purchase.view')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                            ->pluck('name', 'id');
        $purchase = Transaction::where('business_id', $business_id)
                                ->where('id', $id)
                                ->with(
                                    'contact',
                                    'purchase_lines',
                                    'purchase_lines.product',
                                    'purchase_lines.product.unit',
                                    'purchase_lines.product.second_unit',
                                    'purchase_lines.variations',
                                    'purchase_lines.variations.product_variation',
                                    'purchase_lines.sub_unit',
                                    'location',
                                    'payment_lines',
                                    'tax'
                                )
                                ->firstOrFail();

        foreach ($purchase->purchase_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                $purchase->purchase_lines[$key] = $formated_purchase_line;
            }
        }

        $payment_methods = $this->productUtil->payment_types($purchase->location_id, true);

        $purchase_taxes = [];
        if (! empty($purchase->tax)) {
            if ($purchase->tax->is_tax_group) {
                $purchase_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($purchase->tax, $purchase->tax_amount));
            } else {
                $purchase_taxes[$purchase->tax->name] = $purchase->tax_amount;
            }
        }

        //Purchase orders
        $purchase_order_nos = '';
        $purchase_order_dates = '';
        if (! empty($purchase->purchase_order_ids)) {
            $purchase_orders = Transaction::find($purchase->purchase_order_ids);

            $purchase_order_nos = implode(', ', $purchase_orders->pluck('ref_no')->toArray());
            $order_dates = [];
            foreach ($purchase_orders as $purchase_order) {
                $order_dates[] = $this->transactionUtil->format_date($purchase_order->transaction_date, true);
            }
            $purchase_order_dates = implode(', ', $order_dates);
        }

        $activities = Activity::forSubject($purchase)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        $statuses = $this->productUtil->orderStatuses();

        return view('purchase.show')
                ->with(compact('taxes', 'purchase', 'payment_methods', 'purchase_taxes', 'activities', 'statuses', 'purchase_order_nos', 'purchase_order_dates'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }
    
        $business_id = request()->session()->get('user.business_id');
    
        // Check if subscribed or not
        if (!$this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\PurchaseController::class, 'index']));
        }
    
        // Check if the transaction can be edited or not
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (!$this->transactionUtil->canBeEdited($id, $edit_days)) {
            return back()->with('status', [
                'success' => 0,
                'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]),
            ]);
        }
    
        // Check if return exists then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            return back()->with('status', [
                'success' => 0,
                'msg' => __('lang_v1.return_exist'),
            ]);
        }
    
        $business = Business::find($business_id);
        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
    
        $taxes = TaxRate::where('business_id', $business_id)
            ->ExcludeForTaxGroup()
            ->get();
    
        $purchase = Transaction::where('business_id', $business_id)
            ->where('id', $id)
            ->with([
                'contact',
                'purchase_lines',
                'purchase_lines.product',
                'purchase_lines.product.unit',
                'purchase_lines.product.second_unit',
                'purchase_lines.variations',
                'purchase_lines.variations.product_variation',
                'location',
                'purchase_lines.sub_unit',
                'purchase_lines.purchase_order_line',
            ])
            ->first();
    
        foreach ($purchase->purchase_lines as $key => $value) {
            if (!empty($value->sub_unit_id)) {
                $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                $purchase->purchase_lines[$key] = $formated_purchase_line;
            }
    
            // Get sub-units with multiplier key set by default if not existing
            $sub_unit_data = $this->productUtil->getSubUnits($business_id, $value->product->unit->id, false, $value->product->id);
    
            if (isset($sub_unit_data['units'])) {
                foreach ($sub_unit_data['units'] as $sub_unit_key => $sub_unit_value) {
                    if (!isset($sub_unit_data['units'][$sub_unit_key]['multiplier'])) {
                        $sub_unit_data['units'][$sub_unit_key]['multiplier'] = 1; // Setting default multiplier
                    }
                }
                $value->sub_units_options = $sub_unit_data['units'];
            } else {
                $value->sub_units_options = [];
            }
        }
    
        $orderStatuses = $this->productUtil->orderStatuses();
        $business_locations = BusinessLocation::forDropdown($business_id);
    
        $default_purchase_status = null;
        if (request()->session()->get('business.enable_purchase_status') != 1) {
            $default_purchase_status = 'received';
        }
    
        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);
    
        $business_details = $this->businessUtil->getDetails($business_id);
        $shortcuts = json_decode($business_details->keyboard_shortcuts, true);
    
        $common_settings = !empty(session('business.common_settings')) ? session('business.common_settings') : [];
    
        $purchase_orders = null;
        if (!empty($common_settings['enable_purchase_order'])) {
            $purchase_orders = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase_order')
                ->where('contact_id', $purchase->contact_id)
                ->where(function ($q) use ($purchase) {
                    $q->where('status', '!=', 'completed');
                    if (!empty($purchase->purchase_order_ids)) {
                        $q->orWhereIn('id', $purchase->purchase_order_ids);
                    }
                })
                ->pluck('ref_no', 'id');
        }
    
        return view('purchase.edit')->with(compact(
            'taxes',
            'purchase',
            'orderStatuses',
            'business_locations',
            'business',
            'currency_details',
            'default_purchase_status',
            'customer_groups',
            'types',
            'shortcuts',
            'purchase_orders',
            'common_settings'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $transaction = Transaction::findOrFail($id);

            //Validate document size
            $request->validate([
                'document' => 'file|max:'.(config('constants.document_size_limit') / 1000),
            ]);

            $transaction = Transaction::findOrFail($id);
            $before_status = $transaction->status;
            $business_id = request()->session()->get('user.business_id');
            $enable_product_editing = $request->session()->get('business.enable_editing_product_from_purchase');

            $transaction_before = $transaction->replicate();

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            $update_data = $request->only(['ref_no', 'status', 'contact_id',
                'transaction_date', 'total_before_tax',
                'discount_type', 'discount_amount', 'tax_id',
                'tax_amount', 'shipping_details',
                'shipping_charges', 'final_total',
                'additional_notes', 'exchange_rate', 'pay_term_number', 'pay_term_type', 'purchase_order_ids', ]);

            $exchange_rate = $update_data['exchange_rate'];

            //Reverse exchage rate and save
            //$update_data['exchange_rate'] = number_format(1 / $update_data['exchange_rate'], 2);

            $update_data['transaction_date'] = $this->productUtil->uf_date($update_data['transaction_date'], true);

            //unformat input values
            $update_data['total_before_tax'] = $this->productUtil->num_uf($update_data['total_before_tax'], $currency_details) * $exchange_rate;

            // If discount type is fixed them multiply by exchange rate, else don't
            if ($update_data['discount_type'] == 'fixed') {
                $update_data['discount_amount'] = $this->productUtil->num_uf($update_data['discount_amount'], $currency_details) * $exchange_rate;
            } elseif ($update_data['discount_type'] == 'percentage') {
                $update_data['discount_amount'] = $this->productUtil->num_uf($update_data['discount_amount'], $currency_details);
            } else {
                $update_data['discount_amount'] = 0;
            }

            $update_data['tax_amount'] = $this->productUtil->num_uf($update_data['tax_amount'], $currency_details) * $exchange_rate;
            $update_data['shipping_charges'] = $this->productUtil->num_uf($update_data['shipping_charges'], $currency_details) * $exchange_rate;
            $update_data['final_total'] = $this->productUtil->num_uf($update_data['final_total'], $currency_details) * $exchange_rate;
            //unformat input values ends

            $update_data['custom_field_1'] = $request->input('custom_field_1', null);
            $update_data['custom_field_2'] = $request->input('custom_field_2', null);
            $update_data['custom_field_3'] = $request->input('custom_field_3', null);
            $update_data['custom_field_4'] = $request->input('custom_field_4', null);

            $update_data['shipping_custom_field_1'] = $request->input('shipping_custom_field_1', null);
            $update_data['shipping_custom_field_2'] = $request->input('shipping_custom_field_2', null);
            $update_data['shipping_custom_field_3'] = $request->input('shipping_custom_field_3', null);
            $update_data['shipping_custom_field_4'] = $request->input('shipping_custom_field_4', null);
            $update_data['shipping_custom_field_5'] = $request->input('shipping_custom_field_5', null);

            //upload document
            $document_name = $this->transactionUtil->uploadFile($request, 'document', 'documents');
            if (! empty($document_name)) {
                $update_data['document'] = $document_name;
            }

            $purchase_order_ids = $transaction->purchase_order_ids ?? [];

            $update_data['additional_expense_key_1'] = $request->input('additional_expense_key_1');
            $update_data['additional_expense_key_2'] = $request->input('additional_expense_key_2');
            $update_data['additional_expense_key_3'] = $request->input('additional_expense_key_3');
            $update_data['additional_expense_key_4'] = $request->input('additional_expense_key_4');

            $update_data['additional_expense_value_1'] = $request->input('additional_expense_value_1') != '' ? $this->productUtil->num_uf($request->input('additional_expense_value_1'), $currency_details) * $exchange_rate : 0;
            $update_data['additional_expense_value_2'] = $request->input('additional_expense_value_2') != '' ? $this->productUtil->num_uf($request->input('additional_expense_value_2'), $currency_details) * $exchange_rate : 0;
            $update_data['additional_expense_value_3'] = $request->input('additional_expense_value_3') != '' ? $this->productUtil->num_uf($request->input('additional_expense_value_3'), $currency_details) * $exchange_rate : 0;
            $update_data['additional_expense_value_4'] = $request->input('additional_expense_value_4') != '' ? $this->productUtil->num_uf($request->input('additional_expense_value_4'), $currency_details) * $exchange_rate : 0;

            DB::beginTransaction();

            //update transaction
            $transaction->update($update_data);

            //Update transaction payment status
            $payment_status = $this->transactionUtil->updatePaymentStatus($transaction->id);
            $transaction->payment_status = $payment_status;

            $purchases = $request->input('purchases');

            $delete_purchase_lines = $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchases, $currency_details, $enable_product_editing, $before_status);

            //Update mapping of purchase & Sell.
            $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($before_status, $transaction, $delete_purchase_lines);

            //Adjust stock over selling if found
            $this->productUtil->adjustStockOverSelling($transaction);

            $new_purchase_order_ids = $transaction->purchase_order_ids ?? [];
            $purchase_order_ids = array_merge($purchase_order_ids, $new_purchase_order_ids);
            if (! empty($purchase_order_ids)) {
                $this->transactionUtil->updatePurchaseOrderStatus($purchase_order_ids);
            }

            $this->transactionUtil->activityLog($transaction, 'edited', $transaction_before);

            PurchaseCreatedOrModified::dispatch($transaction);

            // Sync linked supplier_exchange_receive (handles ALL status transitions)
            $this->syncSupplierReceiveOnPurchaseUpdate(
                $transaction,
                $request->input('purchases'),
                $business_id,
                $request->user()->id
            );

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('purchase.purchase_update_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return back()->with('status', $output);
        }

        return redirect('purchases')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('purchase.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if (request()->ajax()) {
                $business_id = request()->session()->get('user.business_id');

                // Check if return exist then not allowed
                if ($this->transactionUtil->isReturnExist($id)) {
                    $output = [
                        'success' => false,
                        'msg' => __('lang_v1.return_exist'),
                    ];
                    return $output;
                }

                $transaction = Transaction::where('id', $id)
                    ->where('business_id', $business_id)
                    ->with(['purchase_lines'])
                    ->first();

                // Check if lot numbers from the purchase is selected in sale
                if (request()->session()->get('business.enable_lot_number') == 1 && $this->transactionUtil->isLotUsed($transaction)) {
                    $output = [
                        'success' => false,
                        'msg' => __('lang_v1.lot_numbers_are_used_in_sale'),
                    ];
                    return $output;
                }

                $delete_purchase_lines = $transaction->purchase_lines;
                DB::beginTransaction();

                $log_properities = [
                    'id' => $transaction->id,
                    'ref_no' => $transaction->ref_no,
                ];
                $this->transactionUtil->activityLog($transaction, 'purchase_deleted', $log_properities);

                $transaction_status = $transaction->status;
                if ($transaction_status != 'received') {
                    $transaction->delete();
                } else {
                    // Create stock backup for delete_purchase
                    $stock_lines = [];
                    foreach ($delete_purchase_lines as $purchase_line) {
                        // Fetch the last new_qty for the product_id, variation_id, and location_id
                        $latest_stock = CurrentStockBackup::where('business_id', $business_id)
                            ->where('product_id', $purchase_line->product_id)
                            ->where('variation_id', $purchase_line->variation_id)
                            ->where('location_id', $transaction->location_id) // Verify location_id
                            ->orderBy('transaction_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        $current_stock = $latest_stock ? $latest_stock->new_qty : 0;

                        // Calculate quantity (already in base unit in PurchaseLine)
                        $quantity = $purchase_line->quantity;

                        // Calculate new new_qty (subtract the quantity since we're reversing a purchase)
                        $new_qty = $current_stock - $quantity;

                        // Prepare stock backup line with location_id
                        $stock_line = [
                            'business_id' => $business_id,
                            'location_id' => $transaction->location_id, // Add location_id
                            'product_id' => $purchase_line->product_id,
                            'variation_id' => $purchase_line->variation_id,
                            'transaction_id' => $transaction->id,
                            't_type' => 'delete_purchase',
                            'qty_change' => $quantity, // Store the quantity being removed
                            'new_qty' => $new_qty, // Stock level after the transaction
                            'transaction_date' => now(),
                        ];

                        $stock_lines[] = new CurrentStockBackup($stock_line);
                    }

                    // Save the delete_purchase stock backup lines
                    if (!empty($stock_lines)) {
                        CurrentStockBackup::insert(
                            array_map(function ($line) {
                                return $line->toArray();
                            }, $stock_lines)
                        );
                    }

                    // Delete purchase lines
                    $delete_purchase_line_ids = [];
                    foreach ($delete_purchase_lines as $purchase_line) {
                        $delete_purchase_line_ids[] = $purchase_line->id;
                        $this->productUtil->decreaseProductQuantity(
                            $purchase_line->product_id,
                            $purchase_line->variation_id,
                            $transaction->location_id,
                            $purchase_line->quantity
                        );
                    }
                    PurchaseLine::where('transaction_id', $transaction->id)
                        ->whereIn('id', $delete_purchase_line_ids)
                        ->delete();

                    // Update mapping of purchase & sell
                    $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($transaction_status, $transaction, $delete_purchase_lines);
                }

                // Delete Transaction
                $transaction->delete();

                // Delete account transactions
                AccountTransaction::where('transaction_id', $id)->delete();

                PurchaseCreatedOrModified::dispatch($transaction, true);

                // Delete linked supplier_exchange_receive if exists
                $this->deleteSupplierReceiveOnPurchaseDestroy(
                    $transaction,
                    $business_id,
                    auth()->id()
                );

                DB::commit();

                $output = ['success' => true,
                    'msg' => __('lang_v1.purchase_delete_success'),
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * Retrieves supliers list.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSuppliers()
    {
        if (request()->ajax()) {
            $term = request()->q;
            if (empty($term)) {
                return json_encode([]);
            }

            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $query = Contact::where('business_id', $business_id)
                            ->active();

            $suppliers = $query->where(function ($query) use ($term) {
                $query->where('name', 'like', '%'.$term.'%')
                                ->orWhere('supplier_business_name', 'like', '%'.$term.'%')
                                ->orWhere('contacts.contact_id', 'like', '%'.$term.'%');
            })
                        ->select(
                            'contacts.id',
                            DB::raw('IF(name="", supplier_business_name, name) as text'),
                            'supplier_business_name as business_name',
                            'contacts.mobile',
                            'contacts.address_line_1',
                            'contacts.address_line_2',
                            'contacts.city',
                            'contacts.state',
                            'contacts.country',
                            'contacts.zip_code',
                            'contacts.contact_id',
                            'contacts.pay_term_type',
                            'contacts.pay_term_number',
                            'contacts.balance'
                        )
                        ->onlySuppliers()
                        ->get();

            return json_encode($suppliers);
        }
    }

    /**
     * Retrieves products list.
     *
     * @return \Illuminate\Http\Response
     */
    public function getProducts()
    {
        if (request()->ajax()) {
            $term = request()->term;

            $check_enable_stock = true;
            if (isset(request()->check_enable_stock)) {
                $check_enable_stock = filter_var(request()->check_enable_stock, FILTER_VALIDATE_BOOLEAN);
            }

            $only_variations = false;
            if (isset(request()->only_variations)) {
                $only_variations = filter_var(request()->only_variations, FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($term)) {
                return json_encode([]);
            }

            $business_id = request()->session()->get('user.business_id');
            $q = Product::leftJoin(
                'variations',
                'products.id',
                '=',
                'variations.product_id'
            )
                ->where(function ($query) use ($term) {
                    $query->where('products.name', 'like', '%'.$term.'%');
                    $query->orWhere('sku', 'like', '%'.$term.'%');
                    $query->orWhere('sub_sku', 'like', '%'.$term.'%');
                })
                ->active()
                ->where('business_id', $business_id)
                ->whereNull('variations.deleted_at')
                ->select(
                    'products.id as product_id',
                    'products.name',
                    'products.type',
                    // 'products.sku as sku',
                    'variations.id as variation_id',
                    'variations.name as variation',
                    'variations.sub_sku as sub_sku'
                )
                ->groupBy('variation_id');

            if ($check_enable_stock) {
                $q->where('enable_stock', 1);
            }
            if (! empty(request()->location_id)) {
                $q->ForLocation(request()->location_id);
            }
            $products = $q->get();

            $products_array = [];
            foreach ($products as $product) {
                $products_array[$product->product_id]['name'] = $product->name;
                $products_array[$product->product_id]['sku'] = $product->sub_sku;
                $products_array[$product->product_id]['type'] = $product->type;
                $products_array[$product->product_id]['variations'][]
                = [
                    'variation_id' => $product->variation_id,
                    'variation_name' => $product->variation,
                    'sub_sku' => $product->sub_sku,
                ];
            }

            $result = [];
            $i = 1;
            $no_of_records = $products->count();
            if (! empty($products_array)) {
                foreach ($products_array as $key => $value) {
                    if ($no_of_records > 1 && $value['type'] != 'single' && ! $only_variations) {
                        $result[] = ['id' => $i,
                            'text' => $value['name'].' - '.$value['sku'],
                            'variation_id' => 0,
                            'product_id' => $key,
                        ];
                    }
                    $name = $value['name'];
                    foreach ($value['variations'] as $variation) {
                        $text = $name;
                        if ($value['type'] == 'variable') {
                            $text = $text.' ('.$variation['variation_name'].')';
                        }
                        $i++;
                        $result[] = ['id' => $i,
                            'text' => $text.' - '.$variation['sub_sku'],
                            'product_id' => $key,
                            'variation_id' => $variation['variation_id'],
                        ];
                    }
                    $i++;
                }
            }

            return json_encode($result);
        }
    }

    /**
     * Retrieves products list.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPurchaseEntryRow(Request $request)
    {
        if (request()->ajax()) {
            $product_id = $request->input('product_id');
            $variation_id = $request->input('variation_id');
            $business_id = request()->session()->get('user.business_id');
            $location_id = $request->input('location_id');
            $is_purchase_order = $request->has('is_purchase_order');
            $supplier_id = $request->input('supplier_id');

            $hide_tax = 'hide';
            if ($request->session()->get('business.enable_inline_tax') == 1) {
                $hide_tax = '';
            }

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            if (!empty($product_id)) {
                $row_count = $request->input('row_count');
                $product = Product::where('id', $product_id)
                                    ->with(['unit', 'second_unit'])
                                    ->first();

                // Retrieve sub-units and determine the default selected unit
                $sub_unit_data = $this->productUtil->getSubUnits($business_id, $product->unit->id, false, $product_id);
                $sub_units = isset($sub_unit_data['units']) ? $sub_unit_data['units'] : [];
                $default_selected_unit = isset($sub_unit_data['default_selected_unit']) ? $sub_unit_data['default_selected_unit'] : null;

                $query = Variation::where('product_id', $product_id)
                                ->with([
                                    'product_variation',
                                    'variation_location_details' => function ($q) use ($location_id) {
                                        $q->where('location_id', $location_id);
                                    },
                                ]);
                if ($variation_id !== '0') {
                    $query->where('id', $variation_id);
                }

                $variations = $query->get();
                $taxes = TaxRate::where('business_id', $business_id)
                            ->ExcludeForTaxGroup()
                            ->get();

                $last_purchase_line = $this->getLastPurchaseLine($variation_id, $location_id, $supplier_id);

                // GET PURCHASE TYPES
                $purchase_types = [];
                $business = Business::find($business_id);
                if ($business->enable_purchase_type == 1) {
                    $purchase_types = DB::table('purchase_types')
                        ->where('business_id', $business_id)
                        ->whereNull('deleted_at')
                        ->pluck('name', 'id');
                }

                return view('purchase.partials.purchase_entry_row')
                ->with(compact(
                    'product',
                    'variations',
                    'row_count',
                    'variation_id',
                    'taxes',
                    'currency_details',
                    'hide_tax',
                    'sub_units',
                    'is_purchase_order',
                    'last_purchase_line',
                    'default_selected_unit',
                    'purchase_types'
                ));
            }
        }
    }

    /**
     * Finds last purchase line of a variation for the supplier for a location
     */
    private function getLastPurchaseLine($variation_id, $location_id, $supplier_id = null)
    {
        $query = PurchaseLine::join('transactions as t', 'purchase_lines.transaction_id',
                        '=', 't.id')
                        ->where('t.location_id', $location_id)
                        ->where('t.type', 'purchase')
                        ->where('t.status', 'received')
                        ->where('purchase_lines.variation_id', $variation_id);

        if (! empty($supplier_id)) {
            $query = $query->where('t.contact_id', '=', $supplier_id);
        }
        $purchase_line = $query->orderBy('transaction_date', 'desc')
                            ->select('purchase_lines.*')
                            ->first();

        return $purchase_line;
    }

    public function importPurchaseProducts(Request $request)
    {
        try {
            $file = $request->file('file');

            $parsed_array = Excel::toArray([], $file);
            //Remove header row
            $imported_data = array_splice($parsed_array[0], 1);

            $business_id = $request->session()->get('user.business_id');
            $location_id = $request->input('location_id');
            $row_count = $request->input('row_count');

            $formatted_data = [];
            $row_index = 0;
            $error_msg = '';
            foreach ($imported_data as $key => $value) {
                $row_index = $key + 1;
                $temp_array = [];

                if (! empty($value[0])) {
                    $variation = Variation::where('sub_sku', trim($value[0]))
                                        ->with([
                                            'product_variation',
                                            'variation_location_details' => function ($q) use ($location_id) {
                                                $q->where('location_id', $location_id);
                                            },
                                        ])
                                        ->first();

                    $temp_array['variation'] = $variation;

                    if (empty($variation)) {
                        $error_msg = __('lang_v1.product_not_found_exception', ['row' => $row_index, 'sku' => $value[0]]);
                        break;
                    }

                    $product = Product::where('id', $variation->product_id)
                                    ->where('business_id', $business_id)
                                    ->with(['unit'])
                                    ->first();

                    if (empty($product)) {
                        $error_msg = __('lang_v1.product_not_found_exception', ['row' => $row_index, 'sku' => $value[0]]);
                        break;
                    }

                    $temp_array['product'] = $product;

                    $sub_units = $this->productUtil->getSubUnits($business_id, $product->unit->id, false, $product->id);

                    $temp_array['sub_units'] = $sub_units;
                } else {
                    $error_msg = __('lang_v1.product_not_found_exception', ['row' => $row_index, 'sku' => $value[0]]);
                    break;
                }

                if (! empty($value[0])) {
                    $temp_array['quantity'] = $value[1];
                } else {
                    $error_msg = __('lang_v1.quantity_required', ['row' => $row_index]);
                    break;
                }

                $temp_array['unit_cost_before_discount'] = ! empty($value[2]) ? $value[2] : $variation->default_purchase_price;
                $temp_array['discount_percent'] = ! empty($value[3]) ? $value[3] : 0;

                $tax_id = null;

                if (! empty($value[4])) {
                    $tax_name = trim($value[4]);
                    $tax = TaxRate::where('business_id', $business_id)
                                ->where('name', 'like', "%{$tax_name}%")
                                ->first();

                    $tax_id = $tax->id ?? $tax_id;
                }

                $temp_array['tax_id'] = $tax_id;
                $temp_array['lot_number'] = ! empty($value[5]) ? $value[5] : null;
                $temp_array['mfg_date'] = ! empty($value[6]) ? $this->productUtil->format_date($value[6]) : null;
                $temp_array['exp_date'] = ! empty($value[7]) ? $this->productUtil->format_date($value[7]) : null;

                $formatted_data[] = $temp_array;
            }

            if (! empty($error_msg)) {
                return [
                    'success' => false,
                    'msg' => $error_msg,
                ];
            }

            $hide_tax = 'hide';
            if ($request->session()->get('business.enable_inline_tax') == 1) {
                $hide_tax = '';
            }

            $taxes = TaxRate::where('business_id', $business_id)
                            ->ExcludeForTaxGroup()
                            ->get();

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            $html = view('purchase.partials.imported_purchase_product_rows')
                        ->with(compact('formatted_data', 'taxes', 'currency_details', 'hide_tax', 'row_count'))->render();

            return [
                'success' => true,
                'msg' => __('lang_v.imported'),
                'html' => $html,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }
    }

    public function getPurchaseOrderLines($purchase_order_id)
    {
        $business_id = request()->session()->get('user.business_id');

        $purchase_order = Transaction::where('business_id', $business_id)
                        ->where('type', 'purchase_order')
                        ->with([
                            'purchase_lines',
                            'purchase_lines.variations',
                            'purchase_lines.product',
                            'purchase_lines.product.unit',
                            'purchase_lines.variations.product_variation'
                        ])
                        ->findOrFail($purchase_order_id);

        $taxes = TaxRate::where('business_id', $business_id)
                            ->ExcludeForTaxGroup()
                            ->get();

        $sub_units_array = [];
        $default_units_array = [];
        $base_unit_multipliers = [];
        $remaining_quantities = []; // Add array to track remaining quantities

        foreach ($purchase_order->purchase_lines as $pl) {
            $sub_unit_data = $this->productUtil->getSubUnits($business_id, $pl->product->unit->id, false, $pl->product_id);
            $sub_units_array[$pl->id] = isset($sub_unit_data['units']) ? $sub_unit_data['units'] : [];
            
            // Set the default selected unit using sub_unit_id from purchase line
            $default_units_array[$pl->id] = $pl->sub_unit_id;
            
            // Get base_unit_multiplier if sub_unit_id is not null
            if (!is_null($pl->sub_unit_id)) {
                $base_unit_multiplier = Unit::where('id', $pl->sub_unit_id)->value('base_unit_multiplier');
                $base_unit_multipliers[$pl->id] = $base_unit_multiplier;
            } else {
                $base_unit_multipliers[$pl->id] = 1; // Default multiplier is 1
            }
            
            // Calculate remaining quantity in base units
            $remaining_qty_base = $pl->quantity - $pl->po_quantity_purchased;
            
            // Convert to display units if sub_unit is used
            $remaining_qty_display = $remaining_qty_base;
            if (!is_null($pl->sub_unit_id) && $base_unit_multipliers[$pl->id] > 1) {
                $remaining_qty_display = $remaining_qty_base / $base_unit_multipliers[$pl->id];
            }
            
            // Store both base and display quantities for validation
            $remaining_quantities[$pl->id] = [
                'base' => $remaining_qty_base,
                'display' => $remaining_qty_display,
                'multiplier' => $base_unit_multipliers[$pl->id]
            ];
        }
        
        $hide_tax = request()->session()->get('business.enable_inline_tax') == 1 ? '' : 'hide';
        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
        $row_count = request()->input('row_count');

        $html = view('purchase.partials.purchase_order_lines')
                ->with(compact(
                    'purchase_order',
                    'taxes',
                    'hide_tax',
                    'currency_details',
                    'row_count',
                    'sub_units_array',
                    'default_units_array',
                    'base_unit_multipliers',
                    'remaining_quantities' // Pass remaining quantities to view
                ))
                ->render();    
        
        return [
            'html' => $html,
            'po' => $purchase_order,
        ];
    }

    /**
     * Checks if ref_number and supplier combination already exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkRefNumber(Request $request)
    {
        $business_id     = $request->session()->get('user.business_id');
        $contact_id      = $request->input('contact_id');
        $ref_no          = $request->input('ref_no');
        $purchase_id     = $request->input('purchase_id');
        $original_ref_no = $request->input('original_ref_no');

        // If ref_no unchanged from original (edit form, user didn't touch it) → always valid
        if (!empty($original_ref_no) && $original_ref_no === $ref_no) {
            echo 'true';
            exit;
        }

        $count = 0;
        if (! empty($contact_id) && ! empty($ref_no)) {
            // Only check purchase type (not supplier_exchange_receive which shares same ref_no)
            // Only check non-deleted purchases (deleted ref_no can be reused)
            $query = Transaction::where('business_id', $business_id)
                            ->where('ref_no', $ref_no)
                            ->where('contact_id', $contact_id)
                            ->where('type', 'purchase')
                            ->whereNull('deleted_at');
            if (! empty($purchase_id)) {
                $query->where('id', '!=', $purchase_id);
            }
            $count = $query->count();
        }
        if ($count == 0) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    /**
     * Checks if ref_number and supplier combination already exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function printInvoice($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $taxes = TaxRate::where('business_id', $business_id)
                                ->pluck('name', 'id');
            $purchase = Transaction::where('business_id', $business_id)
                                    ->where('id', $id)
                                    ->with(
                                        'contact',
                                        'purchase_lines',
                                        'purchase_lines.product',
                                        'purchase_lines.variations',
                                        'purchase_lines.variations.product_variation',
                                        'location',
                                        'payment_lines'
                                    )
                                    ->first();
            $payment_methods = $this->productUtil->payment_types(null, false, $business_id);

            //Purchase orders
            $purchase_order_nos = '';
            $purchase_order_dates = '';
            if (! empty($purchase->purchase_order_ids)) {
                $purchase_orders = Transaction::find($purchase->purchase_order_ids);

                $purchase_order_nos = implode(', ', $purchase_orders->pluck('ref_no')->toArray());
                $order_dates = [];
                foreach ($purchase_orders as $purchase_order) {
                    $order_dates[] = $this->transactionUtil->format_date($purchase_order->transaction_date, true);
                }
                $purchase_order_dates = implode(', ', $order_dates);
            }

            $output = ['success' => 1, 'receipt' => [], 'print_title' => $purchase->ref_no];
            $output['receipt']['html_content'] = view('purchase.partials.show_details', compact('taxes', 'purchase', 'payment_methods', 'purchase_order_nos', 'purchase_order_dates'))->render();
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Update purchase status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request)
    {
        if (! auth()->user()->can('purchase.update') && ! auth()->user()->can('purchase.update_status')) {
            abort(403, 'Unauthorized action.');
        }
        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (! $this->transactionUtil->canBeEdited($request->input('purchase_id'), $edit_days)) {
            return ['success' => 0,
                'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ];
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $transaction = Transaction::where('business_id', $business_id)
                                ->where('type', 'purchase')
                                ->with(['purchase_lines'])
                                ->findOrFail($request->input('purchase_id'));

            $before_status = $transaction->status;

            $update_data['status'] = $request->input('status');

            DB::beginTransaction();

            //update transaction
            $transaction->update($update_data);

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
            foreach ($transaction->purchase_lines as $purchase_line) {
                $this->productUtil->updateProductStock($before_status, $transaction, $purchase_line->product_id, $purchase_line->variation_id, $purchase_line->quantity, $purchase_line->quantity, $currency_details);
            }

            //Update mapping of purchase & Sell.
            $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($before_status, $transaction, null);

            //Adjust stock over selling if found
            $this->productUtil->adjustStockOverSelling($transaction);

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('purchase.purchase_update_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        } 

        return $output;
    }
}