<?php

namespace Botble\StaticPageSeo\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;

class StaticPageSeo extends BaseModel
{
    protected $table = 'static_page_seos';

    protected $fillable = [
        'name',
        'page_key',
        'url_path',
        'status',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
        'page_key' => SafeContent::class,
        'url_path' => SafeContent::class,
    ];

    public function getSlugAttribute(): string
    {
        return $this->page_key ?: '';
    }

    public function getUrlAttribute(): string
    {
        $path = $this->url_path ?: ($this->page_key === 'home' ? '/' : '/' . ltrim($this->page_key, '/'));
        $baseUrl = env('CUSTOM_URL', config('app.url'));

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
