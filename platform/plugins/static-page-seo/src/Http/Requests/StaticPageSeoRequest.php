<?php

namespace Botble\StaticPageSeo\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class StaticPageSeoRequest extends Request
{
    public function rules(): array
    {
        $id = $this->route('static_page_seo')?->id ?: $this->route('static_page_seo');

        return [
            'name' => ['required', 'string', 'max:255'],
            'page_key' => [
                'required',
                'string',
                'max:120',
                Rule::unique('static_page_seos', 'page_key')->ignore($id),
            ],
            'url_path' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(BaseStatusEnum::values())],
        ];
    }
}
