<?php

namespace App\Http\Controllers;
use App\MiddleCurrency;
use App\ShippingAddress;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Milon\Barcode\DNS1D;
use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\InvoiceScheme;
use App\Media;
use App\Product;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\TypesOfService;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Warranty;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Queue;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class SellController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $contactUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = ['method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    public function index()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (!$is_admin && !auth()->user()->hasAnyPermission([
            'sell.view', 'sell.create', 'direct_sell.access', 'direct_sell.view', 
            'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 
            'access_own_shipping', 'access_commission_agent_shipping', 'so.view_all', 'so.view_own'
        ])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        // Check if cache should be refreshed
        $shouldRefreshCache = session()->has('sale_cache_refresh') || session()->has('status');
        if ($shouldRefreshCache) {
            $this->refreshAllSalesCache($business_id);
            session()->forget('sale_cache_refresh');
        }

        if (request()->ajax()) {
            // Get sale_type
            $sale_type = !empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';
            
            return $this->handlePaginationAjaxRequest($business_id, $is_admin, $is_crm, $sale_type);
        }

        // Get dropdown data (same as old code)
        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (!empty($is_cmsn_agent_enabled)) {
            $commission_agents = User::forDropdown($business_id, false, true, true);
        }

        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $sources = $this->transactionUtil->getSources($business_id);
        if ($is_woocommerce) {
            $sources['woocommerce'] = 'Woocommerce';
        }

        $sales_order_statuses = [
            '' => __('lang_v1.all'),
            'draft' => __('sale.draft'),
            'partial' => __('sale.partial'),
            'ordered' => __('sale.ordered'),
            'completed' => __('sale.completed'),
        ];

        return view('sell.index')->with(compact(
            'business_locations', 'customers', 'is_woocommerce', 'sales_representative', 
            'is_cmsn_agent_enabled', 'commission_agents', 'service_staffs', 'is_tables_enabled', 
            'is_service_staff_enabled', 'is_types_service_enabled', 'shipping_statuses', 'sources',
            'sales_order_statuses'
        ))->with('cache_refreshed', $shouldRefreshCache);
    }

private function handlePaginationAjaxRequest($business_id, $is_admin, $is_crm, $sale_type)
{
    try {
        $draw = intval(request()->get('draw', 1));
        $start = intval(request()->get('start', 0));
        $length = intval(request()->get('length', 25));
        $search = request()->get('search');
        $searchValue = $search['value'] ?? '';

        // FIX: Handle "Show All" case - when length is -1 or very large number
        $isShowAll = ($length == -1 || $length > 100000);
        
        if ($isShowAll) {
            // For "Show All", get total count and set reasonable limits
            $totalCount = $this->getUltraFastCount($business_id, $sale_type, !empty($searchValue));
            $length = min($totalCount, 10000); // Cap at 10,000 records for performance
            $start = 0; // Always start from beginning for "Show All"
        }

        // FIXED: Always try cache first, even with filters (except search)
        if (empty($searchValue) && !$isShowAll) {
            $cachedResult = $this->serveFastPaginationCache($business_id, $sale_type, $start, $length, $draw, $is_admin);
            if ($cachedResult) {
                return $cachedResult; // Return in 2-3ms!
            }
        }

        // STEP 2: If no cache or "Show All", build and return the result
        return $this->buildAndCachePagination($business_id, $is_admin, $is_crm, $sale_type, $draw, $start, $length, $searchValue, $isShowAll);

    } catch (\Exception $e) {
        Log::error('Pagination Ajax error: ' . $e->getMessage());
        return response()->json([
            'draw' => $draw ?? 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Error loading data'
        ], 500);
    }
}

private function serveFastPaginationCache($business_id, $sale_type, $start, $length, $draw, $is_admin)
{
    // Calculate page number from start/length
    $pageNumber = floor($start / $length) + 1;
    
    // Generate cache keys for this page and metadata
    $cacheKey = $this->generateFastCacheKey($business_id, $sale_type, $pageNumber, $length);
    $metaKey = $this->generateFastMetaKey($business_id, $sale_type);
    
    // Try to get cached page and metadata
    $cachedPage = Cache::get($cacheKey);
    $cachedMeta = Cache::get($metaKey);
    
    // If both cache exists, check freshness and return
    if ($cachedPage && $cachedMeta) {
        // Check if cache is still fresh (no new data since cache timestamp)
        if (!$this->hasNewDataSinceCache($business_id, $sale_type, $cachedMeta['timestamp'])) {
            
            // ⭐ CRITICAL: Regenerate action buttons for current user
            // Cache doesn't store action buttons because they're user-specific
            foreach ($cachedPage as &$row) {
                // Convert array to object for action button generation
                $rowObj = (object)$row;
                
                // Get the correct sale_type from row data (handles sell vs sales_order)
                $sale_type_for_actions = $row['type'] ?? 'sell';
                
                // Generate fresh action buttons for current user
                $row['action'] = $this->generateActionButtonsComplete($rowObj, $is_admin, $sale_type_for_actions);
            }
            
            // Return cached data with fresh action buttons (ultra-fast 2-3ms response)
            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $cachedMeta['total_count'],
                'recordsFiltered' => $cachedMeta['total_count'],
                'data' => array_values($cachedPage),
                'cached' => true,
                'load_time_ms' => 2 // Ultra-fast response indicator
            ]);
        }
    }
    
    // No valid cache found - return false to trigger fresh data fetch
    return false;
}

/**
 * BUILD AND CACHE: When cache miss, build data and cache by pages
 */
private function buildAndCachePagination($business_id, $is_admin, $is_crm, $sale_type, $draw, $start, $length, $searchValue, $isShowAll = false)
{
    $queryStart = microtime(true);
    
    // Build optimized query
    $query = $this->buildOptimizedQuery($business_id, $sale_type);
    $this->applyUserPermissions($query, $sale_type);

    // Check if we have filters (search only)
    $hasFilters = !empty($searchValue);

    // Get total count - use cache when possible
    $totalCount = $this->getUltraFastCount($business_id, $sale_type, $hasFilters);

    // Apply search if provided
    if (!empty($searchValue)) {
        $this->applySearchToQuery($query, $searchValue);
        $filteredCount = $query->count();
    } else {
        $filteredCount = $totalCount;
    }

    // Handle pagination properly for "Show All" case
    if (!$isShowAll && $length > 0) {
        $query->offset($start)->limit($length);
    } elseif ($isShowAll) {
        // For "Show All", apply reasonable limit and no offset
        $query->limit($length);
    }

    // Get paginated data
    $data = $query->get();

    // OPTIMIZED: Batch fetch related data (much faster than JOINs)
    $transactionIds = $data->pluck('id')->toArray();
    $paymentTotals = $this->getPaymentTotals($transactionIds);
    $paymentMethods = $this->getPaymentMethods($transactionIds);
    $returnData = $this->getReturnData($transactionIds);
    $sellLineTotals = $this->getSellLineTotals($transactionIds);
    
    // Fetch sales order invoices for sells
    $salesOrderIdsList = $data->pluck('sales_order_ids')->toArray();
    $salesOrderInvoices = $this->getSalesOrderInvoices($salesOrderIdsList);

    // Process data for response with all batch data
    $processedData = [];
    foreach ($data as $row) {
        $processedData[] = $this->processRowForResponse(
            $row, 
            $is_admin, 
            $is_crm, 
            $sale_type, 
            $paymentTotals, 
            $paymentMethods, 
            $returnData, 
            $sellLineTotals, 
            $salesOrderInvoices
        );
    }

    // ⭐ CRITICAL FIX: Cache results when no search and not "Show All"
    if (empty($searchValue) && !$isShowAll) {
        $this->cacheMultiplePages($business_id, $sale_type, $start, $length, $processedData, $totalCount);
    }

    $totalTime = (microtime(true) - $queryStart) * 1000;
    
    $response = [
        'draw' => $draw,
        'recordsTotal' => $totalCount,
        'recordsFiltered' => $filteredCount,
        'data' => array_values($processedData),
        'load_time_ms' => round($totalTime, 2)
    ];

    // Add "Show All" indicator
    if ($isShowAll) {
        $response['show_all'] = true;
        $response['message'] = "Showing all {$filteredCount} records";
    }
    
    return response()->json($response);
}

/**
 * SMART CACHING: Cache current page + nearby pages for instant navigation
 */
private function cacheMultiplePages($business_id, $sale_type, $start, $length, $currentPageData, $totalCount)
{
    $currentPage = floor($start / $length) + 1;
    
    // Cache current page
    $cacheKey = $this->generateFastCacheKey($business_id, $sale_type, $currentPage, $length);
    Cache::put($cacheKey, $currentPageData, 1800); // 30 minutes
    
    // Cache metadata
    $metaKey = $this->generateFastMetaKey($business_id, $sale_type);
    $metaData = [
        'total_count' => $totalCount,
        'timestamp' => time(),
        'business_id' => $business_id,
        'last_page' => ceil($totalCount / $length)
    ];
    Cache::put($metaKey, $metaData, 1800);
    
    // Pre-cache next 2 pages and previous 2 pages for instant navigation
    $this->preCacheNearbyPages($business_id, $sale_type, $currentPage, $length, $totalCount);
    
    // Track cache keys for cleanup
    $this->trackCacheKey($business_id, $cacheKey);
    $this->trackCacheKey($business_id, $metaKey);
}

/**
 * PRE-CACHE: Cache nearby pages for instant navigation
 */
private function preCacheNearbyPages($business_id, $sale_type, $currentPage, $length, $totalCount)
{
    $lastPage = ceil($totalCount / $length);
    
    // Pages to pre-cache (current page ± 2)
    $pagesToCache = [];
    for ($i = max(1, $currentPage - 2); $i <= min($lastPage, $currentPage + 2); $i++) {
        if ($i != $currentPage) { // Don't re-cache current page
            $pagesToCache[] = $i;
        }
    }
    
    // Also cache first page and last page for quick access
    if ($currentPage > 3) $pagesToCache[] = 1;
    if ($currentPage < $lastPage - 2) $pagesToCache[] = $lastPage;
    
    // Remove duplicates
    $pagesToCache = array_unique($pagesToCache);
    
    // Cache each page (run this in background if possible)
    foreach ($pagesToCache as $pageNum) {
        $this->cacheSpecificPage($business_id, $sale_type, $pageNum, $length);
    }
}

/**
 * CACHE SPECIFIC PAGE: Cache a specific page number
 */
private function cacheSpecificPage($business_id, $sale_type, $pageNumber, $length)
{
    $start = ($pageNumber - 1) * $length;
    $cacheKey = $this->generateFastCacheKey($business_id, $sale_type, $pageNumber, $length);
    
    // Check if already cached
    if (Cache::has($cacheKey)) {
        return;
    }
    
    try {
        // Build query for this specific page
        $query = $this->buildOptimizedQuery($business_id, $sale_type);
        $this->applyUserPermissions($query, $sale_type);
        
        // Get data for this page
        $data = $query->offset($start)->limit($length)->get();
        
        // FIX: Fetch all related data before processing
        $transactionIds = $data->pluck('id')->toArray();
        $paymentTotals = $this->getPaymentTotals($transactionIds);
        $paymentMethods = $this->getPaymentMethods($transactionIds);
        $returnData = $this->getReturnData($transactionIds);
        $sellLineTotals = $this->getSellLineTotals($transactionIds);
        $salesOrderIdsList = $data->pluck('sales_order_ids')->toArray();
        $salesOrderInvoices = $this->getSalesOrderInvoices($salesOrderIdsList);
        
        // Process data (but without action buttons - will be generated when served)
        $processedData = [];
        foreach ($data as $row) {
            $rowData = $this->processRowForResponse($row, true, false, $sale_type, $paymentTotals, $paymentMethods, $returnData, $sellLineTotals, $salesOrderInvoices);
            // Remove action buttons from cache (will be regenerated per user)
            unset($rowData['action']);
            $processedData[] = $rowData;
        }
        
        // Cache this page
        Cache::put($cacheKey, $processedData, 1800); // 30 minutes
        $this->trackCacheKey($business_id, $cacheKey);
        
    } catch (\Exception $e) {
        Log::warning("Failed to cache page {$pageNumber}: " . $e->getMessage());
    }
}

/**
 * CHECK FOR NEW DATA: Fast check based on timestamp
 * UPDATED: Now checks both created_at AND updated_at to catch API updates
 */
private function hasNewDataSinceCache($business_id, $sale_type, $cacheTimestamp)
{
    try {
        $timestamp = date('Y-m-d H:i:s', $cacheTimestamp);

        // Check if any transaction was Created OR Updated after the cache was made
        $hasChanges = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', $sale_type)
            ->where(function($query) use ($timestamp) {
                $query->where('created_at', '>', $timestamp)
                      ->orWhere('updated_at', '>', $timestamp);
            })
            ->exists(); // .exists() is faster than .count()
            
        return $hasChanges;
        
    } catch (\Exception $e) {
        // If check fails, return true to force a fresh load (safety fallback)
        return true; 
    }
}

