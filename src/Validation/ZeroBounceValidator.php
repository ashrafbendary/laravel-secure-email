<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ZeroBounce /v2/validate driver.
 *
 * - Accepted verdicts are cached (default 24h) so retries don't burn credits.
 * - Rejected verdicts are never cached, so a user who fixes a typo, or a
 *   mailbox that comes back, is re-checked on the next attempt.
 * - Outages soft-fail: logged and returned as "unavailable", never thrown.
 * - Logs carry only the recipient domain, never the full address or API key.
 */
final class ZeroBounceValidator implements EmailValidator
{
    public const DEFAULT_REJECTED_STATUSES = ['invalid', 'spamtrap', 'abuse', 'do_not_mail'];

    /**
     * @param  array<string, mixed>  $config  The `secure-email.validation.zerobounce` block.
     */
    public function __construct(private readonly array $config) {}

    public function validate(string $email): ValidationResult
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return ValidationResult::skipped();
        }

        $email = trim($email);
        $cache = Cache::store($this->config['cache_store'] ?? null);
        $cacheKey = 'secure-email:zerobounce:'.sha1(Str::lower($email));

        $cached = $cache->get($cacheKey);
        if (is_array($cached) && isset($cached['status'])) {
            return $this->toResult((string) $cached['status'], $cached['sub_status'] ?? null);
        }

        $response = $this->lookup($email, $apiKey);
        if ($response === null) {
            return ValidationResult::unavailable();
        }

        $result = $this->toResult($response['status'], $response['sub_status']);

        if ($result->deliverable === true) {
            $cache->put($cacheKey, $response, (int) ($this->config['cache_ttl'] ?? 86400));
        }

        return $result;
    }

    /**
     * @return array{status: string, sub_status: ?string}|null  null on any failure
     */
    private function lookup(string $email, string $apiKey): ?array
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://api.zerobounce.net/v2'), '/');
        $domain = Str::after($email, '@');

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->acceptJson()
                ->get($baseUrl.'/validate', [
                    'api_key' => $apiKey,
                    'email' => $email,
                    'ip_address' => '',
                ]);
        } catch (\Throwable $e) {
            Log::warning('ZeroBounce lookup failed', ['domain' => $domain, 'error' => $e::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ZeroBounce returned an error status', ['domain' => $domain, 'status' => $response->status()]);

            return null;
        }

        $status = $response->json('status');
        if (! is_string($status) || $status === '') {
            // e.g. {"error": "Invalid API Key or your account ran out of credits"}
            Log::warning('ZeroBounce returned no status', ['domain' => $domain]);

            return null;
        }

        $subStatus = $response->json('sub_status');

        return [
            'status' => Str::lower($status),
            'sub_status' => is_string($subStatus) && $subStatus !== '' ? $subStatus : null,
        ];
    }

    private function toResult(string $status, ?string $subStatus): ValidationResult
    {
        $rejected = array_map(
            'strtolower',
            (array) ($this->config['rejected_statuses'] ?? self::DEFAULT_REJECTED_STATUSES)
        );

        return in_array($status, $rejected, true)
            ? ValidationResult::rejected($status, $subStatus)
            : ValidationResult::accepted($status, $subStatus);
    }
}
