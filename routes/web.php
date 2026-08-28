<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmsaController;
use App\Http\Controllers\promotionController;
use Botble\Ecommerce\Http\Controllers\ProductFragranceNoteController;
use Botble\Ecommerce\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;

// Define a route group with a prefix
Route::prefix('admin/ecommerce/smsa')->group(function () {
    Route::get('/', [SmsaController::class, 'index'])->name('smsa.index');
    Route::get('/getData', [SmsaController::class, 'getData'])->name('smsa.data');
    Route::get('/edit/{id}', [SmsaController::class, 'edit'])->name('smsa.edit');
    Route::post('/bulkEdit', [SmsaController::class, 'bulkEdit'])->name('smsa.bulk-edit');
    Route::post('/submit', [SmsaController::class, 'submit'])->name('smsa.submit');
    Route::post('/bulkSubmit', [SmsaController::class, 'bulkSubmit'])->name('smsa.bulk-submit');
    Route::post('/bulkPrint', [SmsaController::class, 'bulkPrint'])->name('smsa.bulk-print');
    Route::get('/track/{awb}', [SmsaController::class, 'track'])->name('smsa.track');
});
Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
Route::get('promotions/data', [PromotionController::class, 'data'])->name('promotions.data');
Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
Route::delete('/promotions/bulk-delete', [PromotionController::class, 'bulkDelete'])->name('promotions.bulkDelete');
Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');

Route::get('products/get-for-tag-input', [
    'as' => 'products.get-for-tag-input',
    'uses' => '\Botble\Ecommerce\Http\Controllers\ProductController@getForTagInput', // Assumes your admin controller is named ProductController
    'permission' => 'products.index',
]);

// Route::group([ 'prefix' => 'admin', 'middleware' => ['web', 'auth'],], function () {
//     Route::resource('product-fragrance-notes', ProductFragranceNoteController::class)->parameters(['product-fragrance-notes' => 'id']);
//     Route::delete('product-fragrance-notes/items/destroy', [ProductFragranceNoteController::class, 'destroy'])->name('product-fragrance-notes.deletes');
// });

Route::resource('/admin/product-reviews', ProductReviewController::class);
Route::group(['prefix' => 'admin/product-reviews', 'as' => 'product-reviews.', 'middleware' => ['web', 'auth'],], function() {
    Route::get('/', [ProductReviewController::class, 'index'])->name('index');
    Route::get('/{product_review}', [ProductReviewController::class, 'show'])->name('show');
    Route::post('/{product_review}/approve', [ProductReviewController::class, 'approve'])->name('approve');
    Route::delete('/{product_review}', [ProductReviewController::class, 'destroy'])->name('destroy');
});
// --- START: Fragrance Profiles ---
Route::group([
    'prefix' => 'admin/product-fragrance-notes',
    'as' => 'product-fragrance-notes.',
    'middleware' => ['web', 'auth'],
], function () {
    Route::resource('', ProductFragranceNoteController::class)->parameters(['' => 'id']);
    Route::delete('items/destroy', [
        'as' => 'deletes',
        'uses' => '\Botble\Ecommerce\Http\Controllers\ProductFragranceNoteController@destroy',
        'permission' => 'products.destroy', // Reuse existing permission
    ]);
});

dashboard_menu()->registerItem([
    'id' => 'cms-plugins-product-fragrance-notes',
    'priority' => 6,
    'parent_id' => 'cms-plugins-ecommerce',
    'name' => 'Fragrance Profiles',
    'icon' => 'fa fa-vial',
    'url' => route('product-fragrance-notes.index'),
    'permissions' => ['product-fragrance-notes.index'],
]);
// --- END: Fragrance Profiles ---

// --- START: Analytics Dashboard ---
use App\Http\Controllers\Admin\AnalyticsController;

Route::group(['middleware' => ['web', 'auth']], function () {
    // Main dashboard — requires analytics.dashboard permission
    Route::get('/admin/analytics', [AnalyticsController::class, 'index'])
        ->name('analytics.dashboard');

    // Visitor journey — requires analytics.visitor-journey permission
    Route::get('/admin/analytics/visitor-journey/{visitor_id}', [AnalyticsController::class, 'visitorJourney'])
        ->name('analytics.visitor-journey');

    // Recovery email — requires analytics.dashboard permission (write action)
    Route::post('/admin/analytics/send-recovery-email', [AnalyticsController::class, 'sendRecoveryEmail'])
        ->name('analytics.send-recovery-email');

    // Recovery SMS — requires analytics.dashboard permission
    Route::post('/admin/analytics/send-recovery-sms', [AnalyticsController::class, 'sendRecoverySms'])
        ->name('analytics.send-recovery-sms');

    // AJAX load-more events — requires analytics.dashboard permission
    Route::get('/admin/analytics/load-more-events', [AnalyticsController::class, 'loadMoreEvents'])
        ->name('analytics.load-more-events');
});

dashboard_menu()->registerItem([
    'id' => 'cms-plugins-analytics-dashboard',
    'priority' => 7,
    'parent_id' => 'cms-plugins-ecommerce',
    'name' => 'Analytics Dashboard',
    'icon' => 'fa fa-chart-line',
    'url' => fn() => route('analytics.dashboard'),
    'permissions' => ['analytics.dashboard'],
]);
// --- END: Analytics Dashboard ---