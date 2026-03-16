<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountReportsController;
use App\Http\Controllers\AccountTypeController;
// use App\Http\Controllers\Auth;
use App\Http\Controllers\AllRingBalanceController;
use App\Http\Controllers\BackUpController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\CashDepositController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedPurchaseReturnController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CurrenciesController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CustomerRingBalanceController;
use App\Http\Controllers\CustomerRingController;
use App\Http\Controllers\DashboardConfiguratorController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\DocumentAndNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GpsTrackingController;
use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportOpeningStockController;
use App\Http\Controllers\ImportProductsController;
use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\Install;
use App\Http\Controllers\InvoiceLayoutController;
use App\Http\Controllers\InvoiceQrsController;
use App\Http\Controllers\InvoiceSchemeController;
use App\Http\Controllers\LabelsController;
use App\Http\Controllers\LedgerDiscountController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\ManageUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSaleVisitController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PurchaseStockReceiveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportSaleTrackingController;
use App\Http\Controllers\Restaurant;
use App\Http\Controllers\RewardExchangeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCommissionAgentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesOrderRewardController;
use App\Http\Controllers\SalesOrderRewardSupllierController;
use App\Http\Controllers\SalesOrderRewardSupllierReceiveController;
use App\Http\Controllers\SalesOrderScanController;
use App\Http\Controllers\SalesOrderVisitController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellingPriceGroupController;
use App\Http\Controllers\SellPosController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockMovementReportController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierCashRingBalanceController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TelegramSettingController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\TypesOfServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationTemplateController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once 'install_r.php';

Route::middleware(['setData'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });

    Auth::routes();

    Route::get('/business/register', [BusinessController::class, 'getRegister'])->name('business.getRegister');
    Route::post('/business/register', [BusinessController::class, 'postRegister'])->name('business.postRegister');
    Route::post('/business/register/check-username', [BusinessController::class, 'postCheckUsername'])->name('business.postCheckUsername');
    Route::post('/business/register/check-email', [BusinessController::class, 'postCheckEmail'])->name('business.postCheckEmail');

    Route::get('/invoice/{token}', [SellPosController::class, 'showInvoice'])
        ->name('show_invoice');
    Route::get('/quote/{token}', [SellPosController::class, 'showInvoice'])
        ->name('show_quote');

    Route::get('/pay/{token}', [SellPosController::class, 'invoicePayment'])
        ->name('invoice_payment');
    Route::post('/confirm-payment/{id}', [SellPosController::class, 'confirmPayment'])
        ->name('confirm_payment');
});

