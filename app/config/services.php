<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ops_alerts' => [
        'enabled' => (bool) env('OPS_ALERTS_ENABLED', false),
        'webhook_url' => env('OPS_ALERTS_WEBHOOK_URL'),
        'bearer_token' => env('OPS_ALERTS_BEARER_TOKEN'),
        'timeout_seconds' => (int) env('OPS_ALERTS_TIMEOUT_SECONDS', 4),
        'report_retention_days' => (int) env('VALUATION_REPORT_RETENTION_DAYS', 14),
        'valuation_min_severity' => env('OPS_ALERTS_VALUATION_MIN_SEVERITY', 'med'),
        'valuation_min_count' => (int) env('OPS_ALERTS_VALUATION_MIN_COUNT', 1),
    ],
        'anomaly' => [
            'void_spike_lookback_days' => env('ANOMALY_VOID_SPIKE_LOOKBACK_DAYS', 14),
            'void_spike_min_floor' => env('ANOMALY_VOID_SPIKE_MIN_FLOOR', 3),
            'void_spike_sigma_multiplier' => env('ANOMALY_VOID_SPIKE_SIGMA_MULTIPLIER', 3.0),
            'void_spike_growth_multiplier' => env('ANOMALY_VOID_SPIKE_GROWTH_MULTIPLIER', 2.0),
            'void_spike_p95_multiplier' => env('ANOMALY_VOID_SPIKE_P95_MULTIPLIER', 1.1),
            'void_spike_min_sample_events' => env('ANOMALY_VOID_SPIKE_MIN_SAMPLE_EVENTS', 30),
            'void_spike_additive_buffer' => env('ANOMALY_VOID_SPIKE_ADDITIVE_BUFFER', 2),
            'void_spike_max_threshold' => env('ANOMALY_VOID_SPIKE_MAX_THRESHOLD', 250),

            'adjustment_spike_lookback_days' => env('ANOMALY_ADJUSTMENT_SPIKE_LOOKBACK_DAYS', 21),
            'adjustment_spike_min_floor' => env('ANOMALY_ADJUSTMENT_SPIKE_MIN_FLOOR', 100),
            'adjustment_spike_sigma_multiplier' => env('ANOMALY_ADJUSTMENT_SPIKE_SIGMA_MULTIPLIER', 3.0),
            'adjustment_spike_growth_multiplier' => env('ANOMALY_ADJUSTMENT_SPIKE_GROWTH_MULTIPLIER', 2.0),
            'adjustment_spike_p95_multiplier' => env('ANOMALY_ADJUSTMENT_SPIKE_P95_MULTIPLIER', 1.1),
            'adjustment_spike_min_sample_events' => env('ANOMALY_ADJUSTMENT_SPIKE_MIN_SAMPLE_EVENTS', 30),
            'adjustment_spike_additive_buffer' => env('ANOMALY_ADJUSTMENT_SPIKE_ADDITIVE_BUFFER', 2),
            'adjustment_spike_max_threshold' => env('ANOMALY_ADJUSTMENT_SPIKE_MAX_THRESHOLD', 5000),

            'invalid_date_window_minutes' => env('ANOMALY_INVALID_DATE_WINDOW_MINUTES', 15),
            'invalid_date_baseline_hours' => env('ANOMALY_INVALID_DATE_BASELINE_HOURS', 24),
            'invalid_date_min_floor' => env('ANOMALY_INVALID_DATE_MIN_FLOOR', 10),
            'invalid_date_growth_multiplier' => env('ANOMALY_INVALID_DATE_GROWTH_MULTIPLIER', 3.0),
            'invalid_date_additive_buffer' => env('ANOMALY_INVALID_DATE_ADDITIVE_BUFFER', 2),
            'invalid_date_min_sample_events' => env('ANOMALY_INVALID_DATE_MIN_SAMPLE_EVENTS', 30),
            'invalid_date_max_threshold' => env('ANOMALY_INVALID_DATE_MAX_THRESHOLD', 100),

            'void_split_window_minutes' => env('ANOMALY_VOID_SPLIT_WINDOW_MINUTES', 60),
            'void_split_baseline_hours' => env('ANOMALY_VOID_SPLIT_BASELINE_HOURS', 24),
            'void_split_small_amount_limit' => env('ANOMALY_VOID_SPLIT_SMALL_AMOUNT_LIMIT', 50000),
            'void_split_actor_count_threshold' => env('ANOMALY_VOID_SPLIT_ACTOR_COUNT_THRESHOLD', 20),
            'void_split_tenant_min_floor' => env('ANOMALY_VOID_SPLIT_TENANT_MIN_FLOOR', 25),
            'void_split_growth_multiplier' => env('ANOMALY_VOID_SPLIT_GROWTH_MULTIPLIER', 2.5),
            'void_split_additive_buffer' => env('ANOMALY_VOID_SPLIT_ADDITIVE_BUFFER', 3),
            'void_split_min_sample_events' => env('ANOMALY_VOID_SPLIT_MIN_SAMPLE_EVENTS', 30),
            'void_split_max_threshold' => env('ANOMALY_VOID_SPLIT_MAX_THRESHOLD', 300),
        ],

        'security' => [
            'manager_pin' => [
                'max_attempts' => env('SECURITY_MANAGER_PIN_MAX_ATTEMPTS', 5),
                'lockout_seconds' => env('SECURITY_MANAGER_PIN_LOCKOUT_SECONDS', 900),
            ],
        ],

];
