<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\TabbyCronController;
use App\Http\Controllers\Api\ProductReviewController as ApiProductReviewController;
use App\Http\Controllers\Api\SaudiAddressController;
use App\Http\Controllers\Api\ApplePayController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/ 
// Auth Routes
Route::middleware('customLogs')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup']);

    Route::post('/verifyOTP', [AuthController::class, 'verifyOTP']);

    Route::post('/sendOTP', [AuthController::class, 'sendOTP']);

    Route::post('/signin', [AuthController::class, 'signin']);

    Route::get('/signout', [AuthController::class, 'signout']);

    Route::get('/customer', [AuthController::class, 'getCustomer']);

    Route::withoutMiddleware('customLogs')->post('/submitReview', [AuthController::class, 'submitReview']);

    Route::get('/freeGiftProducts', [ProductController::class, 'freeGiftProducts']);

    Route::get('/bogoProducts', [ProductController::class, 'bogoProducts']);

    Route::withoutMiddleware('customLogs')->match(['get', 'post'], '/specialOffers', [ProductController::class, 'specialOffers']);
    Route::withoutMiddleware('customLogs')->match(['get', 'post'], '/getBestSelling', [ProductController::class, 'getBestSelling']);

    // Product Category Routes
    Route::withoutMiddleware('customLogs')->post('/productCategories', [ProductCategoryController::class, 'getProductCategories']);
    Route::withoutMiddleware('customLogs')->post('/productCategoriesTemp', [ProductCategoryController::class, 'getProductCategoriesTemp']);
    Route::withoutMiddleware('customLogs')->post('/productCategorySEO', [ProductCategoryController::class, 'getProductCategorySEO']);

    // Product Routes
    Route::withoutMiddleware('customLogs')->post('/products', [ProductController::class, 'getProducts']);
    Route::withoutMiddleware('customLogs')->post('/products/live-status', [ProductController::class, 'getProductsLiveStatus']);

    //Search Suggestion
    Route::get('/search-suggestions', [ProductController::class, 'getSearchSuggestions']);

    // All Product Routes
    Route::withoutMiddleware('customLogs')->post('/allProducts', [ProductController::class, 'getAllProducts']);
    Route::withoutMiddleware('customLogs')->post('/exportProducts', [ProductController::class, 'getExportProducts']);
    Route::withoutMiddleware('customLogs')->post('/productSEO', [ProductController::class, 'getProductSEO']);
    Route::withoutMiddleware('customLogs')->match(['get', 'post'], '/staticPageSEO', [\Botble\StaticPageSeo\Http\Controllers\Api\StaticPageSeoApiController::class, 'getStaticPageSEO']);

    // Order Routes
    Route::post('/storeOrder', [OrderController::class, 'storeOrder']);
    Route::post('/payFortPaymentRedirect', [OrderController::class, 'payFortPaymentRedirect']);
    Route::get('/tabbyPaymentRedirect', [OrderController::class, 'tabbyPaymentRedirect']);
    Route::post('/tabbyPaymentRedirect', [OrderController::class, 'tabbyPaymentRedirect']);
    Route::post('/trackOrder', [OrderController::class, 'trackOrder']);
    Route::post('/orderDetails', [OrderController::class, 'orderDetails']);
    Route::post('/validateCoupon', [OrderController::class, 'validateCoupon']);

    // Direct Apple Pay (PayTabs) — allowlisted customers only, enforced in ApplePayController
    Route::get('/applePay/config', [ApplePayController::class, 'config']);
    Route::post('/applePay/validateMerchant', [ApplePayController::class, 'validateMerchant'])->middleware('throttle:20,1');
    Route::post('/applePay/charge', [ApplePayController::class, 'charge'])->middleware('throttle:10,1');

    // Blog Routes
    Route::withoutMiddleware('customLogs')->post('/blogs', [BlogController::class, 'getBlogs']);
    Route::withoutMiddleware('customLogs')->post('/getBlogDetails', [BlogController::class, 'getBlogDetails']);
    Route::withoutMiddleware('customLogs')->post('/blogSEO', [BlogController::class, 'getBlogSEO']);

    // Contact Route
    Route::post('/contact', [ContactController::class, 'contact']);
    Route::post('/campaign', [ContactController::class, 'campaign']);

    // Cron Route
    Route::get('/tabbyAllPayments', [TabbyCronController::class, 'tabbyAllPayments']);

    Route::post('/customerDetails', [OrderController::class, 'customerDetails']);
    Route::post('/customerUpdate', [OrderController::class, 'customerUpdate']);
    Route::post('/customerAddressDetails', [OrderController::class, 'customerAddressDetails']);
    Route::post('/customerAddressUpdate', [OrderController::class, 'customerAddressUpdate']);
    Route::get('/customerOrders', [OrderController::class, 'customerOrders']);
    Route::post('/customerOrderDetails', [OrderController::class, 'customerOrderDetails']);
    Route::post('/customerCancelOrder', [OrderController::class, 'customerCancelOrder']);
    Route::post('/customerCouponDetails', [OrderController::class, 'customerCouponDetails']);
    Route::post('/customerPasswordCheck', [OrderController::class, 'customerPasswordCheck']);
    Route::post('/address/lookup', [SaudiAddressController::class, 'lookup']);
    Route::get('/address/search', [SaudiAddressController::class, 'search']);

    Route::withoutMiddleware('restrict.domains')->post('/tamaraPaymentResponse', [OrderController::class, 'tamaraPaymentResponse']);
    Route::withoutMiddleware('restrict.domains')->any('/tamaraPaymentWebhook', [OrderController::class, 'tamaraPaymentWebhook']);

    Route::withoutMiddleware('customLogs')->get('/products/{product}/reviews', [ApiProductReviewController::class, 'index']);
    Route::withoutMiddleware('customLogs')->post('/reviews', [ApiProductReviewController::class, 'store']);

    Route::withoutMiddleware('restrict.domains')->post('/payTabsPaymentRedirect', [OrderController::class, 'payTabsPaymentRedirect']);
    Route::withoutMiddleware('restrict.domains')->post('/payTabsCallback', [OrderController::class, 'payTabsCallback']);
});
