<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrackerEvent;
use App\Models\TrackerRecoveryLog;
use App\Services\MyInboxMediaRecoveryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendCartRecoveryMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:cart-recovery {--session_id= : Specific session ID to recover}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated SMS recovery messages for abandoned carts (2+ hours old)';

    /**
     * Execute the console command.
     */
    public function handle(MyInboxMediaRecoveryService $recoveryService)
    {
        $specificSession = $this->option('session_id');

        $query = TrackerEvent::whereIn('event_name', ['begin_checkout', 'verify_otp', 'add_payment_info'])
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '');

        if ($specificSession) {
            $query->where('session_id', $specificSession);
        } else {
            // Find abandoned carts created between 2 hours and 24 hours ago
            $query->whereBetween('created_at', [
                Carbon::now()->subHours(24),
                Carbon::now()->subHours(2)
            ]);
        }

        $checkoutEvents = $query->orderBy('created_at', 'DESC')->get();

        if ($checkoutEvents->isEmpty()) {
            $this->info('No eligible abandoned checkout sessions found.');
            return 0;
        }

        $processedSessions = [];
        $sentCount = 0;

        foreach ($checkoutEvents as $event) {
            $sessionId = $event->session_id;
            $phone = preg_replace('/[^\d]/', '', $event->customer_phone);

            if (empty($phone) || in_array($sessionId, $processedSessions)) {
                continue;
            }

            $processedSessions[] = $sessionId;

            // 1. Skip if customer already completed a purchase for this session or phone
            $hasPurchased = TrackerEvent::where('event_name', 'purchase')
                ->where(function ($q) use ($sessionId, $phone) {
                    $q->where('session_id', $sessionId)
                      ->orWhere('customer_phone', $phone);
                })
                ->exists();

            if ($hasPurchased) {
                continue;
            }

            // 2. Skip if already sent a recovery message for this session or phone in the last 48 hours
            $alreadySent = TrackerRecoveryLog::where(function ($q) use ($sessionId, $phone) {
                    $q->where('session_id', $sessionId)
                      ->orWhere('phone', $phone);
                })
                ->where('created_at', '>=', Carbon::now()->subHours(48))
                ->exists();

            if ($alreadySent) {
                continue;
            }

            // 3. Construct Localized Recovery Message based on session page URL language
            $isEnglish = str_contains(strtolower($event->page_url ?? ''), '/en');
            $langCode  = $isEnglish ? 'en' : 'ar';
            $checkoutUrl = "https://ksa.ahmedalmaghribi.com/{$langCode}/shop-cart?utm_source=sms_recovery&utm_medium=sms&utm_campaign=cart_abandonment&recovery_session_id={$sessionId}";

            if ($isEnglish) {
                $message = "Dear Customer, items in your cart at Ahmed Al Maghribi Perfumes are waiting for you! 🛒 Complete your order now: {$checkoutUrl}";
            } else {
                $message = "عزيزي العميل، منتجاتك المفضلة في أحمد المغربي للعطور تنتظرك بالسلة! 🛒 أتمم طلبك الآن: {$checkoutUrl}";
            }

            // 4. Send Recovery SMS
            $result = $recoveryService->sendRecoverySMS($phone, $message);

            // 5. Log Recovery Execution
            TrackerRecoveryLog::create([
                'session_id'     => $sessionId,
                'phone'          => $phone,
                'channel'        => 'sms',
                'status'         => $result['success'] ? 'sent' : 'failed',
                'message_content'=> $message,
                'api_response'   => substr($result['response'] ?? '', 0, 1000),
                'created_at'     => Carbon::now(),
            ]);

            if ($result['success']) {
                $sentCount++;
                $this->info("Recovery SMS successfully sent to phone {$phone} (Session: {$sessionId})");
            } else {
                $this->error("Failed sending SMS to phone {$phone}: " . ($result['response'] ?? 'Unknown error'));
            }
        }

        $this->info("Completed cart recovery execution. Sent: {$sentCount} messages.");
        return 0;
    }
}
