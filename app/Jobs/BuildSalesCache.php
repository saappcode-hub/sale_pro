<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\User;
use App\Utils\TransactionUtil;

class BuildSalesCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $businessId;
    protected $cacheKey;
    protected $userId;
    protected $filters;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 2;

    public function __construct($businessId, $cacheKey, $userId, $filters = [])
    {
        $this->businessId = $businessId;
        $this->cacheKey = $cacheKey;
        $this->userId = $userId;
        $this->filters = $filters;
    }

    public function handle()
    {
        try {
            Log::info("Starting background cache build for business_id: {$this->businessId}");
            
            $user = User::find($this->userId);
            if (!$user) {
                Log::error("User {$this->userId} not found for cache building");
                return;
            }
            
            $result = $this->buildCache($user);
            
            if ($result) {
                Log::info("Background cache build completed successfully for business_id: {$this->businessId}");
            } else {
                Log::error("Background cache build failed for business_id: {$this->businessId}");
            }
            
        } catch (\Exception $e) {
            Log::error("Background cache build exception for business_id {$this->businessId}: " . $e->getMessage());
            Cache::forget($this->cacheKey . '_building');
            throw $e;
        }
    }

    private function buildCache($user)
    {
        try {
            // Get sale_type from filters (KEY CHANGE)
            $sale_type = $this->filters['sale_type'] ?? 'sell';
            
            Log::info("Building cache for business_id: {$this->businessId}, sale_type: {$sale_type}");
            
            $transactionUtil = new TransactionUtil();
            
            $query = $this->getOptimizedSellsQuery($this->businessId, $sale_type); // Pass sale_type
            $query = $this->applyFiltersOptimized($query, $user, $sale_type); // Pass sale_type

            $allData = [];
            $chunkSize = 500;
            $totalProcessed = 0;

            $query->chunk($chunkSize, function($chunk) use (&$allData, &$totalProcessed, $transactionUtil, $sale_type) {
                foreach ($chunk as $row) {
                    $allData[] = $this->processRowForCache($row, $transactionUtil, $sale_type); // Pass sale_type
                }
                $totalProcessed += count($chunk);
                
                if ($totalProcessed % 2000 == 0) {
                    Log::info("Cache progress for business_id {$this->businessId}: {$totalProcessed} records processed");
                }
            });

            // Store in cache with sale_type info
            Cache::put($this->cacheKey, [
                'data' => $allData,
                'total' => count($allData),
                'created_at' => now(),
                'business_id' => $this->businessId,
                'sale_type' => $sale_type // Add sale_type to cache data
            ], env('SALES_CACHE_DURATION', 7200));

            // Remove building flag
            Cache::forget($this->cacheKey . '_building');

            // Update the cache updated timestamp
            Cache::put("sales_cache_updated_{$this->businessId}", time(), 7200);

            Log::info("Cache completed for business_id: {$this->businessId}, sale_type: {$sale_type}. Cached " . count($allData) . " records.");
            return true;

        } catch (\Exception $e) {
            Log::error("Cache building failed: " . $e->getMessage());
            Cache::forget($this->cacheKey . '_building');
            return false;
        }
    }

    // UPDATED: Now accepts sale_type parameter
    private function getOptimizedSellsQuery($business_id, $sale_type = 'sell')
{
    $baseSelect = [
        'transactions.id',
        'transactions.transaction_date',
        'transactions.type',
        'transactions.is_direct_sale',
        'transactions.sales_order_ids',
        'transactions.invoice_no',
        'contacts.name',
        'contacts.mobile',
        'contacts.contact_id',
        'contacts.supplier_business_name',
        'transactions.status',
        'transactions.sub_status',
        'transactions.is_quotation',
        'transactions.payment_status',
        'transactions.final_total',
        'transactions.tax_amount',
        'transactions.discount_amount',
        'transactions.discount_type',
        'transactions.total_before_tax',
        'transactions.types_of_service_id',
        'transactions.shipping_status',
        'transactions.additional_notes',
        'transactions.staff_note',
        'transactions.shipping_details',
        'transactions.document',
        'transactions.custom_field_1',
        'transactions.custom_field_2',
        'transactions.custom_field_3',
        'transactions.custom_field_4',
        'transactions.service_custom_field_1',
        'transactions.is_recurring',
        'transactions.recur_parent_id',
        'transactions.is_export',
        'transactions.rp_redeemed',
        'transactions.rp_earned',
        'transactions.business_id',
        DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
        'bl.name as business_location',
        'tos.name as types_of_service_name',
        DB::raw("CONCAT(COALESCE(ss.surname, ''),' ',COALESCE(ss.first_name, ''),' ',COALESCE(ss.last_name,'')) as waiter"),
        'tables.name as table_name',
    ];

    // NO CHANGES for sell type - keep existing performance
    if ($sale_type == 'sell') {
        $baseSelect = array_merge($baseSelect, [
            DB::raw('COALESCE((SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE TP.transaction_id=transactions.id), 0) as total_paid'),
            DB::raw('COALESCE((SELECT COUNT(SR.id) FROM transactions AS SR WHERE SR.return_parent_id = transactions.id), 0) as return_exists'),
            DB::raw('COALESCE((SELECT SUM(TP2.amount) FROM transaction_payments AS TP2 WHERE TP2.transaction_id=(SELECT SR2.id FROM transactions AS SR2 WHERE SR2.return_parent_id = transactions.id LIMIT 1)), 0) as return_paid'),
            DB::raw('COALESCE((SELECT SR3.final_total FROM transactions AS SR3 WHERE SR3.return_parent_id = transactions.id LIMIT 1), 0) as amount_return'),
            DB::raw('(SELECT SR4.id FROM transactions AS SR4 WHERE SR4.return_parent_id = transactions.id LIMIT 1) as return_transaction_id'),
            DB::raw('COALESCE((SELECT COUNT(DISTINCT tsl.id) FROM transaction_sell_lines AS tsl WHERE tsl.transaction_id = transactions.id AND tsl.parent_sell_line_id IS NULL), 0) as total_items'),
            DB::raw('COALESCE((SELECT SUM(tsl2.quantity) FROM transaction_sell_lines AS tsl2 WHERE tsl2.transaction_id = transactions.id), 0) as total_qty'),
            DB::raw('COALESCE((SELECT so_trans.invoice_no FROM transactions AS so_trans WHERE FIND_IN_SET(so_trans.id, REPLACE(REPLACE(REPLACE(transactions.sales_order_ids, "[", ""), "]", ""), "\\"", "")) AND so_trans.type = "sales_order" LIMIT 1), "") as sales_order_invoice'),
            DB::raw('(SELECT GROUP_CONCAT(DISTINCT tp.method) FROM transaction_payments tp WHERE tp.transaction_id = transactions.id) as payment_methods_raw'),
            DB::raw('0 as so_qty_remaining'),
            DB::raw('0 as product_count_kpi'),
        ]);
    } else if ($sale_type == 'sales_order') {
        // FIXED: For sales_order, use SUM from joined tables like old code
        $baseSelect = array_merge($baseSelect, [
            DB::raw('0 as total_paid'),
            DB::raw('0 as return_exists'),
            DB::raw('0 as return_paid'),
            DB::raw('0 as amount_return'),
            DB::raw('NULL as return_transaction_id'),
            DB::raw('0 as total_items'),
            // FIXED: Use SUM from joined table like old code
            DB::raw('COALESCE(SUM(tsl.quantity), 0) as total_qty'),
            DB::raw('"" as sales_order_invoice'),
            DB::raw('"" as payment_methods_raw'),
            // FIXED: Use SUM from joined table like old code
            DB::raw('COALESCE(SUM(tsl.quantity - COALESCE(tsl.so_quantity_invoiced, 0)), 0) as so_qty_remaining'),
            // FIXED: Use SUM from joined table with products join like old code
            DB::raw('COALESCE(SUM(CASE WHEN sp.product_kpi = 2 THEN tsl.quantity ELSE 0 END), 0) as product_count_kpi'),
        ]);
    }

    $query = DB::table('transactions')
        ->select($baseSelect)
        ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
        ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
        ->leftJoin('users as ss', 'transactions.res_waiter_id', '=', 'ss.id')
        ->join('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
        ->leftJoin('types_of_services as tos', 'transactions.types_of_service_id', '=', 'tos.id')
        ->leftJoin('res_tables as tables', 'transactions.res_table_id', '=', 'tables.id');

    // ONLY add joins for sales_order type (doesn't affect sell performance)
    if ($sale_type == 'sales_order') {
        // FIXED: Add transaction_sell_lines join for correct quantity calculation like old code
        $query->leftJoin('transaction_sell_lines as tsl', function ($join) {
            $join->on('transactions.id', '=', 'tsl.transaction_id')
                ->whereNull('tsl.parent_sell_line_id'); // Only parent sell lines like old code
        })
        // FIXED: Add products join for product_kpi calculation like old code
        ->leftJoin('products as sp', 'tsl.product_id', '=', 'sp.id')
        // FIXED: Add GROUP BY for aggregated quantities like old code
        ->groupBy('transactions.id');
    }

    $query->where('transactions.business_id', $business_id)
        ->where('transactions.type', $sale_type)
        ->orderBy('transactions.transaction_date', 'desc')
        ->orderBy('transactions.id', 'desc');

    // Add status conditions based on sale_type
    if ($sale_type == 'sell') {
        $query->where(function($q) {
            $q->where('transactions.status', 'final')
                ->orWhere('transactions.status', 'draft');
        });
    }
    // For sales_order, no additional status filter needed

    return $query;
}


    // UPDATED: Now accepts sale_type parameter
    private function applyFiltersOptimized($query, $user, $sale_type = 'sell')
    {
        // Apply user permissions based on sale_type
        $permitted_locations = $user->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        // Different permission checks based on sale_type
        if ($sale_type === 'sales_order') {
            // Sales order permissions
            if (!$user->can('so.view_all') && $user->can('so.view_own')) {
                $query->where('transactions.created_by', $this->userId);
            }
        } else {
            // Regular sell permissions
            if (!$user->can('direct_sell.view')) {
                $query->where(function ($q) use ($user) {
                    if ($user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $q->where('transactions.created_by', $this->userId);
                    }
                    if ($user->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $q->orWhere('transactions.commission_agent', $this->userId);
                    }
                });
            }
        }

        // Apply filters from stored filters array
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereDate('transactions.transaction_date', '>=', $this->filters['start_date'])
                  ->whereDate('transactions.transaction_date', '<=', $this->filters['end_date']);
        }

        if (!empty($this->filters['location_id'])) {
            $query->where('transactions.location_id', $this->filters['location_id']);
        }

        if (!empty($this->filters['customer_id'])) {
            $query->where('contacts.id', $this->filters['customer_id']);
        }

        // Payment status filter (only for regular sells)
        if ($sale_type == 'sell' && !empty($this->filters['payment_status'])) {
            if ($this->filters['payment_status'] == 'overdue') {
                $query->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            } else {
                $query->where('transactions.payment_status', $this->filters['payment_status']);
            }
        }

        // Status filter (works for both)
        if (!empty($this->filters['status'])) {
            $query->where('transactions.status', $this->filters['status']);
        }

        if (!empty($this->filters['sale_status'])) {
            $sale_status = $this->filters['sale_status'];
            switch ($sale_status) {
                case 'final':
                    $query->where('transactions.status', 'final');
                    break;
                case 'draft':
                    $query->where('transactions.status', 'draft')
                        ->where(function($q) {
                            $q->where('transactions.sub_status', '!=', 'proforma')
                                ->orWhereNull('transactions.sub_status');
                        })
                        ->where(function($q) {
                            $q->where('transactions.sub_status', '!=', 'quotation')
                                ->orWhereNull('transactions.sub_status');
                        });
                    break;
                case 'proforma':
                    $query->where('transactions.status', 'draft')
                        ->where('transactions.sub_status', 'proforma');
                    break;
                case 'quotation':
                    $query->where('transactions.status', 'draft')
                        ->where('transactions.sub_status', 'quotation');
                    break;
            }
        }

        if (!empty($this->filters['created_by'])) {
            $query->where('transactions.created_by', $this->filters['created_by']);
        }

        if (!empty($this->filters['sales_cmsn_agnt'])) {
            $query->where('transactions.commission_agent', $this->filters['sales_cmsn_agnt']);
        }

        if (!empty($this->filters['service_staffs'])) {
            $query->where('transactions.res_waiter_id', $this->filters['service_staffs']);
        }

        if (!empty($this->filters['shipping_status'])) {
            $query->where('transactions.shipping_status', $this->filters['shipping_status']);
        }

        if (!empty($this->filters['source'])) {
            if ($this->filters['source'] == 'woocommerce') {
                try {
                    $query->whereNotNull('transactions.woocommerce_order_id');
                } catch (\Exception $e) {
                    $query->where('transactions.source', 'woocommerce');
                }
            } else {
                $query->where('transactions.source', $this->filters['source']);
            }
        }

        if (!empty($this->filters['only_subscriptions'])) {
            $query->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                    ->orWhere('transactions.is_recurring', 1);
            });
        }

        return $query;
    }

    // UPDATED: Now accepts sale_type parameter
    private function processRowForCache($row, $transactionUtil, $sale_type = 'sell')
{
    $base_data = [
        'id' => $row->id,
        'transaction_date' => $row->transaction_date ? date('m/d/Y H:i', strtotime($row->transaction_date)) : '',
        'invoice_no' => $this->processInvoiceNumberWithIconsForCache($row),
        'sales_order_invoice' => $row->sales_order_invoice ?: '', // ← MAKE SURE THIS LINE EXISTS
        'conatct_name' => (!empty($row->supplier_business_name) ? $row->supplier_business_name . ', <br>' : '') . ($row->name ?: ''),
        'mobile' => $row->mobile ?: '',
        'business_location' => $row->business_location ?: '',
        // ... rest of your existing code stays exactly the same
        'final_total' => '<span class="final-total" data-orig-value="' . $row->final_total . '">' . $transactionUtil->num_f($row->final_total, true) . '</span>',
        'types_of_service_name' => '<span class="service-type-label" data-orig-value="'.($row->types_of_service_name ?: '').'" data-status-name="'.($row->types_of_service_name ?: '').'">'.($row->types_of_service_name ?: '').'</span>',
        'service_custom_field_1' => $row->service_custom_field_1 ?: '',
        'custom_field_1' => $row->custom_field_1 ?: '',
        'custom_field_2' => $row->custom_field_2 ?: '',
        'custom_field_3' => $row->custom_field_3 ?: '',
        'custom_field_4' => $row->custom_field_4 ?: '',
        'added_by' => $row->added_by ?: '',
        'additional_notes' => $row->additional_notes ?: '',
        'staff_note' => $row->staff_note ?: '',
        'shipping_details' => $row->shipping_details ?: '',
        'table_name' => $row->table_name ?: '',
        'waiter' => $row->waiter ?: '',
        'status' => $row->status ?: '',
        'total_qty' => $row->total_qty ?: 0,
        'shipping_status' => !empty($row->shipping_status) ? 
            '<span class="label ' . (($this->getShippingStatusColors()[$row->shipping_status] ?? 'bg-gray')) . '">' . 
            (($this->getShippingStatusLabels()[$row->shipping_status] ?? ucfirst($row->shipping_status))) . 
            '</span>' : '',
        'type' => $sale_type,
        'is_direct_sale' => $row->is_direct_sale ?: 0,
        'is_quotation' => $row->is_quotation ?: 0,
        'sale_date' => $row->transaction_date ? date('Y/m/d', strtotime($row->transaction_date)) : '',
        'document' => $row->document ?: '',
        'sub_status' => $row->sub_status ?: null,
        'woocommerce_order_id' => null,
        'crm_is_order_request' => null,
        'is_recurring' => $row->is_recurring ?: 0,
        'recur_parent_id' => $row->recur_parent_id ?: null,
        'is_export' => $row->is_export ?: 0,
        'business_id' => $row->business_id ?: $this->businessId,
        'action' => '',
    ];

    // Keep all your existing logic for $sale_type == 'sell' and $sale_type == 'sales_order'
    // NO OTHER CHANGES NEEDED

    if ($sale_type == 'sell') {
        // Your existing sell logic - NO CHANGES
        $total_remaining = $row->final_total - ($row->total_paid ?: 0);
        
        $return_due_html = '';
        if (!empty($row->return_exists)) {
            $return_due = ($row->amount_return ?: 0) - ($row->return_paid ?: 0);
            if (!empty($row->return_transaction_id)) {
                $return_due_html = '<a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->return_transaction_id]).'" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="'.$return_due.'">'.$transactionUtil->num_f($return_due, true).'</span></a>';
            } else {
                $return_due_html = '<span class="sell_return_due" data-orig-value="'.$return_due.'">'.$transactionUtil->num_f($return_due, true).'</span>';
            }
        }

        $discount = !empty($row->discount_amount) ? $row->discount_amount : 0;
        if (!empty($discount) && $row->discount_type == 'percentage') {
            $discount = ($row->total_before_tax ?: 0) * ($discount / 100);
        }
        
        $base_data = array_merge($base_data, [
            'payment_status' => $this->getPaymentStatusHtml($row->payment_status),
            'payment_methods' => $this->processPaymentMethodsForCache($row, $transactionUtil),
            'total_paid' => '<span class="total-paid" data-orig-value="' . ($row->total_paid ?: 0) . '">' . $transactionUtil->num_f($row->total_paid ?: 0, true) . '</span>',
            'total_remaining' => '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $transactionUtil->num_f($total_remaining, true) . '</span>',
            'return_due' => $return_due_html,
            'sell_status' => $this->getSellStatusLabel($row->status, $row->sub_status),
            'total_items' => $row->total_items ?: 0,
            'tax_amount' => '<span class="total-tax" data-orig-value="' . ($row->tax_amount ?: 0) . '">' . $transactionUtil->num_f($row->tax_amount ?: 0, true) . '</span>',
            'discount_amount' => '<span class="total-discount" data-orig-value="'.$discount.'">'.$transactionUtil->num_f($discount, true).'</span>',
            'total_before_tax' => '<span class="total_before_tax" data-orig-value="' . ($row->total_before_tax ?: 0) . '">' . $transactionUtil->num_f($row->total_before_tax ?: 0, true) . '</span>',
            'so_qty_remaining' => 0,
            'product_count_kpi' => 0,
            'amount_return' => $row->amount_return ?: 0,
            'return_paid' => $row->return_paid ?: 0,
            'return_transaction_id' => $row->return_transaction_id ?: null,
            'return_exists' => $row->return_exists ?: 0,
            'payment_methods_raw' => $row->payment_methods_raw ?: '',
        ]);
    } else if ($sale_type == 'sales_order') {
        // Your existing sales_order logic - NO CHANGES
        $base_data = array_merge($base_data, [
            'payment_status' => '',
            'payment_methods' => '',
            'total_paid' => '<span class="total-paid" data-orig-value="0">0.00</span>',
            'total_remaining' => '<span class="payment_due" data-orig-value="0">0.00</span>',
            'return_due' => '',
            'sell_status' => $this->getSalesOrderStatusLabel($row->status),
            'total_items' => 0,
            'tax_amount' => '<span class="total-tax" data-orig-value="0">0.00</span>',
            'discount_amount' => '<span class="total-discount" data-orig-value="0">0.00</span>',
            'total_before_tax' => '<span class="total_before_tax" data-orig-value="0">0.00</span>',
            'so_qty_remaining' => $row->so_qty_remaining ?: 0,
            'product_count_kpi' => $row->product_count_kpi ?: 0,
            'amount_return' => 0,
            'return_paid' => 0,
            'return_transaction_id' => null,
            'return_exists' => 0,
            'payment_methods_raw' => '',
        ]);
    }
    
    return $base_data;
}

    // Add sales order status method
    private function getSalesOrderStatusLabel($status)
    {
        $sales_order_statuses = [
            'draft' => ['label' => 'Draft', 'class' => 'bg-gray'],
            'partial' => ['label' => 'Partial', 'class' => 'bg-yellow'],
            'ordered' => ['label' => 'Ordered', 'class' => 'bg-blue'],
            'completed' => ['label' => 'Completed', 'class' => 'bg-green'],
        ];
        
        $status_info = $sales_order_statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-gray'];
        return '<span class="label '.$status_info['class'].'">'.$status_info['label'].'</span>';
    }

    // Rest of the methods stay the same...
    private function processPaymentMethodsForCache($row, $transactionUtil)
    {
        if (isset($row->payment_methods_raw) && !empty($row->payment_methods_raw)) {
            $payment_methods = explode(',', $row->payment_methods_raw);
            $payment_methods = array_unique(array_filter($payment_methods));
        } else {
            return '';
        }
        
        if (empty($payment_methods)) {
            return '';
        }
        
        $business_id = $row->business_id ?? $this->businessId;
        $payment_types = $transactionUtil->payment_types(null, true, $business_id);
        
        $count = count($payment_methods);
        $payment_method = '';
        
        if ($count == 1) {
            $payment_method = $payment_types[$payment_methods[0]] ?? $payment_methods[0];
        } elseif ($count > 1) {
            $payment_method = __('lang_v1.checkout_multi_pay');
        }

        return '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>';
    }

    private function getShippingStatusColors()
    {
        return [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info', 
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    private function getShippingStatusLabels()
    {
        return [
            'ordered' => 'Ordered',
            'packed' => 'Packed',
            'shipped' => 'Shipped', 
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
    }

    private function processInvoiceNumberWithIconsForCache($row)
    {
        $invoice_no = $row->invoice_no;
        
        if (isset($row->woocommerce_order_id) && !empty($row->woocommerce_order_id)) {
            $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="Synced from WooCommerce"></i>';
        }
        
        if (!empty($row->return_exists)) {
            $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="Some quantity returned from sell"><i class="fas fa-undo"></i></small>';
        }
        
        if (!empty($row->is_recurring)) {
            $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="Subscribed invoice"><i class="fas fa-recycle"></i></small>';
        }

        if (!empty($row->recur_parent_id)) {
            $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="Subscription invoice"><i class="fas fa-recycle"></i></small>';
        }

        if (!empty($row->is_export)) {
            $invoice_no .= '</br><small class="label label-default no-print" title="Export">Export</small>';
        }

        return $invoice_no;
    }

    private function getPaymentStatusHtml($payment_status)
    {
        $status_class = '';
        switch($payment_status) {
            case 'paid':
                $status_class = 'label-success';
                break;
            case 'due':
                $status_class = 'label-warning';
                break;
            case 'partial':
                $status_class = 'label-info';
                break;
            case 'overdue':
                $status_class = 'label-danger';
                break;
            default:
                $status_class = 'label-default';
        }
        
        return '<span class="label '.$status_class.'" data-orig-value="'.$payment_status.'" data-status-name="'.$payment_status.'">'.ucfirst($payment_status).'</span>';
    }

    private function getSellStatusLabel($status, $sub_status)
    {
        if ($status == 'final') {
            return '<span class="label bg-green">Final</span>';
        } elseif ($status == 'draft') {
            if ($sub_status == 'quotation') {
                return '<span class="label bg-blue">Quotation</span>';
            } elseif ($sub_status == 'proforma') {
                return '<span class="label bg-orange">Proforma</span>';
            } else {
                return '<span class="label bg-gray text-white">Draft</span>';
            }
        }
        return '<span class="label bg-gray">' . ucfirst($status) . '</span>';
    }

    public function failed(\Exception $exception)
    {
        Log::error("BuildSalesCache job failed for business_id {$this->businessId}: " . $exception->getMessage());
        Cache::forget($this->cacheKey . '_building');
    }
}