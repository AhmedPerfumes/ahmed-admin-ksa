<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyInboxMediaRecoveryService
{
    /**
     * Send Abandoned Cart Recovery SMS via MyInboxMedia SendSMS API
     *
     * @param string $phone
     * @param string $message
     * @return array ['success' => bool, 'response' => string]
     */
    public function sendRecoverySMS(string $phone, string $message): array
    {
        try {
            $password = env("INBOXMEDIA_PASSWORD");
            
            // Format phone number: strip non-digits and leading zeros/country codes
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);
            if (str_starts_with($cleanPhone, '966')) {
                $cleanPhone = substr($cleanPhone, 3);
            }
            $cleanPhone = ltrim($cleanPhone, '0');
            $mobile = '966' . $cleanPhone;

            $url = "https://myinboxmedia.ae/api/mim/SendSMS?userid=MIM2500371&pwd=" . $password . "&mobile=" . $mobile . "&sender=AHMDPRF&msg=" . urlencode($message) . "&msgtype=19";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            Log::info("Cart Recovery SMS Sent to {$mobile} | HTTP {$httpCode} | Raw Response: {$result}");

            $isSuccess = ($httpCode >= 200 && $httpCode < 300) && (str_contains(strtolower($result), 'success') || str_contains(strtolower($result), 'sent') || str_contains(strtolower($result), 'ok') || str_contains($result, '19'));

            return [
                'success' => $isSuccess,
                'response' => $result ?? 'No response',
            ];
        } catch (\Exception $e) {
            Log::error("Cart Recovery SMS Exception: " . $e->getMessage());
            return [
                'success' => false,
                'response' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send Abandoned Cart Recovery WhatsApp via MyInboxMedia WhatsApp API
     * (Currently commented out & pre-configured for future activation)
     *
     * @param string $phone
     * @param string $customerName
     * @param string $checkoutUrl
     * @return array ['success' => bool, 'response' => string]
     */
    /*
    public function sendRecoveryWhatsApp(string $phone, string $customerName, string $checkoutUrl): array
    {
        try {
            $cleanPhone = preg_replace('/[^\d]/', '', $phone);
            if (str_starts_with($cleanPhone, '966')) {
                $cleanPhone = substr($cleanPhone, 3);
            }
            $cleanPhone = ltrim($cleanPhone, '0');
            $mobile = '966' . $cleanPhone;

            $payload = [
                "ProfileId" => "MIM2400074",
                "APIKey"    => "#JpXt4fbMCFj",
                "MobileNumber" => intval($mobile),
                "templateName" => "cart_recovery_message",
                "Parameters" => [
                    $customerName,
                    $checkoutUrl,
                ],
                "HeaderType" => "Text",
                "Text"       => "",
                "MediaUrl"   => "",
                "isTemplate" => "true",
            ];

            $ch = curl_init('https://waba.myinboxmedia.in/api/sendwaba');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            Log::info("Cart Recovery WhatsApp Sent to {$mobile} | HTTP {$httpCode} | Raw Response: {$result}");

            return [
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'response' => $result ?? 'No response',
            ];
        } catch (\Exception $e) {
            Log::error("Cart Recovery WhatsApp Exception: " . $e->getMessage());
            return [
                'success' => false,
                'response' => $e->getMessage(),
            ];
        }
    }
    */
}
