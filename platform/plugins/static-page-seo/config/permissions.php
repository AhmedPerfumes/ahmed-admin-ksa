<?php

return [
    [
        'name' => 'Static Page SEO',
        'flag' => 'static-page-seo.index',
    ],
    [
        'name' => 'Create',
        'flag' => 'static-page-seo.create',
        'parent_flag' => 'static-page-seo.index',
    ],
    [
        'name' => 'Edit',
        'flag' => 'static-page-seo.edit',
        'parent_flag' => 'static-page-seo.index',
    ],
    [
        'name' => 'Delete',
        'flag' => 'static-page-seo.destroy',
        'parent_flag' => 'static-page-seo.index',
    ],
];
