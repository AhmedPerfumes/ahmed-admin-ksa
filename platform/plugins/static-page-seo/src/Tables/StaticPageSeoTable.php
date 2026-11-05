<?php

namespace Botble\StaticPageSeo\Tables;

use Botble\StaticPageSeo\Models\StaticPageSeo;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\BulkChanges\CreatedAtBulkChange;
use Botble\Table\BulkChanges\NameBulkChange;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\LinkableColumn;
use Botble\Table\Columns\StatusColumn;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Illuminate\Database\Eloquent\Builder;

class StaticPageSeoTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(StaticPageSeo::class)
            ->addColumns([
                IdColumn::make(),
                LinkableColumn::make('name')
                    ->title(trans('plugins/static-page-seo::static-page-seo.name'))
                    ->route('static-page-seo.edit')
                    ->alignStart(),
                FormattedColumn::make('page_key')
                    ->title(trans('plugins/static-page-seo::static-page-seo.page_key'))
                    ->alignStart(),
                FormattedColumn::make('url_path')
                    ->title(trans('plugins/static-page-seo::static-page-seo.url_path'))
                    ->alignStart()
                    ->withEmptyState(),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addHeaderAction(CreateHeaderAction::make()->route('static-page-seo.create'))
            ->addActions([
                EditAction::make()->route('static-page-seo.edit'),
                DeleteAction::make()->route('static-page-seo.destroy'),
            ])
            ->addBulkAction(DeleteBulkAction::make()->permission('static-page-seo.destroy'))
            ->addBulkChanges([
                NameBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query) {
                return $query
                    ->select([
                        'id',
                        'name',
                        'page_key',
                        'url_path',
                        'status',
                        'created_at',
                    ]);
            });
    }
}
