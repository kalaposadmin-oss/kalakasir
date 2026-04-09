<?php

namespace App\Listeners;

use App\Events\SubscriptionPlanChanged;
use App\Support\FeatureGateConfig;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionPlanChanged
{
    public function handle(SubscriptionPlanChanged $event): void
    {
        FeatureGateConfig::forgetEntitlements($event->oldPlan);
        FeatureGateConfig::forgetEntitlements($event->newPlan);

        Log::info('subscription.plan.changed', [
            'tenant_id' => $event->tenantId,
            'actor_id' => $event->actorId,
            'old_plan' => $event->oldPlan,
            'new_plan' => $event->newPlan,
            'reason' => $event->reason,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }
}