/**
 * GENERATE FAST CACHE KEYS
 */
private function generateFastCacheKey($business_id, $sale_type, $pageNumber, $length)
{
    $filters = $this->buildFiltersArray();
    
    // Remove empty values and normalize for consistent caching
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '' && $value !== [];
    });
    
    // Sort filters for consistent hash
    ksort($filters);
    
    $filterHash = md5(serialize($filters));
    $userHash = md5(auth()->user()->id . serialize(auth()->user()->permitted_locations()));
    
    return "fast_page_v1_{$sale_type}_{$business_id}_{$pageNumber}_{$length}_{$filterHash}_{$userHash}";
}
private function generateFastMetaKey($business_id, $sale_type)
{
    $filters = $this->buildFiltersArray();
    $filterHash = md5(serialize($filters));
    $userHash = md5(auth()->user()->id . serialize(auth()->user()->permitted_locations()));
    
    return "fast_meta_v1_{$sale_type}_{$business_id}_{$filterHash}_{$userHash}";
}

/**
 * TRACK CACHE KEYS: Keep track of cache keys for cleanup
 */
private function trackCacheKey($business_id, $cacheKey)
{
    $trackingKey = "fast_cache_keys_{$business_id}";
    $keys = Cache::get($trackingKey, []);
    
    if (!in_array($cacheKey, $keys)) {
        $keys[] = $cacheKey;
        // Keep only latest 1000 keys to prevent memory issues
        if (count($keys) > 1000) {
            $keys = array_slice($keys, -1000);
        }
        Cache::put($trackingKey, $keys, 3600);
    }
}

/**
 * IMPROVED: Clear cache for specific sale type (clears fast cache)
 */
public function clearSaleTypeCache($business_id, $sale_type)
{
    try {
        // Clear fast pagination cache
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        foreach ($keys as $key) {
            if (strpos($key, "fast_page_v1_{$sale_type}") !== false || 
                strpos($key, "fast_meta_v1_{$sale_type}") !== false) {
                Cache::forget($key);
            }
        }
        
        // Clear total count cache
        $countKeys = Cache::get("count_cache_keys_{$business_id}", []);
        foreach ($countKeys as $key) {
            if (strpos($key, "total_count_{$business_id}_{$sale_type}") !== false) {
                Cache::forget($key);
            }
        }
        
        Log::info("Fast cache cleared for sale_type: {$sale_type}, business_id: {$business_id}");
        
    } catch (\Exception $e) {
        Log::error("Fast cache clear failed: " . $e->getMessage());
    }
}

/**
 * IMPROVED: Update cache after store (clear fast cache)
 */
public function updateCacheAfterStore($transaction)
{
    try {
        $business_id = $transaction->business_id;
        $sale_type = $transaction->type;

        // For NEW transactions, use smart update
        $this->smartCacheUpdate($business_id, $sale_type, $transaction);

        Log::info("Smart cache updated after transaction store: {$transaction->id}");
    } catch (\Exception $e) {
        Log::error("Smart cache update after store failed: " . $e->getMessage());
    }
}

private function updateSalesOrderStatusInCache($business_id, $sales_order_ids)
{
    if (empty($sales_order_ids)) {
        return;
    }

    try {
        // Convert to array if it's a JSON string
        if (is_string($sales_order_ids)) {
            $sales_order_ids = json_decode($sales_order_ids, true);
        }
        
        if (!is_array($sales_order_ids)) {
            return;
        }

        Log::info("Updating sales order status in cache for IDs: " . implode(',', $sales_order_ids));

        // Get fresh status for these sales orders
        $fresh_statuses = DB::table('transactions')
            ->whereIn('id', $sales_order_ids)
            ->pluck('status', 'id')
            ->toArray();

        // Update all cached pages
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        $updatedCount = 0;
        foreach ($keys as $key) {
            // Only process sales_order cache keys
            if (strpos($key, 'fast_page_v1_sales_order_') === false) {
                continue;
            }
            
            $cached = Cache::get($key);
            if (!$cached || !is_array($cached)) {
                continue;
            }
            
            $updated = false;
            
            // Update sales order status in cache
            foreach ($cached as &$item) {
                if (isset($item['id']) && isset($fresh_statuses[$item['id']])) {
                    $new_status = $fresh_statuses[$item['id']];
                    
                    // Update status field
                    $item['status'] = $new_status;
                    
                    // Update sell_status field (the display status)
                    $item['sell_status'] = $this->getSalesOrderStatusLabel($new_status, true, $item['id']);
                    
                    $updated = true;
                    Log::info("Updated sales order {$item['id']} status to {$new_status} in cache");
                }
            }
            
            if ($updated) {
                Cache::put($key, $cached, 1800);
                $updatedCount++;
            }
        }
        
        Log::info("Updated {$updatedCount} cache pages with fresh sales order status");

    } catch (\Exception $e) {
        Log::error("Failed to update sales order status in cache: " . $e->getMessage());
    }
}

public function updateCacheAfterEdit($transaction, $transaction_before = null)
{
    try {
        $business_id = $transaction->business_id;
        $sale_type = $transaction->type;

        // For edits, invalidate potentially affected pages
        $this->invalidateEditCaches($business_id, $sale_type, $transaction);

        // SIMPLE FIX: Update sales order status instead of clearing cache
        $all_so_ids = [];
        
        if (!empty($transaction->sales_order_ids)) {
            $current_ids = is_string($transaction->sales_order_ids) 
                ? json_decode($transaction->sales_order_ids, true) 
                : $transaction->sales_order_ids;
            if (is_array($current_ids)) {
                $all_so_ids = array_merge($all_so_ids, $current_ids);
            }
        }
        
        if ($transaction_before && !empty($transaction_before->sales_order_ids)) {
            $before_ids = is_string($transaction_before->sales_order_ids) 
                ? json_decode($transaction_before->sales_order_ids, true) 
                : $transaction_before->sales_order_ids;
            if (is_array($before_ids)) {
                $all_so_ids = array_merge($all_so_ids, $before_ids);
            }
        }
        
        if (!empty($all_so_ids)) {
            $all_so_ids = array_unique($all_so_ids);
            $this->updateSalesOrderStatusInCache($business_id, $all_so_ids);
        }

        Log::info("Smart cache updated after transaction edit: {$transaction->id}");
    } catch (\Exception $e) {
        Log::error("Smart cache update after edit failed: " . $e->getMessage());
    }
}

public function invalidateEditCaches($business_id, $sale_type, $transaction)
{
    try {
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        $invalidatedCount = 0;
        foreach ($keys as $key) {
            // Invalidate first few pages where the transaction might appear
            if (preg_match("/fast_page_v1_{$sale_type}_{$business_id}_([1-5])_/", $key)) {
                Cache::forget($key);
                $invalidatedCount++;
                Log::info("Invalidated edit cache: {$key}");
            }
        }
        
        // Also invalidate meta keys
        foreach ($keys as $key) {
            if (strpos($key, "fast_meta_v1_{$sale_type}_{$business_id}") !== false) {
                Cache::forget($key);
                $invalidatedCount++;
                Log::info("Invalidated meta cache: {$key}");
            }
        }
        
        Log::info("Invalidated {$invalidatedCount} cache keys for edit");
        
    } catch (\Exception $e) {
        Log::error("Edit cache invalidation failed: " . $e->getMessage());
    }
}

/**
 * REMOVE TRANSACTION FROM CACHE: Remove specific transaction from cached pages
 */
public function removeTransactionFromCache($business_id, $transaction_id)
{
    try {
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        $updatedCount = 0;
        foreach ($keys as $key) {
            $cached = Cache::get($key);
            
            if (!$cached || !is_array($cached)) {
                continue;
            }
            
            $originalCount = count($cached);
            
            // Remove transaction from cache
            $cached = array_filter($cached, function($item) use ($transaction_id) {
                return !isset($item['id']) || $item['id'] != $transaction_id;
            });
            
            if (count($cached) < $originalCount) {
                // Re-index array and update cache
                $cached = array_values($cached);
                Cache::put($key, $cached, 1800);
                $updatedCount++;
                Log::info("Removed transaction {$transaction_id} from cache: {$key}");
            }
        }
        
        Log::info("Removed transaction from {$updatedCount} cache entries");
        
    } catch (\Exception $e) {
        Log::error("Remove transaction from cache failed: " . $e->getMessage());
    }
}

/**
 * SMART CACHE UPDATE: Updates cache intelligently instead of clearing everything
 */
private function smartCacheUpdate($business_id, $sale_type, $transaction)
{
    try {
        // 1. Update count caches (increment by 1)
        $this->updateCountCaches($business_id, $sale_type, 1);

        // 2. Only invalidate first page cache (where new record appears)
        $this->invalidateFirstPageCache($business_id, $sale_type);

        // 3. SIMPLE FIX: Update sales order status instead of clearing cache
        if ($sale_type === 'sell' && !empty($transaction->sales_order_ids)) {
            $this->updateSalesOrderStatusInCache($business_id, $transaction->sales_order_ids);
        }
        
        Log::info("Smart cache update completed - only first page invalidated");

    } catch (\Exception $e) {
        Log::error("Smart cache update failed: " . $e->getMessage());
        // Fallback: clear all cache only if smart update fails
        $this->clearSaleTypeCache($business_id, $sale_type);
    }
}


/**
 * UPDATE COUNT CACHES: Increment counts by specified amount
 */
public function updateCountCaches($business_id, $sale_type, $increment = 1)
{
    // Update ultra_count caches
    $patterns = [
        "ultra_count_{$business_id}_{$sale_type}",
        "total_count_{$business_id}_{$sale_type}"
    ];
    
    foreach ($patterns as $pattern) {
        // Get all cache keys and update matching ones
        $allKeys = Cache::get("fast_cache_keys_{$business_id}", []);
        foreach ($allKeys as $key) {
            if (strpos($key, $pattern) !== false) {
                $currentCount = Cache::get($key);
                if ($currentCount !== null) {
                    Cache::put($key, $currentCount + $increment, 300);
                    Log::info("Updated count cache {$key}: +{$increment}");
                }
            }
        }
    }
}

/**
 * INVALIDATE FIRST PAGE: Only clear the first page where new records appear
 */
public function invalidateFirstPageCache($business_id, $sale_type)
{
    try {
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        $invalidatedCount = 0;
        foreach ($keys as $key) {
            // Only invalidate page 1 (first page) where new records appear
            if (strpos($key, "fast_page_v1_{$sale_type}_{$business_id}_1_") !== false) {
                Cache::forget($key);
                $invalidatedCount++;
                Log::info("Invalidated first page cache: {$key}");
            }
        }
        
        // Also invalidate meta keys (they contain total counts)
        foreach ($keys as $key) {
            if (strpos($key, "fast_meta_v1_{$sale_type}_{$business_id}") !== false) {
                Cache::forget($key);
                $invalidatedCount++;
                Log::info("Invalidated meta cache: {$key}");
            }
        }
        
        Log::info("Invalidated {$invalidatedCount} cache keys for first page");
        
    } catch (\Exception $e) {
        Log::error("First page cache invalidation failed: " . $e->getMessage());
    }
}
     /**
     * Build optimized query with filters
     */
    private function buildOptimizedQuery($business_id, $sale_type)
    {
        $query = $this->getOptimizedSellsQuery($business_id, $sale_type);
        $query = $this->applyFiltersOptimized($query, $sale_type);
        return $query;
    }

    /**
     * Apply user permissions to query
     */
    private function applyUserPermissions($query, $sale_type)
    {
        // Apply location permissions
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        // Apply type-specific permissions
        if ($sale_type == 'sales_order') {
            if (!auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                $query->where('transactions.created_by', request()->session()->get('user.id'));
            }
        } else {
            if (!auth()->user()->can('direct_sell.view')) {
                $query->where(function ($q) {
                    if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $q->where('transactions.created_by', request()->session()->get('user.id'));
                    }
                    if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                    }
                });
            }
        }
    }

    /**
     * Apply search filters to query
     */
   private function applySearchToQuery($query, $searchValue)
{
    $sale_type = request()->input('sale_type', 'sell');
    
    $query->where(function($q) use ($searchValue, $sale_type) {
        // Keep your original fast searches
        $q->where('transactions.invoice_no', 'like', "%{$searchValue}%")
          ->orWhere('contacts.name', 'like', "%{$searchValue}%")
          ->orWhere('contacts.mobile', 'like', "%{$searchValue}%")
          ->orWhere('bl.name', 'like', "%{$searchValue}%");
        
        // FAST sales order search - only when pattern suggests it could be a sales order
        if ($sale_type == 'sell' && $this->isLikelySalesOrderPattern($searchValue)) {
            // Step 1: Fast lookup of sales order IDs that match the search
            $salesOrderIds = $this->getCachedSalesOrderIds($searchValue);
            
            // Step 2: If we found matching sales orders, search for transactions that reference them
            if (!empty($salesOrderIds)) {
                $q->orWhere(function($subQ) use ($salesOrderIds) {
                    foreach ($salesOrderIds as $soId) {
                        // Use simple LIKE patterns for JSON/string matching (much faster than FIND_IN_SET)
                        $subQ->orWhere('transactions.sales_order_ids', 'like', "%\"{$soId}\"%")
                             ->orWhere('transactions.sales_order_ids', 'like', "%[{$soId}]%")
                             ->orWhere('transactions.sales_order_ids', 'like', "%,{$soId},%")
                             ->orWhere('transactions.sales_order_ids', 'like', "%,{$soId}]%")
                             ->orWhere('transactions.sales_order_ids', 'like', "%[{$soId},%");
                    }
                });
            }
        }
    });
}
private function isLikelySalesOrderPattern($searchValue)
{
    // Only search sales orders if pattern suggests it could be one
    // Adjust these patterns based on your sales order format
    return (
        // Pattern like "2025/1430" (year/number)
        preg_match('/^\d{4}\/\d+$/', $searchValue) ||
        // Starts with common sales order prefixes
        stripos($searchValue, 'SO') === 0 ||
        stripos($searchValue, 'SALES') === 0 ||
        stripos($searchValue, 'ORDER') === 0 ||
        // Contains year and slash (like 2025/1430)
        preg_match('/\d{4}\//', $searchValue) ||

        // FIX: Also check if it's a number (could be a numeric SO invoice)
        // We check for 4 or more digits to avoid searching on every simple number
        preg_match('/^\d{4,}$/', $searchValue)
    );
}

