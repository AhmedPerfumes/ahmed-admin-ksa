<?php

namespace Botble\StaticPageSeo\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Botble\StaticPageSeo\Models\StaticPageSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaticPageSeoApiController extends Controller
{
    public function getStaticPageSEO(Request $request): JsonResponse
    {
        $page = $request->input('page') ?? $request->input('page_key') ?? $request->input('slug');

        if (! $page) {
            return response()->json([
                'status' => false,
                'message' => 'Kindly Provide Page Key or Slug',
            ], 400);
        }

        $page = trim($page);
        $normalizedKey = trim($page, '/');
        if ($page === '/' || $page === '') {
            $normalizedKey = 'home';
        }

        $record = StaticPageSeo::query()
            ->where('status', 'published')
            ->where(function ($query) use ($page, $normalizedKey) {
                $query->where('page_key', $normalizedKey)
                    ->orWhere('page_key', $page)
                    ->orWhere('url_path', $page)
                    ->orWhere('url_path', '/' . $normalizedKey);
            })
            ->first();

        if (! $record) {
            return response()->json([
                'status' => false,
                'message' => 'Static Page SEO not found',
            ], 404);
        }

        $seoMeta = DB::table('meta_boxes')
            ->where('reference_id', $record->id)
            ->where('reference_type', StaticPageSeo::class)
            ->where('meta_key', 'seo_meta')
            ->value('meta_value');

        $arSeoMeta = DB::table('meta_boxes')
            ->where('reference_id', $record->id)
            ->where('reference_type', StaticPageSeo::class)
            ->where('meta_key', 'ar_seo_meta')
            ->value('meta_value');

        $parsedMeta = [];
        if (! empty($seoMeta)) {
            $decoded = json_decode($seoMeta, true);
            $parsedMeta = is_array($decoded) && isset($decoded[0]) ? $decoded[0] : (is_array($decoded) ? $decoded : []);
        }

        $parsedArMeta = [];
        if (! empty($arSeoMeta)) {
            $decodedAr = json_decode($arSeoMeta, true);
            $parsedArMeta = is_array($decodedAr) && isset($decodedAr[0]) ? $decodedAr[0] : (is_array($decodedAr) ? $decodedAr : []);
        }

        return response()->json([
            'status' => true,
            'id' => $record->id,
            'name' => $record->name,
            'page_key' => $record->page_key,
            'url_path' => $record->url_path,
            'meta_value' => $seoMeta ?: '[]',
            'seo_title' => $parsedMeta['seo_title'] ?? null,
            'seo_description' => $parsedMeta['seo_description'] ?? null,
            'seo_image' => $parsedMeta['seo_image'] ?? null,
            'index' => $parsedMeta['index'] ?? 'index',
            'seo_title_ar' => $parsedArMeta['seo_title'] ?? ($parsedMeta['seo_title_ar'] ?? null),
            'seo_description_ar' => $parsedArMeta['seo_description'] ?? ($parsedMeta['seo_description_ar'] ?? null),
        ]);
    }
}
