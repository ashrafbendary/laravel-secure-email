<?php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Listeners\CheckEmailBeforeSending;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Tests\TestCase;
use Illuminate\Mail\Events\MessageSending;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class CheckEmailBeforeSendingTest extends TestCase
{
    private function makeEvent(string $to, string $subject = 'Hello'): MessageSending
    {
        $from = 'sender' . '@' . 'example.test';

        $message = (new Email())
            ->from($from)
            ->to(new Address($to))
            ->subject($subject)
            ->text('hi');

        return new MessageSending($message);
    }

    #[Test]
    public function it_allows_email_when_recipient_has_no_history(): void
    {
        $this->assertTrue(
            (new CheckEmailBeforeSending())->handle(
                $this->makeEvent('unknown-' . uniqid() . '@example.test')
            )
        );
    }

    #[Test]
    public function it_blocks_email_with_a_permanent_bounce(): void
    {
        $email = 'perm-' . uniqid() . '@example.test';

        SesNotification::create([
            'message_id' => 'perm-1',
            'type' => 'Bounce',
            'notification_type' => 'Permanent',
            'email' => $email,
            'bounce_type' => 'Permanent',
            'notification_data' => [],
        ]);

        $this->assertFalse(
            (new CheckEmailBeforeSending())->handle($this->makeEvent($email))
        );
    }

    #[Test]
    public function it_blocks_email_after_reaching_bounce_threshold(): void
    {
        config()->set('secure-email.rules.bounces.max_bounces', 2);
        config()->set('secure-email.rules.bounces.block_permanent_bounces', false);
        config()->set('secure-email.rules.bounces.days_to_check', 0);

        $email = 'transient-' . uniqid() . '@example.test';

        foreach (range(1, 2) as $i) {
            SesNotification::create([
                'message_id' => "transient-{$i}-" . uniqid(),
                'type' => 'Bounce',
                'notification_type' => 'Transient',
                'email' => $email,
                'bounce_type' => 'Transient',
                'notification_data' => [],
            ]);
        }

        $this->assertFalse(
            (new CheckEmailBeforeSending())->handle($this->makeEvent($email))
        );
    }

    #[Test]
    public function it_blocks_email_on_a_single_complaint(): void
    {
        config()->set('secure-email.rules.complaints.max_complaints', 1);
        config()->set('secure-email.rules.complaints.check_by_subject', false);

        $email = 'comp-' . uniqid() . '@example.test';

        SesNotification::create([
            'message_id' => 'comp-1-' . uniqid(),
            'type' => 'Complaint',
            'notification_type' => 'abuse',
            'email' => $email,
            'complaint_feedback_type' => 'abuse',
            'notification_data' => [],
        ]);

        $this->assertFalse(
            (new CheckEmailBeforeSending())->handle($this->makeEvent($email))
        );
    }

    #[Test]
    public function it_allows_email_when_package_is_disabled(): void
    {
        config()->set('secure-email.enabled', false);

        $email = 'perm-' . uniqid() . '@example.test';

        SesNotification::create([
            'message_id' => 'perm-disabled-' . uniqid(),
            'type' => 'Bounce',
            'notification_type' => 'Permanent',
            'email' => $email,
            'bounce_type' => 'Permanent',
            'notification_data' => [],
        ]);

        $this->assertTrue(
            (new CheckEmailBeforeSending())->handle($this->makeEvent($email))
        );
    }
}