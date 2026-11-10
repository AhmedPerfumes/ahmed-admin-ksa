<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApplePayService;
use Botble\Ecommerce\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ApplePayController extends Controller
{
    public function __construct(protected ApplePayService $applePay)
    {
    }

    /**
     * GET /api/applePay/config — tells the checkout whether to render the widget.
     * Non-eligible callers get {eligible:false} (no details leaked).
     */
    public function config(): JsonResponse
    {
        $customer = $this->customer();

        if (! $this->applePay->isEligible($customer)) {
            return response()->json(['eligible' => false]);
        }

        return response()->json($this->applePay->clientConfig());
    }

    /**
     * POST /api/applePay/validateMerchant {validation_url}
     */
    public function validateMerchant(Request $request): JsonResponse
    {
        if ($denied = $this->deny()) {
            return $denied;
        }

        $url = (string) $request->input('validation_url', '');

        if (! $this->applePay->isValidValidationUrl($url)) {
            return response()->json(['message' => 'Invalid validation URL.'], 422);
        }

        try {
            return response()->json(['merchant_session' => $this->applePay->validateMerchant($url)]);
        } catch (Throwable $e) {
            Log::error('ApplePay: merchant validation error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Apple Pay merchant validation failed.'], 502);
        }
    }

    /**
     * POST /api/applePay/charge {order_code, display_total, apple_pay_token}
     */
    public function charge(Request $request): JsonResponse
    {
        if ($denied = $this->deny()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'order_code' => 'required|string|max:64',
            'display_total' => 'required|numeric|min:0.01',
            'apple_pay_token' => 'required|array',
            'apple_pay_token.paymentData' => 'required|array',
            'apple_pay_token.paymentData.data' => 'required|string',
            'apple_pay_token.paymentData.signature' => 'required|string',
            'apple_pay_token.paymentData.version' => 'required|string',
            'apple_pay_token.paymentData.header' => 'required|array',
            'apple_pay_token.paymentMethod' => 'nullable|array',
            'apple_pay_token.transactionIdentifier' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'order_code' => $request->input('order_code'),
                'message' => 'Invalid Apple Pay payment data.',
            ], 422);
        }

        [$httpStatus, $body] = $this->applePay->charge(
            $this->customer(),
            (string) $request->input('order_code'),
            (float) $request->input('display_total'),
            $request->input('apple_pay_token'),
            $request->ip()
        );

        return response()->json($body, $httpStatus);
    }

    protected function customer(): ?Customer
    {
        $user = Auth::guard('api')->user();

        return $user instanceof Customer ? $user : null;
    }

    /**
     * 401 when not authenticated, 403 when not on the Apple Pay allowlist.
     */
    protected function deny(): ?JsonResponse
    {
        $customer = $this->customer();

        if (! $customer) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
        }

        if (! $this->applePay->isEligible($customer)) {
            return response()->json(['status' => 'failed', 'message' => 'Apple Pay is not available for this account.'], 403);
        }

        return null;
    }
}
