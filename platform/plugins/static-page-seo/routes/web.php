<?php

use Botble\Base\Facades\AdminHelper;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Botble\StaticPageSeo\Http\Controllers'], function () {
    AdminHelper::registerRoutes(function () {
        Route::group(['prefix' => 'static-page-seos', 'as' => 'static-page-seo.'], function () {
            Route::resource('', 'StaticPageSeoController')->parameters(['' => 'static-page-seo']);
        });
    });
});