private function getCachedSalesOrderIds($searchValue)
{
    // Use static cache to avoid repeated queries during same request
    static $cache = [];
    $cacheKey = md5($searchValue);
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    $business_id = request()->session()->get('user.business_id');
    
    // Fast query to get sales order IDs - uses indexes
    $salesOrderIds = DB::table('transactions')
        ->where('business_id', $business_id)
        ->where('type', 'sales_order')
        ->where('invoice_no', 'like', "%{$searchValue}%")
        ->pluck('id')
        ->toArray();
    
    // Cache result for this request
    $cache[$cacheKey] = $salesOrderIds;
    
    return $salesOrderIds;
}
    /**
     * Get count from query efficiently
     */
 private function getCountFromQuery($query)
{
    try {
        // For sales_order with GROUP BY, we need a different approach
        $sale_type = request()->input('sale_type', 'sell');
        
        if ($sale_type == 'sales_order') {
            // Create a subquery to handle GROUP BY correctly
            $subQuery = $query->toSql();
            $bindings = $query->getBindings();
            
            $countQuery = DB::table(DB::raw("({$subQuery}) as sub"))
                ->setBindings($bindings);
            
            return $countQuery->count();
        } else {
            // For regular sells, simple count works
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            // Remove ORDER BY for count query
            $countSql = preg_replace('/\s+order\s+by\s+.+$/i', '', $sql);
            
            return DB::select("SELECT COUNT(*) as count FROM ({$countSql}) as count_table", $bindings)[0]->count;
        }
        
    } catch (\Exception $e) {
        Log::warning('Count query failed: ' . $e->getMessage());
        
        // Fallback: Get approximate count from transactions table
        return DB::table('transactions')
            ->where('business_id', request()->session()->get('user.business_id'))
            ->where('type', request()->input('sale_type', 'sell'))
            ->count();
    }
}

    /**
     * Refresh all sales cache
     */
    private function refreshAllSalesCache($business_id)
    {
        try {
            Log::info("Refreshing all sales cache for business_id: {$business_id}");
            
            // Clear all cache for this business
            $cacheKeysList = Cache::get("sales_cache_keys_list_{$business_id}", []);
            foreach ($cacheKeysList as $key) {
                Cache::forget($key);
            }

            Cache::forget("sales_cache_keys_list_{$business_id}");
            Cache::forget("last_cache_time_{$business_id}_sell");
            Cache::forget("last_cache_time_{$business_id}_sales_order");

            Log::info("Sales cache refresh completed for business_id: {$business_id}");

        } catch (\Exception $e) {
            Log::error("Error refreshing sales cache: " . $e->getMessage());
        }
    }

private function buildFiltersArray()
{
    return [
        'sale_type' => request()->input('sale_type', 'sell'),
        'start_date' => request()->input('start_date'),
        'end_date' => request()->input('end_date'),
        'location_id' => request()->input('location_id'),
        'customer_id' => request()->input('customer_id'),
        'payment_status' => request()->input('payment_status'),
        'sale_status' => request()->input('sale_status'),
        'status' => request()->input('status'),
        'created_by' => request()->input('created_by'),
        'sales_cmsn_agnt' => request()->input('sales_cmsn_agnt'),
        'service_staffs' => request()->input('service_staffs'),
        'shipping_status' => request()->input('shipping_status'),
        'source' => request()->input('source'),
        'only_subscriptions' => request()->input('only_subscriptions') ? 1 : 0,
    ];
}

    private function createActionRowObject($row)
{
    // Handle both array and object input
    if (is_array($row)) {
        return (object)[
            'id' => $row['id'] ?? null,
            'type' => $row['type'] ?? 'sell',
            'is_direct_sale' => isset($row['is_direct_sale']) ? (int)$row['is_direct_sale'] : 1,
            'status' => $row['status'] ?? 'final',
            'sub_status' => $row['sub_status'] ?? null,
            'payment_status' => isset($row['payment_status']) ? strip_tags($row['payment_status']) : '',
            'shipping_status' => $row['shipping_status'] ?? '',
            'document' => $row['document'] ?? '',
            'is_quotation' => $row['is_quotation'] ?? 0,
            'woocommerce_order_id' => $row['woocommerce_order_id'] ?? null,
            'return_exists' => $row['return_exists'] ?? 0,
            'is_recurring' => $row['is_recurring'] ?? 0,
            'recur_parent_id' => $row['recur_parent_id'] ?? null,
            'is_export' => $row['is_export'] ?? 0,
            'crm_is_order_request' => $row['crm_is_order_request'] ?? null,
        ];
    }
    
    // If already object, ensure all properties exist
    return (object)[
        'id' => $row->id ?? null,
        'type' => $row->type ?? 'sell',
        'is_direct_sale' => isset($row->is_direct_sale) ? (int)$row->is_direct_sale : 1,
        'status' => $row->status ?? 'final',
        'sub_status' => $row->sub_status ?? null,
        'payment_status' => isset($row->payment_status) ? strip_tags($row->payment_status) : '',
        'shipping_status' => $row->shipping_status ?? '',
        'document' => $row->document ?? '',
        'is_quotation' => $row->is_quotation ?? 0,
        'woocommerce_order_id' => $row->woocommerce_order_id ?? null,
        'return_exists' => $row->return_exists ?? 0,
        'is_recurring' => $row->is_recurring ?? 0,
        'recur_parent_id' => $row->recur_parent_id ?? null,
        'is_export' => $row->is_export ?? 0,
        'crm_is_order_request' => $row->crm_is_order_request ?? null,
    ];
}

private function processPaymentMethods($row)
{
    // First check if we have payment_methods_raw from the query
    if (isset($row->payment_methods_raw) && !empty($row->payment_methods_raw)) {
        $payment_methods = explode(',', $row->payment_methods_raw);
        $payment_methods = array_unique(array_filter($payment_methods));
    } else {
        // Fallback to separate query if not available
        $payment_methods = DB::table('transaction_payments')
            ->where('transaction_id', $row->id)
            ->pluck('method')
            ->unique()
            ->toArray();
    }
    
    if (empty($payment_methods)) {
        return '';
    }
    
    $business_id = $row->business_id ?? request()->session()->get('user.business_id');
    $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
    
    $count = count($payment_methods);
    $payment_method = '';
    
    if ($count == 1) {
        $payment_method = $payment_types[$payment_methods[0]] ?? $payment_methods[0];
    } elseif ($count > 1) {
        $payment_method = __('lang_v1.checkout_multi_pay');
    }

    return '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>';
}