//Routes for authenticated users only
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])->group(function () {
    Route::get('pos/payment/{id}', [SellPosController::class, 'edit'])->name('edit-pos-payment');
    Route::get('service-staff-availability', [SellPosController::class, 'showServiceStaffAvailibility']);
    Route::get('pause-resume-service-staff-timer/{user_id}', [SellPosController::class, 'pauseResumeServiceStaffTimer']);
    Route::get('mark-as-available/{user_id}', [SellPosController::class, 'markAsAvailable']);

    Route::resource('purchase-requisition', PurchaseRequisitionController::class)->except(['edit', 'update']);
    Route::post('/get-requisition-products', [PurchaseRequisitionController::class, 'getRequisitionProducts'])->name('get-requisition-products');
    Route::get('get-purchase-requisitions/{location_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitions']);
    Route::get('get-purchase-requisition-lines/{purchase_requisition_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitionLines']);

    Route::get('/sign-in-as-user/{id}', [ManageUserController::class, 'signInAsUser'])->name('sign-in-as-user');

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/get-totals', [HomeController::class, 'getTotals']);
    Route::get('/home/product-stock-alert', [HomeController::class, 'getProductStockAlert']);
    Route::get('/home/purchase-payment-dues', [HomeController::class, 'getPurchasePaymentDues']);
    Route::get('/home/sales-payment-dues', [HomeController::class, 'getSalesPaymentDues']);
    Route::post('/attach-medias-to-model', [HomeController::class, 'attachMediasToGivenModel'])->name('attach.medias.to.model');
    Route::get('/calendar', [HomeController::class, 'getCalendar'])->name('calendar');

    Route::post('/test-email', [BusinessController::class, 'testEmailConfiguration']);
    Route::post('/test-sms', [BusinessController::class, 'testSmsConfiguration']);
    Route::get('/business/settings', [BusinessController::class, 'getBusinessSettings'])->name('business.getBusinessSettings');
    Route::post('/business/update', [BusinessController::class, 'postBusinessSettings'])->name('business.postBusinessSettings');
    Route::get('/user/profile', [UserController::class, 'getProfile'])->name('user.getProfile');
    Route::post('/user/update', [UserController::class, 'updateProfile'])->name('user.updateProfile');
    Route::post('/user/update-password', [UserController::class, 'updatePassword'])->name('user.updatePassword');

    Route::resource('brands', BrandController::class);

    // Route::resource('payment-account', 'PaymentAccountController');

    Route::resource('tax-rates', TaxRateController::class);

    Route::resource('units', UnitController::class);

    Route::resource('ledger-discount', LedgerDiscountController::class)->only('edit', 'destroy', 'store', 'update');
    
    Route::get('contacts/contracts/{contact_id}', [ContactController::class, 'getCustomerContracts']);
    Route::get('contacts/contract-form/{contact_id}', [ContactController::class, 'getCustomerContractForm']);
    Route::get('contacts/contract-edit/{id}', [ContactController::class, 'editCustomerContract']);
    Route::post('contacts/contract-store', [ContactController::class, 'storeCustomerContract']);
    Route::put('contacts/contract-update/{id}', [ContactController::class, 'updateCustomerContract']);
    Route::delete('contacts/contract-delete/{id}', [ContactController::class, 'deleteCustomerContract']);
    Route::get('/contacts/products-list-contract', [ContactController::class, 'getProductsForContract'])->name('contacts.products-list-contract');
    Route::delete('contacts/contract-media-delete/{id}', [ContactController::class, 'deleteCustomerContractMedia']);
    Route::get('contacts/contract-view/{id}', [ContactController::class, 'showCustomerContract']);

    Route::post('check-mobile', [ContactController::class, 'checkMobile']);
    Route::get('/get-contact-due/{contact_id}', [ContactController::class, 'getContactDue']);
    Route::get('/contacts/payments/{contact_id}', [ContactController::class, 'getContactPayments']);
    Route::get('/contacts/map', [ContactController::class, 'contactMap']);
    Route::get('/contacts/update-status/{id}', [ContactController::class, 'updateStatus']);
    Route::get('/contacts/stock-report/{supplier_id}', [ContactController::class, 'getSupplierStockReport']);
    Route::get('/contacts/ledger', [ContactController::class, 'getLedger']);
    Route::post('/contacts/send-ledger', [ContactController::class, 'sendLedger']);
    Route::get('/contacts/import', [ContactController::class, 'getImportContacts'])->name('contacts.import');
    Route::post('/contacts/import', [ContactController::class, 'postImportContacts']);
    Route::post('/contacts/check-contacts-id', [ContactController::class, 'checkContactId']);
    Route::get('/contacts/customers', [ContactController::class, 'getCustomers']);
    Route::resource('contacts', ContactController::class);
    Route::get('/get-districts/{province_id}', [ContactController::class, 'getDistricts']);
    Route::get('/get-communes/{district_id}', [ContactController::class, 'getCommunes']);    
    // Shipping Address Routes
    Route::get('/contacts/shipping-addresses/{contact_id}', [ContactController::class, 'getShippingAddresses']);
    Route::get('/contacts/shipping-address-form/{contact_id}', [ContactController::class, 'getShippingAddressForm']);
    Route::post('/contacts/shipping-address', [ContactController::class, 'storeShippingAddress']);
    Route::get('/contacts/shipping-address/edit/{id}', [ContactController::class, 'editShippingAddress']);
    Route::put('/contacts/shipping-address/{id}', [ContactController::class, 'updateShippingAddress']);
    Route::delete('/contacts/shipping-address/{id}', [ContactController::class, 'deleteShippingAddress']);
    Route::post('/contacts/shipping-address/set-default/{id}', [ContactController::class, 'setDefaultShippingAddress']);
    Route::post('/contacts/add-shipping-label', [ContactController::class, 'addShippingLabel']);
    Route::get('/contacts/get-default-shipping-map/{contact_id}', [ContactController::class, 'getDefaultShippingMap'])->name('contacts.get-default-shipping-map');

    Route::get('taxonomies-ajax-index-page', [TaxonomyController::class, 'getTaxonomyIndexPage']);
    Route::resource('taxonomies', TaxonomyController::class);

    Route::resource('variation-templates', VariationTemplateController::class);

    Route::get('/products/download-excel', [ProductController::class, 'downloadExcel']);
    Route::post('/products/SyncProduct', [ProductController::class, 'SyncProduct']);
    Route::get('/get_units_by_base_id', [ProductController::class, 'getUnitsByBaseId'])->name('get_units_by_base_id');

    Route::get('/products/stock-history/{id}', [ProductController::class, 'productStockHistory']);
    Route::get('/delete-media/{media_id}', [ProductController::class, 'deleteMedia']);
    Route::post('/products/mass-deactivate', [ProductController::class, 'massDeactivate']);
    Route::get('/products/activate/{id}', [ProductController::class, 'activate']);
    Route::get('/products/view-product-group-price/{id}', [ProductController::class, 'viewGroupPrice']);
    Route::get('/products/add-selling-prices/{id}', [ProductController::class, 'addSellingPrices']);
    Route::post('/products/save-selling-prices', [ProductController::class, 'saveSellingPrices']);
    Route::post('/products/mass-delete', [ProductController::class, 'massDestroy']);
    Route::get('/products/view/{id}', [ProductController::class, 'view']);
    Route::get('/products/list', [ProductController::class, 'getProducts']);
    Route::get('/products/list-no-variation', [ProductController::class, 'getProductsWithoutVariations']);
    Route::post('/products/bulk-edit', [ProductController::class, 'bulkEdit']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/bulk-update-location', [ProductController::class, 'updateProductLocation']);
    Route::get('/products/get-product-to-edit/{product_id}', [ProductController::class, 'getProductToEdit']);
    Route::post('/products/refresh-stock-data', [ProductController::class, 'refreshStockData'])->name('products.refresh-stock-data');
    Route::get('/products/stock-refresh-progress', [ProductController::class, 'getStockRefreshProgress']);

    Route::post('/products/get_sub_categories', [ProductController::class, 'getSubCategories']);
    Route::get('/products/get_sub_units', [ProductController::class, 'getSubUnits']);
    Route::post('/products/product_form_part', [ProductController::class, 'getProductVariationFormPart']);
    Route::post('/products/get_product_variation_row', [ProductController::class, 'getProductVariationRow']);
    Route::post('/products/get_variation_template', [ProductController::class, 'getVariationTemplate']);
    Route::get('/products/get_variation_value_row', [ProductController::class, 'getVariationValueRow']);
    Route::post('/products/check_product_sku', [ProductController::class, 'checkProductSku']);
    Route::post('/products/validate_variation_skus', [ProductController::class, 'validateVaritionSkus']); //validates multiple skus at once
    Route::get('/products/quick_add', [ProductController::class, 'quickAdd']);
    Route::post('/products/save_quick_product', [ProductController::class, 'saveQuickProduct']);
    Route::get('/products/get-combo-product-entry-row', [ProductController::class, 'getComboProductEntryRow']);
    Route::post('/products/toggle-woocommerce-sync', [ProductController::class, 'toggleWooCommerceSync']);
    Route::post('/products/update-kpi', [ProductController::class, 'updateKpi'])->name('products.update-kpi');

    Route::resource('products', ProductController::class);

    Route::get('/toggle-subscription/{id}', [SellPosController::class, 'toggleRecurringInvoices']);
    Route::post('/sells/pos/get-types-of-service-details', [SellPosController::class, 'getTypesOfServiceDetails']);
    Route::get('/sells/subscriptions', [SellPosController::class, 'listSubscriptions']);
    Route::get('/sells/duplicate/{id}', [SellController::class, 'duplicateSell']);
    Route::get('/sells/drafts', [SellController::class, 'getDrafts']);
    Route::get('/sells/convert-to-draft/{id}', [SellPosController::class, 'convertToInvoice']);
    Route::get('/sells/convert-to-proforma/{id}', [SellPosController::class, 'convertToProforma']);
    Route::get('/sells/quotations', [SellController::class, 'getQuotations']);
    Route::get('/sells/draft-dt', [SellController::class, 'getDraftDatables']);
    Route::resource('sells', SellController::class)->except(['show']);
    Route::get('/sells/copy-quotation/{id}', [SellPosController::class, 'copyQuotation']);
    Route::get('/sell/create-from-sales-order/{id}', [SellController::class, 'createFromSalesOrder'])->name('sell.createFromSalesOrder');
    Route::get('/get-sales-order-lines-for-create', [SellController::class, 'getSalesOrderLinesForCreate']);
    Route::get('/sells/create-invoice/{id}', [SellController::class, 'createinvoicedraft'])->name('sell.createinvoicedraft');
    Route::get('/sells/delivery-label/{id}', [SellController::class, 'getDeliveryLabel'])->name('sell.getDeliveryLabel');

    Route::post('/import-purchase-products', [PurchaseController::class, 'importPurchaseProducts']);
    Route::post('/purchases/update-status', [PurchaseController::class, 'updateStatus']);
    Route::get('/purchases/get_products', [PurchaseController::class, 'getProducts']);
    Route::get('/purchases/get_suppliers', [PurchaseController::class, 'getSuppliers']);
    Route::post('/purchases/get_purchase_entry_row', [PurchaseController::class, 'getPurchaseEntryRow']);
    Route::post('/purchases/check_ref_number', [PurchaseController::class, 'checkRefNumber']);
    Route::resource('purchases', PurchaseController::class)->except(['show']);

    Route::get('/import-sales', [ImportSalesController::class, 'index']);
    Route::post('/import-sales/preview', [ImportSalesController::class, 'preview']);
    Route::post('/import-sales', [ImportSalesController::class, 'import']);
    Route::get('/revert-sale-import/{batch}', [ImportSalesController::class, 'revertSaleImport']);

    Route::get('/sells/pos/get_product_row/{variation_id}/{location_id}', [SellPosController::class, 'getProductRow']);
    Route::post('/sells/pos/get_payment_row', [SellPosController::class, 'getPaymentRow']);
    Route::post('/sells/pos/get-reward-details', [SellPosController::class, 'getRewardDetails']);
    Route::get('/sells/pos/get-recent-transactions', [SellPosController::class, 'getRecentTransactions']);
    Route::get('/sells/pos/get-product-suggestion', [SellPosController::class, 'getProductSuggestion']);
    Route::get('/sells/pos/get-featured-products/{location_id}', [SellPosController::class, 'getFeaturedProducts']);
    Route::get('/reset-mapping', [SellController::class, 'resetMapping']);

    Route::post('/sells/pos/get_customer_group_price', [SellPosController::class, 'getCustomerGroupPrice'])->name('sells.pos.get_customer_group_price');
    
    Route::resource('pos', SellPosController::class);

    Route::resource('roles', RoleController::class);

    Route::get('users/get-districts', [ManageUserController::class, 'getDistricts'])
        ->name('users.get-districts');

    Route::get('users/get-communes', [ManageUserController::class, 'getCommunes'])
        ->name('users.get-communes');

    Route::resource('users', ManageUserController::class);

    Route::resource('group-taxes', GroupTaxController::class);

    Route::get('/barcodes/set_default/{id}', [BarcodeController::class, 'setDefault']);
    Route::resource('barcodes', BarcodeController::class);

    //Invoice schemes..
    Route::get('/invoice-schemes/set_default/{id}', [InvoiceSchemeController::class, 'setDefault']);
    Route::resource('invoice-schemes', InvoiceSchemeController::class);

    //Print Labels
    Route::get('/labels/show', [LabelsController::class, 'show']);
    Route::get('/labels/add-product-row', [LabelsController::class, 'addProductRow']);
    Route::get('/labels/preview', [LabelsController::class, 'preview']);

    //Reports...
    Route::get('/reports/gst-purchase-report', [ReportController::class, 'gstPurchaseReport']);
    Route::get('/reports/gst-sales-report', [ReportController::class, 'gstSalesReport']);
    Route::get('/reports/get-stock-by-sell-price', [ReportController::class, 'getStockBySellingPrice']);
    Route::get('/reports/purchase-report', [ReportController::class, 'purchaseReport']);
    Route::get('/reports/sale-report', [ReportController::class, 'saleReport']);
    Route::get('/reports/service-staff-report', [ReportController::class, 'getServiceStaffReport']);
    Route::get('/reports/service-staff-line-orders', [ReportController::class, 'serviceStaffLineOrders']);
    Route::get('/reports/table-report', [ReportController::class, 'getTableReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'getProfitLoss']);
    Route::get('/reports/get-opening-stock', [ReportController::class, 'getOpeningStock']);
    Route::get('/reports/purchase-sell', [ReportController::class, 'getPurchaseSell']);
    Route::get('/reports/customer-supplier', [ReportController::class, 'getCustomerSuppliers']);
    Route::get('/reports/stock-report', [ReportController::class, 'getStockReport']);
    Route::get('/reports/stock-details', [ReportController::class, 'getStockDetails']);
    Route::get('/reports/tax-report', [ReportController::class, 'getTaxReport']);
    Route::get('/reports/tax-details', [ReportController::class, 'getTaxDetails']);
    Route::get('/reports/trending-products', [ReportController::class, 'getTrendingProducts']);
    Route::get('/reports/expense-report', [ReportController::class, 'getExpenseReport']);
    Route::get('/reports/stock-adjustment-report', [ReportController::class, 'getStockAdjustmentReport']);
    Route::get('/reports/register-report', [ReportController::class, 'getRegisterReport']);
    Route::get('/reports/sales-representative-report', [ReportController::class, 'getSalesRepresentativeReport']);
    Route::get('/reports/sales-representative-total-expense', [ReportController::class, 'getSalesRepresentativeTotalExpense']);
    Route::get('/reports/sales-representative-total-sell', [ReportController::class, 'getSalesRepresentativeTotalSell']);
    Route::get('/reports/sales-representative-total-commission', [ReportController::class, 'getSalesRepresentativeTotalCommission']);
    Route::get('/reports/stock-expiry', [ReportController::class, 'getStockExpiryReport']);
    Route::get('/reports/stock-expiry-edit-modal/{purchase_line_id}', [ReportController::class, 'getStockExpiryReportEditModal']);
    Route::post('/reports/stock-expiry-update', [ReportController::class, 'updateStockExpiryReport'])->name('updateStockExpiryReport');
    Route::get('/reports/customer-group', [ReportController::class, 'getCustomerGroup']);
    Route::get('/reports/product-purchase-report', [ReportController::class, 'getproductPurchaseReport']);
    Route::get('/reports/product-sell-grouped-by', [ReportController::class, 'productSellReportBy']);
    Route::get('/reports/product-sell-report', [ReportController::class, 'getproductSellReport']);
    Route::get('/reports/product-sell-report-with-purchase', [ReportController::class, 'getproductSellReportWithPurchase']);
    Route::get('/reports/product-sell-grouped-report', [ReportController::class, 'getproductSellGroupedReport']);
    Route::get('/reports/lot-report', [ReportController::class, 'getLotReport']);
    Route::get('/reports/purchase-payment-report', [ReportController::class, 'purchasePaymentReport']);
    Route::get('/reports/sell-payment-report', [ReportController::class, 'sellPaymentReport']);
    Route::get('/reports/product-stock-details', [ReportController::class, 'productStockDetails']);
    Route::get('/reports/adjust-product-stock', [ReportController::class, 'adjustProductStock']);
    Route::get('/reports/get-profit/{by?}', [ReportController::class, 'getProfit']);
    Route::get('/reports/items-report', [ReportController::class, 'itemsReport']);
    Route::get('/reports/get-stock-value', [ReportController::class, 'getStockValue']);
    Route::get('/reports/daily-sales-payment-report', [ReportController::class, 'DailySalesPaymentReport'])->name('reports.daily-sales-payment-report');
    Route::get('/reports/daily-payment-report', [ReportController::class, 'DailyPaymentReport'])->name('reports.daily-payment-report');
    Route::get('/reports/daily-ring-top-up', [ReportController::class, 'DailyRingTopUp'])->name('reports.daily-ring-top-up');

    Route::get('/reports/report-export-center', [ReportController::class, 'ReportExportCenter'])->name('reports.report-export-center');

    Route::get('/reports/sales-report-summary', [ReportController::class, 'SalesReportSummary'])->name('reports.sales-report-summary');

    Route::get('/reports/sales-detail-report', [ReportController::class, 'SaleDetailReport'])->name('reports.sales-detail-report');

    Route::get('/reports/sales-detail-report-sale', [ReportController::class, 'SaleDetailReportForSell'])->name('reports.sales-detail-report-sale');

    Route::get('/reports/claim-report', [ReportController::class, 'ClaimReport'])->name('reports.claim-report');

    Route::get('business-location/activate-deactivate/{location_id}', [BusinessLocationController::class, 'activateDeactivateLocation']);

    //Business Location Settings...
    Route::prefix('business-location/{location_id}')->name('location.')->group(function () {
        Route::get('settings', [LocationSettingsController::class, 'index'])->name('settings');
        Route::post('settings', [LocationSettingsController::class, 'updateSettings'])->name('settings_update');
    });

    Route::get('/reports/sales-order-detail-report', [ReportController::class, 'SalesOrderDetailReport'])->name('reports.sales-order-detail-report');

    //Business Locations...
    Route::post('business-location/check-location-id', [BusinessLocationController::class, 'checkLocationId']);
    Route::resource('business-location', BusinessLocationController::class);

    //currencies
    Route::resource('currencies-settings', CurrencyController::class);

    //Invoice layouts..
    Route::resource('invoice-layouts', InvoiceLayoutController::class);

    //invoice-qrs
    Route::resource('invoice-qrs', InvoiceQrsController::class);
    

    Route::post('get-expense-sub-categories', [ExpenseCategoryController::class, 'getSubCategories']);

    //Expense Categories...
    Route::resource('expense-categories', ExpenseCategoryController::class);

    //Expenses...
    Route::resource('expenses', ExpenseController::class);

    //Transaction payments...
    // Route::get('/payments/opening-balance/{contact_id}', 'TransactionPaymentController@getOpeningBalancePayments');
    Route::get('/payments/show-child-payments/{payment_id}', [TransactionPaymentController::class, 'showChildPayments']);
    Route::get('/payments/view-payment/{payment_id}', [TransactionPaymentController::class, 'viewPayment']);
    Route::get('/payments/add_payment/{transaction_id}', [TransactionPaymentController::class, 'addPayment']);
    Route::get('/payments/pay-contact-due/{contact_id}', [TransactionPaymentController::class, 'getPayContactDue']);
    Route::post('/payments/pay-contact-due', [TransactionPaymentController::class, 'postPayContactDue']);
    Route::resource('payments', TransactionPaymentController::class);

    //Printers...
    Route::resource('printers', PrinterController::class);

    Route::get('/stock-adjustments/remove-expired-stock/{purchase_line_id}', [StockAdjustmentController::class, 'removeExpiredStock']);
    Route::post('/stock-adjustments/get_product_row', [StockAdjustmentController::class, 'getProductRow']);
    Route::resource('stock-adjustments', StockAdjustmentController::class);

    Route::get('/cash-register/register-details', [CashRegisterController::class, 'getRegisterDetails']);
    Route::get('/cash-register/close-register/{id?}', [CashRegisterController::class, 'getCloseRegister']);
    Route::post('/cash-register/close-register', [CashRegisterController::class, 'postCloseRegister']);
    Route::resource('cash-register', CashRegisterController::class);

    //Import products
    Route::get('/import-products', [ImportProductsController::class, 'index']);
    Route::post('/import-products/store', [ImportProductsController::class, 'store']);

    //Sales Commission Agent
    Route::resource('sales-commission-agents', SalesCommissionAgentController::class);

    //Stock Transfer
    Route::get('stock-transfers/print/{id}', [StockTransferController::class, 'printInvoice']);
    Route::post('stock-transfers/update-status/{id}', [StockTransferController::class, 'updateStatus']);
    Route::resource('stock-transfers', StockTransferController::class);

    Route::get('/opening-stock/add/{product_id}', [OpeningStockController::class, 'add']);
    Route::post('/opening-stock/save', [OpeningStockController::class, 'save']);

    //Customer Groups
    Route::resource('customer-group', CustomerGroupController::class);

    //Import opening stock
    Route::get('/import-opening-stock', [ImportOpeningStockController::class, 'index']);
    Route::post('/import-opening-stock/store', [ImportOpeningStockController::class, 'store']);

    //Sell return
    Route::get('validate-invoice-to-return/{invoice_no}', [SellReturnController::class, 'validateInvoiceToReturn']);
    Route::resource('sell-return', SellReturnController::class);
    Route::get('sell-return/get-product-row', [SellReturnController::class, 'getProductRow']);
    Route::get('/sell-return/print/{id}', [SellReturnController::class, 'printInvoice']);
    Route::get('/sell-return/add/{id}', [SellReturnController::class, 'add']);

    //Backup
    Route::get('backup/download/{file_name}', [BackUpController::class, 'download']);
    Route::get('backup/delete/{file_name}', [BackUpController::class, 'delete']);
    Route::resource('backup', BackUpController::class)->only('index', 'create', 'store');

    Route::get('selling-price-group/activate-deactivate/{id}', [SellingPriceGroupController::class, 'activateDeactivate']);
    Route::get('update-product-price', [SellingPriceGroupController::class, 'updateProductPrice'])->name('update-product-price');
    Route::get('index_sync', [ProductController::class, 'index_sync'])->name('index_sync');
    Route::get('export-product-price', [SellingPriceGroupController::class, 'export']);
    Route::post('import-product-price', [SellingPriceGroupController::class, 'import']);

    Route::resource('selling-price-group', SellingPriceGroupController::class);

    Route::resource('notification-templates', NotificationTemplateController::class)->only(['index', 'store']);
    Route::get('notification/get-template/{transaction_id}/{template_for}', [NotificationController::class, 'getTemplate']);
    Route::post('notification/send', [NotificationController::class, 'send']);

    Route::post('/purchase-return/update', [CombinedPurchaseReturnController::class, 'update']);
    Route::get('/purchase-return/edit/{id}', [CombinedPurchaseReturnController::class, 'edit']);
    Route::post('/purchase-return/save', [CombinedPurchaseReturnController::class, 'save']);
    Route::post('/purchase-return/get_product_row', [CombinedPurchaseReturnController::class, 'getProductRow']);
    Route::get('/purchase-return/create', [CombinedPurchaseReturnController::class, 'create']);
    Route::get('/purchase-return/add/{id}', [PurchaseReturnController::class, 'add']);
    Route::resource('/purchase-return', PurchaseReturnController::class)->except('create');

    Route::get('/discount/activate/{id}', [DiscountController::class, 'activate']);
    Route::post('/discount/mass-deactivate', [DiscountController::class, 'massDeactivate']);
    Route::resource('discount', DiscountController::class);

    Route::prefix('account')->group(function () {
        Route::resource('/account', AccountController::class);
        Route::get('/fund-transfer/{id}', [AccountController::class, 'getFundTransfer']);
        Route::post('/fund-transfer', [AccountController::class, 'postFundTransfer']);
        Route::get('/deposit/{id}', [AccountController::class, 'getDeposit']);
        Route::post('/deposit', [AccountController::class, 'postDeposit']);
        Route::get('/close/{id}', [AccountController::class, 'close']);
        Route::get('/activate/{id}', [AccountController::class, 'activate']);
        Route::get('/delete-account-transaction/{id}', [AccountController::class, 'destroyAccountTransaction']);
        Route::get('/edit-account-transaction/{id}', [AccountController::class, 'editAccountTransaction']);
        Route::post('/update-account-transaction/{id}', [AccountController::class, 'updateAccountTransaction']);
        Route::get('/get-account-balance/{id}', [AccountController::class, 'getAccountBalance']);
        Route::get('/balance-sheet', [AccountReportsController::class, 'balanceSheet']);
        Route::get('/trial-balance', [AccountReportsController::class, 'trialBalance']);
        Route::get('/payment-account-report', [AccountReportsController::class, 'paymentAccountReport']);
        Route::get('/link-account/{id}', [AccountReportsController::class, 'getLinkAccount']);
        Route::post('/link-account', [AccountReportsController::class, 'postLinkAccount']);
        Route::get('/cash-flow', [AccountController::class, 'cashFlow']);
    });

    Route::resource('account-types', AccountTypeController::class);

    //Restaurant module
    Route::prefix('modules')->group(function () {
        Route::resource('tables', Restaurant\TableController::class);
        Route::resource('modifiers', Restaurant\ModifierSetsController::class);

        //Map modifier to products
        Route::get('/product-modifiers/{id}/edit', [Restaurant\ProductModifierSetController::class, 'edit']);
        Route::post('/product-modifiers/{id}/update', [Restaurant\ProductModifierSetController::class, 'update']);
        Route::get('/product-modifiers/product-row/{product_id}', [Restaurant\ProductModifierSetController::class, 'product_row']);

        Route::get('/add-selected-modifiers', [Restaurant\ProductModifierSetController::class, 'add_selected_modifiers']);

        Route::get('/kitchen', [Restaurant\KitchenController::class, 'index']);
        Route::get('/kitchen/mark-as-cooked/{id}', [Restaurant\KitchenController::class, 'markAsCooked']);
        Route::post('/refresh-orders-list', [Restaurant\KitchenController::class, 'refreshOrdersList']);
        Route::post('/refresh-line-orders-list', [Restaurant\KitchenController::class, 'refreshLineOrdersList']);

        Route::get('/orders', [Restaurant\OrderController::class, 'index']);
        Route::get('/orders/mark-as-served/{id}', [Restaurant\OrderController::class, 'markAsServed']);
        Route::get('/data/get-pos-details', [Restaurant\DataController::class, 'getPosDetails']);
        Route::get('/orders/mark-line-order-as-served/{id}', [Restaurant\OrderController::class, 'markLineOrderAsServed']);
        Route::get('/print-line-order', [Restaurant\OrderController::class, 'printLineOrder']);
    });

    Route::get('bookings/get-todays-bookings', [Restaurant\BookingController::class, 'getTodaysBookings']);
    Route::resource('bookings', Restaurant\BookingController::class);
    
    Route::get('/cash-deposit', [CashDepositController::class, 'index'])->name('cash_deposit.index');
    Route::post('/cash-deposit', [CashDepositController::class, 'store'])->name('cash_deposit.store');
    Route::post('/cash-deposit/upload-slip', [CashDepositController::class, 'uploadDepositSlip'])->name('cash_deposit.upload_slip');
    Route::get('/cash-deposit/history', [CashDepositController::class, 'getDepositHistory'])->name('cash_deposit.history');
    Route::get('/cash-deposit/{id}/attachments', [CashDepositController::class, 'getDepositAttachments'])->name('cash_deposit.attachments');

    // --- NEW ROUTES ---
    Route::get('/cash-deposit/search-payments', [CashDepositController::class, 'searchPendingPayments'])->name('cash_deposit.search_payments');
    Route::get('/cash-deposit/{id}/edit', [CashDepositController::class, 'edit'])->name('cash_deposit.edit');
    Route::put('/cash-deposit/{id}', [CashDepositController::class, 'update'])->name('cash_deposit.update');
    Route::delete('/cash-deposit/{id}', [CashDepositController::class, 'destroy'])->name('cash_deposit.destroy');
    Route::delete('/cash-deposit/media/{id}', [CashDepositController::class, 'deleteDepositMedia'])->name('cash_deposit.delete_media');
    Route::get('/cash-deposit/{id}/payment-details', [CashDepositController::class, 'getPaymentDetails']);

    Route::resource('customer-contracts-list', \App\Http\Controllers\CustomerContractController::class)->only(['index']);
    Route::get('customer-contracts/get-all', [\App\Http\Controllers\CustomerContractController::class, 'getAllContracts']);
    Route::get('customer-contracts/related-sales/{id}', [\App\Http\Controllers\CustomerContractController::class, 'showRelatedSales']);

    Route::get('/business/purchase-types', [BusinessController::class, 'getPurchaseTypes']);
    Route::post('/business/purchase-types', [BusinessController::class, 'savePurchaseType']);
    Route::post('/business/purchase-types/update', [BusinessController::class, 'updatePurchaseType']);
    Route::delete('/business/purchase-types/{id}', [BusinessController::class, 'deletePurchaseType']);
    Route::get('/business/get-exchange-products', [BusinessController::class, 'getExchangeProducts']);

    Route::resource('types-of-service', TypesOfServiceController::class);
    Route::get('sells/edit-shipping/{id}', [SellController::class, 'editShipping']);
    Route::put('sells/update-shipping/{id}', [SellController::class, 'updateShipping']);
    Route::get('shipments', [SellController::class, 'shipments']);

    Route::post('upload-module', [Install\ModulesController::class, 'uploadModule']);
    Route::delete('manage-modules/destroy/{module_name}', [Install\ModulesController::class, 'destroy']);
    Route::resource('manage-modules', Install\ModulesController::class)
        ->only(['index', 'update']);
    Route::get('regenerate', [Install\ModulesController::class, 'regenerate']);

    Route::resource('warranties', WarrantyController::class);

    Route::resource('dashboard-configurator', DashboardConfiguratorController::class)
        ->only(['edit', 'update']);

    Route::resource('telegram-setting', TelegramSettingController::class);
    Route::post('telegram-setting/{id}/toggle-status', [TelegramSettingController::class, 'toggleStatus'])
        ->name('telegram-setting.toggle-status');

    Route::get('view-media/{model_id}', [SellController::class, 'viewMedia']);
    Route::get('/sales-order-visit-history', [SalesOrderVisitController::class, 'getVisitHistory'])->name('sales-order-visit.history');
    Route::resource('sales_visit', SalesOrderVisitController::class)->only(['index']);
    // Route::post('rewards-exchange', [RewardExchangeController::class, 'store'])->name('reward-exchange.store');
    Route::resource('sales-order-visit', SalesOrderVisitController::class);
    Route::get('/daily-sale-visit-summary', [SalesOrderVisitController::class, 'dailySummary'])->name('daily-sale-visit-summary');
    Route::post('/daily-sale-visit-summary/send-telegram', [SalesOrderVisitController::class, 'sendToTelegram'])->name('daily-sale-visit-summary.send-telegram');
    
    Route::get('/gps-tracking-visit-history', [GpsTrackingController::class, 'getVisitHistory'])->name('gps-tracking.visit-history');

    Route::get('gps-tracking/show-data/{trip_id}', [GpsTrackingController::class, 'getShowData'])->name('gps-tracking.show-data');

    Route::get('/api/osrm-match', function (Illuminate\Http\Request $request) {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $coordinates = $request->get('coordinates');
        
        if (!$coordinates) {
            return response()->json(['error' => 'Coordinates required'], 400);
        }
        
        if (!preg_match('/^[\d\.,;\s-]+$/', $coordinates)) {
            return response()->json(['error' => 'Invalid coordinates format'], 400);
        }
        
        $pairs = explode(';', $coordinates);
        if (count($pairs) > 500) {
            return response()->json(['error' => 'Too many coordinates'], 400);
        }
        
        $osrmUrl = 'http://157.10.73.40:5000/match/v1/driving/' . $coordinates;
        $queryString = $request->getQueryString();
        
        parse_str($queryString, $query);
        unset($query['coordinates']);
        
        if (!empty($query)) {
            $osrmUrl .= '?' . http_build_query($query);
        }
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET'
                ]
            ]);
            
            $response = @file_get_contents($osrmUrl, false, $context);
            
            if ($response === false) {
                \Log::warning('OSRM request failed', ['url' => $osrmUrl]);
                return response()->json(['error' => 'Failed to fetch from OSRM'], 500);
            }
            
            \Log::info('OSRM request successful', [
                'user_id' => auth()->id(),
                'coordinate_pairs' => count($pairs)
            ]);
            
            return response($response, 200)
                ->header('Content-Type', 'application/json')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                
        } catch (\Exception $e) {
            \Log::error('OSRM exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Server error'], 500);
        }
    })->middleware('auth');

    Route::get('gps-tracking', [GpsTrackingController::class, 'index'])->name('gps-tracking.index');
    Route::get('gps-tracking/{id}', [GpsTrackingController::class, 'show'])->name('gps-tracking.show');

    Route::get('/sales-reward/{id}', [SalesOrderRewardController::class, 'show'])->name('sales_reward.show');
    Route::resource('sales_reward', SalesOrderRewardController::class)->only(['index']);
    Route::resource('/sales-reward', SalesOrderRewardController::class);
    Route::get('sales_reward/create', [SalesOrderRewardController::class, 'create'])->name('sales_reward.create');
    Route::post('sales_reward/store', [SalesOrderRewardController::class, 'store'])->name('sales_reward.store');
    Route::get('sales_reward/edit/{id}', [SalesOrderRewardController::class, 'edit'])->name('sales_reward.edit');
    Route::put('sales_reward/update/{id}', [SalesOrderRewardController::class, 'update'])->name('sales_reward.update');
    Route::get('sales_reward/check-transaction', [SalesOrderRewardController::class, 'checkTransaction'])->name('sales_reward.check_transaction');
    Route::post('/sales-reward/check-stock', [SalesOrderRewardController::class, 'checkStock'])->name('sales_reward.check_stock');
    Route::resource('sale-order-scan', SalesOrderScanController::class)
        ->only(['index']);
    Route::delete('/sales-reward/{id}', [SalesOrderRewardController::class, 'destroy'])->name('sales_reward.destroy');
    
    Route::resource('stock-movement-report', StockMovementReportController::class)->only(['index']);

    Route::resource('customer-ring-balance', CustomerRingBalanceController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('sale-order-scan/show/{id}', [SalesOrderScanController::class, 'show'])->name('sale-order-scan.show');
    Route::get('customer-ring-balance/search-product', [CustomerRingBalanceController::class, 'searchProduct'])
        ->name('customer-ring-balance.searchProduct');
    Route::get('customer-ring-balance/customers', [CustomerRingBalanceController::class, 'getCustomerList'])
        ->name('customer-ring-balance.customers');
    Route::get('customer-ring-balance/show/{id}', [CustomerRingBalanceController::class, 'show'])->name('customer-ring-balance.show');
    Route::get('customer-ring-balance/get-ring-units', [CustomerRingBalanceController::class, 'getRingUnits'])->name('customer-ring-balance.getRingUnits');
    Route::get('customer-ring-balance/check-invoice-sell', [CustomerRingBalanceController::class, 'checkInvoiceSell'])->name('customer-ring-balance.checkInvoiceSell');

    Route::resource('all-ring', AllRingBalanceController::class)->only(['index']);
    Route::get('all-ring/getRingStockHistory', [AllRingBalanceController::class, 'getRingStockHistory'])->name('all-ring.getRingStockHistory');
    Route::get('all-ring/{id}', [AllRingBalanceController::class, 'show'])->name('all-ring.show');
    Route::get('all-ring/cash-ring/{id}', [AllRingBalanceController::class, 'showCashRing'])->name('all-ring.showCashRing');
    Route::get('all-ring/get-cash-ring-stock-history/{id}', [AllRingBalanceController::class, 'getCashRingStockHistory'])->name('all-ring.getCashRingStockHistory');

    Route::resource('supplier-cash-ring-balance', SupplierCashRingBalanceController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('supplier-cash-ring-balance/search-product', [SupplierCashRingBalanceController::class, 'searchProduct'])->name('supplier-cash-ring-balance.searchProduct');
    Route::get('supplier-cash-ring-balance/get-product-stock', [SupplierCashRingBalanceController::class, 'getProductStock']);
    Route::get('supplier-cash-ring-balance/show/{id}', [SupplierCashRingBalanceController::class, 'show'])->name('supplier-cash-ring-balance.show');
    Route::get('supplier-cash-ring-balance/{id}/update-status', [SupplierCashRingBalanceController::class, 'showUpdateStatus'])
        ->name('supplier-cash-ring-balance.show-update-status');
    Route::patch('supplier-cash-ring-balance/{id}/update-status', [SupplierCashRingBalanceController::class, 'updateStatus'])
        ->name('supplier-cash-ring-balance.update-status');

    Route::resource('customer-ring', CustomerRingController::class)->only(['index']);
    Route::resource('sale-tracking-report', ReportSaleTrackingController::class)->only(['index']);
    Route::get('sale-tracking-report-data', [ReportSaleTrackingController::class, 'SaleTrackingReport'])->name('sale-tracking-report.data');
    Route::get('competitor-report-data', [ReportSaleTrackingController::class, 'CompetitorReport'])->name('competitor-report.data');

    Route::get('customer-ring/getRingStockHistory', [CustomerRingController::class, 'getRingStockHistory'])
        ->name('customer-ring.getRingStockHistory');

    Route::get('customer-ring/show-ring/{contact_id}', [CustomerRingController::class, 'show_ring'])->name('customer-ring.show_ring');
    Route::get('customer-ring/view/{contact_id}', [CustomerRingController::class, 'show'])->name('customer-ring.show');

    Route::get('customer-ring/contact-details/{contact_id}', [CustomerRingController::class, 'getContactDetails'])->name('customer-ring.contact-details');
    Route::get('/customer-ring/get-ring-balances/{contact_id?}', [CustomerRingController::class, 'getRingBalances'])->name('customer-ring.getRingBalances');
    Route::get('/customer-ring/get-transaction-top-ups/{contact_id?}', [CustomerRingController::class, 'getTransactionTopUps'])->name('customer-ring.getTransactionTopUps');
    Route::get('customer-ring/view-transaction/{id}', [CustomerRingController::class, 'showTransactionTopUp'])->name('customer-ring.showTransactionTopUp');

    Route::get('customer-ring/adjust-stock-products', [CustomerRingController::class, 'getAdjustStockProducts'])
        ->name('customer-ring.adjust-stock-products');

    Route::get('customer-ring/search-products', [CustomerRingController::class, 'searchProducts'])
        ->name('customer-ring.search-products');

    Route::post('customer-ring/adjust-stock', [CustomerRingController::class, 'adjustStock'])
        ->name('customer-ring.adjust-stock');

    Route::resource('warehouse', WarehouseController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('warehouse/show/{id}', [WarehouseController::class, 'show'])->name('warehouse.show');
    Route::put('/warehouse/update-status/{id}', [WarehouseController::class, 'updateStatus'])->name('warehouse.update_status');

    Route::resource('purchase-stock-receive', PurchaseStockReceiveController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('purchase-stock-receive/get-purchase-orders/{contact_id}', [PurchaseStockReceiveController::class, 'getPurchaseOrders']);
    Route::get('purchase-stock-receive/get-purchase-order-details/{purchase_order_id}', [PurchaseStockReceiveController::class, 'getPurchaseOrderDetails']);

    Route::post('/cache-remaining-pages', [SellController::class, 'cacheRemainingPages']);
    Route::post('/cache-chunks', [SellController::class, 'cacheAllPagesInChunks']);
    Route::get('sells/debug-pagination', [SellController::class, 'debugPagination'])->name('sells.debug-pagination');
    Route::get('sells/auto-refresh-check', [SellController::class, 'autoRefreshCheck'])->name('sells.auto-refresh-check');
    Route::post('sells/force-refresh', [SellController::class, 'forceRefresh'])->name('sells.force-refresh');
    Route::get('sells/cache-stats', [SellController::class, 'getCacheStats'])->name('sells.cache-stats');
    Route::post('/sells/calculations', [SellController::class, 'getCalculations'])->name('sells.calculations');
    Route::get('/sells/cache-status', [SellController::class, 'getCacheStatus'])
        ->name('sells.cache.status');
    Route::post('/sells/refresh-cache', [SellController::class, 'refreshCache'])
        ->name('sells.cache.refresh');
    Route::get('/sells/check-cache-updates', [SellController::class, 'checkCacheUpdates'])
        ->name('sells.cache.check-updates');
    Route::post('/sells/clear-cache-refresh', [SellController::class, 'clearCacheOnRefresh'])
        ->name('sells.cache.clear-refresh');
    Route::post('/sells/row-calculations', [SellController::class, 'getRowCalculations'])
        ->name('sell.rowCalculations')
        ->middleware(['auth', 'SetSessionData']);

    Route::post('/sells/row-data', [SellController::class, 'getRowData'])->name('sells.rowData');

    Route::get('sells/avoid', [SellController::class, 'avoidSellIndex'])
        ->name('sells.avoid');

    Route::resource('sale-reward-supplier', SalesOrderRewardSupllierController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('sale-reward-supplier/search-product', [SalesOrderRewardSupllierController::class, 'searchProduct'])->name('sale-reward-supplier.searchProduct');
    Route::get('sale-reward-supplier/get-product', [SalesOrderRewardSupllierController::class, 'getProduct'])->name('sale-reward-supplier.getProduct');
    Route::get('sale-reward-supplier/show/{id}', [SalesOrderRewardSupllierController::class, 'show'])->name('sale-reward-supplier.show');
    Route::delete('sale-reward-supplier/{id}', [SalesOrderRewardSupllierController::class, 'destroy'])->name('sale-reward-supplier.destroy');

    Route::get('sale-reward-supplier-receive/search-product', [SalesOrderRewardSupllierReceiveController::class, 'searchProduct'])->name('sale-reward-supplier-receive.searchProduct');
    Route::get('sale-reward-supplier-receive/get-product', [SalesOrderRewardSupllierReceiveController::class, 'getProduct'])->name('sale-reward-supplier-receive.getProduct');
    Route::get('sale-reward-supplier-receive/fetch-exchange-data', [SalesOrderRewardSupllierReceiveController::class, 'fetchExchangeData'])->name('sale-reward-supplier-receive.fetch-exchange-data');
    Route::resource('sale-reward-supplier-receive', SalesOrderRewardSupllierReceiveController::class);
    Route::get('sale-reward-supplier-receive/{id}/payment', [SalesOrderRewardSupllierReceiveController::class, 'payment'])->name('sale-reward-supplier-receive.payment');
    Route::get('sale-reward-supplier-receive/{id}/status', [SalesOrderRewardSupllierReceiveController::class, 'status'])->name('sale-reward-supplier-receive.status');
    Route::put('sale-reward-supplier-receive/{id}/update-status', [SalesOrderRewardSupllierReceiveController::class, 'update_status'])->name('sale-reward-supplier-receive.update_status');

    Route::get('product_sale_visit', [ProductSaleVisitController::class, 'index'])->name('product_sale_visit.index');
    Route::post('product_sale_visit/update', [ProductSaleVisitController::class, 'updateSaleVisit'])->name('product_sale_visit.update');

    Route::get('rewards_exchange', [RewardExchangeController::class, 'index'])->name('rewards_exchange.index');
    Route::get('reward-exchange/create', [RewardExchangeController::class, 'create'])->name('reward-exchange.create');
    Route::post('reward-exchange/store', [RewardExchangeController::class, 'store'])->name('reward-exchange.store');
    Route::get('rewards_exchange/edit/{id}', [RewardExchangeController::class, 'edit'])->name('rewards_exchange.edit');
    Route::put('rewards_exchange/update/{id}', [RewardExchangeController::class, 'update'])->name('rewards_exchange.update');
    Route::delete('rewards_exchange/destroy/{id}', [RewardExchangeController::class, 'destroy'])->name('rewards_exchange.destroy');
    Route::get('search-product', [RewardExchangeController::class, 'searchProduct'])->name('search-product');
    Route::get('rewards_exchange/check_product', [RewardExchangeController::class, 'checkProduct'])->name('rewards_exchange.check_product');
    Route::get('ring-units', [RewardExchangeController::class, 'ringUnits'])->name('ring-units.index');
    Route::get('ring-units/create', [RewardExchangeController::class, 'createRingUnits'])->name('ring-units.create');
    Route::post('ring-units/store', [RewardExchangeController::class, 'storeRingUnits'])->name('ring-units.store');
    Route::get('search-product-ring', [RewardExchangeController::class, 'searchProductRing'])->name('search-product-ring');
    Route::get('/ring-units/{id}/edit', [RewardExchangeController::class, 'editRingUnits'])->name('ring-units.edit');
    Route::put('/ring-units/{id}', [RewardExchangeController::class, 'updateRingUnits'])->name('ring-units.update');
    Route::get('/ring-units/check-product', [RewardExchangeController::class, 'checkProductRing'])->name('ring-units.check-product');
    Route::delete('/ring-units/destroy/{id}', [RewardExchangeController::class, 'destroyRingUnits'])->name('ring-units.destroy');
    Route::get('/rewards-exchange/cash-ring', [RewardExchangeController::class, 'cashRingIndex'])->name('cash-ring.index');
    Route::get('/rewards-exchange/cash-ring/create', [RewardExchangeController::class, 'createCashRing'])->name('cash-ring.create');
    Route::post('/rewards-exchange/cash-ring', [RewardExchangeController::class, 'storeCashRing'])->name('cash-ring.store');
    Route::get('/rewards-exchange/cash-ring/{id}/edit', [RewardExchangeController::class, 'editCashRing'])->name('cash-ring.edit');
    Route::put('/rewards-exchange/cash-ring/{id}', [RewardExchangeController::class, 'updateCashRing'])->name('cash-ring.update');
    Route::get('search-product-ring-cash', [RewardExchangeController::class, 'searchProductRingCash'])->name('search-product-ring-cash');
    Route::delete('cash-ring/{id}', [RewardExchangeController::class, 'destroyCashRing'])->name('cash-ring.destroy');
    Route::post('rewards-exchange/save-exchange-rate', [RewardExchangeController::class, 'saveExchangeRate'])
        ->name('rewards_exchange.save_rate');
    Route::get('reports/sale-revenue-ar', [ReportController::class, 'SaleRevenueAR'])
        ->name('reports.sale-revenue-ar');
   
    Route::get('get-document-note-page', [DocumentAndNoteController::class, 'getDocAndNoteIndexPage']);
    Route::post('post-document-upload', [DocumentAndNoteController::class, 'postMedia']);
    Route::resource('note-documents', DocumentAndNoteController::class);
    Route::resource('purchase-order', PurchaseOrderController::class);
    Route::get('get-purchase-orders/{contact_id}', [PurchaseOrderController::class, 'getPurchaseOrders']);
    Route::get('get-purchase-order-lines/{purchase_order_id}', [PurchaseController::class, 'getPurchaseOrderLines']);
    Route::get('edit-purchase-orders/{id}/status', [PurchaseOrderController::class, 'getEditPurchaseOrderStatus']);
    Route::put('update-purchase-orders/{id}/status', [PurchaseOrderController::class, 'postEditPurchaseOrderStatus']);
    Route::resource('sales-order', SalesOrderController::class)->only(['index']);
    Route::get('get-sales-orders/{customer_id}', [SalesOrderController::class, 'getSalesOrders']);
    Route::get('get-sales-order-lines', [SellPosController::class, 'getSalesOrderLines']);
    Route::get('edit-sales-orders/{id}/status', [SalesOrderController::class, 'getEditSalesOrderStatus']);
    Route::put('update-sales-orders/{id}/status', [SalesOrderController::class, 'postEditSalesOrderStatus']);
    Route::get('reports/activity-log', [ReportController::class, 'activityLog']);
    Route::get('user-location/{latlng}', [HomeController::class, 'getUserLocation']);
});

// Route::middleware(['EcomApi'])->prefix('api/ecom')->group(function () {
//     Route::get('products/{id?}', [ProductController::class, 'getProductsApi']);
//     Route::get('categories', [CategoryController::class, 'getCategoriesApi']);
//     Route::get('brands', [BrandController::class, 'getBrandsApi']);
//     Route::post('customers', [ContactController::class, 'postCustomersApi']);
//     Route::get('settings', [BusinessController::class, 'getEcomSettings']);
//     Route::get('variations', [ProductController::class, 'getVariationsApi']);
//     Route::post('orders', [SellPosController::class, 'placeOrdersApi']);
// });

//common route
Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
});

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone'])->group(function () {
    Route::get('/load-more-notifications', [HomeController::class, 'loadMoreNotifications']);
    Route::get('/get-total-unread', [HomeController::class, 'getTotalUnreadNotifications']);
    Route::get('/purchases/print/{id}', [PurchaseController::class, 'printInvoice']);
    Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('/download-purchase-order/{id}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseOrder.downloadPdf');
    Route::get('/sells/{id}', [SellController::class, 'show']);
    Route::get('/sells/{transaction_id}/print', [SellPosController::class, 'printInvoice'])->name('sell.printInvoice');
    Route::get('/download-sells/{transaction_id}/pdf', [SellPosController::class, 'downloadPdf'])->name('sell.downloadPdf');
    Route::get('/download-quotation/{id}/pdf', [SellPosController::class, 'downloadQuotationPdf'])
        ->name('quotation.downloadPdf');
    Route::get('/download-packing-list/{id}/pdf', [SellPosController::class, 'downloadPackingListPdf'])
        ->name('packing.downloadPdf');
    Route::get('/sells/invoice-url/{id}', [SellPosController::class, 'showInvoiceUrl']);
    Route::get('/show-notification/{id}', [HomeController::class, 'showNotification']);
});