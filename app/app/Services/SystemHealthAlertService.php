<?php

namespace App\Services;

use App\Models\SystemHealthEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SystemHealthAlertService
{
    public function notifyForEvent(SystemHealthEvent $event): void
    {
        if (! config('system_health.alerts.enabled', true)) {
            return;
        }

        $severity = strtolower((string) $event->severity);

        if ($severity === 'medium') {
            return;
        }

        $context = $this->messageContext($event);
        $this->writeLog($severity, $context);

        if ($severity === 'low') {
            return;
        }

        if ($severity === 'high' && ! $this->passesHighThreshold($event)) {
            return;
        }

        $this->sendEmail($event, $context);
        $this->sendSlack($event, $context);

        $event->forceFill([
            'last_alerted_at' => CarbonImmutable::now('UTC'),
        ])->save();
    }

    public function remindOpenCritical(SystemHealthEvent $event): void
    {
        if (strtolower((string) $event->severity) !== 'critical' || $event->status !== 'open') {
            return;
        }

        $context = array_merge($this->messageContext($event), [
            'reminder' => true,
        ]);

        $this->writeLog('critical', $context);
        $this->sendEmail($event, $context);
        $this->sendSlack($event, $context);

        $event->forceFill([
            'last_alerted_at' => CarbonImmutable::now('UTC'),
        ])->save();
    }

    private function passesHighThreshold(SystemHealthEvent $event): bool
    {
        $definition = config('system_health.events.'.$event->event_name.'.high_threshold', []);
        $default = config('system_health.alerts.high_default_threshold', []);
        $minOccurrences = max(1, (int) ($definition['occurrences'] ?? $default['occurrences'] ?? 3));
        $windowMinutes = max(1, (int) ($definition['window_minutes'] ?? $default['window_minutes'] ?? 60));
        $windowStart = CarbonImmutable::now('UTC')->subMinutes($windowMinutes);

        $recentOccurrences = $event->occurrences()
            ->where('detected_at', '>=', $windowStart)
            ->count();

        return $recentOccurrences >= $minOccurrences;
    }

    private function writeLog(string $severity, array $context): void
    {
        $channel = config('system_health.alerts.log_channel');
        if ($channel) {
            match ($severity) {
                'critical' => Log::channel((string) $channel)->critical('system_health_event', $context),
                'high' => Log::channel((string) $channel)->error('system_health_event', $context),
                default => Log::channel((string) $channel)->warning('system_health_event', $context),
            };

            return;
        }

        match ($severity) {
            'critical' => Log::critical('system_health_event', $context),
            'high' => Log::error('system_health_event', $context),
            default => Log::warning('system_health_event', $context),
        };
    }

    private function sendEmail(SystemHealthEvent $event, array $context): void
    {
        if (! config('system_health.alerts.email_enabled', false)) {
            return;
        }

        $recipients = config('system_health.alerts.email_to', []);
        if (! is_array($recipients) || $recipients === []) {
            return;
        }

        $subject = '['.strtoupper((string) $event->severity).'] '.$event->event_name;
        $body = implode("\n", [
            'system_health_event',
            'event_name: '.$event->event_name,
            'severity: '.$event->severity,
            'status: '.$event->status,
            'tenant_id: '.($event->tenant_id ?? '-'),
            'branch_id: '.($event->branch_id ?? '-'),
            'entity: '.trim(($event->entity_type ?? '-').' '.($event->entity_id ?? '-')),
            'source: '.$event->source,
            'summary: '.$event->summary,
            'occurrence_count: '.$event->occurrence_count,
            'payload: '.json_encode($event->payload_json, JSON_UNESCAPED_SLASHES),
        ]);

        foreach ($recipients as $email) {
            Mail::raw($body, function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        }
    }

    private function sendSlack(SystemHealthEvent $event, array $context): void
    {
        if (! config('system_health.alerts.slack_enabled', false)) {
            return;
        }

        $webhook = (string) config('system_health.alerts.slack_webhook_url', '');
        if ($webhook === '') {
            return;
        }

        try {
            Http::timeout(max(1, (int) config('system_health.alerts.slack_timeout_seconds', 4)))
                ->post($webhook, [
                    'text' => sprintf(
                        '[%s] %s | tenant=%s | %s',
                        strtoupper((string) $event->severity),
                        $event->event_name,
                        $event->tenant_id ?? '-',
                        $event->summary,
                    ),
                    'context' => $context,
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::error('gagal mengirim system health slack alert', [
                'event_id' => $event->id,
                'event_name' => $event->event_name,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function messageContext(SystemHealthEvent $event): array
    {
        return [
            'event_id' => $event->id,
            'event_name' => $event->event_name,
            'severity' => $event->severity,
            'status' => $event->status,
            'tenant_id' => $event->tenant_id,
            'branch_id' => $event->branch_id,
            'entity_type' => $event->entity_type,
            'entity_id' => $event->entity_id,
            'source' => $event->source,
            'summary' => $event->summary,
            'occurrence_count' => $event->occurrence_count,
            'payload_json' => $event->payload_json,
        ];
    }
}