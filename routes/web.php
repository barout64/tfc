<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DealerPortalController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DriverTrackingController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OperationsMapController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\TransporterController;
use App\Http\Controllers\TransporterPortalController;
use App\Http\Controllers\UserController as KilimoUserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public welcome site (before signing in) - Kilimo's own public portal,
| reused as TFC's front door: what TFC does, prices, advice, farmer/dealer
| self-registration, complaints, and the public driver trip-tracking page.
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicController::class, 'home'])->name('public.home');
Route::get('/sale-points', [PublicController::class, 'salePoints'])->name('public.sale-points');
Route::get('/prices', [PublicController::class, 'prices'])->name('public.prices');
Route::get('/advice', [PublicController::class, 'advice'])->name('public.advice');
Route::get('/register', [PublicController::class, 'registerForm'])->name('public.register');
Route::post('/register', [PublicController::class, 'register'])->middleware('throttle:10,1');
Route::get('/my-allowance', [PublicController::class, 'allowanceForm'])->name('public.allowance');
Route::post('/my-allowance', [PublicController::class, 'allowance'])->middleware('throttle:20,1');
Route::post('/my-allowance/reserve', [PublicController::class, 'reserve'])->name('public.reserve')->middleware('throttle:10,1');
Route::get('/complain', [PublicController::class, 'complaintForm'])->name('public.complaint');
Route::post('/complain', [PublicController::class, 'complaint'])->middleware('throttle:10,1');
Route::get('/become-a-dealer', [PublicController::class, 'dealerForm'])->name('public.dealer');
Route::post('/become-a-dealer', [PublicController::class, 'dealerApply'])->middleware('throttle:5,1');
Route::get('/locale/{locale}', [PublicController::class, 'locale'])->name('locale');

