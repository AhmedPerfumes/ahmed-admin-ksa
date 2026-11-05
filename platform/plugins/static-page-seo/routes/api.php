<?php

use Botble\StaticPageSeo\Http\Controllers\Api\StaticPageSeoApiController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'api',
    'middleware' => ['api'],
], function () {
    Route::match(['get', 'post'], '/staticPageSEO', [StaticPageSeoApiController::class, 'getStaticPageSEO']);
});
