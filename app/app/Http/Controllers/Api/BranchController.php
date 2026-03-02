<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Location;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::query()->orderBy('name')->get(['id', 'name', 'phone', 'address', 'is_active']);
        $locations = Location::query()->get(['id', 'branch_id', 'name', 'is_default']);

        $rows = $branches->map(function ($branch) use ($locations) {
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

        return response()->json([
            'branches' => $rows,
        ]);
    }

    public function show(string $branch)
    {
        $branchRow = Branch::query()->where('id', $branch)->firstOrFail();
        $locations = Location::query()
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

        return response()->json([
            'branch_id' => $branchId,
        ], 201);
    }

    public function update(Request $request, string $branch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'default_location_id' => ['nullable', 'uuid'],
        ]);

        $branchRow = Branch::query()->where('id', $branch)->firstOrFail();
        $branchRow->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['default_location_id'])) {
            Location::query()
                ->where('branch_id', $branchRow->id)
                ->update(['is_default' => false]);

            Location::query()
                ->where('branch_id', $branchRow->id)
                ->where('id', $validated['default_location_id'])
                ->update(['is_default' => true]);
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
