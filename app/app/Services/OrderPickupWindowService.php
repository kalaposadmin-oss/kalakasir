<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class OrderPickupWindowService
{
    public const SETTING_KEY = 'order_online.pickup_window';

    /**
     * @return array<string, mixed>
     */
    public function resolve(?Tenant $tenant): array
    {
        $raw = [];

        if ($tenant && Schema::hasTable('settings')) {
            $setting = Setting::query()
                ->where('tenant_id', $tenant->id)
                ->where('setting_key', self::SETTING_KEY)
                ->first(['value_json']);
            $raw = $setting?->value_json ?? [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return $this->normalize(is_array($raw) ? $raw : [], $tenant?->timezone);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(string $tenantId, array $payload, ?string $timezone = null): array
    {
        $settings = $this->normalize([
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'start_time' => $payload['start_time'] ?? null,
            'end_time' => $payload['end_time'] ?? null,
        ], $timezone);

        Setting::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'setting_key' => self::SETTING_KEY,
            ],
            [
                'value_json' => [
                    'enabled' => $settings['enabled'],
                    'start_time' => $settings['start_time'],
                    'end_time' => $settings['end_time'],
                ],
            ]
        );

        return $settings;
    }

    public function pickupTimeValidationMessage(?Tenant $tenant, mixed $value): ?string
    {
        $settings = $this->resolve($tenant);

        if (! ($settings['enabled'] ?? false)) {
            return null;
        }

        $time = $this->clockTime((string) $value);
        if ($time === null) {
            return 'Pilih waktu pengambilan yang tersedia.';
        }

        $timeMinutes = $this->minutes($time);
        $startMinutes = $this->minutes((string) $settings['start_time']);
        $endMinutes = $this->minutes((string) $settings['end_time']);

        if ($timeMinutes === null || $startMinutes === null || $endMinutes === null || $startMinutes >= $endMinutes) {
            return null;
        }

        if ($timeMinutes < $startMinutes || $timeMinutes > $endMinutes) {
            return 'Waktu pengambilan harus antara '.$settings['start_label'].' - '.$settings['end_label'].'.';
        }

        return null;
    }

    public function submittedTimeLabel(mixed $value): ?string
    {
        $time = $this->clockTime((string) $value);

        return $time ? $this->displayTime($time) : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw, ?string $timezone): array
    {
        $startTime = $this->clockTime((string) ($raw['start_time'] ?? '')) ?? '11:00';
        $endTime = $this->clockTime((string) ($raw['end_time'] ?? '')) ?? '17:00';
        $enabled = (bool) ($raw['enabled'] ?? false);
        $startMinutes = $this->minutes($startTime);
        $endMinutes = $this->minutes($endTime);

        if ($enabled && $startMinutes !== null && $endMinutes !== null && $startMinutes >= $endMinutes) {
            $enabled = false;
        }

        $startLabel = $this->displayTime($startTime);
        $endLabel = $this->displayTime($endTime);

        return [
            'enabled' => $enabled,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_label' => $startLabel,
            'end_label' => $endLabel,
            'window_label' => 'Hari ini '.$startLabel.' - '.$endLabel,
            'timezone' => $timezone ?: 'Asia/Jakarta',
        ];
    }

    private function clockTime(string $value): ?string
    {
        if (! preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', $value, $matches)) {
            return null;
        }

        return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
    }

    private function minutes(string $time): ?int
    {
        $time = $this->clockTime($time);
        if ($time === null) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private function displayTime(string $time): string
    {
        return str_replace(':', '.', $this->clockTime($time) ?? $time);
    }
}
