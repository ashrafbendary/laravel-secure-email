# Installation Guide

This guide will walk you through the complete installation and setup process for Laravel SES Monitor.

## Prerequisites

Before you begin, ensure you have:

1. **Laravel 12 Application**: A working Laravel 12 installation
2. **PHP 8.2+**: Your server must be running PHP 8.2 or higher
3. **AWS Account**: Active AWS account with SES configured
4. **Verified SES Domain/Email**: At least one verified domain or email in SES
5. **Database**: MySQL, PostgreSQL, or any Laravel-supported database

## Step-by-Step Installation

### 1. Install the Package

Open your terminal in your Laravel project directory and run:

```bash
composer require ashrafbendary/laravel-secure-email
```

### 2. Publish Configuration

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=secure-email-config
```

This creates `config/secure-email.php` where you can customize the package behavior.

### 3. Run Migrations

Create the required database tables:

```bash
php artisan migrate
```

This creates two tables:
- `sns_subscriptions`: Stores SNS subscription data
- `ses_notifications`: Stores bounce, complaint, and delivery notifications

### 4. Configure Environment Variables

Add the following to your `.env` file:

```env
# Enable the package
SES_MONITOR_ENABLED=true

# Auto-confirm SNS subscriptions
SES_MONITOR_AUTO_CONFIRM=true

# Validate SNS messages (recommended for production)
SES_MONITOR_VALIDATE_SNS=true

# Bounce configuration
SES_MONITOR_CHECK_BOUNCES=true
SES_MONITOR_MAX_BOUNCES=3
SES_MONITOR_BLOCK_PERMANENT_BOUNCES=true
SES_MONITOR_BOUNCE_DAYS=30

# Complaint configuration
SES_MONITOR_CHECK_COMPLAINTS=true
SES_MONITOR_MAX_COMPLAINTS=1
SES_MONITOR_COMPLAINT_DAYS=0
```

### 5. Configure AWS SNS Topics

#### 5.1 Create SNS Topics

1. Log in to AWS Console
2. Navigate to SNS (Simple Notification Service)
3. Create three topics:
   - `ses-bounces` (for bounce notifications)
   - `ses-complaints` (for complaint notifications)
   - `ses-deliveries` (for delivery notifications)

#### 5.2 Create Subscriptions

For each topic, create an HTTPS subscription:

**Bounces Topic:**
- Protocol: HTTPS
- Endpoint: `https://yourdomain.com/aws/sns/ses/bounces`

**Complaints Topic:**
- Protocol: HTTPS
- Endpoint: `https://yourdomain.com/aws/sns/ses/complaints`

**Deliveries Topic:**
- Protocol: HTTPS
- Endpoint: `https://yourdomain.com/aws/sns/ses/deliveries`

Replace `yourdomain.com` with your actual domain.

#### 5.3 Configure SES to Use SNS Topics

1. Go to AWS SES Console
2. Select "Verified identities"
3. Click on your verified domain/email
4. Go to "Notifications" tab
5. Click "Edit" in the "Feedback notifications" section
6. Configure:
   - Bounces: Select your `ses-bounces` topic
   - Complaints: Select your `ses-complaints` topic
   - Deliveries: Select your `ses-deliveries` topic (optional)
7. Save changes

### 6. Verify Installation

#### 6.1 Check Routes

Run this command to verify routes are registered:

```bash
php artisan route:list | grep secure-email
```

You should see three POST routes:
- `/aws/sns/ses/bounces`
- `/aws/sns/ses/complaints`
- `/aws/sns/ses/deliveries`

#### 6.2 Test Subscription Confirmation

If auto-confirm is enabled, subscriptions should confirm automatically. To check:

```bash
php artisan secure-email:subscribe-urls
```

If there are unconfirmed subscriptions, this command will display them.

#### 6.3 Test with a Bounce

Send a test email to AWS's bounce test address:

```php
Mail::to('[email protected]')->send(new TestMail());
```

