<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Events\PurchaseCreatedOrModified;
use App\Media;
use App\PaymentAccount;
use App\PurchaseLine;
use App\TaxRate;
use App\Transaction;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class PurchaseStockReceiveController extends Controller
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
        $this->purchaseOrderStatuses = [
            'ordered' => [
                'label' => __('lang_v1.ordered'),
                'class' => 'bg-info',
            ],
            'partial' => [
                'label' => __('lang_v1.partial'),
                'class' => 'bg-yellow',
            ],
            'completed' => [
                'label' => __('restaurant.completed'),
                'class' => 'bg-green',
            ],
        ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $business_id = request()->session()->get('user.business_id');
        
        if (request()->ajax()) {
            $purchase_orders = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                    ->join(
                        'business_locations AS BS',
                        'transactions.location_id',
                        '=',
                        'BS.id'
                    )
                    ->leftJoin('purchase_lines as pl', 'transactions.id', '=', 'pl.transaction_id')
                    ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase_order')
                    ->select(
                        'transactions.id',
                        'transactions.document',
                        'transactions.transaction_date',
                        'transactions.ref_no',
                        'transactions.status',
                        'transactions.contact_id',
                        'transactions.location_id',
                        'contacts.name',
                        'contacts.supplier_business_name',
                        'transactions.final_total',
                        'BS.name as location_name',
                        'transactions.pay_term_number',
                        'transactions.pay_term_type',
                        'transactions.shipping_status',
                        DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                        DB::raw('SUM(pl.quantity - pl.po_quantity_purchased) as po_qty_remaining')
                    )
                    ->groupBy('transactions.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $purchase_orders->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->supplier_id)) {
                $purchase_orders->where('contacts.id', request()->supplier_id);
            }
            if (! empty(request()->location_id)) {
                $purchase_orders->where('transactions.location_id', request()->location_id);
            }

            if (! empty(request()->status)) {
                $purchase_orders->where('transactions.status', request()->status);
            }

            if (! empty(request()->from_dashboard)) {
                $purchase_orders->where('transactions.status', '!=', 'completed')
                    ->orHavingRaw('po_qty_remaining > 0');
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $purchase_orders->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            if (! auth()->user()->can('purchase_order.view_all') && auth()->user()->can('purchase_order.view_own')) {
                $purchase_orders->where('transactions.created_by', request()->session()->get('user.id'));
            }

            if (! empty(request()->input('shipping_status'))) {
                $purchase_orders->where('transactions.shipping_status', request()->input('shipping_status'));
            }

            return Datatables::of($purchase_orders)
                ->addColumn('action', function ($row) use ($is_admin) {
                    $html = '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                data-toggle="dropdown" aria-expanded="false">'.
                                __('messages.actions').
                                '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                    
                    // View action
                    // if (auth()->user()->can('purchase_order.view_all') || auth()->user()->can('purchase_order.view_own')) {
                        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\PurchaseStockReceiveController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    // }
                    
                    // Print action
                    if (auth()->user()->can('purchase_order.view_all') || auth()->user()->can('purchase_order.view_own')) {
                        $html .= '<li><a href="#" class="print-invoice" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'printInvoice'], [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i>'.__('messages.print').'</a></li>';
                    }
                    
                    // Download PDF action
                    if ((auth()->user()->can('purchase_order.view_all') || auth()->user()->can('purchase_order.view_own'))) {
                        $html .= '<li><a href="'.route('purchaseOrder.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_pdf').'</a></li>';
                    }

                    // Stock Receive action - Link to create page with parameters
                    $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseStockReceiveController::class, 'create'], ['po_id' => $row->id]).'"><i class="fas fa-truck" aria-hidden="true"></i> '.__('Stock Receive').'</a></li>';

                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->editColumn(
                    'final_total',
                    '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('po_qty_remaining', '{{@format_quantity($po_qty_remaining)}}')
                ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
                ->editColumn('status', function ($row) use ($is_admin) {
                    $status = '';
                    $order_statuses = $this->purchaseOrderStatuses;
                    if (array_key_exists($row->status, $order_statuses)) {
                        if ($is_admin && $row->status != 'completed') {
                            $status = '<span class="edit-po-status label '.$order_statuses[$row->status]['class']
                            .'" data-href="'.action([\App\Http\Controllers\PurchaseOrderController::class, 'getEditPurchaseOrderStatus'], ['id' => $row->id]).'">'.$order_statuses[$row->status]['label'].'</span>';
                        } else {
                            $status = '<span class="label '.$order_statuses[$row->status]['class']
                            .'" >'.$order_statuses[$row->status]['label'].'</span>';
                        }
                    }

                    return $status;
                })
                ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                    $status_color = ! empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                    $status = ! empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" data-container=".view_modal"><span class="label '.$status_color.'">'.$shipping_statuses[$row->shipping_status].'</span></a>' : '';

                    return $status;
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        return  action([\App\Http\Controllers\PurchaseOrderController::class, 'show'], [$row->id]);
                    }, ])
                ->rawColumns(['final_total', 'action', 'ref_no', 'name', 'status', 'shipping_status'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);
        $purchaseOrderStatuses = [];
        foreach ($this->purchaseOrderStatuses as $key => $value) {
            $purchaseOrderStatuses[$key] = $value['label'];
        }

        return view('purchase_stock_receive.index')->with(compact('business_locations', 'suppliers', 'purchaseOrderStatuses', 'shipping_statuses'));
    }

    /**
     * Show the form for creating a new stock receive (stock_receive.blade.php).
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
  
        $business_id = request()->session()->get('user.business_id');
        
        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }
        
        // Get taxes
        $taxes = TaxRate::where('business_id', $business_id)
                        ->ExcludeForTaxGroup()
                        ->get();
        
        // Get order statuses
        $orderStatuses = $this->productUtil->orderStatuses();
        
        // Get business locations
        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];
        
        // Get currency details
        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
        
        // Get default purchase status
        $default_purchase_status = null;
        if (request()->session()->get('business.enable_purchase_status') != 1) {
            $default_purchase_status = 'received';
        }
        
        // Get types for supplier/customer creation
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
        
        // Get customer groups
        $customer_groups = CustomerGroup::forDropdown($business_id);
        
        // Get business details and shortcuts
        $business_details = $this->businessUtil->getDetails($business_id);
        $shortcuts = json_decode($business_details->keyboard_shortcuts, true);
        
        // Get payment information
        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->productUtil->payment_types(null, true, $business_id);
        
        // Get accounts
        $accounts = $this->moduleUtil->accountsDropdown($business_id, true);
        
        // Get common settings
        $common_settings = ! empty(session('business.common_settings')) ? session('business.common_settings') : [];
        
        // Get suppliers
        $suppliers = Contact::suppliersDropdown($business_id, false);
        
        // Get purchase order statuses
        $purchaseOrderStatuses = [];
        foreach ($this->purchaseOrderStatuses as $key => $value) {
            $purchaseOrderStatuses[$key] = $value['label'];
        }
        
        // Get shipping statuses
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        
        // Get purchase order data if po_id is provided
        $purchase_order = null;
        if ($request->has('po_id')) {
            $purchase_order = Transaction::with(['contact', 'location'])
                            ->where('business_id', $business_id)
                            ->where('type', 'purchase_order')
                            ->where('id', $request->po_id)
                            ->first();
        }
        
        return view('purchase_stock_receive.stock_receive')->with(compact(
            'taxes', 
            'orderStatuses', 
            'business_locations', 
            'currency_details', 
            'default_purchase_status', 
            'customer_groups', 
            'types', 
            'shortcuts', 
            'payment_line', 
            'payment_types', 
            'accounts', 
            'bl_attributes', 
            'common_settings',
            'suppliers',
            'purchaseOrderStatuses',
            'shipping_statuses',
            'purchase_order'
        ));
    }

    /**
     * Get purchase orders for a contact
     *
     * @param int $contact_id
     * @return \Illuminate\Http\Response
     */
    public function getPurchaseOrders($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');
        $purchase_orders = Transaction::where('business_id', $business_id)
                        ->where('type', 'purchase_order')
                        ->whereIn('status', ['partial', 'ordered'])
                        ->where('contact_id', $contact_id)
                        ->select('ref_no as text', 'id')
                        ->get();
        return $purchase_orders;
    }

    /**
     * Get purchase order details with products
     *
     * @param int $purchase_order_id
     * @return \Illuminate\Http\Response
     */
     public function getPurchaseOrderDetails($purchase_order_id)
    {
        $business_id = request()->session()->get('user.business_id');
    
        // Get the purchase order transaction with all necessary relationships
        $purchase_order = Transaction::with([
                'purchase_lines.product.unit',
                'purchase_lines.variations',
                'purchase_lines.sub_unit',
                'contact',
                'location'
            ])
            ->where('business_id', $business_id)
            ->where('type', 'purchase_order')
            ->where('id', $purchase_order_id)
            ->first();
            
        $products = [];
        if ($purchase_order && $purchase_order->purchase_lines) {
            foreach ($purchase_order->purchase_lines as $line) {
                // Calculate remaining quantity in base units
                $remaining_qty_base = $line->quantity - $line->po_quantity_purchased;
            
                // Get multiplier and convert to display quantity
                $multiplier = 1;
                $display_qty = $remaining_qty_base;
                $unit_name = '';
            
                if (!empty($line->sub_unit_id) && $line->sub_unit) {
                    $multiplier = !empty($line->sub_unit->base_unit_multiplier) ? $line->sub_unit->base_unit_multiplier : 1;
                    $display_qty = $remaining_qty_base / $multiplier;
                    $unit_name = $line->sub_unit->short_name;
                } else if ($line->product && $line->product->unit) {
                    $unit_name = $line->product->unit->short_name;
                }
            
                // Convert prices from base unit to display unit
                // If displaying in sub_units (cases), multiply prices by multiplier
                // This converts from price-per-piece to price-per-case
                $display_purchase_price = $line->purchase_price * $multiplier;
                $display_purchase_price_inc_tax = $line->purchase_price_inc_tax * $multiplier;
                $display_pp_without_discount = $line->pp_without_discount * $multiplier;
                $display_item_tax = $line->item_tax * $multiplier;
            
                // Only include products with remaining quantity
                if ($remaining_qty_base > 0) {
                    $products[] = [
                        'product_name' => $line->product->name . ($line->variations ? ' - ' . $line->variations->name : ''),
                        'purchase_quantity' => $display_qty, // Display quantity in sub_unit
                        'quantity_remaining' => $remaining_qty_base, // Base unit quantity for validation
                        'unit_name' => $unit_name, // Add unit short name
                        'product_id' => $line->product_id,
                        'variation_id' => $line->variation_id,
                        'purchase_order_line_id' => $line->id,
                        'purchase_price' => $display_purchase_price, // Price per display unit
                        'purchase_price_inc_tax' => $display_purchase_price_inc_tax, // Price per display unit
                        'pp_without_discount' => $display_pp_without_discount, // Price per display unit
                        'discount_percent' => $line->discount_percent,
                        'item_tax' => $display_item_tax, // Tax per display unit
                        'tax_id' => $line->tax_id,
                        'lot_number' => $line->lot_number,
                        'mfg_date' => $line->mfg_date,
                        'exp_date' => $line->exp_date,
                        'sub_unit_id' => $line->sub_unit_id,
                        'product_unit_id' => $line->product->unit->id ?? null,
                        'base_unit_multiplier' => $multiplier
                    ];
                }
            }
        }
        
        $response = [
            'products' => $products,
            'transaction' => [
                'id' => $purchase_order->id,
                'ref_no' => $purchase_order->ref_no,
                'contact_id' => $purchase_order->contact_id,
                'location_id' => $purchase_order->location_id,
                'supplier_name' => $purchase_order->contact ? $purchase_order->contact->name : '',
                'location_name' => $purchase_order->location ? $purchase_order->location->name : ''
            ]
        ];
        
        return response()->json($response);
    }

    /**
     * Store stock receive
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // if (!auth()->user()->can('purchase.create')) {
        //     abort(403, 'Unauthorized action.');
        // }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (!$this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\PurchaseController::class, 'index']));
            }

            $transaction_data = $request->only([
                'ref_no', 'status', 'contact_id', 'transaction_date', 
                'location_id', 'pay_term_number', 'pay_term_type', 
                'purchase_order_id', 'purchase_order_ref'
            ]);

            $request->validate([
                'status' => 'required',
                'contact_id' => 'required',
                'transaction_date' => 'required',
                'location_id' => 'required',
                'products' => 'required|array|min:1',
                'document' => 'file|max:' . (config('constants.document_size_limit') / 1000),
            ]);

            $user_id = $request->session()->get('user.id');
            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
            $exchange_rate = 1;

            // Get products and convert to purchases format
            $products = $request->input('products', []);
            $purchases = [];
            $total_before_tax = 0;
            $total_tax = 0;
            
            // Validate quantities against purchase order if exists
            if (!empty($transaction_data['purchase_order_id'])) {
                $purchase_order = Transaction::with('purchase_lines.product.unit')
                    ->where('business_id', $business_id)
                    ->where('type', 'purchase_order')
                    ->where('id', $transaction_data['purchase_order_id'])
                    ->first();
                
                if ($purchase_order) {
                    // Create a map of purchase order lines for validation
                    $po_lines_map = [];
                    foreach ($purchase_order->purchase_lines as $line) {
                        $key = $line->product_id . '_' . $line->variation_id;
                        $po_lines_map[$key] = [
                            'quantity' => $line->quantity,
                            'po_quantity_purchased' => $line->po_quantity_purchased,
                            'remaining' => $line->quantity - $line->po_quantity_purchased,
                            'product_unit_id' => $line->product->unit->id ?? null
                        ];
                    }
                    
                    // Validate each product quantity
                    foreach ($products as $product) {
                        $key = $product['product_id'] . '_' . $product['variation_id'];
                        if (isset($po_lines_map[$key])) {
                            $remaining = $po_lines_map[$key]['remaining'];
                            if (floatval($product['quantity']) > $remaining) {
                                return redirect()->back()->with('status', [
                                    'success' => 0,
                                    'msg' => __('Quantity exceeds remaining purchase order quantity')
                                ]);
                            }
                        }
                    }
                }
            }
            
            foreach ($products as $index => $product) {
                $quantity = floatval($product['quantity']);
                $purchase_price = floatval($product['purchase_price'] ?? 0);
                $purchase_price_inc_tax = floatval($product['purchase_price_inc_tax'] ?? 0);
                $item_tax = floatval($product['item_tax'] ?? 0);
                
                $line_total = $quantity * $purchase_price;
                $total_before_tax += $line_total;
                $total_tax += $item_tax;
                
                // Get product_unit_id from purchase order line or product
                $product_unit_id = null;
                if (!empty($transaction_data['purchase_order_id']) && isset($po_lines_map)) {
                    $key = $product['product_id'] . '_' . $product['variation_id'];
                    if (isset($po_lines_map[$key])) {
                        $product_unit_id = $po_lines_map[$key]['product_unit_id'];
                    }
                }
                
                // Convert to purchases format for createOrUpdatePurchaseLines
                $purchases[$index] = [
                    'product_id' => $product['product_id'] ?? null,
                    'variation_id' => $product['variation_id'] ?? null,
                    'quantity' => $quantity,
                    'purchase_price' => $purchase_price,
                    'purchase_price_inc_tax' => $purchase_price_inc_tax,
                    'pp_without_discount' => $purchase_price,
                    'discount_percent' => floatval($product['discount_percent'] ?? 0),
                    'item_tax' => $item_tax,
                    'purchase_line_tax_id' => $product['tax_id'] ?? null,
                    'lot_number' => $product['lot_number'] ?? null,
                    'mfg_date' => $product['mfg_date'] ?? null,
                    'exp_date' => $product['exp_date'] ?? null,
                    'sub_unit_id' => $product['sub_unit_id'] ?? null,
                    'purchase_order_line_id' => $product['purchase_order_line_id'] ?? null,
                    'product_unit_id' => $product_unit_id
                ];
            }

            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'purchase';
            $transaction_data['payment_status'] = 'due';
            $transaction_data['transaction_date'] = $this->productUtil->uf_date($transaction_data['transaction_date'], true);
            $transaction_data['total_before_tax'] = $total_before_tax;
            $transaction_data['tax_amount'] = $total_tax;
            $transaction_data['final_total'] = $total_before_tax + $total_tax;
            $transaction_data['exchange_rate'] = $exchange_rate;

            if ($request->hasFile('document')) {
                $transaction_data['document'] = $this->transactionUtil->uploadFile($request, 'document', 'documents');
            }

            DB::beginTransaction();

            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type']);
            if (empty($transaction_data['ref_no'])) {
                $transaction_data['ref_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count);
            }

            $transaction = Transaction::create($transaction_data);

            // Set purchase_order_ids AFTER creating the transaction to avoid Laravel's automatic JSON encoding
            if (!empty($transaction_data['purchase_order_id'])) {
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update(['purchase_order_ids' => '["' . $transaction_data['purchase_order_id'] . '"]']);
            }

            // Create purchase lines
            $enable_product_editing = $request->session()->get('business.enable_editing_product_from_purchase');
            $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchases, $currency_details, $enable_product_editing);

            // Update purchase order status
            if (!empty($transaction_data['purchase_order_id'])) {
                $this->transactionUtil->updatePurchaseOrderStatus([$transaction_data['purchase_order_id']]);
            }

            $this->productUtil->adjustStockOverSelling($transaction);
            $this->transactionUtil->activityLog($transaction, 'stock_received');
            PurchaseCreatedOrModified::dispatch($transaction);

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('Stock received successfully')
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return redirect('purchase-stock-receive')->with('status', $output);
    }

     public function show($id)
    {
        // if (!auth()->user()->can('purchase_order.view_all') && !auth()->user()->can('purchase_order.view_own')) {
        //     abort(403, 'Unauthorized action.');
        // }
    
        $business_id = request()->session()->get('user.business_id');
    
        // Load the purchase order with related data
        $purchase = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase_order')
            ->with([
                'contact',
                'purchase_lines',
                'purchase_lines.product',
                'purchase_lines.product.unit',
                'purchase_lines.variations',
                'purchase_lines.variations.product_variation',
                'purchase_lines.sub_unit',
                'location',
                'business',
                'media',
                'payment_lines',
            ])
            ->findOrFail($id);
    
        // Check ownership for view_own permission
        if (!auth()->user()->can('purchase_order.view_all') && auth()->user()->can('purchase_order.view_own')) {
            if ($purchase->created_by != auth()->user()->id) {
                abort(403, 'Unauthorized action.');
            }
        }
    
        // Calculate po_qty_remaining for each purchase line
        foreach ($purchase->purchase_lines as $purchase_line) {
            // Ensure quantities are numeric and not null
            $quantity = is_numeric($purchase_line->quantity) ? floatval($purchase_line->quantity) : 0;
            $po_quantity_purchased = is_numeric($purchase_line->po_quantity_purchased) ? floatval($purchase_line->po_quantity_purchased) : 0;
    
            // Initialize multiplier and unit names
            $base_unit_multiplier = 1;
            $base_unit_name = $purchase_line->product->unit->short_name ?? '';
            $sub_unit_name = $purchase_line->product->unit->short_name ?? '';
            $display_unit = $base_unit_name;
    
            // If sub_unit_id exists, fetch base_unit_multiplier and unit names
            $has_sub_unit = !empty($purchase_line->sub_unit_id);
            if ($has_sub_unit) {
                $sub_unit = Unit::where('id', $purchase_line->sub_unit_id)->first();
                if ($sub_unit && !empty($sub_unit->base_unit_multiplier)) {
                    $base_unit_multiplier = $sub_unit->base_unit_multiplier;
                    $base_unit_name = $sub_unit->short_name; // Unit from sub_unit_id (e.g., "Case")
                    // Fetch the smaller unit (base_unit_id from sub_unit)
                    $base_unit = Unit::where('id', $sub_unit->base_unit_id)->first();
                    if ($base_unit) {
                        $sub_unit_name = $base_unit->short_name; // Smaller unit (e.g., "Can")
                        $display_unit = $base_unit_name;
                    }
                }
            }
    
            // Convert quantities to base unit (e.g., Cases)
            $quantity_base = $base_unit_multiplier != 0 ? $quantity / $base_unit_multiplier : $quantity;
            $po_quantity_purchased_base = $base_unit_multiplier != 0 ? $po_quantity_purchased / $base_unit_multiplier : $po_quantity_purchased;
            $po_qty_remaining = $quantity_base - $po_quantity_purchased_base;
    
            // Format quantity for display (always in base unit)
            $quantity_display = number_format($quantity_base, 0) . " {$base_unit_name}";
    
            // Format po_qty_remaining for display
            $po_qty_remaining_display = '';
            if (!$has_sub_unit) {
                // No sub-unit, display with base unit
                $po_qty_remaining_display = number_format($po_qty_remaining, 2) . " {$base_unit_name}";
            } else {
                // Check if the last two digits are "00" (i.e., whole number)
                $decimal_part = fmod($po_qty_remaining, 1);
                $is_whole_number = abs($decimal_part) < 0.0001;
                if ($is_whole_number) {
                    $po_qty_remaining_display = number_format($po_qty_remaining, 0) . " {$base_unit_name}";
                } else {
                    // Split into whole and fractional parts
                    $whole_part = (int) $po_qty_remaining; // e.g., 9 from 9.79
                    $fractional_part = $po_qty_remaining - $whole_part; // e.g., 0.79
                    $sub_unit_quantity = round($fractional_part * $base_unit_multiplier); // e.g., 0.79 * 24 = 19
    
                    // If sub-unit quantity equals or exceeds multiplier, adjust whole part
                    if ($sub_unit_quantity >= $base_unit_multiplier) {
                        $whole_part += 1;
                        $sub_unit_quantity = 0;
                    }
    
                    // Format display
                    if ($sub_unit_quantity == 0) {
                        $po_qty_remaining_display = "{$whole_part} {$base_unit_name}";
                    } else {
                        $po_qty_remaining_display = "{$whole_part} {$base_unit_name} {$sub_unit_quantity} {$sub_unit_name}";
                    }
                }
            }
    
            // Store adjusted quantities and display strings
            $purchase_line->quantity = $quantity_base;
            $purchase_line->po_quantity_purchased = $po_quantity_purchased_base;
            $purchase_line->po_qty_remaining = max(0, $po_qty_remaining);
            $purchase_line->quantity_display = $quantity_display;
            $purchase_line->po_qty_remaining_display = $po_qty_remaining_display;
            $purchase_line->display_unit = $display_unit;
        }
    
        // Debug: Compare with index listing calculation
        $index_po_qty_remaining = $purchase->purchase_lines->sum(function ($line) {
            $quantity = is_numeric($line->quantity) ? floatval($line->quantity) : 0;
            $po_quantity_purchased = is_numeric($line->po_quantity_purchased) ? floatval($line->po_quantity_purchased) : 0;
            $base_unit_multiplier = 1;
            if (!empty($line->sub_unit_id)) {
                $sub_unit = Unit::where('id', $line->sub_unit_id)->first();
                $base_unit_multiplier = $sub_unit && !empty($sub_unit->base_unit_multiplier) ? $sub_unit->base_unit_multiplier : 1;
            }
            return ($quantity / $base_unit_multiplier) - ($po_quantity_purchased / $base_unit_multiplier);
        });
    
        // Prepare additional data
        $business_locations = BusinessLocation::forDropdown($business_id);
        $po_statuses = $this->productUtil->orderStatuses();
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $shipping_status_colors = $this->shipping_status_colors ?? ['ordered' => 'bg-info', 'partial' => 'bg-yellow', 'delivered' => 'bg-green'];
        $taxes = TaxRate::where('business_id', $business_id)->pluck('name', 'id');
        $payment_methods = PaymentAccount::account_types();
        $purchase_order_nos = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->whereIn('id', $purchase->purchase_lines->pluck('purchase_id')->filter())
            ->pluck('ref_no')
            ->implode(', ');
        $purchase_order_dates = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->whereIn('id', $purchase->purchase_lines->pluck('purchase_id')->filter())
            ->pluck('transaction_date')
            ->map(function ($date) {
                return format_date($date);
            })
            ->implode(', ');
    
        // Calculate purchase taxes
        $purchase_taxes = [];
        foreach ($purchase->purchase_lines as $purchase_line) {
            if ($purchase_line->item_tax > 0 && !empty($purchase_line->tax_id)) {
                $tax_name = $taxes[$purchase_line->tax_id] ?? 'Unknown';
                if (!isset($purchase_taxes[$tax_name])) {
                    $purchase_taxes[$tax_name] = 0;
                }
                $purchase_taxes[$tax_name] += $purchase_line->item_tax;
            }
        }
    
        // Load activities (if applicable)
        $activities = Activity::forSubject($purchase)->get();
    
        return view('purchase_stock_receive.show')->with(compact(
            'purchase',
            'business_locations',
            'po_statuses',
            'shipping_statuses',
            'shipping_status_colors',
            'taxes',
            'payment_methods',
            'purchase_order_nos',
            'purchase_order_dates',
            'purchase_taxes',
            'activities'
        ));
    }
}