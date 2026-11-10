<?php

namespace App\Services;

use Botble\Ecommerce\Enums\OrderStatusEnum;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Services\CreatePaymentForOrderService;
use Botble\Payment\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Direct Apple Pay through the PayTabs Direct Payment API.
 *
 * - Eligibility: backend allowlist (config/applepay.php), never the browser.
 * - Merchant validation: our own Apple Pay Merchant Identity Certificate
 *   against Apple's validationURL (PayTabs does not proxy this step).
 * - Charge: POST {PAYTABS_BASE_URL}/payment/request with apple_pay_token,
 *   amount always taken from the stored order.
 *
 * Never log the Apple Pay token or the merchant session.
 */
class ApplePayService
{
    // Apple Pay gateway hosts (production pods, China, and the sandbox "-cert" hosts)
    private const VALIDATION_HOST_PATTERN = '/^(cn-)?apple-pay-gateway(-[a-z0-9-]+)?\.apple\.com$/i';

    // PayTabs payment_result.response_status values that mean "not charged"
    private const DECLINED_STATUSES = ['D', 'E', 'X', 'V'];

    public function __construct(protected CreatePaymentForOrderService $createPaymentForOrderService)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  Eligibility                                                         */
    /* ------------------------------------------------------------------ */

    public function isEligible(?Customer $customer): bool
    {
        if (! $customer || ! config('applepay.enabled')) {
            return false;
        }

        return in_array((string) $customer->getKey(), config('applepay.allowed_customer_ids', []), true);
    }

