<?php

return [
    'report_slow_ms' => (int) env('SYSTEM_HEALTH_REPORT_SLOW_MS', 1500),
    'stale_summary_hours' => (int) env('SYSTEM_HEALTH_STALE_SUMMARY_HOURS', 24),

    'alerts' => [
        'enabled' => (bool) env('SYSTEM_HEALTH_ALERTS_ENABLED', true),
        'log_channel' => env('SYSTEM_HEALTH_ALERT_LOG_CHANNEL'),
        'email_enabled' => (bool) env('SYSTEM_HEALTH_EMAIL_ENABLED', false),
        'email_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('SYSTEM_HEALTH_ALERT_EMAILS', ''))))),
        'slack_enabled' => (bool) env('SYSTEM_HEALTH_SLACK_ENABLED', false),
        'slack_webhook_url' => env('SYSTEM_HEALTH_SLACK_WEBHOOK_URL'),
        'slack_timeout_seconds' => (int) env('SYSTEM_HEALTH_SLACK_TIMEOUT_SECONDS', 4),
        'high_default_threshold' => [
            'occurrences' => (int) env('SYSTEM_HEALTH_HIGH_OCCURRENCES', 3),
            'window_minutes' => (int) env('SYSTEM_HEALTH_HIGH_WINDOW_MINUTES', 60),
        ],
        'critical_reminder_minutes' => (int) env('SYSTEM_HEALTH_CRITICAL_REMINDER_MINUTES', 30),
    ],

    'events' => [
        'sales_cost_missing_aggregate' => [
            'severity' => 'critical',
            'source' => 'sales-cost-readiness',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 60],
        ],
        'sales_cost_header_mismatch' => [
            'severity' => 'critical',
            'source' => 'sales-cost-readiness',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 60],
        ],
        'strict_reader_blocked' => [
            'severity' => 'high',
            'source' => 'strict-reader',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 2, 'window_minutes' => 30],
        ],
        'sales_cost_validate_failed' => [
            'severity' => 'high',
            'source' => 'sales-cost:validate',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 1440],
        ],
        'sales_cost_rebuild_failed' => [
            'severity' => 'critical',
            'source' => 'sales-cost:rebuild',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 60],
        ],
        'daily_summary_refresh_failed' => [
            'severity' => 'high',
            'source' => 'daily-sales-summary',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 2, 'window_minutes' => 120],
        ],
        'refund_cost_reversal_mismatch' => [
            'severity' => 'high',
            'source' => 'refund-consistency-scan',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 1440],
        ],
        'cross_report_mismatch' => [
            'severity' => 'high',
            'source' => 'report-consistency-scan',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 1440],
        ],
        'report_query_slow' => [
            'severity' => 'low',
            'source' => 'report-query',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 5, 'window_minutes' => 30],
        ],
        'finance_audit_mismatch_detected' => [
            'severity' => 'critical',
            'source' => 'financial-consistency-audit',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 1440],
        ],
        'finance_audit_job_failed' => [
            'severity' => 'high',
            'source' => 'financial-consistency-audit',
            'dedupe' => 'bump',
            'high_threshold' => ['occurrences' => 1, 'window_minutes' => 1440],
        ],
    ],
];