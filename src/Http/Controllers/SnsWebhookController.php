<?php

namespace AshrafBendary\SecureEmail\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use AshrafBendary\SecureEmail\Models\SnsSubscription;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Events\SesBounceReceived;
use AshrafBendary\SecureEmail\Events\SesComplaintReceived;
use AshrafBendary\SecureEmail\Events\SesDeliveryReceived;

class SnsWebhookController extends Controller
{
    protected function notificationModel(): string
    {
        return config('secure-email.models.notification') ?: SesNotification::class;
    }

    protected function subscriptionModel(): string
    {
        return config('secure-email.models.subscription') ?: SnsSubscription::class;
    }

    /**
     * Handle bounce notifications.
     */
    public function handleBounce(Request $request): JsonResponse
    {
        return $this->handleNotification($request, 'bounces');
    }

    /**
     * Handle complaint notifications.
     */
    public function handleComplaint(Request $request): JsonResponse
    {
        return $this->handleNotification($request, 'complaints');
    }

    /**
     * Handle delivery notifications.
     */
    public function handleDelivery(Request $request): JsonResponse
    {
        return $this->handleNotification($request, 'deliveries');
    }

    /**
     * Handle SNS notification.
     */
    protected function handleNotification(Request $request, string $type): JsonResponse
    {
        try {
            $message = $this->validateAndParseMessage($request);
            
            if (!$message) {
                return response()->json(['error' => 'Invalid message'], 400);
            }

            $messageType = $message['Type'] ?? null;

            switch ($messageType) {
                case 'SubscriptionConfirmation':
                    return $this->handleSubscriptionConfirmation($message, $type);
                    
                case 'Notification':
                    return $this->handleSesNotification($message, $type);
                    
                default:
                    Log::warning('Unknown SNS message type', ['type' => $messageType]);
                    return response()->json(['error' => 'Unknown message type'], 400);
            }
        } catch (\Throwable $e) {
            Log::error('Error handling SNS notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Validate and parse SNS message.
     */
    protected function validateAndParseMessage(Request $request): ?array
    {
        $message = json_decode($request->getContent(), true);

        if (!$message) {
            return null;
        }

        // Validate SNS message signature if enabled
        if (config('secure-email.validate_sns_messages', true)) {
            try {
                $validator = new MessageValidator();
                $snsMessage = Message::fromRawPostData();
                $validator->validate($snsMessage);
            } catch (\Throwable $e) {
                Log::error('SNS message validation failed', ['error' => $e->getMessage()]);
                return null;
            }
        }

        return $message;
    }

    /**
     * Handle subscription confirmation.
     */
    protected function handleSubscriptionConfirmation(array $message, string $type): JsonResponse
    {
        $topicArn = $message['TopicArn'] ?? null;
        $subscribeUrl = $message['SubscribeURL'] ?? null;
        $token = $message['Token'] ?? null;

        if (!$topicArn || !$subscribeUrl) {
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        $subscriptionModel = $this->subscriptionModel();

        $subscription = $subscriptionModel::updateOrCreate(
            ['topic_arn' => $topicArn],
            [
                'type' => $type,
                'subscribe_url' => $subscribeUrl,
                'token' => $token,
            ]
        );

        // Auto-confirm if enabled
        if (config('secure-email.auto_confirm_subscriptions', true)) {
            $this->confirmSubscription($subscribeUrl, $subscription);
        }

        return response()->json(['message' => 'Subscription confirmation received'], 200);
    }

    /**
     * Confirm SNS subscription.
     */
    protected function confirmSubscription(string $subscribeUrl, \Illuminate\Database\Eloquent\Model $subscription): void
    {
        try {
            $response = Http::timeout(10)->get($subscribeUrl);

            if (!$response->successful()) {
                Log::warning('SNS subscribe URL returned non-2xx', [
                    'status' => $response->status(),
                ]);
                return;
            }
            $data = $response->json();
            $subscriptionArn = $data['SubscribeResponse']['SubscribeResult']['SubscriptionArn'] ?? null;

            if ($subscriptionArn) {
                $subscription->markAsConfirmed($subscriptionArn);
                Log::info('SNS subscription confirmed', ['subscription_arn' => $subscriptionArn]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to confirm SNS subscription', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle SES notification.
     */
    protected function handleSesNotification(array $message, string $type): JsonResponse
    {
        $notificationMessage = json_decode($message['Message'] ?? '{}', true);
        
        if (!$notificationMessage) {
            return response()->json(['error' => 'Invalid notification'], 400);
        }

        $notificationType = $notificationMessage['notificationType'] ?? null;

        switch ($notificationType) {
            case 'Bounce':
                $this->processBounce($notificationMessage);
                break;
                
            case 'Complaint':
                $this->processComplaint($notificationMessage);
                break;
                
            case 'Delivery':
                $this->processDelivery($notificationMessage);
                break;
                
            default:
                Log::warning('Unknown notification type', ['type' => $notificationType]);
                return response()->json(['error' => 'Unknown notification type'], 400);
        }

        return response()->json(['message' => 'Notification processed'], 200);
    }

    /**
     * Process bounce notification.
     */
    protected function processBounce(array $notification): void
    {
        $bounce = $notification['bounce'] ?? [];
        $mail = $notification['mail'] ?? [];
        $notificationModel = $this->notificationModel();

        $bouncedRecipients = $bounce['bouncedRecipients'] ?? [];

        foreach ($bouncedRecipients as $recipient) {
            $email = $recipient['emailAddress'] ?? null;
            
            if (!$email) {
                continue;
            }

            $sesNotification = $notificationModel::create([
                'message_id' => $mail['messageId'] ?? null,
                'type' => 'Bounce',
                'notification_type' => $bounce['bounceType'] ?? null,
                'email' => $email,
                'subject' => $mail['commonHeaders']['subject'] ?? null,
                'bounce_type' => $bounce['bounceType'] ?? null,
                'bounce_sub_type' => $bounce['bounceSubType'] ?? null,
                'notification_data' => $notification,
                'sent_at' => isset($mail['timestamp']) ? new \DateTime($mail['timestamp']) : null,
            ]);

            if (config('secure-email.events.bounce', true)) {
                event(new SesBounceReceived($sesNotification));
            }
        }
    }

    /**
     * Process complaint notification.
     */
    protected function processComplaint(array $notification): void
    {
        $complaint = $notification['complaint'] ?? [];
        $mail = $notification['mail'] ?? [];
        $notificationModel = $this->notificationModel();

        $complainedRecipients = $complaint['complainedRecipients'] ?? [];

        foreach ($complainedRecipients as $recipient) {
            $email = $recipient['emailAddress'] ?? null;
            
            if (!$email) {
                continue;
            }

            $sesNotification = $notificationModel::create([
                'message_id' => $mail['messageId'] ?? null,
                'type' => 'Complaint',
                'notification_type' => $complaint['complaintFeedbackType'] ?? 'abuse',
                'email' => $email,
                'subject' => $mail['commonHeaders']['subject'] ?? null,
                'complaint_feedback_type' => $complaint['complaintFeedbackType'] ?? null,
                'notification_data' => $notification,
                'sent_at' => isset($mail['timestamp']) ? new \DateTime($mail['timestamp']) : null,
            ]);

            if (config('secure-email.events.complaint', true)) {
                event(new SesComplaintReceived($sesNotification));
            }
        }
    }

    /**
     * Process delivery notification.
     */
    protected function processDelivery(array $notification): void
    {
        $delivery = $notification['delivery'] ?? [];
        $mail = $notification['mail'] ?? [];
        $notificationModel = $this->notificationModel();

        $recipients = $delivery['recipients'] ?? [];

        foreach ($recipients as $email) {
            $sesNotification = $notificationModel::create([
                'message_id' => $mail['messageId'] ?? null,
                'type' => 'Delivery',
                'notification_type' => 'delivered',
                'email' => $email,
                'subject' => $mail['commonHeaders']['subject'] ?? null,
                'notification_data' => $notification,
                'sent_at' => isset($mail['timestamp']) ? new \DateTime($mail['timestamp']) : null,
            ]);

            if (config('secure-email.events.delivery', true)) {
                event(new SesDeliveryReceived($sesNotification));
            }
        }
    }
}
