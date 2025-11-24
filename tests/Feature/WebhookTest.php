<?php
// tests/Feature/WebhookTest.php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Tests\TestCase;
use AshrafBendary\SecureEmail\Models\EmailBlacklist;
use AshrafBendary\SecureEmail\Models\SesNotification;
use Illuminate\Support\Facades\Http;

class WebhookTest extends TestCase
{
    /** @test */
    public function it_handles_bounce_webhook()
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'mail' => [
                    'messageId' => 'test-message-123',
                    'timestamp' => '2025-01-01T12:00:00.000Z',
                ],
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [
                        [
                            'emailAddress' => 'bounce@example.com',
                            'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                        ],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson('/webhooks/ses/bounce', $payload);

        $response->assertStatus(200);
        $this->assertTrue(EmailBlacklist::isBlacklisted('bounce@example.com'));
        $this->assertDatabaseHas('ses_notifications', [
            'email' => 'bounce@example.com',
            'type' => 'bounce',
        ]);
    }

    /** @test */
    public function it_handles_complaint_webhook()
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Complaint',
                'mail' => [
                    'messageId' => 'test-message-456',
                ],
                'complaint' => [
                    'complaintFeedbackType' => 'abuse',
                    'complainedRecipients' => [
                        [
                            'emailAddress' => 'complaint@example.com',
                        ],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson('/webhooks/ses/complaint', $payload);

        $response->assertStatus(200);
        $this->assertTrue(EmailBlacklist::isBlacklisted('complaint@example.com'));
    }

    /** @test */
    public function it_handles_delivery_webhook()
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Delivery',
                'mail' => [
                    'messageId' => 'test-message-789',
                ],
                'delivery' => [
                    'recipients' => ['delivered@example.com'],
                ],
            ]),
        ];

        $response = $this->postJson('/webhooks/ses/delivery', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ses_notifications', [
            'email' => 'delivered@example.com',
            'type' => 'delivery',
        ]);
    }

    /** @test */
    public function it_confirms_sns_subscription()
    {
        Http::fake();

        $payload = [
            'Type' => 'SubscriptionConfirmation',
            'SubscribeURL' => 'https://sns.amazonaws.com/confirm-subscription',
            'Message' => 'Subscription confirmation',
        ];

        $response = $this->postJson('/webhooks/ses/bounce', $payload);

        $response->assertStatus(200);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sns.amazonaws.com/confirm-subscription';
        });
    }

    /** @test */
    public function it_handles_soft_bounce_threshold()
    {
        $email = 'softbounce@example.com';

        // First 2 soft bounces - should not blacklist
        for ($i = 0; $i < 2; $i++) {
            EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'soft']);
        }

        $this->assertFalse(EmailBlacklist::isBlacklisted($email));

        // 3rd soft bounce - should blacklist
        EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'soft']);
        
        $blacklist = EmailBlacklist::where('email', $email)->first();
        $this->assertEquals(3, $blacklist->bounce_count);
    }
}