Route::get('/trip/{token}', [DriverTrackingController::class, 'show'])->name('trip.show');
Route::post('/trip/{token}', [DriverTrackingController::class, 'update'])->name('trip.update')->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Authentication - one sign-in for the whole system
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [PortalController::class, 'index'])->name('home');
    Route::get('/home', [AuthController::class, 'landing'])->name('landing');
    Route::get('/open/{system}', [PortalController::class, 'open'])->name('open')->middleware('throttle:30,1');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class)->except(['show', 'destroy']);
        Route::get('/activity', ActivityController::class)->name('activity');
    });

    /*
    |----------------------------------------------------------------------
    | Kilimo - fertilizer distribution (warehouses, stock, dispatch, sales,
    | farmers, agro-dealers). URL-namespaced only; one login, one database.
    |----------------------------------------------------------------------
    */
    Route::prefix('kilimo')->group(function () {
        Route::middleware('ability:dashboard')->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/operations-map', [OperationsMapController::class, 'index'])->name('map');
            Route::get('/operations-map/data', [OperationsMapController::class, 'data'])->name('map.data');
        });

        Route::middleware('ability:stock.view')->group(function () {
            Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show')->whereNumber('warehouse');
            Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('/stock/ledger', [StockController::class, 'ledger'])->name('stock.ledger');
            Route::get('/stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
            Route::get('/stock-counts/{stockCount}', [StockCountController::class, 'show'])->name('stock-counts.show')->whereNumber('stockCount');
            Route::get('/stock-reports', [StockReportController::class, 'index'])->name('stock-reports.index');
            Route::get('/stock-reports/{stockReport}', [StockReportController::class, 'show'])->name('stock-reports.show')->whereNumber('stockReport');
        });
        Route::middleware('ability:approve,warehouses.manage')->group(function () {
            Route::post('/stock-reports', [StockReportController::class, 'store'])->name('stock-reports.store');
            Route::post('/stock-reports/{stockReport}/apply', [StockReportController::class, 'apply'])->name('stock-reports.apply');
        });
        Route::middleware('ability:warehouses.manage')->group(function () {
            Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
            Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
            Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        });
        Route::middleware('ability:stock.manage')->group(function () {
            Route::get('/stock-counts/create', [StockCountController::class, 'create'])->name('stock-counts.create');
            Route::post('/stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
        });
        Route::post('/stock-counts/{stockCount}/approve', [StockCountController::class, 'approve'])->name('stock-counts.approve')->middleware('ability:approve');

        Route::middleware('ability:logistics.view')->group(function () {
            Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
            Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show')->whereNumber('shipment');
            Route::get('/dispatches', [DispatchController::class, 'index'])->name('dispatches.index');
            Route::get('/dispatches/{dispatch}', [DispatchController::class, 'show'])->name('dispatches.show')->where('dispatch', 'DSP-[0-9-]+');
            Route::get('/dispatches/{dispatch}/waybill', [DispatchController::class, 'waybill'])->name('dispatches.waybill');
            Route::get('/transporters', [TransporterController::class, 'index'])->name('transporters.index');
            Route::get('/transporters/{transporter}', [TransporterController::class, 'show'])->name('transporters.show')->whereNumber('transporter');
        });
        Route::middleware('ability:logistics.manage')->group(function () {
            Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
            Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
            Route::post('/shipments/{shipment}/status', [ShipmentController::class, 'status'])->name('shipments.status');
            Route::post('/shipments/{shipment}/complete', [ShipmentController::class, 'complete'])->name('shipments.complete');
            Route::get('/dispatches/create', [DispatchController::class, 'create'])->name('dispatches.create');
            Route::post('/dispatches', [DispatchController::class, 'store'])->name('dispatches.store');
            Route::post('/dispatches/{dispatch}/depart', [DispatchController::class, 'depart'])->name('dispatches.depart');
            Route::post('/dispatches/{dispatch}/cancel', [DispatchController::class, 'cancel'])->name('dispatches.cancel');
            Route::post('/dispatches/{dispatch}/track', [DispatchController::class, 'track'])->name('dispatches.track');
            Route::get('/transporters/create', [TransporterController::class, 'create'])->name('transporters.create');
            Route::post('/transporters', [TransporterController::class, 'store'])->name('transporters.store');
            Route::get('/transporters/{transporter}/edit', [TransporterController::class, 'edit'])->name('transporters.edit');
            Route::put('/transporters/{transporter}', [TransporterController::class, 'update'])->name('transporters.update');
            Route::post('/transporters/{transporter}/vehicles', [TransporterController::class, 'storeVehicle'])->name('transporters.vehicles.store');
            Route::post('/transporters/{transporter}/drivers', [TransporterController::class, 'storeDriver'])->name('transporters.drivers.store');
        });
        Route::post('/dispatches/{dispatch}/approve', [DispatchController::class, 'approve'])->name('dispatches.approve')->middleware('ability:approve,logistics.manage');
        Route::middleware('ability:stock.receive')->group(function () {
            Route::post('/shipments/{shipment}/receive', [ShipmentController::class, 'receive'])->name('shipments.receive');
            Route::get('/dispatches/{dispatch}/receive', [DispatchController::class, 'receiveForm'])->name('dispatches.receive');
            Route::post('/dispatches/{dispatch}/receive', [DispatchController::class, 'receive']);
        });
        Route::middleware('ability:finance.view')->get('/transporter-payments', [TransporterController::class, 'payments'])->name('transporter-payments.index');
        Route::middleware('ability:finance.manage')->post('/transporter-payments/{payment}', [TransporterController::class, 'updatePayment'])->name('transporter-payments.update');

        Route::middleware('ability:sales.view')->group(function () {
            Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
            Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show')->where('sale', 'SL-[0-9-]+');
        });
        Route::middleware('ability:sales.manage')->group(function () {
            Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
            Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
            Route::get('/sales/lookup/customer', [SaleController::class, 'lookup'])->name('sales.lookup');
        });
        Route::post('/payments/{payment}/confirm', [SaleController::class, 'confirmPayment'])->name('payments.confirm')->middleware('ability:finance.manage,sales.manage');

        Route::middleware('ability:farmers.view')->group(function () {
            Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
            Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show')->where('farmer', 'TFC-F-[0-9-]+');
            Route::get('/vouchers', [FarmerController::class, 'vouchers'])->name('vouchers.index');
            Route::get('/reservations', [FarmerController::class, 'reservations'])->name('reservations.index');
            Route::get('/cooperatives', [CooperativeController::class, 'index'])->name('cooperatives.index');
            Route::get('/cooperatives/{cooperative}', [CooperativeController::class, 'show'])->name('cooperatives.show')->whereNumber('cooperative');
        });
        Route::middleware('ability:farmers.manage')->group(function () {
            Route::get('/farmers/create', [FarmerController::class, 'create'])->name('farmers.create');
            Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');
            Route::get('/farmers/{farmer}/edit', [FarmerController::class, 'edit'])->name('farmers.edit');
            Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->name('farmers.update');
            Route::post('/farmers/{farmer}/vouchers', [FarmerController::class, 'issueVoucher'])->name('farmers.vouchers.store');
            Route::post('/farmers/{farmer}/entitlements', [FarmerController::class, 'refreshEntitlements'])->name('farmers.entitlements.refresh');
            Route::get('/cooperatives/create', [CooperativeController::class, 'create'])->name('cooperatives.create');
            Route::post('/cooperatives', [CooperativeController::class, 'store'])->name('cooperatives.store');
        });

        Route::middleware('ability:dealers.view,sales.manage')->group(function () {
            Route::get('/dealers', [DealerController::class, 'index'])->name('dealers.index');
            Route::get('/dealers/{dealer}', [DealerController::class, 'show'])->name('dealers.show');
        });
        Route::post('/dealers/{dealer}/review', [DealerController::class, 'review'])->name('dealers.review')->middleware('ability:approve');

        Route::middleware('ability:complaints.manage')->group(function () {
            Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
            Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
            Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
        });
        Route::middleware('ability:advisories.manage,complaints.manage')->group(function () {
            Route::get('/advisories', [AdvisoryController::class, 'index'])->name('advisories.index');
            Route::post('/advisories', [AdvisoryController::class, 'store'])->name('advisories.store');
            Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');
        });

        Route::middleware('ability:planning.manage')->group(function () {
            Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
            Route::post('/planning/targets', [PlanningController::class, 'storeTarget'])->name('planning.targets.store');
            Route::post('/planning/prices', [PlanningController::class, 'storePrice'])->name('planning.prices.store');
        });
        Route::middleware('ability:reports')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        });

        Route::middleware('ability:users.manage')->group(function () {
            Route::get('/users', [KilimoUserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [KilimoUserController::class, 'create'])->name('users.create');
            Route::post('/users', [KilimoUserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [KilimoUserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [KilimoUserController::class, 'update'])->name('users.update');
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
            Route::post('/master-data/products', [MasterDataController::class, 'storeProduct'])->name('master-data.products.store');
            Route::post('/master-data/seasons', [MasterDataController::class, 'storeSeason'])->name('master-data.seasons.store');
            Route::post('/master-data/seasons/{season}/activate', [MasterDataController::class, 'activateSeason'])->name('master-data.seasons.activate');
            Route::post('/master-data/recommendations', [MasterDataController::class, 'storeRecommendation'])->name('master-data.recommendations.store');
            Route::post('/master-data/suppliers', [MasterDataController::class, 'storeSupplier'])->name('master-data.suppliers.store');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Partner portals (agro-dealers, transporters)
    |----------------------------------------------------------------------
    */
    Route::middleware('ability:dealer.portal')->prefix('dealer')->name('dealer.')->group(function () {
        Route::get('/', [DealerPortalController::class, 'index'])->name('home');
        Route::get('/sell', [DealerPortalController::class, 'sellForm'])->name('sell');
        Route::post('/sell', [DealerPortalController::class, 'sell']);
    });
    Route::middleware('ability:transporter.portal')->prefix('transporter')->name('transporter.')->group(function () {
        Route::get('/', [TransporterPortalController::class, 'index'])->name('home');
    });
});
