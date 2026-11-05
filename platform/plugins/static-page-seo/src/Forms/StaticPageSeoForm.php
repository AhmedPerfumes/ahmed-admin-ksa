<?php

namespace Botble\StaticPageSeo\Forms;

use Botble\Base\Forms\FieldOptions\NameFieldOption;
use Botble\Base\Forms\FieldOptions\StatusFieldOption;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\StaticPageSeo\Http\Requests\StaticPageSeoRequest;
use Botble\StaticPageSeo\Models\StaticPageSeo;

class StaticPageSeoForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(StaticPageSeo::class)
            ->setValidatorClass(StaticPageSeoRequest::class)
            ->add(
                'name',
                TextField::class,
                NameFieldOption::make()
                    ->label(trans('plugins/static-page-seo::static-page-seo.name'))
                    ->required()
                    ->toArray()
            )
            ->add('page_key', TextField::class, [
                'label' => trans('plugins/static-page-seo::static-page-seo.page_key'),
                'required' => true,
                'attr' => [
                    'placeholder' => 'e.g. home, about, contact, terms',
                    'data-counter' => 120,
                ],
                'help_block' => [
                    'text' => trans('plugins/static-page-seo::static-page-seo.page_key_help'),
                ],
            ])
            ->add('url_path', TextField::class, [
                'label' => trans('plugins/static-page-seo::static-page-seo.url_path'),
                'attr' => [
                    'placeholder' => 'e.g. /, /about, /contact',
                    'data-counter' => 255,
                ],
            ])
            ->add('status', SelectField::class, StatusFieldOption::make()->toArray())
            ->setBreakFieldPoint('status');
    }
}
