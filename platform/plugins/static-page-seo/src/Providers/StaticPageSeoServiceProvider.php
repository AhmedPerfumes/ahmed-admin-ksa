<?php

namespace Botble\StaticPageSeo\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Botble\SeoHelper\Facades\SeoHelper;
use Botble\StaticPageSeo\Models\StaticPageSeo;

class StaticPageSeoServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/static-page-seo')
            ->loadAndPublishConfigurations(['permissions'])
            ->loadMigrations()
            ->loadAndPublishTranslations()
            ->loadRoutes(['web', 'api']);

        DashboardMenu::default()->beforeRetrieving(function () {
            DashboardMenu::make()
                ->registerItem([
                    'id' => 'cms-plugins-static-page-seo',
                    'priority' => 15,
                    'name' => 'plugins/static-page-seo::static-page-seo.menu_name',
                    'icon' => 'ti ti-seo',
                    'route' => 'static-page-seo.index',
                    'permissions' => ['static-page-seo.index'],
                ]);
        });

        $this->app->booted(function () {
            SeoHelper::registerModule([
                StaticPageSeo::class,
            ]);

            if (defined('LANGUAGE_ADVANCED_MODULE_SCREEN_NAME')) {
                LanguageAdvancedManager::registerModule(StaticPageSeo::class, [
                    'name',
                ]);
            }
        });
    }
}
