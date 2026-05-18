<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Location;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $rows = Cache::remember("branches:index:{$tenantId}", now('UTC')->addMinutes(5), function () use ($tenantId) {
            $branches = Branch::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'address', 'is_active']);
            $locations = Location::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->get(['id', 'branch_id', 'name', 'is_default']);

            return $branches->map(function ($branch) use ($locations) {
                $branchLocations = $locations->where('branch_id', $branch->id)->values();
                $defaultLocation = $branchLocations->firstWhere('is_default', true);

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'is_active' => (bool) $branch->is_active,
                    'locations' => $branchLocations->map(function ($location) {
                        return [
                            'id' => $location->id,
                            'name' => $location->name,
                            'is_default' => (bool) $location->is_default,
                        ];
                    })->values(),
                    'default_location' => $defaultLocation ? [
                        'id' => $defaultLocation->id,
                        'name' => $defaultLocation->name,
                    ] : null,
                ];
            });
        });

        return response()->json([
            'branches' => $rows,
        ]);
    }

    public function show(string $branch)
    {
        $tenantId = TenantContext::id();

        $branchRow = Branch::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $branch)
            ->firstOrFail();
        $locations = Location::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('branch_id', $branchRow->id)
            ->get(['id', 'name', 'is_default']);

        $defaultLocation = $locations->firstWhere('is_default', true);

        return response()->json([
            'branch' => [
                'id' => $branchRow->id,
                'name' => $branchRow->name,
                'phone' => $branchRow->phone,
                'address' => $branchRow->address,
                'is_active' => (bool) $branchRow->is_active,
            ],
            'locations' => $locations->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'is_default' => (bool) $location->is_default,
                ];
            })->values(),
            'default_location' => $defaultLocation ? [
                'id' => $defaultLocation->id,
                'name' => $defaultLocation->name,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branchId = (string) Str::uuid();
        Branch::query()->create([
            'id' => $branchId,
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Location::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => 'Lokasi Utama',
            'is_default' => true,
        ]);
        Cache::forget("branches:index:{$tenantId}");

        return response()->json([
            'branch_id' => $branchId,
        ], 201);
    }

    public function update(Request $request, string $branch)
    {
        $tenantId = TenantContext::id();

        $branchRow = Branch::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $branch)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'default_location_id' => [
                'nullable',
                'uuid',
                $tenantId
                    ? Rule::exists('locations', 'id')->where(function ($query) use ($tenantId, $branchRow) {
                        $query->where('tenant_id', $tenantId)
                            ->where('branch_id', $branchRow->id);
                    })
                    : Rule::exists('locations', 'id')->where(fn ($query) => $query->where('branch_id', $branchRow->id)),
            ],
        ]);

        $branchRow->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['default_location_id'])) {
            Location::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('branch_id', $branchRow->id)
                ->update(['is_default' => false]);

            Location::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('branch_id', $branchRow->id)
                ->where('id', $validated['default_location_id'])
                ->update(['is_default' => true]);
        }
        Cache::forget("branches:index:{$tenantId}");

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
