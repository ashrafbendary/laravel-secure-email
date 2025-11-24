# Usage Examples

This document provides practical examples of using Laravel SES Monitor in your application.

## Table of Contents

1. [Basic Usage](#basic-usage)
2. [Checking Email Status](#checking-email-status)
3. [Querying Notifications](#querying-notifications)
4. [Using the Facade](#using-the-facade)
5. [Event Listeners](#event-listeners)
6. [Custom Logic](#custom-logic)
7. [Admin Dashboard](#admin-dashboard)

## Basic Usage

### Automatic Email Blocking

The package automatically blocks emails before they're sent:

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\NewsletterMail;

// This email will be automatically blocked if the recipient has bounced/complained
Mail::to('[email protected]')->send(new NewsletterMail());

// Check your logs for: "Email blocked by SES Monitor"
```

## Checking Email Status

### Using the Model Directly

```php
use AshrafBendary\SecureEmail\Models\SesNotification;

// Check if email has permanent bounce
if (SesNotification::hasPermanentBounce('[email protected]')) {
    // Don't send email
    return;
}

// Count total bounces
$bounceCount = SesNotification::countBouncesForEmail('[email protected]');

// Count bounces in last 30 days
$recentBounces = SesNotification::countBouncesForEmail('[email protected]', null, 30);

// Count bounces for specific subject
$newsletterBounces = SesNotification::countBouncesForEmail(
    '[email protected]',
    'Weekly Newsletter',
    30
);

// Count complaints
$complaints = SesNotification::countComplaintsForEmail('[email protected]');
```

### Using the Facade

```php
use AshrafBendary\SecureEmail\Facades\SesMonitor;

// Check if should block
if (SesMonitor::shouldBlockEmail('[email protected]', 'Newsletter')) {
    // Handle blocked email
    Log::warning('Email blocked', ['email' => '[email protected]']);
    return;
}

// Get bounce count
$bounces = SesMonitor::countBouncesForEmail('[email protected]');

// Get complaint count
$complaints = SesMonitor::countComplaintsForEmail('[email protected]');

// Check permanent bounce
if (SesMonitor::hasPermanentBounce('[email protected]')) {
    // Handle permanent bounce
}
```

## Querying Notifications

### Get All Bounces

```php
use AshrafBendary\SecureEmail\Models\SesNotification;

// Get all bounces
$bounces = SesNotification::bounces()->get();

// Get only permanent bounces
$permanentBounces = SesNotification::permanentBounces()->get();

// Get bounces for specific email
$userBounces = SesNotification::bounces()
    ->forEmail('[email protected]')
    ->get();

// Get recent bounces (last 7 days)
$recentBounces = SesNotification::bounces()
    ->recent(7)
    ->get();
```

### Get All Complaints

```php
// Get all complaints
$complaints = SesNotification::complaints()->get();

// Get complaints for specific email
$userComplaints = SesNotification::complaints()
    ->forEmail('[email protected]')
    ->get();

// Get recent complaints
$recentComplaints = SesNotification::complaints()
    ->recent(30)
    ->get();
```

### Get Deliveries

```php
// Get all successful deliveries
$deliveries = SesNotification::deliveries()->get();

// Get deliveries for specific email
$userDeliveries = SesNotification::deliveries()
    ->forEmail('[email protected]')
    ->get();
```

### Complex Queries

```php
// Get all notifications for an email, ordered by date
$notifications = SesNotification::forEmail('[email protected]')
    ->orderBy('created_at', 'desc')
    ->get();

// Get bounces with specific subject
$bounces = SesNotification::bounces()
    ->withSubject('Weekly Newsletter')
    ->recent(30)
    ->get();

// Count notifications by type
$stats = [
    'bounces' => SesNotification::bounces()->count(),
    'complaints' => SesNotification::complaints()->count(),
    'deliveries' => SesNotification::deliveries()->count(),
];

// Get problematic emails (more than 2 bounces)
$problematicEmails = SesNotification::bounces()
    ->select('email', DB::raw('count(*) as bounce_count'))
    ->groupBy('email')
    ->having('bounce_count', '>', 2)
    ->get();
```

## Event Listeners

### Register Event Listeners

In `app/Providers/EventServiceProvider.php`:

```php
use AshrafBendary\SecureEmail\Events\SesBounceReceived;
use AshrafBendary\SecureEmail\Events\SesComplaintReceived;
use AshrafBendary\SecureEmail\Events\SesDeliveryReceived;
use App\Listeners\HandleSesBounce;
use App\Listeners\HandleSesComplaint;
use App\Listeners\HandleSesDelivery;

protected $listen = [
    SesBounceReceived::class => [
        HandleSesBounce::class,
    ],
    SesComplaintReceived::class => [
        HandleSesComplaint::class,
    ],
    SesDeliveryReceived::class => [
        HandleSesDelivery::class,
    ],
];
```

### Bounce Event Listener

```php
namespace App\Listeners;

use AshrafBendary\SecureEmail\Events\SesBounceReceived;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class HandleSesBounce
{
    public function handle(SesBounceReceived $event)
    {
        $notification = $event->getNotification();
        $email = $event->getEmail();

        Log::info('Bounce received', [
            'email' => $email,
            'type' => $notification->bounce_type,
        ]);

        // If permanent bounce, unsubscribe user
        if ($event->isPermanent()) {
            User::where('email', $email)->update([
                'email_verified_at' => null,
                'subscribed' => false,
            ]);

            Log::error('Permanent bounce - user unsubscribed', [
                'email' => $email,
            ]);
        }

        // If transient bounce, increment bounce counter
        if ($event->isTransient()) {
            // Maybe retry later or track for monitoring
        }
    }
}
```

### Complaint Event Listener

```php
namespace App\Listeners;

use AshrafBendary\SecureEmail\Events\SesComplaintReceived;
use App\Models\User;
use App\Notifications\ComplaintNotification;
use Illuminate\Support\Facades\Mail;

class HandleSesComplaint
{
    public function handle(SesComplaintReceived $event)
    {
        $email = $event->getEmail();
        $feedbackType = $event->getFeedbackType();

        // Immediately unsubscribe user
        $user = User::where('email', $email)->first();
        
        if ($user) {
            $user->update([
                'subscribed' => false,
                'complaint_at' => now(),
            ]);
        }

        // Notify admin
        Mail::to('[email protected]')->send(
            new ComplaintNotification($email, $feedbackType)
        );

        Log::critical('Complaint received', [
            'email' => $email,
            'feedback_type' => $feedbackType,
        ]);
    }
}
```

### Delivery Event Listener

```php
namespace App\Listeners;

use AshrafBendary\SecureEmail\Events\SesDeliveryReceived;
use App\Models\EmailCampaign;

class HandleSesDelivery
{
    public function handle(SesDeliveryReceived $event)
    {
        $notification = $event->getNotification();

        // Update campaign statistics
        EmailCampaign::where('message_id', $notification->message_id)
            ->increment('delivered_count');

        // Track in analytics
        // Analytics::track('email_delivered', [...]);
    }
}
```

## Custom Logic

### Pre-Send Email Validation

```php
use AshrafBendary\SecureEmail\Facades\SesMonitor;

class NewsletterService
{
    public function sendNewsletter($subscribers)
    {
        foreach ($subscribers as $subscriber) {
            // Check before sending
            if (SesMonitor::shouldBlockEmail($subscriber->email)) {
                Log::info('Skipping blocked email', [
                    'email' => $subscriber->email
                ]);
                continue;
            }

            Mail::to($subscriber->email)->send(new Newsletter());
        }
    }
}
```

### Bulk Email Validation

```php
use AshrafBendary\SecureEmail\Models\SesNotification;

class EmailValidator
{
    public function validateEmailList(array $emails): array
    {
        $valid = [];
        $invalid = [];

        foreach ($emails as $email) {
            // Check for permanent bounces
            if (SesNotification::hasPermanentBounce($email)) {
                $invalid[] = [
                    'email' => $email,
                    'reason' => 'permanent_bounce',
                ];
                continue;
            }

            // Check bounce threshold
            $bounces = SesNotification::countBouncesForEmail($email, null, 30);
            if ($bounces >= 3) {
                $invalid[] = [
                    'email' => $email,
                    'reason' => 'too_many_bounces',
                ];
                continue;
            }

            // Check complaints
            $complaints = SesNotification::countComplaintsForEmail($email);
            if ($complaints > 0) {
                $invalid[] = [
                    'email' => $email,
                    'reason' => 'complaint',
                ];
                continue;
            }

            $valid[] = $email;
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }
}
```

## Admin Dashboard

### Dashboard Controller Example

```php
namespace App\Http\Controllers\Admin;

use AshrafBendary\SecureEmail\Models\SesNotification;
use Illuminate\Http\Request;

class EmailMonitorController extends Controller
{
    public function index()
    {
        $stats = [
            'total_bounces' => SesNotification::bounces()->count(),
            'permanent_bounces' => SesNotification::permanentBounces()->count(),
            'total_complaints' => SesNotification::complaints()->count(),
            'total_deliveries' => SesNotification::deliveries()->count(),
            'recent_bounces' => SesNotification::bounces()->recent(7)->count(),
            'recent_complaints' => SesNotification::complaints()->recent(7)->count(),
        ];

        $recentNotifications = SesNotification::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('admin.email-monitor', compact('stats', 'recentNotifications'));
    }

    public function bounces()
    {
        $bounces = SesNotification::bounces()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.bounces', compact('bounces'));
    }

    public function complaints()
    {
        $complaints = SesNotification::complaints()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.complaints', compact('complaints'));
    }

    public function search(Request $request)
    {
        $email = $request->input('email');

        $notifications = SesNotification::forEmail($email)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'bounces' => SesNotification::countBouncesForEmail($email),
            'complaints' => SesNotification::countComplaintsForEmail($email),
            'has_permanent_bounce' => SesNotification::hasPermanentBounce($email),
        ];

        return view('admin.email-details', compact('notifications', 'stats', 'email'));
    }
}
```

### Blade View Example

```blade
<!-- resources/views/admin/email-monitor.blade.php -->
<div class="container">
    <h1>Email Monitor Dashboard</h1>

    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Total Bounces</h5>
                    <h2>{{ $stats['total_bounces'] }}</h2>
                    <small>{{ $stats['recent_bounces'] }} in last 7 days</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Permanent Bounces</h5>
                    <h2>{{ $stats['permanent_bounces'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Complaints</h5>
                    <h2>{{ $stats['total_complaints'] }}</h2>
                    <small>{{ $stats['recent_complaints'] }} in last 7 days</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Deliveries</h5>
                    <h2>{{ $stats['total_deliveries'] }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <h3>Recent Notifications</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Email</th>
                    <th>Subject</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentNotifications as $notification)
                <tr>
                    <td>{{ $notification->created_at->diffForHumans() }}</td>
                    <td>
                        <span class="badge badge-{{ $notification->type === 'Bounce' ? 'warning' : ($notification->type === 'Complaint' ? 'danger' : 'success') }}">
                            {{ $notification->type }}
                        </span>
                    </td>
                    <td>{{ $notification->email }}</td>
                    <td>{{ $notification->subject }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

## Tips and Best Practices

1. **Monitor Your Bounce Rate**: Keep your bounce rate below 5% to maintain good sender reputation
2. **Act on Complaints Immediately**: Complaints are serious - always unsubscribe complainants
3. **Review Permanent Bounces**: Remove permanent bounces from your mailing list
4. **Set Appropriate Thresholds**: Adjust max_bounces and max_complaints based on your needs
5. **Use Events for Custom Logic**: Leverage event listeners for application-specific actions
6. **Monitor Trends**: Set up dashboards to track notification trends
7. **Clean Your Lists**: Regularly remove problematic emails from your database
