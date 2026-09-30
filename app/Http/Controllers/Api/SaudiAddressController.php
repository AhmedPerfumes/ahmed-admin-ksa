<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SaudiAddressProService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaudiAddressController extends Controller
{
    protected SaudiAddressProService $addressService;

    public function __construct(SaudiAddressProService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * Resolve short national address code (e.g. RCTB4359) to full address details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function lookup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shortAddress' => 'required|string',
            'language' => 'nullable|string|in:en,ar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'isValid' => false,
                'errors' => $validator->errors(),
                'message' => 'Valid short address code is required.'
            ], 422);
        }

        $shortAddress = (string) $request->input('shortAddress');
        $language = (string) $request->input('language', 'en');

        $result = $this->addressService->lookup($shortAddress, $language);

        $status = ($result['isValid'] ?? false) ? 200 : 404;

        return response()->json($result, $status);
    }

    /**
     * Search address suggestions by keyword.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
            'language' => 'nullable|string|in:en,ar',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Search query is required (min 2 characters).'
            ], 422);
        }

        $query = (string) $request->input('q');
        $language = (string) $request->input('language', 'en');
        $limit = (int) $request->input('limit', 5);

        $result = $this->addressService->search($query, $language, $limit);

        return response()->json($result);
    }
}
