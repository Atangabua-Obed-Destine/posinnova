<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountReportsController;
use App\Http\Controllers\AccountTypeController;
// use App\Http\Controllers\Auth;
use App\Http\Controllers\BackUpController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedPurchaseReturnController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardConfiguratorController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\DocumentAndNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportOpeningStockController;
use App\Http\Controllers\ImportProductsController;
use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\Install;
use App\Http\Controllers\InvoiceLayoutController;
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
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Restaurant;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCommissionAgentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellingPriceGroupController;
use App\Http\Controllers\SellPosController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentIssueController;
use App\Http\Controllers\DepartmentIssueReportController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\TypesOfServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationTemplateController;
use App\Http\Controllers\WarrantyController;
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
    Route::get('/get-purchase-requisition-product-row/{variation_id}', [PurchaseRequisitionController::class, 'getProductRow'])->name('get-purchase-requisition-product-row');
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

    //Shift settings (PosInnova hospital shifts)
    Route::get('/shift-settings', [\App\Http\Controllers\ShiftSettingController::class, 'index'])->name('shift-settings.index');
    Route::post('/shift-settings', [\App\Http\Controllers\ShiftSettingController::class, 'update'])->name('shift-settings.update');
    Route::get('/user/profile', [UserController::class, 'getProfile'])->name('user.getProfile');
    Route::post('/user/update', [UserController::class, 'updateProfile'])->name('user.updateProfile');
    Route::post('/user/update-password', [UserController::class, 'updatePassword'])->name('user.updatePassword');

    Route::resource('brands', BrandController::class);

    Route::resource('payment-account', 'PaymentAccountController');

    Route::resource('tax-rates', TaxRateController::class);

    Route::resource('units', UnitController::class);

    Route::resource('ledger-discount', LedgerDiscountController::class)->only('edit', 'destroy', 'store', 'update');

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

    Route::post('/contacts/check-tax-number', [ContactController::class, 'checkTaxNumber']);

    Route::get('/contacts/customers', [ContactController::class, 'getCustomers']);
    Route::resource('contacts', ContactController::class);

    Route::get('taxonomies-ajax-index-page', [TaxonomyController::class, 'getTaxonomyIndexPage']);
    Route::resource('taxonomies', TaxonomyController::class);

    Route::resource('variation-templates', VariationTemplateController::class);

    Route::get('/products/download-excel', [ProductController::class, 'downloadExcel']);

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

    Route::post('/products/get_sub_categories', [ProductController::class, 'getSubCategories']);
    Route::get('/products/get_sub_units', [ProductController::class, 'getSubUnits']);
    Route::get('/products/get_per_unit_prices_rows', [ProductController::class, 'getPerUnitPricesRows']);
    Route::post('/products/product_form_part', [ProductController::class, 'getProductVariationFormPart']);
    Route::post('/products/get_product_variation_row', [ProductController::class, 'getProductVariationRow']);
    Route::post('/products/get_variation_template', [ProductController::class, 'getVariationTemplate']);
    Route::get('/products/get_variation_value_row', [ProductController::class, 'getVariationValueRow']);
    Route::post('/products/check_product_sku', [ProductController::class, 'checkProductSku']);
    Route::post('/products/check_product_name', [ProductController::class, 'checkProductName']);
    Route::post('/products/validate_variation_skus', [ProductController::class, 'validateVaritionSkus']); //validates multiple skus at once
    Route::get('/products/quick_add', [ProductController::class, 'quickAdd']);
    Route::post('/products/save_quick_product', [ProductController::class, 'saveQuickProduct']);
    Route::get('/products/get-combo-product-entry-row', [ProductController::class, 'getComboProductEntryRow']);
    Route::post('/products/toggle-woocommerce-sync', [ProductController::class, 'toggleWooCommerceSync']);

    Route::resource('products', ProductController::class);
    Route::get('/toggle-subscription/{id}', 'SellPosController@toggleRecurringInvoices');
    Route::post('/sells/pos/get-types-of-service-details', 'SellPosController@getTypesOfServiceDetails');
    Route::get('/sells/subscriptions', 'SellPosController@listSubscriptions');
    Route::get('/sells/duplicate/{id}', 'SellController@duplicateSell');
    Route::get('/sells/drafts', 'SellController@getDrafts');
    Route::get('/sells/convert-to-draft/{id}', 'SellPosController@convertToInvoice');
    Route::get('/sells/convert-to-proforma/{id}', 'SellPosController@convertToProforma');
    Route::get('/sells/quotations', 'SellController@getQuotations');
    Route::get('/sells/draft-dt', 'SellController@getDraftDatables');
    Route::resource('sells', 'SellController')->except(['show']);
    Route::get('/sells/copy-quotation/{id}', [SellPosController::class, 'copyQuotation']);

    Route::post('/import-purchase-products', [PurchaseController::class, 'importPurchaseProducts']);
    Route::post('/purchases/update-status', [PurchaseController::class, 'updateStatus']);
    Route::get('/purchases/get_products', [PurchaseController::class, 'getProducts']);
    Route::get('/purchases/get_suppliers', [PurchaseController::class, 'getSuppliers']);
    Route::post('/purchases/get_purchase_entry_row', [PurchaseController::class, 'getPurchaseEntryRow']);
    Route::post('/purchases/check_ref_number', [PurchaseController::class, 'checkRefNumber']);
    Route::resource('purchases', PurchaseController::class)->except(['show']);

    Route::get('/toggle-subscription/{id}', [SellPosController::class, 'toggleRecurringInvoices']);
    Route::post('/sells/pos/get-types-of-service-details', [SellPosController::class, 'getTypesOfServiceDetails']);
    Route::get('/sells/subscriptions', [SellPosController::class, 'listSubscriptions']);
    Route::get('/sells/duplicate/{id}', [SellController::class, 'duplicateSell']);
    Route::get('/sells/drafts', [SellController::class, 'getDrafts']);
    Route::get('/sells/convert-to-draft/{id}', [SellPosController::class, 'convertToInvoice']);
    Route::get('/sells/convert-to-proforma/{id}', [SellPosController::class, 'convertToProforma']);
    Route::get('/sells/quotations', [SellController::class, 'getQuotations']);
    Route::get('/sells/draft-dt', [SellController::class, 'getDraftDatables']);
    Route::get('/sells-by-shift', [SellController::class, 'salesByShift'])->name('sells.by_shift');
    Route::resource('sells', SellController::class)->except(['show']);

    Route::get('/import-sales', [ImportSalesController::class, 'index']);
    Route::post('/import-sales/preview', [ImportSalesController::class, 'preview']);
    Route::post('/import-sales', [ImportSalesController::class, 'import']);
    Route::get('/revert-sale-import/{batch}', [ImportSalesController::class, 'revertSaleImport']);

    Route::get('/sells/pos/get_product_row/{variation_id}/{location_id}', [SellPosController::class, 'getProductRow']);
    Route::post('/sells/pos/change-product-base-unit', [SellPosController::class, 'changeProductBaseUnit']);
    Route::get('/sells/pos/get-base-units', [SellPosController::class, 'getBaseUnitsList']);
    Route::post('/sells/pos/get_payment_row', [SellPosController::class, 'getPaymentRow']);
    Route::post('/sells/pos/get-reward-details', [SellPosController::class, 'getRewardDetails']);
    Route::get('/sells/pos/get-recent-transactions', [SellPosController::class, 'getRecentTransactions']);
    Route::get('/sells/pos/get-product-suggestion', [SellPosController::class, 'getProductSuggestion']);
    Route::get('/sells/pos/get-featured-products/{location_id}', [SellPosController::class, 'getFeaturedProducts']);
    Route::get('/sells/pos/get-variation-location-qty/{variation_id}/{location_id}', [SellPosController::class, 'getVariationLocationQty']);
    Route::get('/sells/pos/get-variation-locations/{variation_id}', [SellPosController::class, 'getVariationLocations']);
    Route::get('/reset-mapping', [SellController::class, 'resetMapping']);
    // pos display screen route
    Route::get('/customer-display', [SellPosController::class, 'posDisplay'])->name('pos_display');
    Route::get('/customer-display/details', [SellPosController::class, 'posDisplayDetails'])->name('pos_display_details');

    Route::get('/pos/variations/bulk', [\App\Http\Controllers\ProductController::class, 'getVariationDetailsBulk']);
    // end pos display screen route

    // PosInnova Stock Doctor: detect & heal display/purchase stock drift that
    // blocks POS sales ("Mismatch between sold and purchase quantity").
    Route::get('/pos/stock-integrity/scan', [\App\Http\Controllers\StockIntegrityController::class, 'scan'])->name('stock_integrity.scan');
    Route::post('/pos/stock-integrity/heal', [\App\Http\Controllers\StockIntegrityController::class, 'heal'])->name('stock_integrity.heal');

    Route::resource('pos', SellPosController::class);

    Route::resource('roles', RoleController::class);

    Route::resource('users', ManageUserController::class);

    Route::resource('group-taxes', GroupTaxController::class);

    Route::get('/barcodes/set_default/{id}', [BarcodeController::class, 'setDefault']);
    Route::post('/barcodes/preview', [BarcodeController::class, 'preview'])->name('barcodes.preview');
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
    Route::get('/reports/products-price-list', [ReportController::class, 'getProductsPriceList']);
    Route::get('/reports/stock-details', [ReportController::class, 'getStockDetails']);
    Route::get('/reports/tax-report', [ReportController::class, 'getTaxReport']);
    Route::get('/reports/tax-details', [ReportController::class, 'getTaxDetails']);
    Route::get('/reports/trending-products', [ReportController::class, 'getTrendingProducts']);
    Route::get('/reports/expense-report', [ReportController::class, 'getExpenseReport']);
    Route::get('/reports/stock-adjustment-report', [ReportController::class, 'getStockAdjustmentReport']);
    Route::get('/reports/register-report', [ReportController::class, 'getRegisterReport']);
    Route::get('/reports/shift-summary', [ReportController::class, 'getShiftSummaryReport']);
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

    // ── Rich server-side export routes (Excel & PDF) ────────────────────────
    Route::get('/reports/export/sale-report',         [ReportController::class, 'exportSaleReport']);
    Route::get('/reports/export/purchase-report',     [ReportController::class, 'exportPurchaseReport']);
    Route::get('/reports/export/stock-report',        [ReportController::class, 'exportStockReport']);
    Route::get('/reports/export/stock-expiry',        [ReportController::class, 'exportStockExpiryReport']);
    Route::get('/reports/export/profit-loss',         [ReportController::class, 'exportProfitLoss']);
    Route::get('/reports/export/sell-payment',        [ReportController::class, 'exportSellPaymentReport']);
    Route::get('/reports/export/register-report',     [ReportController::class, 'exportRegisterReport']);
    Route::get('/reports/export/tax-report',          [ReportController::class, 'exportTaxReport']);
    Route::get('/reports/export/product-sell-report', [ReportController::class, 'exportProductSellReport']);
    Route::get('/reports/export/contact-report',      [ReportController::class, 'exportContactReport']);
    // ────────────────────────────────────────────────────────────────────────

    Route::get('business-location/activate-deactivate/{location_id}', [BusinessLocationController::class, 'activateDeactivateLocation']);

    //Business Location Settings...
    Route::prefix('business-location/{location_id}')->name('location.')->group(function () {
        Route::get('settings', [LocationSettingsController::class, 'index'])->name('settings');
        Route::post('settings', [LocationSettingsController::class, 'updateSettings'])->name('settings_update');
    });

    //Business Locations...
    Route::post('business-location/check-location-id', [BusinessLocationController::class, 'checkLocationId']);
    Route::resource('business-location', BusinessLocationController::class);

    //Invoice layouts..
    Route::resource('invoice-layouts', InvoiceLayoutController::class);

    Route::post('get-expense-sub-categories', [ExpenseCategoryController::class, 'getSubCategories']);

    //Expense Categories...
    Route::resource('expense-categories', ExpenseCategoryController::class);

    //Expenses...
    Route::resource('expenses', ExpenseController::class);
    Route::get('import-expense', [ExpenseController::class, 'importExpense']);
    Route::post('store-import-expense', [ExpenseController::class, 'storeExpenseImport']);

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

    // Inventory Count (physical recount – separate from Stock Adjustment)
    Route::get('/inventory-count', [\App\Http\Controllers\InventoryCountController::class, 'index']);
    Route::get('/inventory-count/blank-pdf', [\App\Http\Controllers\InventoryCountController::class, 'blankPdf']);
    Route::get('/inventory-count/recount-queue', [\App\Http\Controllers\InventoryCountController::class, 'recountQueue']);
    Route::post('/inventory-count/recount-queue/create', [\App\Http\Controllers\InventoryCountController::class, 'createRecountSession']);
    Route::post('/inventory-count/recount-queue/dismiss', [\App\Http\Controllers\InventoryCountController::class, 'dismissRecountLines']);
    Route::post('/inventory-count/create', [\App\Http\Controllers\InventoryCountController::class, 'create']);
    Route::get('/inventory-count/{id}', [\App\Http\Controllers\InventoryCountController::class, 'show'])->whereNumber('id');
    Route::get('/inventory-count/{id}/data', [\App\Http\Controllers\InventoryCountController::class, 'sessionData'])->whereNumber('id');
    Route::get('/inventory-count/{id}/variance-pdf', [\App\Http\Controllers\InventoryCountController::class, 'variancePdf'])->whereNumber('id');
    Route::get('/inventory-count/{id}/csv-template', [\App\Http\Controllers\InventoryCountController::class, 'csvTemplate'])->whereNumber('id');
    Route::post('/inventory-count/{id}/csv-import', [\App\Http\Controllers\InventoryCountController::class, 'csvImport'])->whereNumber('id');
    Route::post('/inventory-count/{id}/scan', [\App\Http\Controllers\InventoryCountController::class, 'scanLookup'])->whereNumber('id');
    Route::post('/inventory-count/{id}/save-line', [\App\Http\Controllers\InventoryCountController::class, 'saveLine'])->whereNumber('id');
    Route::get('/inventory-count/{id}/variation-search', [\App\Http\Controllers\InventoryCountController::class, 'variationSearch'])->whereNumber('id');
    Route::post('/inventory-count/{id}/add-lot', [\App\Http\Controllers\InventoryCountController::class, 'addLot'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-lot', [\App\Http\Controllers\InventoryCountController::class, 'updateLot'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-sell-price', [\App\Http\Controllers\InventoryCountController::class, 'updateSellPrice'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-unit-cost', [\App\Http\Controllers\InventoryCountController::class, 'updateUnitCost'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-alert-quantity', [\App\Http\Controllers\InventoryCountController::class, 'updateAlertQuantity'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-product-unit', [\App\Http\Controllers\InventoryCountController::class, 'updateProductUnit'])->whereNumber('id');
    Route::post('/inventory-count/{id}/update-product-info', [\App\Http\Controllers\InventoryCountController::class, 'updateProductInfo'])->whereNumber('id');
    Route::get('/inventory-count/{id}/unit-prices-grid', [\App\Http\Controllers\InventoryCountController::class, 'unitPricesGrid'])->whereNumber('id');
    Route::post('/inventory-count/{id}/post', [\App\Http\Controllers\InventoryCountController::class, 'post'])->whereNumber('id');
    Route::post('/inventory-count/{id}/approve', [\App\Http\Controllers\InventoryCountController::class, 'approve'])->whereNumber('id');
    Route::post('/inventory-count/{id}/reject', [\App\Http\Controllers\InventoryCountController::class, 'reject'])->whereNumber('id');
    Route::post('/inventory-count/{id}/toggle-purpose', [\App\Http\Controllers\InventoryCountController::class, 'toggleCountPurpose'])->whereNumber('id');
    Route::post('/inventory-count/{id}/cancel', [\App\Http\Controllers\InventoryCountController::class, 'cancel'])->whereNumber('id');

    //Shift handover (PosInnova hospital shifts)
    Route::post('/cash-register/extend-shift', [CashRegisterController::class, 'extendShift']);
    Route::post('/cash-register/start-next-shift', [CashRegisterController::class, 'startNextShift']);

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

    /*
    |------------------------------------------------------------------
    | Hospital module (gated per-controller by `hospital` enabled_module)
    | Views reference these via action([Controller::class,'method']).
    |------------------------------------------------------------------
    */
    //Patients
    Route::get('patients/search', [\App\Http\Controllers\PatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', \App\Http\Controllers\PatientController::class);

    //Visits / OPD
    //PosInnova: state changes are POST (with CSRF), never plain GET links
    Route::get('visits/cover', [\App\Http\Controllers\VisitController::class, 'cover']);
    Route::get('visits/{id}/vitals', [\App\Http\Controllers\VisitController::class, 'vitals']);
    Route::post('visits/{id}/vitals', [\App\Http\Controllers\VisitController::class, 'saveVitals']);
    Route::post('visits/{id}/close', [\App\Http\Controllers\VisitController::class, 'close']);
    Route::post('visits/{id}/cancel', [\App\Http\Controllers\VisitController::class, 'cancel']);
    Route::resource('visits', \App\Http\Controllers\VisitController::class)->only(['index', 'create', 'store', 'show']);

    //Consultations
    Route::get('consultations', [\App\Http\Controllers\ConsultationController::class, 'index']);
    Route::get('consultations/create', [\App\Http\Controllers\ConsultationController::class, 'create']);
    Route::post('consultations', [\App\Http\Controllers\ConsultationController::class, 'store']);
    Route::get('consultations/{id}', [\App\Http\Controllers\ConsultationController::class, 'show'])->whereNumber('id');
    Route::post('consultations/{id}/finalize', [\App\Http\Controllers\ConsultationController::class, 'finalize']);
    Route::get('consultations/{id}/prescription', [\App\Http\Controllers\ConsultationController::class, 'prescription']);

    //Laboratory
    Route::resource('lab-tests', \App\Http\Controllers\LabTestController::class)->except(['show']);
    Route::get('lab-requests/{id}/result', [\App\Http\Controllers\LabRequestController::class, 'enterResult']);
    Route::post('lab-requests/{id}/result', [\App\Http\Controllers\LabRequestController::class, 'saveResult']);
    Route::post('lab-requests/{id}/validate', [\App\Http\Controllers\LabRequestController::class, 'validateResult']);
    Route::post('lab-requests/{id}/amend', [\App\Http\Controllers\LabRequestController::class, 'amendResult']);
    Route::get('lab-requests/{id}/print', [\App\Http\Controllers\LabRequestController::class, 'print']);
    Route::resource('lab-requests', \App\Http\Controllers\LabRequestController::class)->only(['index', 'create', 'store', 'show']);

    //Imaging / Radiology
    Route::get('imaging-catalogue', [\App\Http\Controllers\ImagingController::class, 'catalogue']);
    Route::get('imaging-catalogue/create', [\App\Http\Controllers\ImagingController::class, 'createCatalogue']);
    Route::post('imaging-catalogue', [\App\Http\Controllers\ImagingController::class, 'storeCatalogue']);
    Route::get('imaging-catalogue/{id}/edit', [\App\Http\Controllers\ImagingController::class, 'editCatalogue']);
    Route::put('imaging-catalogue/{id}', [\App\Http\Controllers\ImagingController::class, 'updateCatalogue']);
    Route::delete('imaging-catalogue/{id}', [\App\Http\Controllers\ImagingController::class, 'destroyCatalogue']);
    Route::get('imaging-requests/{id}/report', [\App\Http\Controllers\ImagingController::class, 'enterReport']);
    Route::post('imaging-requests/{id}/report', [\App\Http\Controllers\ImagingController::class, 'saveReport']);
    Route::resource('imaging-requests', \App\Http\Controllers\ImagingController::class)->only(['index', 'create', 'store', 'show']);

    //Wards & beds
    Route::resource('wards', \App\Http\Controllers\WardController::class)->except(['show']);

    //Admissions
    Route::get('admissions/{id}/discharge', [\App\Http\Controllers\AdmissionController::class, 'dischargeForm']);
    Route::post('admissions/{id}/discharge', [\App\Http\Controllers\AdmissionController::class, 'discharge']);
    Route::post('admissions/{id}/caretaker', [\App\Http\Controllers\AdmissionController::class, 'addCaretaker']);
    Route::post('admissions/{id}/transfer-ward', [\App\Http\Controllers\AdmissionController::class, 'transferWard']);
    Route::resource('admissions', \App\Http\Controllers\AdmissionController::class)->only(['index', 'create', 'store', 'show']);

    //Nursing (tied to an admission)
    Route::post('nursing/{admission_id}/vitals', [\App\Http\Controllers\NursingController::class, 'storeVitals']);
    Route::post('nursing/{admission_id}/medication', [\App\Http\Controllers\NursingController::class, 'recordMedication']);
    Route::post('nursing/{admission_id}/note', [\App\Http\Controllers\NursingController::class, 'storeNote']);

    //Theatre
    Route::get('theatre-procedures', [\App\Http\Controllers\TheatreController::class, 'catalogue']);
    Route::get('theatre-procedures/create', [\App\Http\Controllers\TheatreController::class, 'createProcedure']);
    Route::post('theatre-procedures', [\App\Http\Controllers\TheatreController::class, 'storeProcedure']);
    Route::get('theatre-procedures/{id}/edit', [\App\Http\Controllers\TheatreController::class, 'editProcedure']);
    Route::put('theatre-procedures/{id}', [\App\Http\Controllers\TheatreController::class, 'updateProcedure']);
    Route::delete('theatre-procedures/{id}', [\App\Http\Controllers\TheatreController::class, 'destroyProcedure']);
    Route::post('theatre/{id}/record', [\App\Http\Controllers\TheatreController::class, 'record']);
    Route::resource('theatre', \App\Http\Controllers\TheatreController::class)->only(['index', 'create', 'store', 'show']);

    //Maternity
    Route::post('antenatal/{id}/visit', [\App\Http\Controllers\AntenatalController::class, 'recordVisit']);
    Route::resource('antenatal', \App\Http\Controllers\AntenatalController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('deliveries', \App\Http\Controllers\DeliveryController::class)->only(['index', 'create', 'store', 'show']);

    //Morgue
    Route::match(['get', 'post'], 'morgue/slots', [\App\Http\Controllers\MorgueController::class, 'slots']);
    Route::post('morgue/{id}/release', [\App\Http\Controllers\MorgueController::class, 'release']);
    Route::post('morgue/{id}/visit', [\App\Http\Controllers\MorgueController::class, 'recordVisit']);
    Route::resource('morgue', \App\Http\Controllers\MorgueController::class)->only(['index', 'create', 'store', 'show']);

    //Hospital reports
    Route::get('reports/hospital/dashboard', [\App\Http\Controllers\HospitalReportController::class, 'dashboard']);
    Route::get('reports/hospital/opd', [\App\Http\Controllers\HospitalReportController::class, 'opd']);
    Route::get('reports/hospital/admissions', [\App\Http\Controllers\HospitalReportController::class, 'admissions']);
    Route::get('reports/hospital/lab', [\App\Http\Controllers\HospitalReportController::class, 'lab']);
    Route::get('reports/hospital/ward', [\App\Http\Controllers\HospitalReportController::class, 'ward']);
    Route::get('reports/hospital/revenue', [\App\Http\Controllers\HospitalReportController::class, 'revenue']);
    Route::get('reports/hospital/mortality', [\App\Http\Controllers\HospitalReportController::class, 'mortality']);
    Route::get('reports/hospital/antenatal', [\App\Http\Controllers\HospitalReportController::class, 'antenatal']);
    Route::get('reports/hospital/morgue', [\App\Http\Controllers\HospitalReportController::class, 'morgue']);
    Route::get('reports/hospital/mutual-claims', [\App\Http\Controllers\HospitalReportController::class, 'mutualClaims']);

    //Clinic reports (hospital module)
    Route::get('clinic/reports', [\App\Http\Controllers\ClinicReportController::class, 'index']);
    Route::get('clinic/reports/{key}', [\App\Http\Controllers\ClinicReportController::class, 'show'])->where('key', '[a-z_]+');

    //Clinic settings (hospital module)
    Route::get('clinic/settings', [\App\Http\Controllers\ClinicSettingsController::class, 'index']);
    Route::post('clinic/settings', [\App\Http\Controllers\ClinicSettingsController::class, 'update']);
    //PosInnova clinic phase 2: consultation packages and the doctor's queue
    Route::get('clinic/packages', [\App\Http\Controllers\ClinicPackageController::class, 'index']);
    Route::get('clinic/packages/create', [\App\Http\Controllers\ClinicPackageController::class, 'create']);
    Route::post('clinic/packages', [\App\Http\Controllers\ClinicPackageController::class, 'store']);
    Route::get('clinic/packages/{id}/edit', [\App\Http\Controllers\ClinicPackageController::class, 'edit']);
    Route::post('clinic/packages/{id}', [\App\Http\Controllers\ClinicPackageController::class, 'update']);
    Route::post('clinic/packages/{id}/delete', [\App\Http\Controllers\ClinicPackageController::class, 'destroy']);
    Route::get('clinic/queue', [\App\Http\Controllers\ClinicQueueController::class, 'index']);
    Route::post('clinic/queue/{id}/start', [\App\Http\Controllers\ClinicQueueController::class, 'start']);
    Route::post('clinic/queue/{id}/let-through', [\App\Http\Controllers\ClinicQueueController::class, 'letThrough']);
    Route::post('clinic/queue/{id}/no-show', [\App\Http\Controllers\ClinicQueueController::class, 'noShow']);
    Route::post('clinic/queue/{id}/requeue', [\App\Http\Controllers\ClinicQueueController::class, 'requeue']);
    Route::get('clinic/dispensary', [\App\Http\Controllers\ClinicDispensaryController::class, 'index']);
    Route::get('clinic/dispensary/{id}', [\App\Http\Controllers\ClinicDispensaryController::class, 'show']);
    Route::post('clinic/dispensary/line/{id}/close', [\App\Http\Controllers\ClinicDispensaryController::class, 'close']);
    Route::post('clinic/dispensary/line/{id}/reopen', [\App\Http\Controllers\ClinicDispensaryController::class, 'reopen']);
    Route::get('clinic/plans', [\App\Http\Controllers\ClinicPlanController::class, 'index']);
    Route::get('clinic/plans/create', [\App\Http\Controllers\ClinicPlanController::class, 'create']);
    Route::get('clinic/plans/coverage', [\App\Http\Controllers\ClinicPlanController::class, 'coverage']);
    Route::post('clinic/plans', [\App\Http\Controllers\ClinicPlanController::class, 'store']);
    Route::post('clinic/plans/bill-now', [\App\Http\Controllers\ClinicPlanController::class, 'billNow']);
    Route::get('clinic/plans/{id}', [\App\Http\Controllers\ClinicPlanController::class, 'show']);
    Route::post('clinic/plans/{id}/cancel', [\App\Http\Controllers\ClinicPlanController::class, 'cancel']);
    Route::get('clinic/accounts', [\App\Http\Controllers\ClinicAccountController::class, 'index']);
    Route::get('clinic/accounts/{contact_id}', [\App\Http\Controllers\ClinicAccountController::class, 'show']);
    Route::post('clinic/accounts/{contact_id}/pay', [\App\Http\Controllers\ClinicAccountController::class, 'pay']);
    Route::get('clinic/insurers', [\App\Http\Controllers\ClinicInsurerController::class, 'index']);
    Route::get('clinic/insurers/create', [\App\Http\Controllers\ClinicInsurerController::class, 'create']);
    Route::post('clinic/insurers', [\App\Http\Controllers\ClinicInsurerController::class, 'store']);
    Route::get('clinic/insurers/{id}/edit', [\App\Http\Controllers\ClinicInsurerController::class, 'edit']);
    Route::post('clinic/insurers/{id}', [\App\Http\Controllers\ClinicInsurerController::class, 'update']);
    Route::post('clinic/insurers/{id}/delete', [\App\Http\Controllers\ClinicInsurerController::class, 'destroy']);
    Route::get('clinic/claims', [\App\Http\Controllers\ClinicClaimController::class, 'index']);
    Route::get('clinic/claims/create', [\App\Http\Controllers\ClinicClaimController::class, 'create']);
    Route::post('clinic/claims', [\App\Http\Controllers\ClinicClaimController::class, 'store']);
    Route::get('clinic/claims/{id}', [\App\Http\Controllers\ClinicClaimController::class, 'show']);
    Route::get('clinic/claims/{id}/print', [\App\Http\Controllers\ClinicClaimController::class, 'printClaim']);
    Route::post('clinic/claims/{id}/refresh', [\App\Http\Controllers\ClinicClaimController::class, 'refresh']);
    Route::post('clinic/claims/{id}/items/{share_id}/remove', [\App\Http\Controllers\ClinicClaimController::class, 'removeItem']);
    Route::post('clinic/claims/{id}/submit', [\App\Http\Controllers\ClinicClaimController::class, 'submit']);
    Route::post('clinic/claims/{id}/reopen', [\App\Http\Controllers\ClinicClaimController::class, 'reopen']);
    Route::post('clinic/claims/{id}/delete', [\App\Http\Controllers\ClinicClaimController::class, 'destroy']);
    Route::post('clinic/claims/{id}/settle', [\App\Http\Controllers\ClinicClaimController::class, 'settle']);
    Route::get('clinic/appointments', [\App\Http\Controllers\ClinicAppointmentController::class, 'index']);
    Route::post('clinic/appointments', [\App\Http\Controllers\ClinicAppointmentController::class, 'store']);
    Route::get('clinic/whatsapp', [\App\Http\Controllers\ClinicWhatsAppController::class, 'index']);
    Route::post('clinic/whatsapp', [\App\Http\Controllers\ClinicWhatsAppController::class, 'save']);
    Route::post('clinic/whatsapp/test-connection', [\App\Http\Controllers\ClinicWhatsAppController::class, 'testConnection']);
    Route::post('clinic/whatsapp/register', [\App\Http\Controllers\ClinicWhatsAppController::class, 'register']);
    Route::post('clinic/whatsapp/subscribe', [\App\Http\Controllers\ClinicWhatsAppController::class, 'subscribe']);
    Route::post('clinic/whatsapp/check-templates', [\App\Http\Controllers\ClinicWhatsAppController::class, 'checkTemplates']);
    Route::post('clinic/whatsapp/send-test', [\App\Http\Controllers\ClinicWhatsAppController::class, 'sendTest']);
    Route::post('clinic/whatsapp/new-verify-token', [\App\Http\Controllers\ClinicWhatsAppController::class, 'newVerifyToken']);
    Route::get('clinic/whatsapp/inbox', [\App\Http\Controllers\ClinicWhatsAppController::class, 'inbox']);
    Route::post('clinic/whatsapp/inbox/{id}/handled', [\App\Http\Controllers\ClinicWhatsAppController::class, 'handled']);
    Route::post('clinic/whatsapp/inbox/{id}/reply', [\App\Http\Controllers\ClinicWhatsAppController::class, 'reply']);
    Route::get('clinic/appointments/upcoming', [\App\Http\Controllers\ClinicAppointmentController::class, 'upcoming']);
    Route::get('clinic/appointments/free-slots', [\App\Http\Controllers\ClinicAppointmentController::class, 'freeSlots']);
    Route::post('clinic/appointments/{id}/move', [\App\Http\Controllers\ClinicAppointmentController::class, 'move']);
    Route::get('clinic/appointments/hours', [\App\Http\Controllers\ClinicAppointmentController::class, 'hours']);
    Route::post('clinic/appointments/hours', [\App\Http\Controllers\ClinicAppointmentController::class, 'storeHours']);
    Route::post('clinic/appointments/hours/{id}/delete', [\App\Http\Controllers\ClinicAppointmentController::class, 'deleteHours']);
    Route::post('clinic/appointments/days-off', [\App\Http\Controllers\ClinicAppointmentController::class, 'storeDayOff']);
    Route::post('clinic/appointments/days-off/{id}/delete', [\App\Http\Controllers\ClinicAppointmentController::class, 'deleteDayOff']);
    Route::post('clinic/appointments/{id}/confirm', [\App\Http\Controllers\ClinicAppointmentController::class, 'confirm']);
    Route::post('clinic/appointments/{id}/decline', [\App\Http\Controllers\ClinicAppointmentController::class, 'decline']);
    Route::post('clinic/appointments/{id}/cancel', [\App\Http\Controllers\ClinicAppointmentController::class, 'cancel']);
    Route::post('clinic/appointments/{id}/no-show', [\App\Http\Controllers\ClinicAppointmentController::class, 'noShow']);
    Route::post('clinic/appointments/{id}/reminded', [\App\Http\Controllers\ClinicAppointmentController::class, 'reminded']);

    //Hospital Departments
    Route::resource('departments', DepartmentController::class);

    //Department Issues
    Route::get('department-issues/print/{id}', [DepartmentIssueController::class, 'printVoucher']);
    Route::resource('department-issues', DepartmentIssueController::class)->except(['edit', 'update']);

    // Department Issue Report
    Route::get('/reports/department-issue', [DepartmentIssueReportController::class, 'index']);
    Route::get('/reports/department-issue/details', [DepartmentIssueReportController::class, 'details']);
    Route::get('/reports/department-issue/by-department', [DepartmentIssueReportController::class, 'byDepartment']);
    Route::get('/reports/department-issue/by-product', [DepartmentIssueReportController::class, 'byProduct']);
    Route::get('/reports/department-issue/by-period', [DepartmentIssueReportController::class, 'byPeriod']);

    Route::get('/opening-stock/add/{product_id}', [OpeningStockController::class, 'add']);
    Route::post('/opening-stock/save', [OpeningStockController::class, 'save']);

    //Customer Groups
    Route::resource('customer-group', CustomerGroupController::class);

    //Import opening stock
    Route::get('/import-opening-stock', [ImportOpeningStockController::class, 'index']);
    Route::post('/import-opening-stock/store', [ImportOpeningStockController::class, 'store']);

    //Sell return
    Route::get('validate-invoice-to-return/{invoice_no}', [SellReturnController::class, 'validateInvoiceToReturn']);
    // service staff replacement
    Route::get('validate-invoice-to-service-staff-replacement/{invoice_no}', [SellPosController::class, 'validateInvoiceToServiceStaffReplacement']);
    Route::put('change-service-staff/{id}', [SellPosController::class, 'change_service_staff'])->name('change_service_staff');

    Route::resource('sell-return', SellReturnController::class);
    Route::get('sell-return/get-product-row', [SellReturnController::class, 'getProductRow']);
    Route::get('/sell-return/print/{id}', [SellReturnController::class, 'printInvoice']);
    Route::get('/sell-return/add/{id}', [SellReturnController::class, 'add']);

    //Backup
    //PosInnova: Settings > Repair payments (sales whose change was subtracted twice)
    Route::get('payment-repair', [\App\Http\Controllers\PaymentRepairController::class, 'index']);
    Route::post('payment-repair', [\App\Http\Controllers\PaymentRepairController::class, 'repair']);
    Route::get('backup/download/{file_name}', [BackUpController::class, 'download']);
    Route::post('backup/{id}/delete', [BackUpController::class, 'delete'])->name('delete_backup');
    Route::post('backup/run', [BackUpController::class, 'create'])->name('backup.run');
    Route::resource('backup', BackUpController::class)->only('index');

    Route::get('selling-price-group/activate-deactivate/{id}', [SellingPriceGroupController::class, 'activateDeactivate']);
    Route::get('update-product-price', [SellingPriceGroupController::class, 'updateProductPrice'])->name('update-product-price');
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
        Route::get('/data/check-staff-pin', [Restaurant\DataController::class, 'checkStaffPin']);
        Route::get('/orders/mark-line-order-as-served/{id}', [Restaurant\OrderController::class, 'markLineOrderAsServed']);
        Route::get('/print-line-order', [Restaurant\OrderController::class, 'printLineOrder']);
    });

    Route::get('bookings/get-todays-bookings', [Restaurant\BookingController::class, 'getTodaysBookings']);
    Route::resource('bookings', Restaurant\BookingController::class);

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

    Route::get('view-media/{model_id}', [SellController::class, 'viewMedia']);

    //common controller for document & note
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

/*
|--------------------------------------------------------------------------
| Patient Portal (hospital module) — external, separate `patient` guard
|--------------------------------------------------------------------------
*/
// PosInnova clinic: Meta's WhatsApp webhook (public; /webhook/* is outside CSRF) and the privacy notice Meta asks for
Route::get('webhook/whatsapp', [\App\Http\Controllers\WhatsAppWebhookController::class, 'verify']);
Route::post('webhook/whatsapp', [\App\Http\Controllers\WhatsAppWebhookController::class, 'receive']);
Route::get('privacy', [\App\Http\Controllers\ClinicWhatsAppController::class, 'privacy'])->middleware('language');

Route::middleware(['setData', 'language'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [\App\Http\Controllers\PatientPortal\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [\App\Http\Controllers\PatientPortal\AuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [\App\Http\Controllers\PatientPortal\AuthController::class, 'logout'])->name('logout');
    Route::get('lang/{locale}', [\App\Http\Controllers\PatientPortal\AuthController::class, 'setLocale'])->name('lang');
    Route::get('offline', fn () => view('patient_portal.offline'))->name('offline');
    Route::get('manifest.webmanifest', fn () => response()->json([
        'name' => 'Patient Portal', 'short_name' => 'Portal', 'start_url' => url('portal'),
        'scope' => url('portal').'/', 'display' => 'standalone', 'orientation' => 'portrait',
        'theme_color' => '#4f46e5', 'background_color' => '#ffffff',
    ])->header('Content-Type', 'application/manifest+json'))->name('manifest');

    Route::middleware(['PatientPortalAuth'])->group(function () {
        Route::get('/', [\App\Http\Controllers\PatientPortal\PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('visits', [\App\Http\Controllers\PatientPortal\PortalController::class, 'visits'])->name('visits');
        Route::get('visits/{id}', [\App\Http\Controllers\PatientPortal\PortalController::class, 'visitShow'])->name('visit-show');
        Route::get('prescriptions', [\App\Http\Controllers\PatientPortal\PortalController::class, 'prescriptions'])->name('prescriptions');
        Route::get('plan', [\App\Http\Controllers\PatientPortal\PortalController::class, 'plan'])->name('plan');
        Route::get('appointments', [\App\Http\Controllers\PatientPortal\AppointmentController::class, 'index'])->name('appointments');
        Route::get('appointments/new', [\App\Http\Controllers\PatientPortal\AppointmentController::class, 'create'])->name('appointment-create');
        Route::get('appointments/slots', [\App\Http\Controllers\PatientPortal\AppointmentController::class, 'slots'])->name('appointment-slots');
        Route::post('appointments', [\App\Http\Controllers\PatientPortal\AppointmentController::class, 'store'])->name('appointment-store');
        Route::post('appointments/{id}/cancel', [\App\Http\Controllers\PatientPortal\AppointmentController::class, 'cancel'])->name('appointment-cancel');
        Route::get('lab-results', [\App\Http\Controllers\PatientPortal\PortalController::class, 'labResults'])->name('lab-results');
        Route::get('lab-results/{id}', [\App\Http\Controllers\PatientPortal\PortalController::class, 'labShow'])->name('lab-show');
        Route::get('imaging', [\App\Http\Controllers\PatientPortal\PortalController::class, 'imaging'])->name('imaging');
        Route::get('admissions', [\App\Http\Controllers\PatientPortal\PortalController::class, 'admissions'])->name('admissions');
        Route::get('maternity', [\App\Http\Controllers\PatientPortal\PortalController::class, 'maternity'])->name('maternity');
        Route::get('bills', [\App\Http\Controllers\PatientPortal\PortalController::class, 'bills'])->name('bills');
        Route::get('bills/{id}/receipt', [\App\Http\Controllers\PatientPortal\PortalController::class, 'billReceipt'])->name('bill-receipt');
        Route::get('profile', [\App\Http\Controllers\PatientPortal\PortalController::class, 'profile'])->name('profile');
        Route::get('profile/edit', [\App\Http\Controllers\PatientPortal\PortalController::class, 'profileEdit'])->name('profile-edit');
        Route::post('profile', [\App\Http\Controllers\PatientPortal\PortalController::class, 'profileUpdate'])->name('profile-update');
    });
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
    Route::post('/sell/check-invoice-number', [SellController::class, 'checkInvoiceNumber']);
});