private function processPaymentStatus($row)
{
    $payment_status = $row->payment_status;
    
    // Create payment status display with proper styling
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

// Update your processRowForResponse method to include payment methods and improved payment status
private function processRowForResponse($row, $is_admin, $is_crm, $sale_type = 'sell', $paymentTotals = [], $paymentMethods = [], $returnData = [], $sellLineTotals = [], $salesOrderInvoices = [])
{
    $is_direct_sale = isset($row->is_direct_sale) ? (int)$row->is_direct_sale : 1;
    $transaction_type = $row->type ?? $sale_type;
    
    $base_data = [
        'id' => $row->id,
        'transaction_date' => $row->transaction_date ? date('m/d/Y H:i', strtotime($row->transaction_date)) : '',
        'invoice_no' => $this->processInvoiceNo($row, $is_crm),
        'conatct_name' => (!empty($row->supplier_business_name) ? $row->supplier_business_name . ', <br>' : '') . ($row->name ?: ''),
        'mobile' => $row->mobile ?: '',
        'business_location' => $row->business_location ?: '',
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
        'delivery_person_name' => $row->delivery_person_name ?: '',
        'shipping_status' => $this->processShippingStatus($row->shipping_status),
        'status' => $row->status ?: '',
        'type' => $transaction_type,
        'is_direct_sale' => $is_direct_sale,
        'is_quotation' => $row->is_quotation ?: 0,
        'sale_date' => $row->transaction_date ? date('Y/m/d', strtotime($row->transaction_date)) : '',
        'document' => $row->document ?: '',
        'sub_status' => $row->sub_status ?: null,
        'woocommerce_order_id' => $row->woocommerce_order_id ?? null,
        'is_recurring' => $row->is_recurring ?: 0,
        'recur_parent_id' => $row->recur_parent_id ?: null,
        'is_export' => $row->is_export ?: 0,
        'crm_is_order_request' => $row->crm_is_order_request ?? null,
    ];

    if ($transaction_type === 'sales_order') {
        $actionRow = $this->createActionRowObject(array_merge($base_data, ['type' => 'sales_order']));
        
        $base_data = array_merge($base_data, [
            'sales_order_invoice' => '', // Sales orders don't have parent sales orders
            'payment_status' => '',
            'payment_methods' => '',
            'total_paid' => '<span class="total-paid" data-orig-value="0">0.00</span>',
            'total_remaining' => '<span class="payment_due" data-orig-value="0">0.00</span>',
            'return_due' => '',
            'sell_status' => $this->getSalesOrderStatusLabel($row->status, $is_admin, $row->id),
            'total_items' => 0,
            'total_qty' => $sellLineTotals[$row->id]['total_qty'] ?? 0,
            'so_qty_remaining' => $sellLineTotals[$row->id]['so_qty_remaining'] ?? 0,
            'product_count_kpi' => $sellLineTotals[$row->id]['product_count_kpi'] ?? 0,
            'tax_amount' => '<span class="total-tax" data-orig-value="0">0.00</span>',
            'discount_amount' => '<span class="total-discount" data-orig-value="0">0.00</span>',
            'total_before_tax' => '<span class="total_before_tax" data-orig-value="0">0.00</span>',
            'amount_return' => 0,
            'delivery_person_name' => $row->delivery_person_name ?: '',
            'delivery_person' => $row->delivery_person_name ?: '',
            'status_ship' => $row->status ?: '',
            'shipping_custom_field_1' => $row->shipping_custom_field_1 ?: '',
            'shipping_custom_field_2' => $row->shipping_custom_field_2 ?: '',
            'shipping_custom_field_3' => $row->shipping_custom_field_3 ?: '',
            'shipping_custom_field_4' => $row->shipping_custom_field_4 ?: '',
            'shipping_custom_field_5' => $row->shipping_custom_field_5 ?: '',
            'return_paid' => 0,
            'return_transaction_id' => null,
            'return_exists' => 0,
            'payment_methods_raw' => '',
            'final_total' => '<span class="final-total" data-orig-value="' . $row->final_total . '">' . round($row->final_total, 2) . '</span>',
            'action' => $this->generateActionButtonsComplete($actionRow, $is_admin, 'sales_order'),
        ]);
    } else {
        // Get data from separate queries
        $total_paid = $paymentTotals[$row->id] ?? 0;
        $payment_methods_raw = $paymentMethods[$row->id] ?? '';
        $return_info = $returnData[$row->id] ?? null;
        $sell_line_info = $sellLineTotals[$row->id] ?? null;

        $total_remaining = $row->final_total - $total_paid;
        
        $return_due_html = '';
        if ($return_info) {
            $return_due = ($return_info['amount_return'] ?? 0) - 0;
            $return_transaction_id = $return_info['return_transaction_id'] ?? null;
            if ($return_transaction_id) {
                $return_due_html = '<a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$return_transaction_id]).'" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="'.$return_due.'">'.round($return_due, 2).'</span></a>';
            } else {
                $return_due_html = '<span class="sell_return_due" data-orig-value="'.$return_due.'">'.round($return_due, 2).'</span>';
            }
        }

        $discount = !empty($row->discount_amount) ? $row->discount_amount : 0;
        if (!empty($discount) && $row->discount_type == 'percentage') {
            $discount = ($row->total_before_tax ?: 0) * ($discount / 100);
        }
        
        $actionRow = $this->createActionRowObject(array_merge($base_data, [
            'type' => $transaction_type,
            'is_direct_sale' => $is_direct_sale
        ]));
        
        // FIX: Get sales_order_invoice from the $salesOrderInvoices parameter
        $sales_order_invoice_display = '';
        if (!empty($row->sales_order_ids) && isset($salesOrderInvoices[$row->sales_order_ids])) {
            $sales_order_invoice_display = $salesOrderInvoices[$row->sales_order_ids];
        }
        
        $base_data = array_merge($base_data, [
            'sales_order_invoice' => $sales_order_invoice_display,
            'payment_status' => $this->processPaymentStatus($row),
            'payment_methods' => $this->processPaymentMethodsFromString($payment_methods_raw),
            'final_total' => '<span class="final-total" data-orig-value="' . $row->final_total . '">'. round($row->final_total, 2) . '</span>',
            'total_paid' => '<span class="total-paid" data-orig-value="' . $total_paid . '">' . round($total_paid, 2) . '</span>',
            'total_remaining' => '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . round($total_remaining, 2) . '</span>',
            'return_due' => $return_due_html,
            'sell_status' => $this->getSellStatusLabel($row->status, $row->sub_status),
            'total_items' => $sell_line_info['total_items'] ?? 0,
            'total_qty' => $sell_line_info['total_qty'] ?? 0,
            'so_qty_remaining' => 0,
            'product_count_kpi' => 0,
            'tax_amount' => '<span class="total-tax" data-orig-value="' . ($row->tax_amount ?: 0) . '">' . round($row->tax_amount ?: 0, 2) . '</span>',
            'discount_amount' => '<span class="total-discount" data-orig-value="'.$discount.'">'.round($discount, 2).'</span>',
            'total_before_tax' => '<span class="total_before_tax" data-orig-value="' . ($row->total_before_tax ?: 0) . '">' . round($row->total_before_tax ?: 0, 2) . '</span>',
            'amount_return' => $return_info['amount_return'] ?? 0,
            'return_paid' => 0,
            'return_transaction_id' => $return_info['return_transaction_id'] ?? null,
            'return_exists' => $return_info ? 1 : 0,
            'payment_methods_raw' => $payment_methods_raw,
            'delivery_person' => $row->delivery_person_name ?: '',
            'status_ship' => $row->status ?: '',
            'shipping_custom_field_1' => $row->shipping_custom_field_1 ?: '',
            'shipping_custom_field_2' => $row->shipping_custom_field_2 ?: '',
            'shipping_custom_field_3' => $row->shipping_custom_field_3 ?: '',
            'shipping_custom_field_4' => $row->shipping_custom_field_4 ?: '',
            'shipping_custom_field_5' => $row->shipping_custom_field_5 ?: '',
            'action' => $this->generateActionButtonsComplete($actionRow, $is_admin, 'sell'),
        ]);
    }
    
    return $base_data;
}

private function processPaymentMethodsFromString($payment_methods_raw)
{
    if (empty($payment_methods_raw)) {
        return '';
    }

    $methods = explode(',', $payment_methods_raw);
    $methods = array_unique(array_filter($methods));
    
    if (count($methods) == 1) {
        $business_id = request()->session()->get('user.business_id');
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
        $method = trim($methods[0]);
        $payment_method = $payment_types[$method] ?? $method;
    } elseif (count($methods) > 1) {
        $payment_method = __('lang_v1.checkout_multi_pay');
    } else {
        return '';
    }

    return '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>';
}

// Helper methods (add these if not exists)
private function processInvoiceNo($row, $is_crm)
{
    $invoice_no = $row->invoice_no;
    if (isset($row->woocommerce_order_id) && !empty($row->woocommerce_order_id)) {
        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
    }
    if (!empty($row->return_exists)) {
        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.some_qty_returned_from_sell').'"><i class="fas fa-undo"></i></small>';
    }
    if (!empty($row->is_recurring)) {
        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.subscribed_invoice').'"><i class="fas fa-recycle"></i></small>';
    }
    if (!empty($row->recur_parent_id)) {
        $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="'.__('lang_v1.subscription_invoice').'"><i class="fas fa-recycle"></i></small>';
    }
    if (!empty($row->is_export)) {
        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
    }
    if ($is_crm && isset($row->crm_is_order_request) && !empty($row->crm_is_order_request)) {
        $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="'.__('crm::lang.order_request').'"><i class="fas fa-tasks"></i></small>';
    }
    
    return $invoice_no;
}


private function processShippingStatus($shipping_status)
{
    if (empty($shipping_status)) {
        return '';
    }
    
    $shipping_statuses = $this->transactionUtil->shipping_statuses();
    $status_color = !empty($this->shipping_status_colors[$shipping_status]) ? $this->shipping_status_colors[$shipping_status] : 'bg-gray';
    
    return '<span class="label ' . $status_color . '">' . 
           ($shipping_statuses[$shipping_status] ?? ucfirst($shipping_status)) . 
           '</span>';
}

public function getSalesOrderStatusLabel($status, $is_admin, $row_id)
{
    $sales_order_statuses = [
        'draft' => ['label' => 'Draft', 'class' => 'bg-gray'],
        'partial' => ['label' => 'Partial', 'class' => 'bg-yellow'],
        'ordered' => ['label' => 'Ordered', 'class' => 'bg-blue'],
        'completed' => ['label' => 'Completed', 'class' => 'bg-green'],
    ];
    
    $status_info = $sales_order_statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-gray'];
    
    if ($is_admin && $status != 'completed') {
        return '<span class="edit-so-status label '.$status_info['class'].'" data-href="'.action([\App\Http\Controllers\SalesOrderController::class, 'getEditSalesOrderStatus'], ['id' => $row_id]).'">'.$status_info['label'].'</span>';
    } else {
        return '<span class="label '.$status_info['class'].'">'.$status_info['label'].'</span>';
    }
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

/**
 * Update the quickCacheUpdate method in your store function
 */
private function quickCacheUpdate($business_id, $transaction)
{
    try {
        // Use the new cache update method
        $this->updateCacheAfterStore($transaction);
        
        Log::info("Quick cache update completed for transaction: {$transaction->id}");
        
    } catch (\Exception $e) {
        // Silent fail - don't break the sale process
        Log::error("Quick cache update failed: " . $e->getMessage());
    }
}

    // Helper methods
  private function getOptimizedSellsQuery($business_id, $sale_type = 'sell')
{
    // OPTIMIZED: Minimal SELECT - only what's displayed, no complex calculations
    $baseSelect = [
        'transactions.id',
        'transactions.transaction_date',
        'transactions.type',
        'transactions.is_direct_sale',
        'transactions.sales_order_ids',
        'transactions.invoice_no',
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
        'transactions.business_id',
        'transactions.created_by',
        'transactions.commission_agent',
        'transactions.location_id',
        'transactions.contact_id',
        'transactions.delivery_person',
        'transactions.res_waiter_id',
        'transactions.res_table_id',
        'transactions.shipping_custom_field_1',
        'transactions.shipping_custom_field_2',
        'transactions.shipping_custom_field_3',
        'transactions.shipping_custom_field_4',
        'transactions.shipping_custom_field_5',
    ];

    // Get contact info - OPTIMIZE: Use LEFT JOIN without subqueries
    $baseSelect = array_merge($baseSelect, [
        'contacts.name',
        'contacts.mobile',
        'contacts.supplier_business_name',
    ]);

    // Get user info
    $baseSelect = array_merge($baseSelect, [
        DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
    ]);

    // Get location name
    $baseSelect = array_merge($baseSelect, [
        'bl.name as business_location',
    ]);

    // Get type of service name
    $baseSelect = array_merge($baseSelect, [
        'tos.name as types_of_service_name',
    ]);

    // Get delivery person
    $baseSelect = array_merge($baseSelect, [
        'dp.first_name as delivery_person_name',
    ]);

    // Get table name
    $baseSelect = array_merge($baseSelect, [
        'tables.name as table_name',
    ]);

    // Get waiter name
    $baseSelect = array_merge($baseSelect, [
        DB::raw("CONCAT(COALESCE(ss.surname, ''),' ',COALESCE(ss.first_name, ''),' ',COALESCE(ss.last_name,'')) as waiter"),
    ]);

    $query = DB::table('transactions')
        ->select($baseSelect)
        ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
        ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
        ->leftJoin('users as ss', 'transactions.res_waiter_id', '=', 'ss.id')
        ->leftJoin('users as dp', 'transactions.delivery_person', '=', 'dp.id')
        ->join('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
        ->leftJoin('types_of_services as tos', 'transactions.types_of_service_id', '=', 'tos.id')
        ->leftJoin('res_tables as tables', 'transactions.res_table_id', '=', 'tables.id');

    // NO COMPLEX SUBQUERIES - Get totals from separate queries in PHP
    // This removes the heavy payment_totals, return_data, sell_lines JOINs

    $query->where('transactions.business_id', $business_id)
        ->where('transactions.type', $sale_type);

    if (Schema::hasColumn('transactions', 'deleted_at')) {
        $query->whereNull('transactions.deleted_at');
    }

    if ($sale_type == 'sell') {
        $query->whereIn('transactions.status', ['final', 'draft']);
    }

    $query->orderBy('transactions.transaction_date', 'desc')
        ->orderBy('transactions.id', 'desc');

    return $query;
}

// NEW: Get payment totals separately (very fast simple query)
private function getPaymentTotals($transactionIds)
{
    if (empty($transactionIds)) {
        return [];
    }

    return DB::table('transaction_payments')
        ->whereIn('transaction_id', $transactionIds)
        ->select('transaction_id', DB::raw('SUM(IF(is_return = 1, -1 * amount, amount)) as total_paid'))
        ->groupBy('transaction_id')
        ->pluck('total_paid', 'transaction_id')
        ->toArray();
}

// NEW: Get payment methods separately
private function getPaymentMethods($transactionIds)
{
    if (empty($transactionIds)) {
        return [];
    }

    return DB::table('transaction_payments')
        ->whereIn('transaction_id', $transactionIds)
        ->select('transaction_id', DB::raw('GROUP_CONCAT(DISTINCT method) as payment_methods_raw'))
        ->groupBy('transaction_id')
        ->pluck('payment_methods_raw', 'transaction_id')
        ->toArray();
}

// NEW: Get return data separately (very fast)
private function getReturnData($transactionIds)
{
    if (empty($transactionIds)) {
        return [];
    }

    $results = DB::table('transactions')
        ->whereIn('return_parent_id', $transactionIds)
        ->whereNotNull('return_parent_id')
        ->select('return_parent_id', DB::raw('COUNT(*) as return_count'), DB::raw('SUM(final_total) as amount_return'), DB::raw('MAX(id) as return_transaction_id'))
        ->groupBy('return_parent_id')
        ->get();

    $returnData = [];
    foreach ($results as $item) {
        // Convert stdClass to array
        $returnData[$item->return_parent_id] = [
            'return_count' => $item->return_count,
            'amount_return' => $item->amount_return,
            'return_transaction_id' => $item->return_transaction_id,
        ];
    }
    return $returnData;
}

// NEW: Get sell line totals separately
private function getSellLineTotals($transactionIds)
{
    if (empty($transactionIds)) {
        return [];
    }

    $results = DB::table('transaction_sell_lines')
        ->whereIn('transaction_id', $transactionIds)
        ->whereNull('parent_sell_line_id')
        ->select('transaction_id', DB::raw('COUNT(DISTINCT id) as total_items'), DB::raw('SUM(quantity) as total_qty'))
        ->groupBy('transaction_id')
        ->get();

    $sellLineTotals = [];
    foreach ($results as $item) {
        // Convert stdClass to array
        $sellLineTotals[$item->transaction_id] = [
            'total_items' => $item->total_items,
            'total_qty' => $item->total_qty,
            'so_qty_remaining' => 0,
            'product_count_kpi' => 0,
        ];
    }
    return $sellLineTotals;
}
private function getSalesOrderInvoices($salesOrderIdsList)
{
    if (empty($salesOrderIdsList)) {
        return [];
    }

    $result = [];
    foreach ($salesOrderIdsList as $soIds) {
        if (empty($soIds)) {
            $result[$soIds] = '';
            continue;
        }
        
        $ids = is_string($soIds) ? json_decode($soIds, true) : $soIds;
        if (!is_array($ids) || empty($ids)) {
            $result[$soIds] = '';
            continue;
        }

        $invoices = DB::table('transactions')
            ->whereIn('id', $ids)
            ->where('type', 'sales_order')
            ->pluck('invoice_no')
            ->toArray();

        $result[$soIds] = !empty($invoices) ? implode(', ', $invoices) : '';
    }
    return $result;
}

private function getUltraFastCount($business_id, $sale_type, $hasFilters = false)
{
    // FIXED: Use filter-specific cache key
    $filters = $this->buildFiltersArray();
    $filterHash = md5(serialize(array_filter($filters, function($value) {
        return !empty($value);
    })));
    
    $cacheKey = "ultra_count_{$business_id}_{$sale_type}_{$filterHash}";
    
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Get count from optimized query
    $query = $this->buildOptimizedQuery($business_id, $sale_type);
    $this->applyUserPermissions($query, $sale_type);
    $count = $this->getCountFromQuery(clone $query);

    // Cache for 5 minutes
    Cache::put($cacheKey, $count, 300);
    
    return $count;
}

   private function applyFiltersOptimized($query, $sale_type = 'sell')
{
    // Date range filter - FIXED: Use parameter binding
    if (!empty(request()->start_date) && !empty(request()->end_date)) {
        $query->whereDate('transactions.transaction_date', '>=', request()->start_date)
              ->whereDate('transactions.transaction_date', '<=', request()->end_date);
    }

    // Location filter
    if (!empty(request()->input('location_id'))) {
        $query->where('transactions.location_id', request()->input('location_id'));
    }

    // Customer filter
    if (!empty(request()->input('customer_id'))) {
        $query->where('contacts.id', request()->input('customer_id'));
    }

    // Payment status filter
    if ($sale_type == 'sell' && !empty(request()->input('payment_status'))) {
        $paymentStatus = request()->input('payment_status');
        
        if ($paymentStatus == 'overdue') {
            $query->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type = 'days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        } else {
            // Handle comma-separated payment statuses (e.g., "due,partial")
            if (strpos($paymentStatus, ',') !== false) {
                $statuses = array_map('trim', explode(',', $paymentStatus));
                $query->whereIn('transactions.payment_status', $statuses);
            } else {
                $query->where('transactions.payment_status', $paymentStatus);
            }
        }
    }

    // Status filter
    if (!empty(request()->input('status'))) {
        $query->where('transactions.status', request()->input('status'));
    }

    // Sale status filter
    if (!empty(request()->input('sale_status'))) {
        $sale_status = request()->input('sale_status');
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

    // Other filters
    if (!empty(request()->input('created_by'))) {
        $query->where('transactions.created_by', request()->input('created_by'));
    }

    if (!empty(request()->input('sales_cmsn_agnt'))) {
        $query->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
    }

    if (!empty(request()->input('service_staffs'))) {
        $query->where('transactions.res_waiter_id', request()->input('service_staffs'));
    }

    if (!empty(request()->input('shipping_status'))) {
        $query->where('transactions.shipping_status', request()->input('shipping_status'));
    }

    if (!empty(request()->input('source'))) {
        if (request()->input('source') == 'woocommerce') {
            try {
                $query->whereNotNull('transactions.woocommerce_order_id');
            } catch (\Exception $e) {
                $query->where('transactions.source', 'woocommerce');
            }
        } else {
            $query->where('transactions.source', request()->input('source'));
        }
    }

    if (request()->only_subscriptions) {
        $query->where(function ($q) {
            $q->whereNotNull('transactions.recur_parent_id')
                ->orWhere('transactions.is_recurring', 1);
        });
    }

    // Only shipments filter - show only transactions that have a shipping status set
    if (request()->input('only_shipments')) {
        $query->whereNotNull('transactions.shipping_status')
              ->where('transactions.shipping_status', '!=', '');
    }

    // Delivery person filter
    if (!empty(request()->input('delivery_person'))) {
        $query->where('transactions.delivery_person', request()->input('delivery_person'));
    }

    return $query;
}
   private function generateActionButtonsComplete($row, $is_admin, $sale_type)
{
    $only_shipments = false;
    
    $html = '<div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                    data-toggle="dropdown" aria-expanded="false">'.
                    __('messages.actions').
                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                
    if ($row->type == 'sales_order') {
        if (($row->status != 'completed') && (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access'))) {
            $html .= '<li><a href="'.route('sell.createFromSalesOrder', [$row->id]).'"><i class="fas fa-plus-circle"></i> '.__('Add Sale Invoice').'</a></li>';
        }
    }
    
    if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
    }
    
    if (!$only_shipments) {
        // ====== CRITICAL FIX: PROPER POS ROUTING ======
        // Step 1: Check if this is a POS transaction (is_direct_sale == 0)
        if ($row->is_direct_sale == 0) {
            if (auth()->user()->can('sell.update')) {
                // Route POS transactions to SellPosController::edit()
                $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
            }
        }
        // Step 2: Check if this is a Sales Order
        elseif ($row->type == 'sales_order') {
            if (auth()->user()->can('so.update')) {
                // Route Sales Orders to SellController::edit()
                $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
            }
        }
        // Step 3: Direct Sell (is_direct_sale == 1)
        else {
            if (auth()->user()->can('direct_sell.update')) {
                // Route Direct Sells to SellController::edit()
                $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
            }
        }

        // ====== DELETE ROUTING (SAME LOGIC) ======
        $delete_link_pos = '<li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i> '.__('messages.delete').'</a></li>';
        $delete_link_sell = '<li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i> '.__('messages.delete').'</a></li>';
        
        if ($row->is_direct_sale == 0) {
            // Delete POS via SellPosController
            if (auth()->user()->can('sell.delete')) {
                $html .= $delete_link_pos;
            }
        } elseif ($row->type == 'sales_order') {
            // Delete Sales Order via SellController
            if (auth()->user()->can('so.delete')) {
                $html .= $delete_link_sell;
            }
        } else {
            // Delete Direct Sell via SellController
            if (auth()->user()->can('direct_sell.delete')) {
                $html .= $delete_link_sell;
            }
        }
    }

    if (config('constants.enable_download_pdf') && auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
        $html .= '<li><a href="'.route('sell.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_pdf').'</a></li>';

        if (!empty($row->shipping_status)) {
            $html .= '<li><a href="'.route('packing.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_paking_pdf').'</a></li>';
        }
    }

    if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.access')) {
        if (!empty($row->document)) {
            $document_name = !empty(explode('_', $row->document, 2)[1]) ? explode('_', $row->document, 2)[1] : $row->document;
            $html .= '<li><a href="'.url('uploads/documents/'.$row->document).'" download="'.$document_name.'"><i class="fas fa-download" aria-hidden="true"></i>'.__('purchase.download_document').'</a></li>';
            if (function_exists('isFileImage') && isFileImage($document_name)) {
                $html .= '<li><a href="#" data-href="'.url('uploads/documents/'.$row->document).'" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>'.__('lang_v1.view_document').'</a></li>';
            }
        }
    }

    if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>'.__('lang_v1.edit_shipping').'</a></li>';
    }

    if ($row->type == 'sell') {
        if (auth()->user()->can('print_invoice')) {
            $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.print_invoice').'</a></li>
            <li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?package_slip=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.packing_slip').'</a></li>
            <li>
                <a href="' . route('sell.createinvoicedraft', $row->id) . '">
                    <i class="fas fa-edit"></i> '.__('Create Invoice').'
                </a>
            </li>
            <li>
                <a class="print-delivery-label" href="' . route('sell.getDeliveryLabel', $row->id) . '">
                    <i class="fas fa-file-alt"></i> '.__('Delivery Label').'
                </a>
            </li>';

            $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?delivery_note=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.delivery_note').'</a></li>';
        }
        
        $html .= '<li class="divider"></li>';
        
        if (!$only_shipments) {
            if ($row->is_direct_sale == 0 && !auth()->user()->can('sell.update') && auth()->user()->can('edit_pos_payment')) {
                $html .= '<li><a href="'.route('edit-pos-payment', [$row->id]).'"><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_edit_payment').'</a></li>';
            }

            if (auth()->user()->can('sell.payments') || auth()->user()->can('edit_sell_payment') || auth()->user()->can('delete_sell_payment')) {
                $transaction = \DB::table('transactions')
                    ->where('id', $row->id)
                    ->select('payment_status')
                    ->first();
                    
                if ($transaction && $transaction->payment_status != 'paid') {
                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.add_payment').'</a></li>';
                }

                $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).'" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.view_payments').'</a></li>';
            }

            if (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) {
                $html .= '<li><a href="'.action([\App\Http\Controllers\SellReturnController::class, 'add'], [$row->id]).'"><i class="fas fa-undo"></i> '.__('lang_v1.sell_return').'</a></li>
                <li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'showInvoiceUrl'], [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i> '.__('lang_v1.view_invoice_url').'</a></li>';
            }
        }
        
        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_sale']).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>'.__('lang_v1.new_sale_notification').'</a></li>';
    } elseif ($row->type == 'sales_order') {
        if (auth()->user()->can('print_invoice')) {
            $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.print_invoice').'</a></li>';
        }
        
        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'viewMedia'], ['model_id' => $row->id, 'model_type' => \App\Transaction::class, 'model_media_type' => 'shipping_document']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>'.__('lang_v1.shipping_documents').'</a></li>';
    } else {
        $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'viewMedia'], ['model_id' => $row->id, 'model_type' => \App\Transaction::class, 'model_media_type' => 'shipping_document']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>'.__('lang_v1.shipping_documents').'</a></li>';
    }

    $html .= '</ul></div>';

    return $html;
}

