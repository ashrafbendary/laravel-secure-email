<?php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Tests\TestCase;
use AshrafBendary\SecureEmail\Models\SnsSubscription;
use AshrafBendary\SecureEmail\Models\SesNotification;
use PHPUnit\Framework\Attributes\Test;
class SesNotificationTest extends TestCase
{
    #[Test]
    public function it_can_create_a_bounce_notification(): void
    {
        $notification = SesNotification::create([
            'message_id' => 'test-message-id',
            'type' => 'Bounce',
            'notification_type' => 'Permanent',
            'email' => '[email protected]',
            'subject' => 'Test Subject',
            'bounce_type' => 'Permanent',
            'bounce_sub_type' => 'General',
            'notification_data' => ['test' => 'data'],
            'sent_at' => now(),
        ]);

        $this->assertDatabaseHas('ses_notifications', [
            'message_id' => 'test-message-id',
            'email' => '[email protected]',
            'type' => 'Bounce',
        ]);

        $this->assertTrue($notification->isBounce());
        $this->assertTrue($notification->isPermanentBounce());
    }

    #[Test]
    public function it_can_count_bounces_for_an_email(): void
    {
        // Create multiple bounce notifications
        for ($i = 0; $i < 3; $i++) {
            SesNotification::create([
                'message_id' => "test-message-{$i}",
                'type' => 'Bounce',
                'notification_type' => 'Permanent',
                'email' => '[email protected]',
                'subject' => 'Test Subject',
                'bounce_type' => 'Permanent',
                'notification_data' => [],
            ]);
        }

        $count = SesNotification::countBouncesForEmail('[email protected]');
        
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_can_detect_permanent_bounces(): void
    {
        $bouncedEmail = 'bounced-' . uniqid() . '@' . 'example.test';
        $cleanEmail = 'clean-' . uniqid() . '@' . 'example.test';

        SesNotification::create([
            'message_id' => 'test-permanent',
            'type' => 'Bounce',
            'notification_type' => 'Permanent',
            'email' => $bouncedEmail,
            'bounce_type' => 'Permanent',
            'notification_data' => [],
        ]);

        $this->assertTrue(
            SesNotification::hasPermanentBounce($bouncedEmail)
        );
        
        $this->assertFalse(
            SesNotification::hasPermanentBounce($cleanEmail)
        );
    }

    #[Test]
    public function it_can_create_a_complaint_notification(): void
    {
        $notification = SesNotification::create([
            'message_id' => 'test-complaint',
            'type' => 'Complaint',
            'notification_type' => 'abuse',
            'email' => '[email protected]',
            'subject' => 'Test Subject',
            'complaint_feedback_type' => 'abuse',
            'notification_data' => [],
        ]);

        $this->assertTrue($notification->isComplaint());
        $this->assertFalse($notification->isBounce());
    }

    #[Test]
    public function it_can_query_notifications_by_type(): void
    {
        // Create bounce
        SesNotification::create([
            'message_id' => 'bounce-1',
            'type' => 'Bounce',
            'email' => '[email protected]',
            'notification_data' => [],
        ]);

        // Create complaint
        SesNotification::create([
            'message_id' => 'complaint-1',
            'type' => 'Complaint',
            'email' => '[email protected]',
            'notification_data' => [],
        ]);

        $this->assertEquals(1, SesNotification::bounces()->count());
        $this->assertEquals(1, SesNotification::complaints()->count());
    }

    #[Test]
    public function it_can_create_sns_subscription(): void
    {
        $subscription = SnsSubscription::create([
            'topic_arn' => 'arn:aws:sns:us-east-1:123456789:test-topic',
            'type' => 'bounces',
            'subscribe_url' => 'https://example.com/subscribe',
            'token' => 'test-token',
        ]);

        $this->assertFalse($subscription->isConfirmed());
        
        $subscription->markAsConfirmed('arn:aws:sns:us-east-1:123456789:subscription');
        
        $this->assertTrue($subscription->fresh()->isConfirmed());
    }
}
