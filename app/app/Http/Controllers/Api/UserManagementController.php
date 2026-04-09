<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use App\Services\TenantDefaultRoleSetupService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $this->ensureDefaultRoles($tenantId);

        $roles = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name'])
            ->orderByRaw("case lower(name) when 'owner' then 1 when 'admin' then 2 when 'manager' then 3 when 'kasir' then 4 when 'staf gudang' then 5 else 99 end")
            ->orderBy('name')
            ->get();

        $roleMap = [];
        $userRoles = DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.tenant_id', $tenantId)
            ->select(['user_roles.user_id', 'roles.name as role_name'])
            ->get();

        foreach ($userRoles as $row) {
            if (! isset($roleMap[$row->user_id])) {
                $roleMap[$row->user_id] = $row->role_name;
            }
        }

        $branchMap = Branch::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->get(['id', 'name'])
            ->keyBy('id');

        $shiftRows = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderByDesc('opened_at')
            ->get(['user_id', 'branch_id', 'opened_at']);

        $shiftMap = [];
        foreach ($shiftRows as $row) {
            if (! isset($shiftMap[$row->user_id])) {
                $shiftMap[$row->user_id] = $row->branch_id;
            }
        }

        $users = User::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'deleted_at']);
        $rows = $users->map(function ($user) use ($roleMap, $branchMap, $shiftMap) {
            $branchName = null;
            if (isset($shiftMap[$user->id])) {
                $branchName = $branchMap[$shiftMap[$user->id]]->name ?? null;
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleMap[$user->id] ?? 'Kasir',
                'branch' => $branchName ?? 'Pusat',
                'status' => $user->deleted_at ? 'inactive' : 'active',
            ];
        });

        $activeSessions = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('status', 'open')
            ->count();

        return response()->json([
            'total_staff' => $rows->count(),
            'active_sessions' => $activeSessions,
            'roles' => $roles,
            'staff' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $this->ensureDefaultRoles($tenantId);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'uuid'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $roleExists = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('id', $payload['role_id'])
            ->exists();

        if (! $roleExists) {
            return response()->json([
                'message' => 'Role tidak ditemukan.',
            ], 422);
        }

        $user = User::query()->create([
            'tenant_id' => $tenantId,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        $this->assignRole($tenantId, $user->id, $payload['role_id']);

        if (($payload['status'] ?? 'active') === 'inactive') {
            $user->delete();
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function update(Request $request, string $user)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->where(function ($query) use ($tenantId, $user) {
                    $query->where('tenant_id', $tenantId)
                        ->where('id', '!=', $user)
                        ->whereNull('deleted_at');
                }),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $userRow = User::withTrashed()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $user)
            ->firstOrFail();

        if (($payload['status'] ?? 'active') === 'inactive') {
            if (! $userRow->trashed()) {
                $userRow->delete();
            }
        } elseif ($userRow->trashed()) {
            $userRow->restore();
        }

        $updateData = [
            'name' => $payload['name'],
            'email' => $payload['email'],
        ];

        if (! empty($payload['password'])) {
            $updateData['password'] = Hash::make($payload['password']);
        }

        $userRow->update($updateData);

        if (! empty($payload['role_id'])) {
            $roleExists = DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->where('id', $payload['role_id'])
                ->exists();

            if (! $roleExists) {
                return response()->json([
                    'message' => 'Role tidak ditemukan.',
                ], 422);
            }

            $this->assignRole($tenantId, $userRow->id, $payload['role_id']);
        }

        return response()->json([
            'user' => [
                'id' => $userRow->id,
                'name' => $userRow->name,
                'email' => $userRow->email,
                'status' => $userRow->trashed() ? 'inactive' : 'active',
            ],
        ]);
    }

    private function ensureDefaultRoles(?string $tenantId): void
    {
        if (! $tenantId) {
            return;
        }

        app(TenantDefaultRoleSetupService::class)->ensure($tenantId);
    }

    private function assignRole(string $tenantId, string $userId, string $roleId): void
    {
        DB::table('user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }
}
