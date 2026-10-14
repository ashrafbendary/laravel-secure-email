<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Facades\SesMonitor;
use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Rules\DeliverableEmail;
use AshrafBendary\SecureEmail\Tests\TestCase;
use AshrafBendary\SecureEmail\Validation\DeliverabilityChecker;
use AshrafBendary\SecureEmail\Validation\EmailValidator;
use AshrafBendary\SecureEmail\Validation\ValidationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class DeliverabilityTest extends TestCase
{
    private function useZeroBounce(string $apiKey = 'test-key'): void
    {
        config()->set('secure-email.validation.driver', 'zerobounce');
        config()->set('secure-email.validation.zerobounce.api_key', $apiKey);
        config()->set('secure-email.validation.zerobounce.base_url', 'https://api.zerobounce.net/v2');
    }

    /** @var (\Closure(): mixed)|null */
    private ?\Closure $responder = null;

    /**
     * Http::fake() stubs stack and the first match wins, so register a single
     * fake that defers to a swappable responder.
     */
    private function respondWith(\Closure $responder): void
    {
        if ($this->responder === null) {
            Http::fake(fn () => ($this->responder)());
        }

        $this->responder = $responder;
    }

    private function fakeStatus(string $status): void
    {
        $this->respondWith(fn () => Http::response(['status' => $status, 'sub_status' => '']));
    }

    private function checker(): DeliverabilityChecker
    {
        return app(DeliverabilityChecker::class);
    }

    private function address(string $prefix = 'user'): string
    {
        return $prefix.'-'.uniqid().'@example.test';
    }

    #[Test]
    public function null_driver_makes_no_http_calls_and_passes_clean_addresses(): void
    {
        Http::fake();

        $this->assertTrue($this->checker()->passes($this->address()));
        Http::assertNothingSent();
    }

    #[Test]
    public function history_rejects_a_permanent_bounce_without_any_provider(): void
    {
        $email = $this->address('perm');
        SesNotification::create([
            'message_id' => 'perm-'.uniqid(),
            'type' => 'Bounce',
            'notification_type' => 'Permanent',
            'email' => $email,
            'bounce_type' => 'Permanent',
            'notification_data' => [],
        ]);

        $result = $this->checker()->check($email);

        $this->assertFalse($result->passes);
        $this->assertSame('permanent_bounce', $result->reason);
    }

    #[Test]
    public function history_rejects_complaints_and_can_be_switched_off(): void
    {
        $email = $this->address('complaint');
        SesNotification::create([
            'message_id' => 'c-'.uniqid(),
            'type' => 'Complaint',
            'email' => $email,
            'notification_data' => [],
        ]);

        $this->assertSame('complaints', $this->checker()->check($email)->reason);

        config()->set('secure-email.validation.check_history', false);
        $this->assertTrue($this->checker()->passes($email));
    }

    #[Test]
    public function zerobounce_valid_and_unknown_pass(): void
    {
        $this->useZeroBounce();

        $this->fakeStatus('valid');
        $this->assertTrue($this->checker()->check($this->address())->passes);

        $this->fakeStatus('unknown');
        $result = $this->checker()->check($this->address());
        $this->assertTrue($result->passes);
        $this->assertSame('unknown', $result->providerStatus);
    }

    #[Test]
    public function zerobounce_hard_reject_statuses_fail_as_undeliverable(): void
    {
        $this->useZeroBounce();

        foreach (['invalid', 'spamtrap', 'abuse', 'do_not_mail'] as $status) {
            $this->fakeStatus($status);
            $result = $this->checker()->check($this->address($status));

            $this->assertFalse($result->passes, $status);
            $this->assertSame('undeliverable', $result->reason);
            $this->assertSame($status, $result->providerStatus);
        }
    }

    #[Test]
    public function accepted_verdicts_are_cached_and_rejections_are_not(): void
    {
        $this->useZeroBounce();

        $this->fakeStatus('valid');
        $good = $this->address('good');
        $this->checker()->check($good);
        $this->checker()->check(strtoupper($good));
        Http::assertSentCount(1);

        $this->fakeStatus('invalid');
        $bad = $this->address('bad');
        $this->checker()->check($bad);
        $this->checker()->check($bad);
        Http::assertSentCount(3); // 1 cached accept + 2 uncached rejects
    }

    #[Test]
    public function provider_errors_soft_fail_and_are_not_cached(): void
    {
        $this->useZeroBounce();
        Log::spy();
        $this->respondWith(fn () => Http::response('', 500));

        $email = $this->address('outage');
        $this->assertTrue($this->checker()->passes($email));
        $this->assertTrue($this->checker()->passes($email));
        Http::assertSentCount(2);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => ! str_contains(json_encode($context), $email)
                && ($context['domain'] ?? null) === 'example.test'
        );
    }

    #[Test]
    public function connection_failures_and_error_payloads_soft_fail(): void
    {
        $this->useZeroBounce();

        $this->respondWith(fn () => throw new ConnectionException('timeout'));
        $this->assertTrue($this->checker()->passes($this->address('timeout')));

        $this->respondWith(fn () => Http::response(['error' => 'Invalid API Key']));
        $this->assertTrue($this->checker()->passes($this->address('nokey')));
    }

    #[Test]
    public function a_blank_api_key_skips_the_lookup(): void
    {
        $this->useZeroBounce('');
        Http::fake();

        $this->assertTrue($this->checker()->passes($this->address()));
        Http::assertNothingSent();
    }

    #[Test]
    public function the_request_carries_the_documented_parameters(): void
    {
        $this->useZeroBounce('secret');
        $this->fakeStatus('valid');

        $email = $this->address();
        $this->checker()->check($email);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.zerobounce.net/v2/validate?')
            && $request['api_key'] === 'secret'
            && $request['email'] === $email);
    }

    #[Test]
    public function the_rule_fails_with_a_translated_message(): void
    {
        $this->useZeroBounce();
        $this->fakeStatus('invalid');

        $validator = Validator::make(['email' => $this->address()], ['email' => [new DeliverableEmail]]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('cannot receive email', $validator->errors()->first('email'));

        app()->setLocale('ar');
        $validator = Validator::make(['email' => $this->address()], ['email' => [new DeliverableEmail]]);
        $this->assertStringContainsString('لا يستقبل الرسائل', $validator->errors()->first('email'));
    }

    #[Test]
    public function the_rule_passes_deliverable_and_empty_values(): void
    {
        $this->useZeroBounce();
        $this->fakeStatus('valid');

        $this->assertTrue(Validator::make(['email' => $this->address()], ['email' => [new DeliverableEmail]])->passes());
        $this->assertTrue(Validator::make(['email' => ''], ['email' => [new DeliverableEmail]])->passes());
    }

    #[Test]
    public function the_facade_exposes_deliverability(): void
    {
        $this->useZeroBounce();
        $this->fakeStatus('spamtrap');

        $this->assertFalse(SesMonitor::isDeliverable($this->address()));
        $this->assertSame('undeliverable', SesMonitor::checkDeliverability($this->address())->reason);
    }

    #[Test]
    public function a_disabled_package_accepts_everything(): void
    {
        $this->useZeroBounce();
        config()->set('secure-email.enabled', false);
        Http::fake();

        $this->assertTrue($this->checker()->passes($this->address()));
        Http::assertNothingSent();
    }

    #[Test]
    public function a_custom_driver_class_is_used(): void
    {
        config()->set('secure-email.validation.driver', RejectEverythingValidator::class);

        $this->assertSame('undeliverable', $this->checker()->check($this->address())->reason);
    }

    #[Test]
    public function an_invalid_driver_class_is_reported(): void
    {
        config()->set('secure-email.validation.driver', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->checker();
    }
}

final class RejectEverythingValidator implements EmailValidator
{
    public function validate(string $email): ValidationResult
    {
        return ValidationResult::rejected('custom');
    }
}
