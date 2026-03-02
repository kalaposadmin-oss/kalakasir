<?php

namespace App\Services;

class AdaptiveAnomalyThresholdService
{
    public function dailyThreshold(
        array $history,
        int $floor,
        float $sigmaMultiplier = 3.0,
        float $growthMultiplier = 2.0,
        float $p95Multiplier = 1.1,
        int $minSampleEvents = 30,
        int $additiveBuffer = 2,
        int $maxThresholdCap = 0
    ): array {
        $clean = array_map(static fn ($value) => max(0.0, (float) $value), $history);
        $sampleEvents = (int) round(array_sum($clean));

        if (empty($clean)) {
            return [
                'threshold' => max($floor, max(0, $additiveBuffer)),
                'avg' => 0.0,
                'stddev' => 0.0,
                'median' => 0.0,
                'mad' => 0.0,
                'winsor_avg' => 0.0,
                'p95' => 0.0,
                'sample_events' => 0,
                'cold_start' => true,
            ];
        }

        if ($sampleEvents < max(1, $minSampleEvents)) {
            return [
                'threshold' => max($floor, max(0, $additiveBuffer)),
                'avg' => array_sum($clean) / max(1, count($clean)),
                'stddev' => 0.0,
                'median' => 0.0,
                'mad' => 0.0,
                'winsor_avg' => 0.0,
                'p95' => 0.0,
                'sample_events' => $sampleEvents,
                'cold_start' => true,
            ];
        }

        $count = count($clean);
        $avg = array_sum($clean) / $count;

        $variance = 0.0;
        foreach ($clean as $value) {
            $variance += ($value - $avg) ** 2;
        }
        $variance = $variance / max(1, $count);
        $stddev = sqrt($variance);

        sort($clean);
        $median = $this->median($clean);
        $p95 = $this->percentile($clean, 0.95);
        $p05 = $this->percentile($clean, 0.05);

        $deviations = [];
        foreach ($clean as $value) {
            $deviations[] = abs($value - $median);
        }
        sort($deviations);
        $mad = $this->median($deviations);
        $robustStd = max($stddev, $mad * 1.4826);

        $winsorized = [];
        foreach ($clean as $value) {
            $winsorized[] = min(max($value, $p05), $p95);
        }
        $winsorAvg = array_sum($winsorized) / max(1, count($winsorized));

        $threshold = max(
            $floor,
            (int) ceil($winsorAvg + ($sigmaMultiplier * $robustStd)),
            (int) ceil($median * $growthMultiplier),
            (int) ceil($p95 * $p95Multiplier)
        );

        if ($maxThresholdCap > 0) {
            $threshold = min($threshold, $maxThresholdCap);
        }

        return [
            'threshold' => $threshold,
            'avg' => $avg,
            'stddev' => $stddev,
            'median' => $median,
            'mad' => $mad,
            'winsor_avg' => $winsorAvg,
            'p95' => $p95,
            'sample_events' => $sampleEvents,
            'cold_start' => false,
        ];
    }

    public function windowThreshold(
        int $historicalCount,
        int $windowCount,
        int $floor,
        float $growthMultiplier = 3.0,
        int $additiveBuffer = 2,
        int $minSampleEvents = 30,
        int $maxThresholdCap = 0
    ): array {
        $historicalCount = max(0, $historicalCount);
        $windowCount = max(1, $windowCount);

        $expected = $historicalCount / $windowCount;
        $threshold = max($floor, (int) ceil($expected * $growthMultiplier) + max(0, $additiveBuffer));
        $coldStart = $historicalCount < max(1, $minSampleEvents);

        if ($coldStart) {
            $threshold = max($floor, max(0, $additiveBuffer));
        }

        if ($maxThresholdCap > 0) {
            $threshold = min($threshold, $maxThresholdCap);
        }

        return [
            'threshold' => $threshold,
            'expected' => $expected,
            'sample_events' => $historicalCount,
            'cold_start' => $coldStart,
        ];
    }

    private function median(array $sortedValues): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ((float) $sortedValues[$mid - 1] + (float) $sortedValues[$mid]) / 2;
        }

        return (float) $sortedValues[$mid];
    }

    private function percentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $p = min(1.0, max(0.0, $percentile));
        $position = ($count - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        if ($lower === $upper) {
            return (float) $sortedValues[$lower];
        }

        $weight = $position - $lower;

        return ((float) $sortedValues[$lower] * (1 - $weight)) + ((float) $sortedValues[$upper] * $weight);
    }
}
