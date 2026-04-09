<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPlanChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $tenantId,
        public ?string $actorId,
        public string $oldPlan,
        public string $newPlan,
        public string $reason,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
