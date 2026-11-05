<?php

namespace Botble\StaticPageSeo\Http\Controllers;

use Botble\Base\Events\BeforeEditContentEvent;
use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\StaticPageSeo\Forms\StaticPageSeoForm;
use Botble\StaticPageSeo\Http\Requests\StaticPageSeoRequest;
use Botble\StaticPageSeo\Models\StaticPageSeo;
use Botble\StaticPageSeo\Tables\StaticPageSeoTable;
use Illuminate\Http\Request;

class StaticPageSeoController extends BaseController
{
    public function index(StaticPageSeoTable $table)
    {
        PageTitle::setTitle(trans('plugins/static-page-seo::static-page-seo.name'));

        return $table->renderTable();
    }

    public function create()
    {
        PageTitle::setTitle(trans('plugins/static-page-seo::static-page-seo.create'));

        return StaticPageSeoForm::create()->renderForm();
    }

    public function store(StaticPageSeoRequest $request, BaseHttpResponse $response)
    {
        $form = StaticPageSeoForm::create()->setRequest($request);
        $form->save();

        return $response
            ->setPreviousUrl(route('static-page-seo.index'))
            ->setNextUrl(route('static-page-seo.edit', $form->getModel()->id))
            ->withCreatedSuccessMessage();
    }

    public function edit(StaticPageSeo $staticPageSeo, Request $request)
    {
        event(new BeforeEditContentEvent($request, $staticPageSeo));

        PageTitle::setTitle(trans('core/base::forms.edit_item', ['name' => $staticPageSeo->name]));

        return StaticPageSeoForm::createFromModel($staticPageSeo)->renderForm();
    }

    public function update(StaticPageSeo $staticPageSeo, StaticPageSeoRequest $request, BaseHttpResponse $response)
    {
        StaticPageSeoForm::createFromModel($staticPageSeo)
            ->setRequest($request)
            ->save();

        return $response
            ->setPreviousUrl(route('static-page-seo.index'))
            ->withUpdatedSuccessMessage();
    }

    public function destroy(StaticPageSeo $staticPageSeo)
    {
        return DeleteResourceAction::make($staticPageSeo);
    }
}
