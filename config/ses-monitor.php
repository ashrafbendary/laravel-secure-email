<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable SES Monitor
    |--------------------------------------------------------------------------
    |
    | If enabled is set to true, this package will intercept each email then
    | check if the email passes all rules defined in this config file. It will
    | also listen to SNS notifications and store them in the database.
    |
    */
    'enabled' => env('SES_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Models used to create a new subscription confirmation request and
    | to store SNS notifications received from AWS.
    |
    */
    'models' => [
        'subscription' => \AshrafBendary\SecureEmail\Models\SnsSubscription::class,
        'notification' => \AshrafBendary\SecureEmail\Models\SesNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the routes that will handle SNS notifications from AWS.
    |
    */
    'route_prefix' => env('SES_MONITOR_ROUTE_PREFIX', 'aws/sns/ses'),
    
    'route_middleware' => ['api'],

    'routes' => [
        'bounces' => env('SES_MONITOR_BOUNCES_ROUTE', 'bounces'),
        'complaints' => env('SES_MONITOR_COMPLAINTS_ROUTE', 'complaints'),
        'deliveries' => env('SES_MONITOR_DELIVERIES_ROUTE', 'deliveries'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller
    |--------------------------------------------------------------------------
    |
    | The controller used to handle all SNS webhook actions.
    |
    */
    'controller' => \AshrafBendary\SecureEmail\Http\Controllers\SnsWebhookController::class,

    /*
    |--------------------------------------------------------------------------
    | Auto-Confirm Subscriptions
    |--------------------------------------------------------------------------
    |
    | Automatically confirm SNS subscription requests.
    |
    */
    'auto_confirm_subscriptions' => env('SES_MONITOR_AUTO_CONFIRM', true),

    /*
    |--------------------------------------------------------------------------
    | Validate SNS Messages
    |--------------------------------------------------------------------------
    |
    | Validate that incoming messages are actually from AWS SNS.
    |
    */
    'validate_sns_messages' => env('SES_MONITOR_VALIDATE_SNS', true),

    /*
    |--------------------------------------------------------------------------
    | Email Checking Rules
    |--------------------------------------------------------------------------
    |
    | Configure the rules for checking emails before sending.
    |
    */
    'rules' => [
        
        /*
        |--------------------------------------------------------------------------
        | Bounce Rules
        |--------------------------------------------------------------------------
        |
        | Configure how the package should handle bounced emails.
        |
        */
        'bounces' => [
            // Enable bounce checking
            'enabled' => env('SES_MONITOR_CHECK_BOUNCES', true),
            
            // Maximum number of bounce notifications before blocking
            'max_bounces' => env('SES_MONITOR_MAX_BOUNCES', 3),
            
            // Check bounces by subject (count bounces for the same subject)
            'check_by_subject' => env('SES_MONITOR_CHECK_BOUNCES_BY_SUBJECT', false),
            
            // Block permanent bounces immediately
            'block_permanent_bounces' => env('SES_MONITOR_BLOCK_PERMANENT_BOUNCES', true),
            
            // Number of days to consider for bounce counting (0 = all time)
            'days_to_check' => env('SES_MONITOR_BOUNCE_DAYS', 30),
        ],

        /*
        |--------------------------------------------------------------------------
        | Complaint Rules
        |--------------------------------------------------------------------------
        |
        | Configure how the package should handle complaint notifications.
        |
        */
        'complaints' => [
            // Enable complaint checking
            'enabled' => env('SES_MONITOR_CHECK_COMPLAINTS', true),
            
            // Maximum number of complaints before blocking
            'max_complaints' => env('SES_MONITOR_MAX_COMPLAINTS', 1),
            
            // Check complaints by subject
            'check_by_subject' => env('SES_MONITOR_CHECK_COMPLAINTS_BY_SUBJECT', true),
            
            // Number of days to consider for complaint counting (0 = all time)
            'days_to_check' => env('SES_MONITOR_COMPLAINT_DAYS', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure whether to fire events for various notification types.
    |
    */
    'events' => [
        'bounce' => true,
        'complaint' => true,
        'delivery' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for storing notifications.
    |
    */
    'database_connection' => env('SES_MONITOR_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'table_names' => [
        'subscriptions' => 'sns_subscriptions',
        'notifications' => 'ses_notifications',
    ],

];
