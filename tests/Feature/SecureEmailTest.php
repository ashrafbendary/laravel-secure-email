<?php
// tests/Feature/SecureEmailTest.php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Tests\TestCase;
use AshrafBendary\SecureEmail\Facades\SecureEmail;
use AshrafBendary\SecureEmail\Models\EmailBlacklist;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Services\SecureEmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Mail\Mailable;

class SecureEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_can_add_email_to_blacklist()
    {
        $email = 'test@example.com';
        
        EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'hard']);
        
        $this->assertDatabaseHas('email_blacklist', [
            'email' => $email,
            'reason' => 'bounce',
        ]);
        
        $this->assertTrue(EmailBlacklist::isBlacklisted($email));
    }

    /** @test */
    public function it_can_remove_email_from_blacklist()
    {
        $email = 'test@example.com';
        
        EmailBlacklist::addToBlacklist($email, 'bounce');
        $this->assertTrue(EmailBlacklist::isBlacklisted($email));
        
        EmailBlacklist::removeFromBlacklist($email);
        $this->assertFalse(EmailBlacklist::isBlacklisted($email));
    }

    /** @test */
    public function it_blocks_blacklisted_emails()
    {
        $email = 'blocked@example.com';
        EmailBlacklist::addToBlacklist($email, 'bounce');
        
        $result = SecureEmail::canSendEmail($email);
        
        $this->assertFalse($result['can_send']);
        $this->assertEquals('blacklisted', $result['reason']);
    }

    /** @test */
    public function it_allows_valid_emails()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'valid',
                'sub_status' => '',
                'zero_bounce_score' => 10,
            ], 200),
        ]);

        $email = 'valid@example.com';
        $result = SecureEmail::canSendEmail($email);
        
        $this->assertTrue($result['can_send']);
        $this->assertTrue($result['validation']['valid']);
    }

    /** @test */
    public function it_validates_with_zerobounce()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'valid',
                'sub_status' => '',
                'zero_bounce_score' => 10,
            ], 200),
        ]);

        $service = app(SecureEmailService::class);
        $result = $service->validateWithZeroBounce('test@example.com');
        
        $this->assertTrue($result['valid']);
        $this->assertEquals('valid', $result['status']);
    }

    /** @test */
    public function it_caches_zerobounce_results()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'valid',
                'sub_status' => '',
                'zero_bounce_score' => 10,
            ], 200),
        ]);

        $service = app(SecureEmailService::class);
        $email = 'cached@example.com';
        
        // First call
        $result1 = $service->validateWithZeroBounce($email);
        
        // Second call should use cache
        Http::fake([]); // No HTTP responses available
        $result2 = $service->validateWithZeroBounce($email);
        
        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_blacklists_invalid_emails_from_zerobounce()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'invalid',
                'sub_status' => 'mailbox_not_found',
            ], 200),
        ]);

        $email = 'invalid@example.com';
        $result = SecureEmail::canSendEmail($email);
        
        $this->assertFalse($result['can_send']);
        $this->assertEquals('invalid_email', $result['reason']);
        $this->assertTrue(EmailBlacklist::isBlacklisted($email));
    }

    /** @test */
    public function it_can_send_email_through_facade()
    {
        Mail::fake();
        
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'valid',
                'sub_status' => '',
            ], 200),
        ]);

        $mailable = new TestMailable();
        $result = SecureEmail::send($mailable, 'test@example.com', 'Test User');
        
        $this->assertTrue($result['success']);
        Mail::assertSent(TestMailable::class);
    }

    /** @test */
    public function it_blocks_sending_to_blacklisted_emails()
    {
        Mail::fake();
        
        $email = 'blocked@example.com';
        EmailBlacklist::addToBlacklist($email, 'manual');
        
        $mailable = new TestMailable();
        $result = SecureEmail::send($mailable, $email);
        
        $this->assertFalse($result['success']);
        $this->assertTrue($result['blocked']);
        $this->assertEquals('blacklisted', $result['reason']);
        Mail::assertNothingSent();
    }

    /** @test */
    public function it_can_validate_batch_emails()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'valid',
            ], 200),
        ]);

        $emails = [
            'test1@example.com',
            'test2@example.com',
            'test3@example.com',
        ];
        
        $results = SecureEmail::validateBatch($emails);
        
        $this->assertCount(3, $results);
        foreach ($results as $email => $result) {
            $this->assertTrue($result['can_send']);
        }
    }

    /** @test */
    public function it_returns_blacklist_statistics()
    {
        EmailBlacklist::addToBlacklist('bounce1@example.com', 'bounce');
        EmailBlacklist::addToBlacklist('bounce2@example.com', 'bounce');
        EmailBlacklist::addToBlacklist('complaint@example.com', 'complaint');
        EmailBlacklist::addToBlacklist('invalid@example.com', 'invalid');
        EmailBlacklist::addToBlacklist('manual@example.com', 'manual');
        
        $stats = SecureEmail::getStats();
        
        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['bounces']);
        $this->assertEquals(1, $stats['complaints']);
        $this->assertEquals(1, $stats['invalid']);
        $this->assertEquals(1, $stats['manual']);
    }

    /** @test */
    public function it_handles_zerobounce_api_errors_gracefully()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([], 500),
        ]);

        $service = app(SecureEmailService::class);
        $result = $service->validateWithZeroBounce('test@example.com');
        
        // Should allow email when API is unavailable
        $this->assertTrue($result['valid']);
        $this->assertEquals('validation_unavailable', $result['status']);
    }

    /** @test */
    public function it_can_get_zerobounce_credits()
    {
        Http::fake([
            'api.zerobounce.net/v2/getcredits*' => Http::response([
                'Credits' => 1000,
            ], 200),
        ]);

        $credits = SecureEmail::getCredits();
        
        $this->assertEquals(1000, $credits);
    }

    /** @test */
    public function it_increments_bounce_count_on_repeated_bounces()
    {
        $email = 'bounce@example.com';
        
        EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'soft']);
        EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'soft']);
        EmailBlacklist::addToBlacklist($email, 'bounce', ['bounce_type' => 'soft']);
        
        $blacklist = EmailBlacklist::where('email', $email)->first();
        
        $this->assertEquals(3, $blacklist->bounce_count);
    }

    /** @test */
    public function it_stores_ses_notifications()
    {
        SesNotification::create([
            'message_id' => 'test-message-123',
            'email' => 'test@example.com',
            'type' => 'bounce',
            'status' => 'permanent',
            'raw_notification' => ['test' => 'data'],
        ]);
        
        $this->assertDatabaseHas('ses_notifications', [
            'message_id' => 'test-message-123',
            'email' => 'test@example.com',
            'type' => 'bounce',
        ]);
    }

    /** @test */
    public function it_normalizes_email_addresses_to_lowercase()
    {
        $email = 'TEST@EXAMPLE.COM';
        
        EmailBlacklist::addToBlacklist($email, 'manual');
        
        $this->assertTrue(EmailBlacklist::isBlacklisted('test@example.com'));
        $this->assertTrue(EmailBlacklist::isBlacklisted('TEST@EXAMPLE.COM'));
    }

    /** @test */
    public function it_can_whitelist_email_using_facade()
    {
        $email = 'test@example.com';
        
        SecureEmail::blacklist($email);
        $this->assertTrue(EmailBlacklist::isBlacklisted($email));
        
        SecureEmail::whitelist($email);
        $this->assertFalse(EmailBlacklist::isBlacklisted($email));
    }

    /** @test */
    public function it_handles_catch_all_emails_as_valid()
    {
        Http::fake([
            'api.zerobounce.net/*' => Http::response([
                'status' => 'catch-all',
                'sub_status' => '',
            ], 200),
        ]);

        $result = SecureEmail::canSendEmail('catchall@example.com');
        
        $this->assertTrue($result['can_send']);
    }
}

/**
 * Test Mailable class
 */
class TestMailable extends Mailable
{
    public function build()
    {
        return $this->subject('Test Email')
                    ->view('emails.test');
    }
}