/**
 * Avoid Sell: identical to index() but lists only soft-deleted sells.
 */
public function avoidSellIndex()
{
    if (!auth()->user()->can('sell.view')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    // Handle AJAX request for DataTable
    if (request()->ajax()) {
        $query = $this->buildOptimizedQuery($business_id, 'sell');
        
        $query->addSelect('transactions.ref_no');

        // Remove the default 'where deleted_at is null' condition
        if (property_exists($query, 'wheres') && is_array($query->wheres)) {
            $query->wheres = collect($query->wheres)
                ->reject(function ($w) {
                    return isset($w['type'], $w['column'])
                        && strtolower($w['type']) === 'null'
                        && $w['column'] === 'transactions.deleted_at';
                })
                ->values()
                ->all();
        }

        // Add the condition to fetch ONLY soft-deleted (voided) sells
        $query->whereNotNull('transactions.deleted_at');

        $query = $this->applyFiltersOptimized($query, 'sell');
        $this->applyUserPermissions($query, 'sell');
        $recordsTotalQuery = clone $query;
        $search = request()->get('search');
        $searchValue = $search['value'] ?? '';
        if (!empty($searchValue)) {
            $this->applySearchToQuery($query, $searchValue);
        }
        
        $recordsFiltered = (clone $query)->count();
        $start  = (int) request()->get('start', 0);
        $length = (int) request()->get('length', 25);
        if ($length != -1) {
            $query->offset($start)->limit($length);
        }
        
        $data = $query->get();
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $is_crm   = $this->moduleUtil->isModuleInstalled('Crm');

        $processed_rows = [];
        foreach ($data as $row) {
            $processed_row = $this->processRowForResponse($row, $is_admin, $is_crm, 'sell');
            $processed_row['order_no'] = $row->ref_no ?? '';
            $processed_row['action'] =
            '<button type="button" onclick="showTransactionDetails(' . $row->id . ')" class="btn btn-xs btn-primary">' .
            '<i class="fa fa-eye"></i> ' . __("messages.view") . '</button>';
            $processed_rows[] = $processed_row;
        }
        
        return response()->json([
            'draw'            => intval(request()->get('draw', 1)),
            'recordsTotal'    => $recordsTotalQuery->count(),
            'recordsFiltered' => $recordsFiltered,
            'data'            => $processed_rows,
        ]);
    }

    // For non-AJAX request, load the view with all necessary filter data
    $business_locations = BusinessLocation::forDropdown($business_id, false);
    $customers = Contact::customersDropdown($business_id, false);
    $sales_representative = User::forDropdown($business_id, false, false, true);
    $shipping_statuses = $this->transactionUtil->shipping_statuses();
    $sources = $this->transactionUtil->getSources($business_id);
    $custom_labels = json_decode(session('business.custom_labels'), true);
    $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
    $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
    $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

    // ## THIS IS THE FIX: Added Commission Agent data ##
    $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
    $commission_agents = [];
    if (!empty($is_cmsn_agent_enabled)) {
        $commission_agents = User::forDropdown($business_id, false, true, true);
    }

    return view('sell.avoid_index', compact(
        'business_locations',
        'customers',
        'sales_representative',
        'shipping_statuses',
        'sources',
        'custom_labels',
        'is_tables_enabled',
        'is_service_staff_enabled',
        'is_types_service_enabled',
        'is_cmsn_agent_enabled',
        'commission_agents'
    ));
}

public function updateCacheAfterDelete($business_id, $transaction_id, $sale_type, $sales_order_ids = null)
{
    try {
        // 1. Decrement count caches
        $this->updateCountCaches($business_id, $sale_type, -1);

        // 2. Remove transaction from cached pages
        $this->removeTransactionFromCache($business_id, $transaction_id);

        // 3. SIMPLE FIX: Update sales order status instead of clearing cache
        if (!empty($sales_order_ids)) {
            $this->updateSalesOrderStatusInCache($business_id, $sales_order_ids);
        }

        Log::info("Cache updated after transaction delete: {$transaction_id}");
    } catch (\Exception $e) {
        Log::error("Cache update after delete failed: " . $e->getMessage());
    }
}
    /**
     * Create a sale from sales order
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    // Updated createFromSalesOrder method
    public function createFromSalesOrder($id)
    {
        if (!auth()->user()->can('sell.create') && !auth()->user()->can('direct_sell.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Check if subscribed or not, then check for users quota
        if (!$this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (!$this->moduleUtil->isQuotaAvailable('invoices', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('invoices', $business_id, action([\App\Http\Controllers\SellController::class, 'index']));
        }

        // Get the sales order with all sell_lines (including combo sub-products)
        $sales_order = Transaction::where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->with([
                'sell_lines', // Load all sell_lines including combo sub-products
                'contact', 
                'location'
            ])
            ->findOrFail($id);

        // Check if user has permission to view this sales order
        if (!auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
            if ($sales_order->created_by != request()->session()->get('user.id')) {
                abort(403, 'Unauthorized action.');
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $default_location = $sales_order->location;

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        // Selling Price Group Dropdown
        $price_groups = SellingPriceGroup::forDropdown($business_id);
        $default_price_group_id = !empty($default_location->selling_price_group_id) && array_key_exists($default_location->selling_price_group_id, $price_groups) ? $default_location->selling_price_group_id : null;

        $default_datetime = $this->businessUtil->format_date('now', true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $invoice_schemes = InvoiceScheme::forDropdown($business_id);
        $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        if (!empty($default_location) && !empty($default_location->sale_invoice_scheme_id)) {
            $default_invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                                        ->findOrFail($default_location->sale_invoice_scheme_id);
        }
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        // Types of service
        $types_of_service = [];
        if ($this->moduleUtil->isModuleEnabled('types_of_service')) {
            $types_of_service = TypesOfService::forDropdown($business_id);
        }

        // Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = !empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (!empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $change_return = $this->dummyPaymentLine;

        // Get exchange rate from sales order or default
        $exchange_rate = $sales_order->exchange_rate 
        ? intval($sales_order->exchange_rate)
        : (MiddleCurrency::where('store_id', $business_id)
            ->where('currency_id', 21)
            ->value('exchange_rate') ?? 4000);

        // Get walk-in customer or use sales order customer
        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);
        
        // Override with sales order customer data if available
        if ($sales_order->contact) {
            $walk_in_customer = [
                'id' => $sales_order->contact->id,
                'name' => $sales_order->contact->name,
                'supplier_business_name' => $sales_order->contact->supplier_business_name,
                'mobile' => $sales_order->contact->mobile,
                'balance' => $sales_order->contact->balance ?? 0,
                'pay_term_number' => $sales_order->contact->pay_term_number,
                'pay_term_type' => $sales_order->contact->pay_term_type,
                'price_calculation_type' => $sales_order->contact->price_calculation_type,
                'selling_price_group_id' => $sales_order->contact->selling_price_group_id,
                'contact_address' => $sales_order->contact->contact_address,
                'shipping_address' => $sales_order->contact->shipping_address,
                'is_export' => $sales_order->contact->is_export ?? false,
                'export_custom_field_1' => $sales_order->contact->export_custom_field_1,
                'export_custom_field_2' => $sales_order->contact->export_custom_field_2,
                'export_custom_field_3' => $sales_order->contact->export_custom_field_3,
                'export_custom_field_4' => $sales_order->contact->export_custom_field_4,
                'export_custom_field_5' => $sales_order->contact->export_custom_field_5,
                'export_custom_field_6' => $sales_order->contact->export_custom_field_6,
                'shipping_custom_field_details' => [
                    'shipping_custom_field_1' => $sales_order->shipping_custom_field_1,
                    'shipping_custom_field_2' => $sales_order->shipping_custom_field_2,
                    'shipping_custom_field_3' => $sales_order->shipping_custom_field_3,
                    'shipping_custom_field_4' => $sales_order->shipping_custom_field_4,
                    'shipping_custom_field_5' => $sales_order->shipping_custom_field_5,
                ]
            ];
        }
        
        // Set sale_type to empty string to indicate normal sale creation
        $sale_type = '';
        
        // Set status to empty string
        $status = '';

        // Prepare sales order data for pre-selection and auto-loading
        $selected_sales_order = [
            'id' => $sales_order->id,
            'invoice_no' => $sales_order->invoice_no,
            'text' => $sales_order->invoice_no
        ];

        // Auto-load sales order data by setting these variables for JavaScript
        $auto_load_sales_order_id = $sales_order->id;
        $auto_load_sales_order_invoice = $sales_order->invoice_no;

        // Get enabled modules
        $enabled_modules = !empty(session('business.enabled_modules')) ? session('business.enabled_modules') : [];

        return view('sell.create')
            ->with(compact(
                'business_details',
                'taxes',
                'selected_sales_order',
                'auto_load_sales_order_id',
                'auto_load_sales_order_invoice',
                'walk_in_customer',
                'business_locations',
                'bl_attributes',
                'default_location',
                'commission_agent',
                'types',
                'customer_groups',
                'payment_line',
                'payment_types',
                'price_groups',
                'default_datetime',
                'pos_settings',
                'invoice_schemes',
                'default_invoice_schemes',
                'types_of_service',
                'accounts',
                'shipping_statuses',
                'status',
                'sale_type',
                'statuses',
                'is_order_request_enabled',
                'users',
                'default_price_group_id',
                'change_return',
                'exchange_rate',
                'enabled_modules'
            ));
    }

    public function createinvoicedraft($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
                            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
                            ->whereIn('type', ['sell', 'sales_order'])
                            ->findorfail($id);

        if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $transaction->location_id;
        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::join(
                            'products AS p',
                            'transaction_sell_lines.product_id',
                            '=',
                            'p.id'
                        )
                        ->join(
                            'variations AS variations',
                            'transaction_sell_lines.variation_id',
                            '=',
                            'variations.id'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_id',
                            '=',
                            'pv.id'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                            $join->on('variations.id', '=', 'vld.variation_id')
                                ->where('vld.location_id', '=', $location_id);
                        })
                        ->leftjoin('units', 'units.id', '=', 'p.unit_id')
                        ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                        ->where('transaction_sell_lines.transaction_id', $id)
                        ->with(['warranties', 'so_line'])
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.id as product_id',
                            'p.enable_stock',
                            'p.name as product_actual_name',
                            'p.type as product_type',
                            'pv.name as product_variation_name',
                            'pv.is_dummy as is_dummy',
                            'variations.name as variation_name',
                            'variations.sub_sku',
                            'p.barcode_type',
                            'p.enable_sr_no',
                            'variations.id as variation_id',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'u.short_name as second_unit',
                            'transaction_sell_lines.secondary_unit_quantity',
                            'transaction_sell_lines.tax_id as tax_id',
                            'transaction_sell_lines.item_tax as item_tax',
                            'transaction_sell_lines.unit_price as default_sell_price',
                            'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                            'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                            'transaction_sell_lines.id as transaction_sell_lines_id',
                            'transaction_sell_lines.id',
                            'transaction_sell_lines.quantity as quantity_ordered',
                            'transaction_sell_lines.sell_line_note as sell_line_note',
                            'transaction_sell_lines.parent_sell_line_id',
                            'transaction_sell_lines.lot_no_line_id',
                            'transaction_sell_lines.line_discount_type',
                            'transaction_sell_lines.line_discount_amount',
                            'transaction_sell_lines.res_service_staff_id',
                            'units.id as unit_id',
                            'transaction_sell_lines.sub_unit_id',
                            'transaction_sell_lines.so_line_id',
                            DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
                        )
                        ->get();

        if (! empty($sell_details)) {
            foreach ($sell_details as $key => $value) {
                //If modifier or combo sell line then unset
                if (! empty($sell_details[$key]->parent_sell_line_id)) {
                    unset($sell_details[$key]);
                } else {
                    if ($transaction->status != 'final') {
                        $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                        $sell_details[$key]->qty_available = $actual_qty_avlbl;
                        $value->qty_available = $actual_qty_avlbl;
                    }

                    $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($value->qty_available, false, null, true);
                    $lot_numbers = [];
                    if (request()->session()->get('business.enable_lot_number') == 1) {
                        $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                        foreach ($lot_number_obj as $lot_number) {
                            //If lot number is selected added ordered quantity to lot quantity available
                            if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                                $lot_number->qty_available += $value->quantity_ordered;
                            }

                            $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                            $lot_numbers[] = $lot_number;
                        }
                    }
                    $sell_details[$key]->lot_numbers = $lot_numbers;

                    $sub_unit_data = $this->productUtil->getSubUnits($business_id, $value->unit_id, true, $value->product_id);
                    $sell_details[$key]->unit_details = $sub_unit_data['units'] ?? [];
                    $sell_details[$key]->default_selected_unit = $sub_unit_data['default_selected_unit'] ?? null;

                    if ($this->transactionUtil->isModuleEnabled('modifiers')) {
                        //Add modifier details to sel line details
                        $sell_line_modifiers = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'modifier')
                            ->get();
                        $modifiers_ids = [];
                        if (count($sell_line_modifiers) > 0) {
                            $sell_details[$key]->modifiers = $sell_line_modifiers;
                            foreach ($sell_line_modifiers as $sell_line_modifier) {
                                $modifiers_ids[] = $sell_line_modifier->variation_id;
                            }
                        }
                        $sell_details[$key]->modifiers_ids = $modifiers_ids;

                        //add product modifier sets for edit
                        $this_product = Product::find($sell_details[$key]->product_id);
                        if (count($this_product->modifier_sets) > 0) {
                            $sell_details[$key]->product_ms = $this_product->modifier_sets;
                        }
                    }

                    //Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo')
                            ->get()
                            ->toArray();
                        if (! empty($sell_line_combos)) {
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        //calculate quantity available if combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['quantity'] / $sell_details[$key]->quantity_ordered,
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available =
                        $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available = $sell_details[$key]->qty_available + $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                    
                    //Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo_single') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo_single')
                            ->get()
                            ->toArray();
                        if (! empty($sell_line_combos)) {
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        //calculate quantity available if combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['quantity'] / $sell_details[$key]->quantity_ordered,
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available =
                        $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available = $sell_details[$key]->qty_available + $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                }
            }
        }
        
        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = [];
        if ($this->productUtil->isModuleEnabled('service_staff') && ! empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $sales_orders = [];
        if (! empty($pos_settings['enable_sales_order']) || $is_order_request_enabled) {
            $sales_orders = Transaction::where('business_id', $business_id)
                                ->where('type', 'sales_order')
                                ->where('contact_id', $transaction->contact_id)
                                ->where(function ($q) use ($transaction) {
                                    $q->where('status', '!=', 'completed');

                                    if (! empty($transaction->sales_order_ids)) {
                                        $q->orWhereIn('id', $transaction->sales_order_ids);
                                    }
                                })
                                ->pluck('invoice_no', 'id');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location_id, false, $business_id);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        return view('sell.createinvoice')
            ->with(compact('business_details', 'taxes', 'sell_details', 'transaction', 'commission_agent', 'types', 'customer_groups', 'pos_settings', 'waiters', 'invoice_schemes', 'default_invoice_schemes', 'redeem_details', 'edit_discount', 'edit_price', 'shipping_statuses', 'warranties', 'statuses', 'sales_orders', 'payment_types', 'accounts', 'payment_lines', 'change_return', 'is_order_request_enabled', 'customer_due', 'users'));
        // Pass sale details to the view
    }

    public function getDeliveryLabel(Request $request, $transaction_id)
    {
        $transaction = Transaction::with(['contact', 'sales_person', 'business', 'location', 'delivery_person_user'])->findOrFail($transaction_id);
        
        try {
            $barcodeGenerator = new DNS1D();
            $barcodeBase64 = $barcodeGenerator->getBarcodePNG(
                $transaction->invoice_no,
                'C128',
                3, // Reduce width scale factor
                45, // Reduce height in pixels
                [0, 0, 0],
                true
            );            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    
        $html_content = view('sell.delivery_label', compact('transaction', 'barcodeBase64'))->render();
    
        return response()->json([
            'is_enabled' => true,
            'print_type' => 'browser',
            'html_content' => $html_content,
        ]);
    }
    
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $sale_type = request()->get('sale_type', '');

        if ($sale_type == 'sales_order') {
            if (! auth()->user()->can('so.create')) {
                abort(403, 'Unauthorized action.');
            }
        } else {
            if (! auth()->user()->can('direct_sell.access')) {
                abort(403, 'Unauthorized action.');
            }
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('invoices', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('invoices', $business_id, action([\App\Http\Controllers\SellController::class, 'index']));
        }

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $default_location = null;
        foreach ($business_locations as $id => $name) {
            $default_location = BusinessLocation::findOrFail($id);
            break;
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        //Selling Price Group Dropdown
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $default_price_group_id = ! empty($default_location->selling_price_group_id) && array_key_exists($default_location->selling_price_group_id, $price_groups) ? $default_location->selling_price_group_id : null;

        $default_datetime = $this->businessUtil->format_date('now', true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $invoice_schemes = InvoiceScheme::forDropdown($business_id);
        $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        if (! empty($default_location) && !empty($default_location->sale_invoice_scheme_id)) {
            $default_invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                                        ->findorfail($default_location->sale_invoice_scheme_id);
        }
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        //Types of service
        $types_of_service = [];
        if ($this->moduleUtil->isModuleEnabled('types_of_service')) {
            $types_of_service = TypesOfService::forDropdown($business_id);
        }

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $status = request()->get('status', '');

        $statuses = Transaction::sell_statuses();

        if ($sale_type == 'sales_order') {
            $status = 'ordered';
        }

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $change_return = $this->dummyPaymentLine;

        $exchange_rate = MiddleCurrency::where('store_id', $business_id)
        ->where('currency_id', 21)
        ->value('exchange_rate') ?? 4000;

        return view('sell.create')
            ->with(compact(
                'business_details',
                'taxes',
                'walk_in_customer',
                'business_locations',
                'bl_attributes',
                'default_location',
                'commission_agent',
                'types',
                'customer_groups',
                'payment_line',
                'payment_types',
                'price_groups',
                'default_datetime',
                'pos_settings',
                'invoice_schemes',
                'default_invoice_schemes',
                'types_of_service',
                'accounts',
                'shipping_statuses',
                'status',
                'sale_type',
                'statuses',
                'is_order_request_enabled',
                'users',
                'default_price_group_id',
                'change_return',
                'exchange_rate'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // if (!auth()->user()->can('sell.view') && !auth()->user()->can('direct_sell.access') && !auth()->user()->can('view_own_sell_only')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                            ->pluck('name', 'id');
        $query = Transaction::where('business_id', $business_id)
                    ->where('id', $id)
                    ->with(['contact', 'delivery_person_user', 'sell_lines' => function ($q) {
                        $q->whereNull('parent_sell_line_id');
                    }, 'sell_lines.product', 'sell_lines.product.unit', 'sell_lines.product.second_unit', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'payment_lines', 'sell_lines.modifiers', 'sell_lines.lot_details', 'tax', 'sell_lines.sub_unit', 'table', 'service_staff', 'sell_lines.service_staff', 'types_of_service', 'sell_lines.warranties', 'media']);

        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
            $query->where('transactions.created_by', request()->session()->get('user.id'));
        }

        $sell = $query->firstOrFail();

        $activities = Activity::forSubject($sell)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        $line_taxes = [];
        foreach ($sell->sell_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_sell_line = $this->transactionUtil->recalculateSellLineTotals($business_id, $value);
                $sell->sell_lines[$key] = $formated_sell_line;
            }

            if (! empty($taxes[$value->tax_id])) {
                if (isset($line_taxes[$taxes[$value->tax_id]])) {
                    $line_taxes[$taxes[$value->tax_id]] += ($value->item_tax * $value->quantity);
                } else {
                    $line_taxes[$taxes[$value->tax_id]] = ($value->item_tax * $value->quantity);
                }
            }
        }

        $payment_types = $this->transactionUtil->payment_types($sell->location_id, true);
        $order_taxes = [];
        if (! empty($sell->tax)) {
            if ($sell->tax->is_tax_group) {
                $order_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($sell->tax, $sell->tax_amount));
            } else {
                $order_taxes[$sell->tax->name] = $sell->tax_amount;
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $shipping_status_colors = $this->shipping_status_colors;
        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;

        $statuses = Transaction::sell_statuses();

        if ($sell->type == 'sales_order') {
            $sales_order_statuses = Transaction::sales_order_statuses(true);
            $statuses = array_merge($statuses, $sales_order_statuses);
        }
        $status_color_in_activity = Transaction::sales_order_statuses();
        $sales_orders = $sell->salesOrders();

        return view('sale_pos.show')
            ->with(compact(
                'taxes',
                'sell',
                'payment_types',
                'order_taxes',
                'pos_settings',
                'shipping_statuses',
                'shipping_status_colors',
                'is_warranty_enabled',
                'activities',
                'statuses',
                'status_color_in_activity',
                'sales_orders',
                'line_taxes'
            ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
            return back()
                ->with('status', ['success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ]);
        }

        //Check if return exist then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            return back()->with('status', ['success' => 0,
                'msg' => __('lang_v1.return_exist'), ]);
        }

        $business_id = request()->session()->get('user.business_id');

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
                            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
                            ->whereIn('type', ['sell', 'sales_order'])
                            ->findorfail($id);

        if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $transaction->location_id;
        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::join(
                            'products AS p',
                            'transaction_sell_lines.product_id',
                            '=',
                            'p.id'
                        )
                        ->join(
                            'variations AS variations',
                            'transaction_sell_lines.variation_id',
                            '=',
                            'variations.id'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_id',
                            '=',
                            'pv.id'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                            $join->on('variations.id', '=', 'vld.variation_id')
                                ->where('vld.location_id', '=', $location_id);
                        })
                        ->leftjoin('units', 'units.id', '=', 'p.unit_id')
                        ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                        ->where('transaction_sell_lines.transaction_id', $id)
                        ->with(['warranties', 'so_line'])
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.id as product_id',
                            'p.enable_stock',
                            'p.name as product_actual_name',
                            'p.type as product_type',
                            'pv.name as product_variation_name',
                            'pv.is_dummy as is_dummy',
                            'variations.name as variation_name',
                            'variations.sub_sku',
                            'p.barcode_type',
                            'p.enable_sr_no',
                            'variations.id as variation_id',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'u.short_name as second_unit',
                            'transaction_sell_lines.secondary_unit_quantity',
                            'transaction_sell_lines.tax_id as tax_id',
                            'transaction_sell_lines.item_tax as item_tax',
                            'transaction_sell_lines.unit_price as default_sell_price',
                            'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                            'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                            'transaction_sell_lines.id as transaction_sell_lines_id',
                            'transaction_sell_lines.id',
                            'transaction_sell_lines.quantity as quantity_ordered',
                            'transaction_sell_lines.sell_line_note as sell_line_note',
                            'transaction_sell_lines.parent_sell_line_id',
                            'transaction_sell_lines.lot_no_line_id',
                            'transaction_sell_lines.line_discount_type',
                            'transaction_sell_lines.line_discount_amount',
                            'transaction_sell_lines.res_service_staff_id',
                            'units.id as unit_id',
                            'transaction_sell_lines.sub_unit_id',
                            'transaction_sell_lines.so_line_id',
                            DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
                        )
                        ->get();

        if (! empty($sell_details)) {
            foreach ($sell_details as $key => $value) {
                //If modifier or combo sell line then unset
                if (! empty($sell_details[$key]->parent_sell_line_id)) {
                    unset($sell_details[$key]);
                } else {
                    if ($transaction->status != 'final') {
                        $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                        $sell_details[$key]->qty_available = $actual_qty_avlbl;
                        $value->qty_available = $actual_qty_avlbl;
                    }

                    $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($value->qty_available, false, null, true);
                    $lot_numbers = [];
                    if (request()->session()->get('business.enable_lot_number') == 1) {
                        $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                        foreach ($lot_number_obj as $lot_number) {
                            //If lot number is selected added ordered quantity to lot quantity available
                            if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                                $lot_number->qty_available += $value->quantity_ordered;
                            }

                            $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                            $lot_numbers[] = $lot_number;
                        }
                    }
                    $sell_details[$key]->lot_numbers = $lot_numbers;

                    $sub_unit_data = $this->productUtil->getSubUnits($business_id, $value->unit_id, true, $value->product_id);
                    $sell_details[$key]->unit_details = $sub_unit_data['units'] ?? [];
                    $sell_details[$key]->default_selected_unit = $sub_unit_data['default_selected_unit'] ?? null;

                    if ($this->transactionUtil->isModuleEnabled('modifiers')) {
                        //Add modifier details to sel line details
                        $sell_line_modifiers = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'modifier')
                            ->get();
                        $modifiers_ids = [];
                        if (count($sell_line_modifiers) > 0) {
                            $sell_details[$key]->modifiers = $sell_line_modifiers;
                            foreach ($sell_line_modifiers as $sell_line_modifier) {
                                $modifiers_ids[] = $sell_line_modifier->variation_id;
                            }
                        }
                        $sell_details[$key]->modifiers_ids = $modifiers_ids;

                        //add product modifier sets for edit
                        $this_product = Product::find($sell_details[$key]->product_id);
                        if (count($this_product->modifier_sets) > 0) {
                            $sell_details[$key]->product_ms = $this_product->modifier_sets;
                        }
                    }

                    // Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo')
                            ->get()
                            ->toArray();
                        if (!empty($sell_line_combos)) {
                            // Update combo_products with per-unit quantity
                            foreach ($sell_line_combos as &$combo_line) {
                                $combo_line['qty_required'] = $combo_line['quantity'] / $sell_details[$key]->quantity_ordered;
                            }
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        // Calculate quantity available for combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['qty_required'], // Use per-unit quantity
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available = $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available += $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }

                    // Get details of combo_single items
                    if ($sell_details[$key]->product_type == 'combo_single') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo_single')
                            ->get()
                            ->toArray();
                        if (!empty($sell_line_combos)) {
                            // Update combo_products with per-unit quantity
                            foreach ($sell_line_combos as &$combo_line) {
                                $combo_line['qty_required'] = $combo_line['quantity'] / $sell_details[$key]->quantity_ordered;
                            }
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        // Calculate quantity available for combo_single product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['qty_required'], // Use per-unit quantity
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available = $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available += $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                }
            }
        }
        
        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = [];
        if ($this->productUtil->isModuleEnabled('service_staff') && ! empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $sales_orders = [];
        if (! empty($pos_settings['enable_sales_order']) || $is_order_request_enabled) {
            $sales_orders = Transaction::where('business_id', $business_id)
                                ->where('type', 'sales_order')
                                ->where('contact_id', $transaction->contact_id)
                                ->where(function ($q) use ($transaction) {
                                    $q->where('status', '!=', 'completed');

                                    if (! empty($transaction->sales_order_ids)) {
                                        $q->orWhereIn('id', $transaction->sales_order_ids);
                                    }
                                })
                                ->pluck('invoice_no', 'id');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location_id, false, $business_id);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        // Fetch exchange rate from transactions table and remove decimal part
        $exchange_rate = $transaction->exchange_rate ?? 4000;
        $exchange_rate = intval($exchange_rate);
        
        return view('sell.edit')
            ->with(compact('business_details', 'taxes', 'sell_details', 'transaction', 'commission_agent', 'types', 'customer_groups', 'pos_settings', 'waiters', 'invoice_schemes', 'default_invoice_schemes', 'redeem_details', 'edit_discount', 'edit_price', 'shipping_statuses', 'warranties', 'statuses', 'sales_orders', 'payment_types', 'accounts', 'payment_lines', 'change_return', 'is_order_request_enabled', 'customer_due', 'users','exchange_rate'));
    }

    /**
     * Display a listing sell drafts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDrafts()
    {
        if (! auth()->user()->can('draft.view_all') && ! auth()->user()->can('draft.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.draft')
            ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Display a listing sell quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getQuotations()
    {
        if (! auth()->user()->can('quotation.view_all') && ! auth()->user()->can('quotation.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.quotations')
                ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Send the datatable response for draft or quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDraftDatables()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $is_quotation = request()->input('is_quotation', 0);

            $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

            $sells = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->join(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftJoin('transaction_sell_lines as tsl', function ($join) {
                    $join->on('transactions.id', '=', 'tsl.transaction_id')
                        ->whereNull('tsl.parent_sell_line_id');
                })
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'invoice_no',
                    'contacts.name',
                    'contacts.mobile',
                    'contacts.supplier_business_name',
                    'bl.name as business_location',
                    'is_direct_sale',
                    'sub_status',
                    DB::raw('FORMAT(final_total, 2) as final_total'),
                    DB::raw('COUNT( DISTINCT tsl.id) as total_items'),
                    DB::raw('SUM(tsl.quantity) as total_quantity'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by"),
                    'transactions.is_export'
                );

            if (Schema::hasColumn('transactions', 'deleted_at')) {
                $sells->whereNull('transactions.deleted_at');
            }

            if ($is_quotation == 1) {
                $sells->where('transactions.sub_status', 'quotation');

                if (! auth()->user()->can('quotation.view_all') && auth()->user()->can('quotation.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            } else {
                if (! auth()->user()->can('draft.view_all') && auth()->user()->can('draft.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transaction_date', '>=', $start)
                            ->whereDate('transaction_date', '<=', $end);
            }

            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }

            if ($is_woocommerce) {
                $sells->addSelect('transactions.woocommerce_order_id');
            }

            $sells->groupBy('transactions.id');

            return Datatables::of($sells)
                 ->addColumn(
                    'action', function ($row) {
                        $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                    data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                    <li>
                                    <a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal">
                                        <i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'
                                    </a>
                                    </li>';

                        if (auth()->user()->can('draft.update') || auth()->user()->can('quotation.update')) {
                            if ($row->is_direct_sale == 1) {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            } else {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            }
                        }

                        $html .= '<li>
                                    <a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i>'.__('messages.print').'</a>
                                </li>';

                        if (config('constants.enable_download_pdf')) {
                            $sub_status = $row->sub_status == 'proforma' ? 'proforma' : '';
                            $html .= '<li>
                                        <a href="'.route('quotation.downloadPdf', ['id' => $row->id, 'sub_status' => $sub_status]).'" target="_blank">
                                            <i class="fas fa-print" aria-hidden="true"></i>'.__('lang_v1.download_pdf').'
                                        </a>
                                    </li>';
                        }

                        if ((auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) && config('constants.enable_convert_draft_to_invoice')) {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToInvoice'], [$row->id]).'" class="convert-draft"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_invoice').'</a>
                                    </li>';
                        }

                        if ($row->sub_status != 'proforma') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToProforma'], [$row->id]).'" class="convert-to-proforma"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_proforma').'</a>
                                    </li>';
                        }

                        if (auth()->user()->can('draft.delete') || auth()->user()->can('quotation.delete')) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i>'.__('messages.delete').'</a>
                                </li>';
                        }

                        if ($row->sub_status == 'quotation') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'copyQuotation'],[$row->id]).'" 
                                        class="copy_quotation"><i class="fas fa-copy"></i>'.
                                        __("lang_v1.copy_quotation").'</a>
                                    </li>
                                    <li>
                                        <a href="#" data-href="'.action("\App\Http\Controllers\NotificationController@getTemplate", ["transaction_id" => $row->id,"template_for" => "new_quotation"]).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>' . __("lang_v1.new_quotation_notification") . '
                                        </a>
                                    </li>';

                            $html .= '<li>
                                        <a href="'.action("\App\Http\Controllers\SellPosController@showInvoiceUrl", [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i>'.__("lang_v1.view_quote_url").'</a>
                                    </li>
                                    <li>
                                        <a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_quotation']).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>'.__('lang_v1.new_quotation_notification').'
                                        </a>
                                    </li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    })
                ->removeColumn('id')
                ->editColumn('invoice_no', function ($row) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }

                    if ($row->sub_status == 'proforma') {
                        $invoice_no .= '<br><span class="label bg-gray">'.__('lang_v1.proforma_invoice').'</span>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('transaction_date', '{{ $transaction_date }}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->editColumn('total_quantity', '{{@format_quantity($total_quantity)}}')
                ->editColumn('final_total', '{{($final_total)}}')
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br>@endif {{$name}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('added_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['action', 'invoice_no', 'transaction_date', 'conatct_name'])
                ->make(true);
        }
    }

    /**
     * Creates copy of the requested sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicateSell($id)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $transaction = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell')
                            ->findorfail($id);
            $duplicate_transaction_data = [];
            foreach ($transaction->toArray() as $key => $value) {
                if (! in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $duplicate_transaction_data[$key] = $value;
                }
            }
            $duplicate_transaction_data['status'] = 'draft';
            $duplicate_transaction_data['payment_status'] = null;
            $duplicate_transaction_data['transaction_date'] = \Carbon::now();
            $duplicate_transaction_data['created_by'] = $user_id;
            $duplicate_transaction_data['invoice_token'] = null;

            DB::beginTransaction();
            $duplicate_transaction_data['invoice_no'] = $this->transactionUtil->getInvoiceNumber($business_id, 'draft', $duplicate_transaction_data['location_id']);

            //Create duplicate transaction
            $duplicate_transaction = Transaction::create($duplicate_transaction_data);

            //Create duplicate transaction sell lines
            $duplicate_sell_lines_data = [];

            foreach ($transaction->sell_lines as $sell_line) {
                $new_sell_line = [];
                foreach ($sell_line->toArray() as $key => $value) {
                    if (! in_array($key, ['id', 'transaction_id', 'created_at', 'updated_at', 'lot_no_line_id'])) {
                        $new_sell_line[$key] = $value;
                    }
                }

                $duplicate_sell_lines_data[] = $new_sell_line;
            }

            $duplicate_transaction->sell_lines()->createMany($duplicate_sell_lines_data);

            DB::commit();

            $output = ['success' => 0,
                'msg' => trans('lang_v1.duplicate_sell_created_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        if (! empty($duplicate_transaction)) {
            if ($duplicate_transaction->is_direct_sale == 1) {
                return redirect()->action([\App\Http\Controllers\SellController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            } else {
                return redirect()->action([\App\Http\Controllers\SellPosController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            }
        } else {
            abort(404, 'Not Found.');
        }
    }

    /**
     * Shows modal to edit shipping details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editShipping($id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }
    
        $business_id = request()->session()->get('user.business_id');
        $transaction = Transaction::where('business_id', $business_id)
                                ->with(['media', 'media.uploaded_by_user'])
                                ->findorfail($id);
        $users = User::forDropdown($business_id, false, false, false);
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $activities = Activity::forSubject($transaction)
            ->with(['causer', 'subject'])
            ->where('activity_log.description', 'shipping_edited')
            ->latest()
            ->get();

        // Initialize variables
        $shipping_addresses = [];
        $default_address_id = null;
        $shipping_address_data = [];

        // Fetch shipping addresses for the contact if contact exists
        if ($transaction->contact_id) {
            $addressCollection = ShippingAddress::where('business_id', $business_id)
                ->where('contact_id', $transaction->contact_id)
                ->with('labelShipping')
                ->get();

            if ($addressCollection->isNotEmpty()) {
                $shipping_address_data = $addressCollection->map(function ($address) use (&$default_address_id) {
                    // Set default address ID
                    if ($address->is_default == 1) {
                        $default_address_id = $address->id;
                    }
                    
                    return [
                        'id' => $address->id,
                        'label' => $address->labelShipping ? $address->labelShipping->name : 'Address ' . $address->id,
                        'mobile' => $address->mobile ?? '',
                        'address' => $address->address ?? ''
                    ];
                })->toArray();

                // Create the dropdown options
                $shipping_addresses = collect($shipping_address_data)->pluck('label', 'id')->toArray();
                
                // Use existing shipping_address_id if available, otherwise use default
                if ($transaction->shipping_address_id && isset($shipping_addresses[$transaction->shipping_address_id])) {
                    $default_address_id = $transaction->shipping_address_id;
                } elseif (!$default_address_id && !empty($shipping_address_data)) {
                    // If no default address is set but we have addresses, use the first one
                    $default_address_id = $shipping_address_data[0]['id'];
                }
            }
        }

        return view('sell.partials.edit_shipping')
            ->with(compact(
                'transaction', 
                'shipping_statuses', 
                'activities', 
                'users', 
                'shipping_addresses', 
                'default_address_id', 
                'shipping_address_data'
            ));
    }

    /**
     * Update shipping.
     *
     * @param  Request  $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateShipping(Request $request, $id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'shipping_details', 'shipping_address',
                'shipping_status', 'delivered_to', 'delivery_person', 
                'shipping_custom_field_1', 'shipping_custom_field_2', 
                'shipping_custom_field_3', 'shipping_custom_field_4', 
                'shipping_custom_field_5',
            ]);

            $business_id = $request->session()->get('user.business_id');

            $transaction = Transaction::where('business_id', $business_id)
                                ->findOrFail($id);

            $transaction_before = $transaction->replicate();

            // Handle shipping address selection
            $shipping_address_select = $request->input('shipping_address_select');
            if ($shipping_address_select) {
                $shipping_address = ShippingAddress::where('business_id', $business_id)
                    ->where('contact_id', $transaction->contact_id)
                    ->where('id', $shipping_address_select)
                    ->first();

                if ($shipping_address) {
                    $input['shipping_address_id'] = $shipping_address->id;
                    
                    $full_address = '';
                    if ($shipping_address->mobile) {
                        $full_address .= $shipping_address->mobile . "\n";
                    }
                    if ($shipping_address->address) {
                        $full_address .= $shipping_address->address;
                    }
                    
                    if (!empty($full_address)) {
                        $input['shipping_address'] = trim($full_address);
                    }
                } else {
                    throw new \Exception('Invalid shipping address selected.');
                }
            } else {
                $input['shipping_address_id'] = null;
            }

            // Update the transaction
            $transaction->update($input);

            // Log activity
            $activity_property = ['update_note' => $request->input('shipping_note', '')];
            $this->transactionUtil->activityLog($transaction, 'shipping_edited', $transaction_before, $activity_property);

            // Clear sales cache so index will reload fresh data
            $this->quickCacheUpdate($business_id, $transaction);

            $output = [
                'success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

/**
 * Clear cache for modifications to existing records
 */
public function clearCacheForExistingRecord($business_id, $transaction_id = null)
{
    try {
        // Clear all cached pages since we don't know which page contains the modified record
        $trackingKey = "fast_cache_keys_{$business_id}";
        $keys = Cache::get($trackingKey, []);
        
        $clearedCount = 0;
        foreach ($keys as $key) {
            if (strpos($key, "fast_page_v1_sell_{$business_id}") !== false) {
                Cache::forget($key);
                $clearedCount++;
            }
        }
        
        // Clear meta keys
        foreach ($keys as $key) {
            if (strpos($key, "fast_meta_v1_sell_{$business_id}") !== false) {
                Cache::forget($key);
                $clearedCount++;
            }
        }
        
        // Clear count caches
        $patterns = [
            "ultra_count_{$business_id}_sell",
            "total_count_{$business_id}_sell"
        ];
        
        foreach ($patterns as $pattern) {
            $allKeys = Cache::get("fast_cache_keys_{$business_id}", []);
            foreach ($allKeys as $key) {
                if (strpos($key, $pattern) !== false) {
                    Cache::forget($key);
                    $clearedCount++;
                }
            }
        }
        
        Log::info("Cleared {$clearedCount} cache keys for existing record modification");
        
    } catch (\Exception $e) {
        Log::error("Failed to clear cache for existing record: " . $e->getMessage());
    }
}

    // Add this helper method
    private function clearSalesCache($business_id)
    {
        try {
            $activeCacheKeys = Cache::get("sales_cache_keys_{$business_id}", []);
            
            foreach ($activeCacheKeys as $cacheKey) {
                Cache::forget($cacheKey);
                Cache::forget($cacheKey . '_building');
            }
            
            Cache::forget("sales_cache_keys_{$business_id}");
            Cache::forget("sales_cache_updated_{$business_id}");
            
            Log::info("Sales cache cleared after shipping update for business_id: {$business_id}");
        } catch (\Exception $e) {
            Log::error("Error clearing sales cache: " . $e->getMessage());
        }
    }

    /**
     * Display list of shipments.
     *
     * @return \Illuminate\Http\Response
     */
    public function shipments()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $delevery_person = User::forDropdown($business_id, false, false, true);

        return view('sell.shipments')->with(compact('shipping_statuses'))
                ->with(compact('business_locations', 'customers', 'sales_representative', 'is_service_staff_enabled', 'service_staffs', 'delevery_person'));
    }

    public function viewMedia($model_id)
    {
        if (request()->ajax()) {
            $model_type = request()->input('model_type');
            $business_id = request()->session()->get('user.business_id');

            $query = Media::where('business_id', $business_id)
                        ->where('model_id', $model_id)
                        ->where('model_type', $model_type);

            $title = __('lang_v1.attachments');
            if (! empty(request()->input('model_media_type'))) {
                $query->where('model_media_type', request()->input('model_media_type'));
                $title = __('lang_v1.shipping_documents');
            }

            $medias = $query->get();

            return view('sell.view_media')->with(compact('medias', 'title'));
        }
    }

    public function resetMapping()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        Artisan::call('pos:mapPurchaseSell');

        echo 'Mapping reset success';
        exit;
    }
}
