<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogRequestsAndResponses
{
    /**
     * Request fields that must never reach the logs (payment tokens, secrets).
     */
    protected array $redactedFields = ['apple_pay_token', 'password', 'password_confirmation'];

    /**
     * Headers that carry credentials.
     */
    protected array $redactedHeaders = ['authorization', 'cookie', 'x-xsrf-token'];

    /**
     * Routes whose response body is sensitive (Apple merchant session).
     */
    protected array $redactedResponsePaths = ['api/applePay/validateMerchant'];

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Log request
        Log::info('>>Incoming request<<', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $this->getHeaders($request),
            'payload' => $this->getPayload($request),
            'ip' => $request->ip(),
            // 'user_agent' => $request->header('User-Agent'),
        ]);

        $response = $next($request);

        // Log response
        Log::info('>>Outgoing response<<', [
            'status' => $response->status(),
            'headers' => $response->headers->all(),
            'request_id' => $request->header('X-Request-ID'),
            'body' => $request->is(...$this->redactedResponsePaths) ? '[redacted]' : $response->original,
            'exception' => $response->exception,
        ]);

        return $response;
    }

    /**
     * Request headers with credentials masked.
     */
    protected function getHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        foreach ($this->redactedHeaders as $name) {
            if (isset($headers[$name])) {
                $headers[$name] = ['[redacted]'];
            }
        }

        return $headers;
    }

    /**
     * Get request payload (limit the size to avoid excessive logging).
     *
     * @return array|string
     */
    protected function getPayload(Request $request)
    {
        $payload = $request->all();
        // if (strlen(json_encode($payload)) > 1000) {
            // return 'Payload too large to log';
        // }

        foreach ($this->redactedFields as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = '[redacted]';
            }
        }

        return $payload;
    }

    /**
     * Get response content (limit the size to avoid excessive logging).
     *
     * @return string
     */
    // protected function getContent(Response $response)
    // {
    //     $content = $response;
    //     if (strlen($content) > 1000) {
    //         return 'Content too large to log';
    //     }

    //     return $content;
    // }
}