    public function clientConfig(): array
    {
        return [
            'eligible' => true,
            'merchant_id' => config('applepay.merchant_id'),
            'display_name' => config('applepay.display_name'),
            'country_code' => config('applepay.country_code'),
            'currency_code' => config('applepay.currency_code'),
            'supported_networks' => config('applepay.supported_networks'),
            'merchant_capabilities' => config('applepay.merchant_capabilities'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Merchant validation                                                 */
    /* ------------------------------------------------------------------ */

    public function isValidValidationUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port'])
            && preg_match(self::VALIDATION_HOST_PATTERN, $parts['host'] ?? '') === 1;
    }

    /**
     * @return array Apple's opaque merchant session object
     * @throws RuntimeException
     */
    public function validateMerchant(string $validationUrl): array
    {
        $cert = $this->resolvePath(config('applepay.merchant_cert_path'));
        $key = $this->resolvePath(config('applepay.merchant_key_path'));
        $passphrase = config('applepay.merchant_key_passphrase');

        if (! is_readable($cert) || ! is_readable($key)) {
            throw new RuntimeException('Apple Pay merchant identity certificate or key is missing/unreadable.');
        }

        $response = Http::timeout(15)
            ->withOptions([
                'cert' => $cert,
                'ssl_key' => $passphrase ? [$key, $passphrase] : $key,
            ])
            ->asJson()
            ->post($validationUrl, [
                'merchantIdentifier' => config('applepay.merchant_id'),
                'displayName' => config('applepay.display_name'),
                'initiative' => 'web',
                'initiativeContext' => config('applepay.domain'),
            ]);

        $session = $response->json();

        if (! $response->successful() || ! is_array($session) || empty($session['merchantSessionIdentifier'])) {
            throw new RuntimeException('Apple merchant validation failed with HTTP ' . $response->status() . '.');
        }

        return $session;
    }

    /* ------------------------------------------------------------------ */
    /*  Charge                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0:int,1:array} [http status, response body]
     */
    public function charge(Customer $customer, string $orderCode, float $displayTotal, array $token, ?string $ip): array
    {
        $code = '#' . ltrim(trim($orderCode), '#');
        $order = Order::query()->where('code', $code)->orderByDesc('id')->first();

        if (! $order) {
            return [404, $this->body('failed', $code, 'Order not found.')];
        }

        if ((int) $order->user_id !== (int) $customer->getKey()) {
            Log::warning('ApplePay: order ownership mismatch', ['order' => $code, 'customer' => $customer->getKey()]);

            return [403, $this->body('failed', $code, 'Order not found.')];
        }

        // One charge attempt per order at a time
        $lock = Cache::lock('applepay:charge:' . $order->getKey(), 120);
        if (! $lock->get()) {
            return [409, $this->body('pending', $code, 'Payment is already being processed.')];
        }

        try {
            $order->refresh();

            // Idempotency: already paid → report success without charging again
            if ($this->hasCompletedPayment($order)) {
                return [200, $this->body('success', $code, 'Payment completed.')];
            }

            // PayTabs approved earlier but recording did not finish → never charge again
            if (Cache::has($this->approvedKey($order))) {
                return [200, $this->body('pending', $code, 'Payment is being verified.')];
            }

            if ($order->getRawOriginal('status') !== OrderStatusEnum::TRASH) {
                return [422, $this->body('failed', $code, 'This order can no longer be paid with Apple Pay.')];
            }

            $maxAge = (int) config('applepay.order_max_age_minutes', 30);
            if ($order->created_at && $order->created_at->lt(now()->subMinutes($maxAge))) {
                return [422, $this->body('failed', $code, 'This order has expired. Please place a new order.')];
            }

            $amount = round((float) $order->amount, 2);
            if ($amount <= 0 || abs($amount - round($displayTotal, 2)) > 0.01) {
                Log::warning('ApplePay: amount mismatch', ['order' => $code, 'server' => $amount, 'display' => $displayTotal]);

                return [422, $this->body('failed', $code, 'Your order total has changed. Please review your order and try again.')];
            }

            @set_time_limit(120);

            return $this->requestPayTabs($order, $amount, $token, $ip);
        } finally {
            $lock->release();
        }
    }

    protected function requestPayTabs(Order $order, float $amount, array $token, ?string $ip): array
    {
        $code = $order->code;
        $cartId = ltrim($code, '#');
        $serverKey = config('applepay.paytabs.server_key');
        $profileId = config('applepay.paytabs.profile_id');

        if (! $serverKey || ! $profileId) {
            Log::error('ApplePay: PayTabs credentials are not configured');

            return [503, $this->body('failed', $code, 'Apple Pay is temporarily unavailable.')];
        }

        $payload = [
            'profile_id' => (int) $profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cartId,
            'cart_description' => $this->cartDescription($order),
            'cart_currency' => config('applepay.currency_code', 'SAR'),
            'cart_amount' => $amount,
            'return' => 'none',
            'customer_details' => $this->customerDetails($order, $ip),
            'apple_pay_token' => $token,
        ];

        try {
            $response = Http::timeout(config('applepay.paytabs.timeout', 40))
                ->withHeaders(['authorization' => $serverKey])
                ->asJson()
                ->acceptJson()
                ->post(config('applepay.paytabs.base_url') . '/payment/request', $payload);
        } catch (ConnectionException $e) {
            Log::error('ApplePay: PayTabs connection error — outcome unknown, reconcile manually', ['order' => $code, 'error' => $e->getMessage()]);

            return [200, $this->body('pending', $code, 'Payment is being verified.')];
        }

        $json = $response->json();
        $status = data_get($json, 'payment_result.response_status');
        $tranRef = data_get($json, 'tran_ref');

        Log::info('ApplePay: PayTabs response', [
            'order' => $code,
            'http' => $response->status(),
            'tran_ref' => $tranRef,
            'response_status' => $status,
            'response_code' => data_get($json, 'payment_result.response_code'),
            'response_message' => data_get($json, 'payment_result.response_message'),
            'error_code' => data_get($json, 'code'),
            'error_message' => data_get($json, 'message'),
        ]);

        // Request rejected before any transaction was created (validation/auth error)
        if (! $status) {
            if ($response->serverError() || ! is_array($json)) {
                return [200, $this->body('pending', $code, 'Payment is being verified.')];
            }

            return [200, $this->body('failed', $code, 'Apple Pay payment could not be processed.')];
        }

        if ($status === 'A') {
            $verified = (string) data_get($json, 'cart_id') === $cartId
                && abs(round((float) data_get($json, 'tran_total'), 2) - $amount) <= 0.001
                && strtoupper((string) data_get($json, 'tran_currency')) === strtoupper(config('applepay.currency_code', 'SAR'));

            // Remember the approval first so a crash below can never lead to a second charge
            Cache::put($this->approvedKey($order), $tranRef ?: true, now()->addDays(2));

            if (! $verified) {
                Log::critical('ApplePay: approved transaction does not match order — manual review required', [
                    'order' => $code, 'tran_ref' => $tranRef,
                    'cart_id' => data_get($json, 'cart_id'), 'tran_total' => data_get($json, 'tran_total'), 'tran_currency' => data_get($json, 'tran_currency'),
                ]);

                return [200, $this->body('pending', $code, 'Payment is being verified.')];
            }

            $this->recordPayment($order, 'A', $tranRef, $json);

            if ($this->hasCompletedPayment($order)) {
                $order->update(['status' => OrderStatusEnum::PROCESSING]);

                return [200, $this->body('success', $code, 'Payment completed.')];
            }

            return [200, $this->body('pending', $code, 'Payment is being verified.')];
        }

        if (in_array($status, self::DECLINED_STATUSES, true)) {
            $this->recordPayment($order, $status, $tranRef, $json);

            return [200, $this->body('failed', $code, 'Your payment was declined. Please try another card or payment method.')];
        }

        // P (pending), H (on hold) or anything unexpected: do not mark paid or failed
        Log::warning('ApplePay: non-final PayTabs status', ['order' => $code, 'tran_ref' => $tranRef, 'response_status' => $status]);

        return [200, $this->body('pending', $code, 'Payment is being verified.')];
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    protected function recordPayment(Order $order, string $status, ?string $tranRef, array $json): void
    {
        $description = trim('ApplePay - ' . data_get($json, 'payment_info.payment_description', '')
            . ' - ' . data_get($json, 'payment_result.response_message', ''), ' -');

        try {
            // Existing service: payment row (charge_id = tran_ref), coupon redeem, SMS, email, history
            $this->createPaymentForOrderService->execute($order, 'paytabs', $status, $order->user_id, $tranRef, $description);
        } catch (Throwable $e) {
            Log::error('ApplePay: recording payment failed', ['order' => $order->code, 'tran_ref' => $tranRef, 'error' => $e->getMessage()]);
        }
    }

    protected function hasCompletedPayment(Order $order): bool
    {
        return Payment::query()
            ->where('order_id', $order->getKey())
            ->where('status', 'completed')
            ->exists();
    }

    protected function approvedKey(Order $order): string
    {
        return 'applepay:approved:' . $order->getKey();
    }

    protected function cartDescription(Order $order): string
    {
        $names = OrderProduct::query()
            ->where('order_id', $order->getKey())
            ->get(['product_name', 'qty'])
            ->map(fn ($p) => $p->product_name . ' (' . $p->qty . ')')
            ->implode(', ');

        return mb_substr($names ?: 'Order ' . $order->code, 0, 128);
    }

    protected function customerDetails(Order $order, ?string $ip): array
    {
        $address = $order->shippingAddress;
        $street = trim(($address->area ?? '') . ' ' . ($address->address ?? '') . ' ' . ($address->building_name ?? ''));

        return array_filter([
            'name' => $address->name ?: optional($order->user)->name,
            'email' => $address->email ?: optional($order->user)->email,
            'phone' => $address->phone ?: optional($order->user)->phone,
            'street1' => $street ?: ($address->city ?? null),
            'city' => $address->city ?: $address->state,
            'state' => $address->state ?: $address->city,
            'country' => 'SA',
            'ip' => $ip,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function body(string $status, ?string $orderCode, string $message): array
    {
        return ['status' => $status, 'order_code' => $orderCode, 'message' => $message];
    }

    protected function resolvePath(?string $path): string
    {
        $path = (string) $path;
        if ($path === '') {
            return '';
        }

        return preg_match('/^([a-zA-Z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }
}
