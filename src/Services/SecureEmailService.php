<?php
// src/Services/SecureEmailService.php

namespace AshrafBendary\SecureEmail\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use AshrafBendary\SecureEmail\Models\EmailBlacklist;

class SecureEmailService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('secure-email');
    }

    /**
     * Validate email with ZeroBounce
     */
    public function validateWithZeroBounce(string $email): array
    {
        if (!$this->config['zerobounce']['enabled']) {
            return ['valid' => true, 'status' => 'validation_disabled'];
        }

        $cacheKey = 'zerobounce_' . md5(strtolower($email));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get('https://api.zerobounce.net/v2/validate', [
                'api_key' => $this->config['zerobounce']['api_key'],
                'email' => $email,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'valid' => in_array($data['status'], ['valid', 'catch-all']),
                    'status' => $data['status'],
                    'sub_status' => $data['sub_status'] ?? null,
                    'score' => $data['zero_bounce_score'] ?? null,
                ];

                Cache::put($cacheKey, $result, $this->config['zerobounce']['cache_ttl']);
                
                return $result;
            }

            return ['valid' => false, 'status' => 'api_error'];

        } catch (\Exception $e) {
            Log::error('[SecureEmail] ZeroBounce API error: ' . $e->getMessage());
            return ['valid' => true, 'status' => 'validation_unavailable'];
        }
    }

    /**
     * Check if email can be sent
     */
    public function canSendEmail(string $email): array
    {
        $email = strtolower($email);

        // Check blacklist
        if (EmailBlacklist::isBlacklisted($email)) {
            return [
                'can_send' => false,
                'reason' => 'blacklisted',
                'message' => 'Email is blacklisted',
            ];
        }

        // Validate with ZeroBounce
        $validation = $this->validateWithZeroBounce($email);
        
        if (!$validation['valid']) {
            EmailBlacklist::addToBlacklist($email, 'invalid', [
                'zerobounce_status' => $validation['status'],
            ]);

            return [
                'can_send' => false,
                'reason' => 'invalid_email',
                'message' => 'Email failed validation',
                'details' => $validation,
            ];
        }

        return [
            'can_send' => true,
            'validation' => $validation,
        ];
    }

    /**
     * Send email with validation
     */
    public function send($mailable, string $to, ?string $name = null): array
    {
        $check = $this->canSendEmail($to);
        
        if (!$check['can_send']) {
            Log::warning("[SecureEmail] Email blocked: {$to}", $check);
            return [
                'success' => false,
                'blocked' => true,
                'reason' => $check['reason'],
                'message' => $check['message'],
            ];
        }

        try {
            Mail::to($to, $name)->send($mailable);

            Log::info("[SecureEmail] Email sent successfully to: {$to}");
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error("[SecureEmail] Failed to send email to {$to}: " . $e->getMessage());
            
            return [
                'success' => false,
                'blocked' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch validate emails
     */
    public function validateBatch(array $emails): array
    {
        $results = [];
        
        foreach ($emails as $email) {
            $results[$email] = $this->canSendEmail($email);
        }
        
        return $results;
    }

    /**
     * Get ZeroBounce credits
     */
    public function getCredits(): ?int
    {
        try {
            $response = Http::timeout(5)->get('https://api.zerobounce.net/v2/getcredits', [
                'api_key' => $this->config['zerobounce']['api_key'],
            ]);

            if ($response->successful()) {
                return $response->json()['Credits'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('[SecureEmail] Failed to get ZeroBounce credits: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get blacklist statistics
     */
    public function getStats(): array
    {
        return [
            'total' => EmailBlacklist::count(),
            'bounces' => EmailBlacklist::where('reason', 'bounce')->count(),
            'complaints' => EmailBlacklist::where('reason', 'complaint')->count(),
            'invalid' => EmailBlacklist::where('reason', 'invalid')->count(),
            'manual' => EmailBlacklist::where('reason', 'manual')->count(),
        ];
    }

    /**
     * Add email to blacklist manually
     */
    public function blacklist(string $email, string $reason = 'manual'): void
    {
        EmailBlacklist::addToBlacklist($email, $reason);
    }

    /**
     * Remove email from blacklist
     */
    public function whitelist(string $email): bool
    {
        return EmailBlacklist::removeFromBlacklist($email);
    }
}