<?php

namespace AshrafBendary\SecureEmail\Tests\Feature;

use AshrafBendary\SecureEmail\Models\SnsSubscription;
use AshrafBendary\SecureEmail\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SubscribeUrlCommandTest extends TestCase
{
    #[Test]
    public function it_reports_when_there_are_no_unconfirmed_subscriptions(): void
    {
        $this->artisan('secure-email:subscribe-urls')
            ->expectsOutputToContain('No unconfirmed subscriptions found.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_lists_unconfirmed_subscriptions(): void
    {
        SnsSubscription::create([
            'topic_arn' => 'arn:aws:sns:us-east-1:123:ses-bounces',
            'type' => 'bounces',
            'subscribe_url' => 'https://sns.example.test/confirm-abc',
            'token' => 'abc',
        ]);

        $this->artisan('secure-email:subscribe-urls')
            ->expectsOutputToContain('arn:aws:sns:us-east-1:123:ses-bounces')
            ->expectsOutputToContain('https://sns.example.test/confirm-abc')
            ->assertSuccessful();
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        SnsSubscription::create([
            'topic_arn' => 'arn:aws:sns:us-east-1:123:ses-bounces',
            'type' => 'bounces',
            'subscribe_url' => 'https://sns.example.test/bounces',
            'token' => 'b',
        ]);

        SnsSubscription::create([
            'topic_arn' => 'arn:aws:sns:us-east-1:123:ses-complaints',
            'type' => 'complaints',
            'subscribe_url' => 'https://sns.example.test/complaints',
            'token' => 'c',
        ]);

        $this->artisan('secure-email:subscribe-urls', ['--type' => 'complaints'])
            ->expectsOutputToContain('https://sns.example.test/complaints')
            ->doesntExpectOutputToContain('https://sns.example.test/bounces')
            ->assertSuccessful();
    }

    #[Test]
    public function it_skips_confirmed_subscriptions(): void
    {
        $subscription = SnsSubscription::create([
            'topic_arn' => 'arn:aws:sns:us-east-1:123:ses-deliveries',
            'type' => 'deliveries',
            'subscribe_url' => 'https://sns.example.test/deliveries',
            'token' => 'd',
        ]);
        $subscription->markAsConfirmed('arn:aws:sns:us-east-1:123:ses-deliveries:sub-x');

        $this->artisan('secure-email:subscribe-urls')
            ->expectsOutputToContain('No unconfirmed subscriptions found.')
            ->assertSuccessful();
    }
}