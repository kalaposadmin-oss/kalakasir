<?php

namespace App\Console\Commands;

use App\Models\SystemHealthEvent;
use App\Services\SystemHealthAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SystemHealthNotifyOpenCriticalCommand extends Command
{
    protected $signature = 'system-health:notify-open-critical';

    protected $description = 'Send reminders for open critical system health events.';

    public function handle(SystemHealthAlertService $alerts): int
    {
        $minutes = max(1, (int) config('system_health.alerts.critical_reminder_minutes', 30));
        $cutoff = CarbonImmutable::now('UTC')->subMinutes($minutes);

        $events = SystemHealthEvent::query()
            ->where('status', 'open')
            ->where('severity', 'critical')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_alerted_at')
                    ->orWhere('last_alerted_at', '<=', $cutoff);
            })
            ->orderBy('detected_at')
            ->get();

        foreach ($events as $event) {
            $alerts->remindOpenCritical($event);
            $this->line('reminded '.$event->event_name.' '.$event->id);
        }

        $this->info('critical reminders sent: '.$events->count());

        return self::SUCCESS;
    }
}