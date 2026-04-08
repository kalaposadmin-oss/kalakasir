<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use App\Support\WebPortalResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PortalAuthController extends Controller
{
    public function showGeneric(Request $request)
    {
        if (Auth::check()) {
            $activePortal = $this->resolvePreferredPortal($request, Auth::user());
            $request->session()->put('auth_portal', $activePortal);

            return redirect($this->portalDashboard($activePortal));
        }

        return view('auth-login', array_merge([
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
        ], $this->portalLoginViewData(app(WebPortalResolver::class)->resolvePortalFromRequest($request))));
    }

    public function showPos(Request $request)
    {
        if (Auth::check()) {
            $targetPortal = $this->resolveAuthenticatedPortal($request, Auth::user(), 'pos');
            $request->session()->put('auth_portal', $targetPortal);

            return redirect($this->portalDashboard($targetPortal));
        }

        return view('auth-login', [
            'tenantId' => $this->resolveTenantId(),
            'loginAction' => '/pos/login',
            'loginTitle' => 'Login POS',
            'loginSubtitle' => 'Masuk cepat untuk operasional kasir harian.',
            'portalLabel' => 'POS',
            'portalTone' => 'primary',
            'showMenu' => false,
            'showRegisterLink' => false,
        ]);
    }

    public function showBackoffice(Request $request)
    {
        if (Auth::check()) {
            $targetPortal = $this->resolveAuthenticatedPortal($request, Auth::user(), 'backoffice');
            $request->session()->put('auth_portal', $targetPortal);

            return redirect($this->portalDashboard($targetPortal));
        }

        return view('auth-login', [
            'tenantId' => $this->resolveTenantId(),
            'loginAction' => '/backoffice/login',
            'loginTitle' => 'Login Backoffice',
            'loginSubtitle' => 'Masuk untuk mengelola master data, laporan, dan pengaturan toko.',
            'portalLabel' => 'BACKOFFICE',
            'portalTone' => 'slate',
            'showMenu' => false,
            'showRegisterLink' => false,
        ]);
    }

    public function loginGeneric(Request $request)
    {
        return $this->attemptLogin($request, null);
    }

    public function loginPos(Request $request)
    {
        return $this->attemptLogin($request, 'pos');
    }

    public function loginBackoffice(Request $request)
    {
        return $this->attemptLogin($request, 'backoffice');
    }

    private function attemptLogin(Request $request, ?string $forcedPortal)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $normalizedEmail = strtolower(trim((string) $validated['email']));
        $user = User::query()
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->first();

        $this->ensureNotRateLimited($request, $normalizedEmail);

        if (! Auth::attempt([
            'email' => $normalizedEmail,
            'password' => $validated['password'],
        ])) {
            $this->recordFailedAttempt($request, $normalizedEmail, $user, $forcedPortal);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
        }

        $this->clearFailedAttempts($request, $normalizedEmail, $user);
        $request->session()->regenerate();

        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::user();
        if (! $authenticatedUser) {
            throw new RuntimeException('Autentikasi web gagal menyelesaikan sesi pengguna.');
        }

        $targetPortal = $forcedPortal ?? $this->resolvePreferredPortal($request, $authenticatedUser);
        $accessError = $this->portalAccessError($targetPortal, $this->resolvePortalRole($authenticatedUser));
        if ($accessError) {
            Auth::guard('web')->logout();
            TenantContext::clear();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $accessError,
            ]);
        }

        $request->session()->put('auth_portal', $targetPortal);

        return redirect()->intended($this->portalDashboard($targetPortal));
    }

    private function ensureNotRateLimited(Request $request, string $email): void
    {
        $key = $this->webLoginThrottleKey($request, $email);
        $maxAttempts = max(1, (int) config('services.security.web_login.max_attempts', 5));

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$seconds.' detik.',
        ]);
    }

    private function recordFailedAttempt(Request $request, string $email, ?User $user, ?string $portal): void
    {
        $failedAttempts = max(1, ((int) ($user?->failed_login_attempts ?? 0)) + 1);
        $baseLockoutSeconds = max(30, (int) config('services.security.web_login.lockout_seconds', 900));
        $maxLockoutSeconds = max($baseLockoutSeconds, (int) config('services.security.web_login.max_lockout_seconds', 3600));
        $lockoutSeconds = min($maxLockoutSeconds, $baseLockoutSeconds * $failedAttempts);

        RateLimiter::hit($this->webLoginThrottleKey($request, $email), $lockoutSeconds);

        if ($user) {
            $user->forceFill([
                'failed_login_attempts' => $failedAttempts,
            ])->save();
        }

        $tenantId = (string) ($user?->tenant_id ?: $this->resolveTenantId() ?: '');
        if ($tenantId !== '' && Schema::hasTable('audit_logs')) {
            app(AuditLogger::class)->log(
                event: 'web_login_failed',
                actorId: null,
                referenceType: $user ? 'user' : 'tenant',
                referenceId: $user?->id ?: $tenantId,
                payload: [
                    'email' => $email,
                    'portal' => $portal ?? 'generic',
                    'failed_login_attempts' => $failedAttempts,
                    'lockout_seconds' => $lockoutSeconds,
                    'path' => $request->path(),
                    'ip_address' => $request->ip(),
                ],
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
                tenantId: $tenantId,
                occurredAt: CarbonImmutable::now('UTC'),
            );
        }
    }

    private function clearFailedAttempts(Request $request, string $email, ?User $user): void
    {
        RateLimiter::clear($this->webLoginThrottleKey($request, $email));

        if ($user && (int) $user->failed_login_attempts !== 0) {
            $user->forceFill([
                'failed_login_attempts' => 0,
            ])->save();
        }
    }

    private function webLoginThrottleKey(Request $request, string $email): string
    {
        return 'security:web-login:'.sha1(strtolower(trim($email)).'|'.$request->ip().'|'.$request->path());
    }

    private function resolvePortalRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $previousTenant = TenantContext::get();
        $tenantId = (string) ($user->tenant_id ?: $this->resolveTenantId() ?: '');
        try {
            if ($tenantId !== '') {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant) {
                    TenantContext::set($tenant);
                }
            }

            $resolver = app(UserRoleResolverService::class);
            $role = $resolver->resolveNormalizedRole($tenantId, (string) $user->getAuthIdentifier());

            if ($role) {
                return $role;
            }

            $fallbackRole = trim((string) ($user->role ?? ''));

            return $fallbackRole !== '' ? $resolver->normalize($fallbackRole) : null;
        } finally {
            if ($previousTenant) {
                TenantContext::set($previousTenant);
            } else {
                TenantContext::clear();
            }
        }
    }

    private function resolvePreferredPortal(?Request $request = null, ?User $user = null): string
    {
        return $this->resolveAuthenticatedPortal($request, $user, null);
    }

    private function resolveAuthenticatedPortal(?Request $request = null, ?User $user = null, ?string $forcedPortal = null): string
    {
        $role = $this->resolvePortalRole($user);

        if (in_array($forcedPortal, ['pos', 'backoffice'], true)
            && $this->portalAccessError($forcedPortal, $role) === null) {
            return $forcedPortal;
        }

        if ($request) {
            $portalResolver = app(WebPortalResolver::class);
            $requestPortal = $portalResolver->resolvePortalFromRequest($request);
            if (in_array($requestPortal, ['pos', 'backoffice'], true)
                && $this->portalAccessError($requestPortal, $role) === null) {
                return $requestPortal;
            }

            $sessionPortal = $portalResolver->resolveSessionPortal($request);
            if (in_array($sessionPortal, ['pos', 'backoffice'], true)
                && $this->portalAccessError($sessionPortal, $role) === null) {
                return $sessionPortal;
            }
        }

        if ($this->portalAccessError('backoffice', $role) === null) {
            return 'backoffice';
        }

        return 'pos';
    }

    private function portalAccessError(string $portal, ?string $role): ?string
    {
        $allowedRoles = $portal === 'backoffice'
            ? ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin']
            : ['cashier', 'staff', 'warehouse', 'supervisor', 'admin', 'owner', 'superadmin'];

        if ($portal === 'pos' && $role === null) {
            return null;
        }

        if (! in_array($role, $allowedRoles, true)) {
            return $portal === 'backoffice'
                ? 'Akun ini tidak memiliki akses ke backoffice.'
                : 'Akun ini tidak memiliki akses ke POS.';
        }

        return null;
    }

    private function portalDashboard(string $portal): string
    {
        return $portal === 'backoffice' ? '/backoffice/dashboard' : '/pos/dashboard';
    }

    private function portalLoginViewData(?string $portal = null): array
    {
        return match ($portal) {
            'pos' => [
                'loginAction' => '/login',
                'loginTitle' => 'Login POS',
                'loginSubtitle' => 'Masuk cepat untuk operasional kasir harian.',
                'portalLabel' => 'POS',
                'portalTone' => 'primary',
                'showRegisterLink' => true,
            ],
            'backoffice' => [
                'loginAction' => '/login',
                'loginTitle' => 'Login Backoffice',
                'loginSubtitle' => 'Masuk untuk mengelola master data, laporan, dan pengaturan toko.',
                'portalLabel' => 'BACKOFFICE',
                'portalTone' => 'slate',
                'showRegisterLink' => false,
            ],
            default => [
                'loginAction' => '/login',
                'loginTitle' => 'Masuk ke KalaKasir',
                'loginSubtitle' => 'Masuk untuk mulai transaksi dan mengelola toko.',
                'portalLabel' => 'PORTAL UTAMA',
                'portalTone' => 'primary',
                'showRegisterLink' => true,
            ],
        };
    }

    private function resolveTenantId(): ?string
    {
        return TenantContext::id() ?: Tenant::query()->value('id');
    }
}