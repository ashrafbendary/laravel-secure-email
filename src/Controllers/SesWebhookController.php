<?php
// src/Controllers/SesWebhookController.php

namespace AshrafBendary\SecureEmail\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Models\EmailBlacklist;

class SesWebhookController extends Controller
{
    /**
     * Handle bounce notifications from AWS SES
     */
    public function handleBounce(Request $request)
    {
        try {
            $message = $this->parseMessage($request);
            
            if (!$message) {
                return response()->json(['error' => 'Invalid message'], 400);
            }

            // Handle SNS subscription confirmation
            if ($message['Type'] === 'SubscriptionConfirmation') {
                return $this->confirmSubscription($message);
            }

            $notification = json_decode($message['Message'], true);
            $bounce = $notification['bounce'];
            
            foreach ($bounce['bouncedRecipients'] as $recipient) {
                $email = $recipient['emailAddress'];
                $bounceType = strtolower($bounce['bounceType']);
                
                // Log notification
                SesNotification::create([
                    'message_id' => $notification['mail']['messageId'] ?? null,
                    'email' => $email,
                    'type' => 'bounce',
                    'status' => $bounceType,
                    'raw_notification' => $notification,
                ]);
                
                // Auto-blacklist hard bounces
                if ($bounceType === 'permanent' && config('secure-email.blacklist.auto_blacklist_hard_bounces', true)) {
                    EmailBlacklist::addToBlacklist($email, 'bounce', [
                        'bounce_type' => $bounceType,
                        'diagnostic_code' => $recipient['diagnosticCode'] ?? null,
                    ]);
                    
                    Log::warning("[SecureEmail] Email blacklisted due to bounce: {$email}");
                }
                
                // Handle soft bounces with threshold
                if ($bounceType === 'transient') {
                    $blacklist = EmailBlacklist::where('email', $email)->first();
                    $bounceCount = $blacklist ? $blacklist->bounce_count : 0;
                    
                    if ($bounceCount >= config('secure-email.blacklist.soft_bounce_threshold', 3)) {
                        EmailBlacklist::addToBlacklist($email, 'bounce', [
                            'bounce_type' => 'soft',
                            'reason' => 'Exceeded soft bounce threshold',
                        ]);
                    }
                }
            }
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('[SecureEmail] Bounce webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Handle complaint notifications from AWS SES
     */
    public function handleComplaint(Request $request)
    {
        try {
            $message = $this->parseMessage($request);
            
            if (!$message) {
                return response()->json(['error' => 'Invalid message'], 400);
            }

            // Handle SNS subscription confirmation
            if ($message['Type'] === 'SubscriptionConfirmation') {
                return $this->confirmSubscription($message);
            }

            $notification = json_decode($message['Message'], true);
            $complaint = $notification['complaint'];
            
            foreach ($complaint['complainedRecipients'] as $recipient) {
                $email = $recipient['emailAddress'];
                
                // Log notification
                SesNotification::create([
                    'message_id' => $notification['mail']['messageId'] ?? null,
                    'email' => $email,
                    'type' => 'complaint',
                    'status' => $complaint['complaintFeedbackType'] ?? 'unknown',
                    'raw_notification' => $notification,
                ]);
                
                // Auto-blacklist complaints
                if (config('secure-email.blacklist.auto_blacklist_complaints', true)) {
                    EmailBlacklist::addToBlacklist($email, 'complaint', [
                        'complaint_type' => $complaint['complaintFeedbackType'] ?? 'unknown',
                        'user_agent' => $complaint['userAgent'] ?? null,
                    ]);
                    
                    Log::warning("[SecureEmail] Email blacklisted due to complaint: {$email}");
                }
            }
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('[SecureEmail] Complaint webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Handle delivery notifications from AWS SES
     */
    public function handleDelivery(Request $request)
    {
        try {
            $message = $this->parseMessage($request);
            
            if (!$message) {
                return response()->json(['error' => 'Invalid message'], 400);
            }

            // Handle SNS subscription confirmation
            if ($message['Type'] === 'SubscriptionConfirmation') {
                return $this->confirmSubscription($message);
            }

            $notification = json_decode($message['Message'], true);
            $delivery = $notification['delivery'];
            
            foreach ($delivery['recipients'] as $email) {
                // Log successful delivery
                SesNotification::create([
                    'message_id' => $notification['mail']['messageId'] ?? null,
                    'email' => $email,
                    'type' => 'delivery',
                    'status' => 'delivered',
                    'raw_notification' => $notification,
                ]);
            }
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('[SecureEmail] Delivery webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Parse SNS message from request
     */
    protected function parseMessage(Request $request): ?array
    {
        $content = $request->getContent();
        $message = json_decode($content, true);
        
        if (!$message || !isset($message['Type'])) {
            return null;
        }
        
        return $message;
    }

    /**
     * Confirm SNS subscription
     */
    protected function confirmSubscription(array $message)
    {
        if (isset($message['SubscribeURL'])) {
            try {
                $response = Http::get($message['SubscribeURL']);

                if ($response->successful()) {
                    Log::info('[SecureEmail] SNS subscription confirmed: ' . $message['SubscribeURL']);
                    return response()->json(['success' => true, 'message' => 'Subscription confirmed']);
                }

                Log::error('[SecureEmail] SNS subscription confirmation returned non-success response');
                return response()->json(['error' => 'Subscription confirmation failed'], 500);
            } catch (\Exception $e) {
                Log::error('[SecureEmail] Failed to confirm SNS subscription: ' . $e->getMessage());
                return response()->json(['error' => 'Subscription confirmation failed'], 500);
            }
        }
        
        return response()->json(['error' => 'No subscription URL'], 400);
    }
}