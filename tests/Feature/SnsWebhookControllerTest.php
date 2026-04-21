<?php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Events\SesBounceReceived;
use AshrafBendary\SecureEmail\Events\SesComplaintReceived;
use AshrafBendary\SecureEmail\Events\SesDeliveryReceived;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Models\SnsSubscription;
use AshrafBendary\SecureEmail\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class SnsWebhookControllerTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('secure-email.validate_sns_messages', false);
        $app['config']->set('secure-email.auto_confirm_subscriptions', false);
    }

    #[Test]
    public function it_stores_a_bounce_notification_and_dispatches_event(): void
    {
        Event::fake([SesBounceReceived::class]);

        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [
                        ['emailAddress' => '[email protected]'],
                    ],
                ],
                'mail' => [
                    'messageId' => 'msg-1',
                    'timestamp' => '2026-04-21T00:00:00.000Z',
                    'commonHeaders' => ['subject' => 'Welcome'],
                ],
            ]),
        ];

        $response = $this->postJson('/aws/sns/ses/bounces', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('ses_notifications', [
            'message_id' => 'msg-1',
            'email' => '[email protected]',
            'type' => 'Bounce',
            'bounce_type' => 'Permanent',
        ]);
        Event::assertDispatched(SesBounceReceived::class);
    }

    #[Test]
    public function it_stores_a_complaint_notification_and_dispatches_event(): void
    {
        Event::fake([SesComplaintReceived::class]);

        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Complaint',
                'complaint' => [
                    'complaintFeedbackType' => 'abuse',
                    'complainedRecipients' => [
                        ['emailAddress' => '[email protected]'],
                    ],
                ],
                'mail' => [
                    'messageId' => 'msg-2',
                    'commonHeaders' => ['subject' => 'Promo'],
                ],
            ]),
        ];

        $response = $this->postJson('/aws/sns/ses/complaints', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('ses_notifications', [
            'message_id' => 'msg-2',
            'type' => 'Complaint',
            'complaint_feedback_type' => 'abuse',
        ]);
        Event::assertDispatched(SesComplaintReceived::class);
    }

    #[Test]
    public function it_stores_a_delivery_notification_and_dispatches_event(): void
    {
        Event::fake([SesDeliveryReceived::class]);

        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Delivery',
                'delivery' => [
                    'recipients' => ['[email protected]'],
                ],
                'mail' => [
                    'messageId' => 'msg-3',
                    'commonHeaders' => ['subject' => 'Receipt'],
                ],
            ]),
        ];

        $response = $this->postJson('/aws/sns/ses/deliveries', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('ses_notifications', [
            'message_id' => 'msg-3',
            'type' => 'Delivery',
        ]);
        Event::assertDispatched(SesDeliveryReceived::class);
    }

    #[Test]
    public function it_stores_a_subscription_confirmation_request(): void
    {
        $payload = [
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => 'arn:aws:sns:us-east-1:123:ses-bounces',
            'SubscribeURL' => 'https://sns.example.test/confirm',
            'Token' => 'abc123',
        ];

        $response = $this->postJson('/aws/sns/ses/bounces', $payload);

        $response->assertOk();
        $subscription = SnsSubscription::where('topic_arn', 'arn:aws:sns:us-east-1:123:ses-bounces')->first();
        $this->assertNotNull($subscription);
        $this->assertFalse($subscription->isConfirmed());
    }

    #[Test]
    public function it_auto_confirms_subscription_when_enabled(): void
    {
        config()->set('secure-email.auto_confirm_subscriptions', true);

        Http::fake([
            'https://sns.example.test/confirm*' => Http::response([
                'SubscribeResponse' => [
                    'SubscribeResult' => [
                        'SubscriptionArn' => 'arn:aws:sns:us-east-1:123:ses-bounces:sub-xyz',
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/aws/sns/ses/bounces', [
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => 'arn:aws:sns:us-east-1:123:ses-bounces',
            'SubscribeURL' => 'https://sns.example.test/confirm',
            'Token' => 'abc123',
        ])->assertOk();

        $subscription = SnsSubscription::where('topic_arn', 'arn:aws:sns:us-east-1:123:ses-bounces')->first();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->fresh()->isConfirmed());
        $this->assertSame(
            'arn:aws:sns:us-east-1:123:ses-bounces:sub-xyz',
            $subscription->fresh()->subscription_arn
        );
    }

    #[Test]
    public function it_returns_400_for_unknown_message_type(): void
    {
        $this->postJson('/aws/sns/ses/bounces', ['Type' => 'Nope'])
            ->assertStatus(400);
    }

    #[Test]
    public function it_returns_400_for_invalid_json_body(): void
    {
        $response = $this->call(
            'POST',
            '/aws/sns/ses/bounces',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not-json'
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function it_skips_recipients_without_email_addresses(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Transient',
                    'bouncedRecipients' => [
                        ['emailAddress' => '[email protected]'],
                        [], // no email address
                    ],
                ],
                'mail' => ['messageId' => 'msg-skip'],
            ]),
        ];

        $this->postJson('/aws/sns/ses/bounces', $payload)->assertOk();

        $this->assertSame(1, SesNotification::where('message_id', 'msg-skip')->count());
    }
}