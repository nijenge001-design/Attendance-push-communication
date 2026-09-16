<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Logging
    |--------------------------------------------------------------------------
    */

    'device_logs' => [
        'enabled' => env('LOG_DEVICE', true),
        'per_file' => env('LOG_DEVICE_PER_FILE', false),
        'debug_sn' => env('DEVICE_DEBUG_SN', ''),
        'debug_dump' => env('DEVICE_DEBUG_DUMP', false),
        'level' => env('LOG_DEVICE_LEVEL', 'info'),
        'days' => env('LOG_DEVICE_DAYS', 14),
        'max_context_string' => env('LOG_DEVICE_MAX_CONTEXT', 2000),
        'sensitive_keys' => [
            'password', 'token', 'api_key', 'secret', 'authorization',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging (middleware + job)
    |--------------------------------------------------------------------------
    */

    'request' => [
        'enabled' => env('LOG_REQUESTS', false),
        'save_to_file' => env('LOG_REQUESTS_TO_FILE', false),
        'channel' => env('LOG_REQUEST_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'level' => env('LOG_REQUEST_LEVEL', 'info'),
        'queue' => env('LOG_REQUEST_QUEUE', 'default'),

        // Body handling
        'max_raw_body_size' => env('LOG_REQUEST_MAX_BODY', 1_048_576), // 1 MB
        'include_parsed_body' => env('LOG_REQUEST_INCLUDE_BODY', true),
        'max_parsed_keys' => env('LOG_REQUEST_MAX_KEYS', 50),
        'max_string_length' => env('LOG_REQUEST_MAX_STRING', 2000),
        'max_header_length' => env('LOG_REQUEST_MAX_HEADER', 500),
        'compress_raw' => env('LOG_REQUEST_COMPRESS', false),

        // Response logging (terminable middleware)
        'log_response' => env('LOG_REQUEST_LOG_RESPONSE', true),
        'log_response_body' => env('LOG_REQUEST_LOG_RESPONSE_BODY', false),
        'max_response_body_size' => (int) env('LOG_REQUEST_MAX_RESPONSE_BODY_SIZE', 64000),

        // Retention for request_logs disk (used by purge command)
        'retention_days' => env('LOG_REQUEST_RETENTION_DAYS', 14),

        // Path filters – comma-separated in .env
        'only_paths' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOG_REQUEST_ONLY_PATHS', ''))
        ))),

        'skip_paths' => [
            'up',
            'health',
            'horizon*',
            'telescope*',
            '_debugbar*',
        ],

        'methods' => ['*'],

        // Sanitization
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
            'secret',
            'authorization',
            'credit_card',
            'cvv',
            'ssn',
        ],

        'sensitive_headers' => [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "monthly", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string)env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'monthly' => [
            'driver' => 'monthly',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'device' => [
            'driver' => 'daily',
            'path' => storage_path('logs/device.log'),
            'level' => env('LOG_DEVICE_LEVEL', 'info'),
            'days' => env('LOG_DEVICE_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'user_info' => [
            'driver' => 'daily',
            'path' => storage_path('logs/user_info.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'attendance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/attendance.log'),
            'level' => 'info',
            'days' => 30,
        ],


    ],

];
