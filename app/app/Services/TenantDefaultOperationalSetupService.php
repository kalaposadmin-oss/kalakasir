<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantDefaultOperationalSetupService
{
    /**
     * @return array{0: Branch, 1: Location}
     */
    public function ensure(Tenant $tenant, ?string $branchName = null, ?string $branchAddress = null): array
    {
        $branch = null;
        $location = null;

        if ($tenant->default_location_id) {
            $location = Location::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $tenant->default_location_id)
                ->first();
        }

        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->orderBy('name')
                ->first();
        }

        if ($location) {
            $branch = Branch::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $location->branch_id)
                ->first();
        }

        if (! $branch) {
            $branch = Branch::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->first();
        }

        if (! $branch) {
            $branch = Branch::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => $branchName ?: $tenant->name,
                'phone' => null,
                'address' => $branchAddress,
                'is_active' => true,
            ]);
        } else {
            $branchPatch = [];
            if ($branchName !== null && trim($branchName) !== '' && $branch->name !== $branchName) {
                $branchPatch['name'] = $branchName;
            }
            if ($branchAddress !== null && $branch->address !== $branchAddress) {
                $branchPatch['address'] = $branchAddress;
            }
            if ($branchPatch !== []) {
                $branch->update($branchPatch);
                $branch->refresh();
            }
        }

        if (! $location) {
            $location = Location::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Lokasi Utama',
                'is_default' => true,
            ]);
        } elseif ($location->branch_id !== $branch->id || ! $location->is_default) {
            $location->update([
                'branch_id' => $branch->id,
                'is_default' => true,
            ]);
            $location->refresh();
        }

        if ($tenant->default_location_id !== $location->id) {
            $tenant->update([
                'default_location_id' => $location->id,
            ]);
            $tenant->refresh();
        }

        return [$branch, $location];
    }
}