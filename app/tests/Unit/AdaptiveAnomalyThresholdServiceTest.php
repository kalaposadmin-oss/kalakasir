<?php

namespace Tests\Unit;

use App\Services\AdaptiveAnomalyThresholdService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdaptiveAnomalyThresholdServiceTest extends TestCase
{
    #[Test]
    public function window_threshold_uses_cold_start_fallback_when_sample_is_small(): void
    {
        $service = app(AdaptiveAnomalyThresholdService::class);

        $result = $service->windowThreshold(
            historicalCount: 12,
            windowCount: 95,
            floor: 10,
            growthMultiplier: 3.0,
            additiveBuffer: 2,
            minSampleEvents: 30,
            maxThresholdCap: 100,
        );

        $this->assertSame(10, (int) $result['threshold']);
        $this->assertTrue((bool) $result['cold_start']);
        $this->assertSame(12, (int) $result['sample_events']);
    }

    #[Test]
    public function window_threshold_applies_max_cap_to_prevent_poisoning(): void
    {
        $service = app(AdaptiveAnomalyThresholdService::class);

        $result = $service->windowThreshold(
            historicalCount: 10000,
            windowCount: 95,
            floor: 10,
            growthMultiplier: 3.0,
            additiveBuffer: 2,
            minSampleEvents: 30,
            maxThresholdCap: 100,
        );

        $this->assertSame(100, (int) $result['threshold']);
        $this->assertFalse((bool) $result['cold_start']);
    }

    #[Test]
    public function daily_threshold_uses_cold_start_fallback_when_total_events_below_minimum(): void
    {
        $service = app(AdaptiveAnomalyThresholdService::class);

        $result = $service->dailyThreshold(
            history: [0, 1, 0, 2, 0, 0, 1],
            floor: 3,
            sigmaMultiplier: 3.0,
            growthMultiplier: 2.0,
            p95Multiplier: 1.1,
            minSampleEvents: 30,
            additiveBuffer: 2,
            maxThresholdCap: 250,
        );

        $this->assertSame(3, (int) $result['threshold']);
        $this->assertTrue((bool) $result['cold_start']);
        $this->assertSame(4, (int) $result['sample_events']);
    }
}
