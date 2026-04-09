<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $search = trim((string) $request->query('search', ''));
        $limit = (int) $request->query('limit', 20);
        if ($limit <= 0) {
            $limit = 20;
        }

        $materials = Material::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->select('id', 'name', 'unit', 'unit_cost', 'supplier_id')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return ApiResponse::success([
            'materials' => $materials,
        ], [
            'count' => $materials->count(),
            'limit' => $limit,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('materials')->where('tenant_id', $tenantId)],
            'unit' => ['required', 'string', 'max:30'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
        ]);

        $material = Material::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'] ?? 0,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'material' => [
                'id' => $material->id,
                'name' => $material->name,
                'unit' => $material->unit,
                'unit_cost' => (float) ($material->unit_cost ?? 0),
                'supplier_id' => $material->supplier_id,
            ],
        ], 201);
    }

    public function show(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = Material::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $material)
            ->select('id', 'name', 'unit', 'unit_cost', 'supplier_id')
            ->firstOrFail();

        return response()->json([
            'material' => $materialRow,
        ]);
    }

    public function update(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = Material::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $material)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('materials')->where('tenant_id', $tenantId)->ignore($materialRow->id)],
            'unit' => ['required', 'string', 'max:30'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['nullable', Rule::in([true, false])],
        ]);

        $materialRow->update([
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'] ?? $materialRow->unit_cost ?? 0,
            'supplier_id' => $validated['supplier_id'] ?? $materialRow->supplier_id,
            'is_active' => $validated['is_active'] ?? $materialRow->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'material' => [
                'id' => $materialRow->id,
                'name' => $materialRow->name,
                'unit' => $materialRow->unit,
                'unit_cost' => (float) ($materialRow->unit_cost ?? 0),
                'supplier_id' => $materialRow->supplier_id,
            ],
        ]);
    }

    public function destroy(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = Material::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $material)
            ->firstOrFail();

        $materialRow->update([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ]);

        $materialRow->delete();

        return response()->json([
            'message' => 'Bahan berhasil dihapus.',
        ]);
    }
}