Check your database for the notification:

```bash
php artisan tinker
>>> AshrafBendary\SecureEmail\Models\SesNotification::count();
```

## Configuration Options

### Bounce Rules

```php
// config/secure-email.php

'rules' => [
    'bounces' => [
        // Enable bounce checking
        'enabled' => true,
        
        // Block after X bounces
        'max_bounces' => 3,
        
        // Count bounces per subject vs. all bounces
        'check_by_subject' => false,
        
        // Block permanent bounces immediately
        'block_permanent_bounces' => true,
        
        // Only count bounces within X days (0 = all time)
        'days_to_check' => 30,
    ],
],
```

### Complaint Rules

```php
'rules' => [
    'complaints' => [
        // Enable complaint checking
        'enabled' => true,
        
        // Block after X complaints (usually 1)
        'max_complaints' => 1,
        
        // Count complaints per subject
        'check_by_subject' => true,
        
        // Time window for counting (0 = all time)
        'days_to_check' => 0,
    ],
],
```

## Advanced Configuration

### Custom Route Prefix

Change the webhook URL prefix:

```php
// config/secure-email.php
'route_prefix' => 'webhooks/aws/ses',
```

This changes URLs to: `https://yourdomain.com/webhooks/aws/ses/bounces`

### Custom Models

Use your own models:

```php
// config/secure-email.php
'models' => [
    'subscription' => App\Models\CustomSnsSubscription::class,
    'notification' => App\Models\CustomSesNotification::class,
],
```

### Disable Validation (Testing Only)

For local testing, you might want to disable SNS validation:

```env
SES_MONITOR_VALIDATE_SNS=false
```

**⚠️ Never disable this in production!**

## Testing Your Setup

### 1. Test Bounce Handling

Send to AWS bounce test addresses:

```php
// This will trigger a bounce
Mail::to('[email protected]')->send(new TestMail());

// This will trigger a complaint
Mail::to('[email protected]')->send(new TestMail());
```

### 2. Check Notifications

```bash
php artisan tinker
>>> AshrafBendary\SecureEmail\Models\SesNotification::all();
```

### 3. Test Email Blocking

After creating bounces/complaints, try sending again:

```php
// This should be blocked
Mail::to('[email protected]')->send(new TestMail());
```

Check your logs for "Email blocked by SES Monitor" message.

## Troubleshooting

### Subscriptions Not Confirming

1. Check your application is accessible via HTTPS
2. Ensure routes are registered: `php artisan route:list`
3. Check logs for errors: `tail -f storage/logs/laravel.log`
4. Manually confirm using: `php artisan secure-email:subscribe-urls`

### Notifications Not Being Stored

1. Verify SNS topics are configured in SES
2. Check SNS subscription status in AWS Console
3. Enable debug logging in Laravel
4. Verify database connection

### Emails Not Being Blocked

1. Check `SES_MONITOR_ENABLED=true` in `.env`
2. Verify notification thresholds in config
3. Check if notifications were actually stored
4. Review application logs

## Production Checklist

Before deploying to production:

- [ ] `SES_MONITOR_VALIDATE_SNS=true`
- [ ] `SES_MONITOR_ENABLED=true`
- [ ] SNS subscriptions confirmed
- [ ] SES configured with SNS topics
- [ ] Database migrations run
- [ ] HTTPS enabled on your domain
- [ ] Routes accessible from AWS
- [ ] Tested with bounce/complaint test addresses
- [ ] Event listeners configured (if needed)
- [ ] Monitoring/alerting set up

## Next Steps

- Set up event listeners for custom logic
- Configure monitoring for notification trends
- Set up admin notifications for bounces/complaints
- Review and adjust threshold settings based on your needs

## Getting Help

- Check the [README](README.md) for usage examples
- Review [example listeners](examples/ExampleListeners.php)
- Open an issue on GitHub
- Check AWS SES documentation
