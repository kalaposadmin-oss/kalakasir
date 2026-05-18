<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureGatingConfigConsistencyTest extends TestCase
{
    #[Test]
    public function feature_flags_and_plan_mapping_stay_in_sync(): void
    {
        $config = json_decode((string) file_get_contents(config_path('feature-gating.json')), true);

        $planEnum = array_values(array_filter($config['planEnum'] ?? []));
        $featureFlags = array_values(is_array($config['featureFlags'] ?? null) ? $config['featureFlags'] : []);
        $planMapping = is_array($config['planMapping'] ?? null) ? $config['planMapping'] : [];

        $featureFlagKeys = collect($featureFlags)
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $mappedKeys = collect($planMapping)
            ->flatten(1)
            ->filter()
            ->unique()
            ->values()
            ->all();

        sort($featureFlagKeys);
        sort($mappedKeys);

        $this->assertSame($planEnum, array_values(array_unique($planEnum)));
        $this->assertSame($featureFlagKeys, $mappedKeys);
    }

    #[Test]
    public function every_feature_flag_uses_known_plans_only(): void
    {
        $config = json_decode((string) file_get_contents(config_path('feature-gating.json')), true);

        $knownPlans = array_values(array_filter($config['planEnum'] ?? []));
        $knownPlanLookup = array_fill_keys($knownPlans, true);

        foreach (($config['featureFlags'] ?? []) as $featureFlag) {
            $featureKey = (string) ($featureFlag['key'] ?? '');
            $featurePlans = array_values(array_filter(is_array($featureFlag['plans'] ?? null) ? $featureFlag['plans'] : []));

            $this->assertNotSame('', $featureKey);
            $this->assertNotEmpty($featurePlans, "Feature {$featureKey} must declare at least one plan.");

            foreach ($featurePlans as $plan) {
                $this->assertArrayHasKey($plan, $knownPlanLookup, "Feature {$featureKey} uses unknown plan {$plan}.");
            }
        }
    }
}