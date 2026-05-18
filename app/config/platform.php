<?php

return [
    'rate_limit_override_token' => env('PLATFORM_RATE_LIMIT_OVERRIDE_TOKEN', ''),
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    'health_alert_webhook' => env('PLATFORM_HEALTH_ALERT_WEBHOOK', ''),
    'health_alert_timeout_seconds' => (int) env('PLATFORM_HEALTH_ALERT_TIMEOUT_SECONDS', 1),
    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    'sensitive_rate_limit_per_minute' => (int) env('SENSITIVE_RATE_LIMIT_PER_MINUTE', 30),
    'danger_rate_limit_per_hour' => (int) env('PLATFORM_DANGER_RATE_LIMIT_PER_HOUR', 10),
    'idempotency_ttl_minutes' => (int) env('PLATFORM_IDEMPOTENCY_TTL_MINUTES', 60),
    'idempotency_lock_seconds' => (int) env('PLATFORM_IDEMPOTENCY_LOCK_SECONDS', 10),
    'auto_consume_on_sell_enabled_default' => filter_var(env('AUTO_CONSUME_ON_SELL_ENABLED_DEFAULT', false), FILTER_VALIDATE_BOOL),
    'allow_negative_raw_material_stock_default' => filter_var(env('ALLOW_NEGATIVE_RAW_MATERIAL_STOCK_DEFAULT', false), FILTER_VALIDATE_BOOL),
    'portals' => [
        'pos_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim(strtolower($host)),
            explode(',', (string) env('POS_PORTAL_HOSTS', ''))
        ))),
        'backoffice_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim(strtolower($host)),
            explode(',', (string) env('BACKOFFICE_PORTAL_HOSTS', ''))
        ))),
    ],
];
