<?php

namespace AshrafBendary\SecureEmail\Console;

use Illuminate\Console\Command;
use AshrafBendary\SecureEmail\Models\SnsSubscription;

class SubscribeUrlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'secure-email:subscribe-urls 
                            {--type= : Filter by type (bounces, complaints, deliveries)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display SNS subscription URLs for manual confirmation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        
        $query = SnsSubscription::unconfirmed();
        
        if ($type) {
            $query->ofType($type);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No unconfirmed subscriptions found.');
            return Command::SUCCESS;
        }

        $this->info('Unconfirmed SNS Subscriptions:');
        $this->newLine();

        foreach ($subscriptions as $subscription) {
            $this->line('Type: ' . $subscription->type);
            $this->line('Topic ARN: ' . $subscription->topic_arn);
            $this->line('Subscribe URL: ' . $subscription->subscribe_url);
            $this->newLine();
            $this->line('To confirm this subscription, visit the URL above or run:');
            $this->comment('curl "' . $subscription->subscribe_url . '"');
            $this->newLine();
            $this->line('---');
            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
