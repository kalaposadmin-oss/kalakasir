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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PortalAuthController extends Controller
{
    public function showRoot(Request $request)
    {
        $requestPortal = app(WebPortalResolver::class)->resolvePortalFromRequest($request);
        if ($requestPortal === 'pos') {
            return $this->renderPosLogin($request, '/', true);
        }

        if ($requestPortal === 'backoffice') {
            return $this->renderBackofficeLogin($request, '/backoffice');
        }

        return $this->showGeneric($request);
    }

    public function showGeneric(Request $request)
    {
        if ($this->shouldStartOnboarding()) {
            return redirect('/register/step-1');
        }

        if (Auth::guard('web')->check()) {
            $activePortal = $this->resolvePreferredPortal($request, Auth::guard('web')->user());
            $request->session()->put('auth_portal', $activePortal);

            return redirect($this->portalDashboard($activePortal));
        }

        return view('auth-login', [
            'tenantId' => $this->resolveTenantId(),
            'loginAction' => '/login',
            'loginTitle' => 'Pilih Area Kerja',
            'loginSubtitle' => 'Masuk ke POS untuk transaksi harian atau ke Backoffice untuk laporan dan pengaturan toko.',
            'portalLabel' => null,
            'portalTone' => 'primary',
            'showMenu' => false,
            'showRegisterLink' => true,
            'showPortalChooserOnly' => true,
        ]);
    }

    public function showPos(Request $request)
    {
        if ($this->shouldStartOnboarding()) {
            return redirect('/register/step-1');
        }

        return $this->renderPosLogin($request, '/pos/login', false);
    }

    public function showBackofficeEntry(Request $request)
    {
        if ($this->shouldStartOnboarding()) {
            return redirect('/register/step-1');
        }

        return $this->renderBackofficeLogin($request, '/backoffice');
    }

    public function showBackoffice(Request $request)
    {
        if ($this->shouldStartOnboarding()) {
            return redirect('/register/step-1');
        }

        if (! Auth::guard('web')->check()) {
            return redirect('/backoffice');
        }

        return $this->renderBackofficeLogin($request, '/backoffice/login');
    }

    public function loginRoot(Request $request)
    {
        return $this->attemptLogin($request, 'pos');
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

    private function renderPosLogin(Request $request, string $loginAction, bool $showRegisterLink)
    {
        if (Auth::guard('web')->check()) {
            $targetPortal = $this->resolveAuthenticatedPortal($request, Auth::guard('web')->user(), 'pos');
            $request->session()->put('auth_portal', $targetPortal);

            return redirect($this->portalDashboard($targetPortal));
        }

        return view('auth-login', [
            'tenantId' => $this->resolveTenantId(),
            'loginAction' => $loginAction,
            'loginTitle' => 'Login POS',
            'loginSubtitle' => 'Area kerja kasir untuk transaksi dan operasional harian.',
            'portalLabel' => 'POS',
            'portalTone' => 'primary',
            'showMenu' => false,
            'showRegisterLink' => $showRegisterLink,
        ]);
    }

    private function renderBackofficeLogin(Request $request, string $loginAction)
    {
        $isSuperadminLogin = $this->isSuperadminLoginIntent($request);

        if (Auth::guard('web')->check()) {
            $targetPortal = $this->resolveAuthenticatedPortal($request, Auth::guard('web')->user(), $isSuperadminLogin ? 'superadmin' : 'backoffice');
            $request->session()->put('auth_portal', $targetPortal);

            return redirect($this->portalDashboard($targetPortal));
        }

        return view('auth-login', [
            'tenantId' => $this->resolveTenantId(),
            'loginAction' => $loginAction,
            'loginTitle' => $isSuperadminLogin ? 'Login Superadmin' : 'Login Backoffice',
            'loginSubtitle' => $isSuperadminLogin
                ? 'Area kontrol platform untuk memantau tenant, insiden, billing, dan kesehatan sistem.'
                : 'Area kerja owner dan admin untuk laporan, data master, dan pengaturan toko.',
            'loginButtonLabel' => $isSuperadminLogin ? 'Masuk Superadmin' : 'Masuk Backoffice',
            'accessBoxTitle' => $isSuperadminLogin ? 'Akses superadmin' : 'Akses backoffice',
            'accessBoxDescription' => $isSuperadminLogin
                ? 'Gunakan akun dengan role superadmin. Akun owner/admin toko akan tetap diarahkan ke backoffice.'
                : 'Gunakan akun owner, admin, supervisor, atau gudang yang sudah terdaftar.',
            'portalLabel' => $isSuperadminLogin ? 'SUPERADMIN' : 'BACKOFFICE',
            'portalTone' => 'slate',
            'showMenu' => false,
            'showRegisterLink' => false,
        ]);
    }

    private function isSuperadminLoginIntent(Request $request): bool
    {
        $intended = (string) $request->session()->get('url.intended', '');
        $intendedPath = parse_url($intended, PHP_URL_PATH) ?: $intended;

        return str_starts_with($intendedPath, '/superadmin');
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

        $credentials = [
            'email' => $normalizedEmail,
            'password' => $validated['password'],
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            $this->recordFailedAttempt($request, $normalizedEmail, $user, $forcedPortal);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
        }

        $this->clearFailedAttempts($request, $normalizedEmail, $user);
        $request->session()->regenerate();

        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::guard('web')->user();
        if (! $authenticatedUser) {
            throw new RuntimeException('Autentikasi web gagal menyelesaikan sesi pengguna.');
        }
        $this->syncAuthenticatedTenant($request, $authenticatedUser);

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

        if (in_array($forcedPortal, ['pos', 'backoffice', 'superadmin'], true)
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

        if ($this->portalAccessError('superadmin', $role) === null) {
            return 'superadmin';
        }

        if ($this->portalAccessError('backoffice', $role) === null) {
            return 'backoffice';
        }

        return 'pos';
    }

    private function portalAccessError(string $portal, ?string $role): ?string
    {
        $allowedRoles = match ($portal) {
            'superadmin' => ['superadmin'],
            'backoffice' => ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'],
            default => ['cashier', 'staff', 'warehouse', 'supervisor', 'admin', 'owner', 'superadmin'],
        };

        if ($portal === 'pos' && $role === null) {
            return null;
        }

        if (! in_array($role, $allowedRoles, true)) {
            return match ($portal) {
                'superadmin' => 'Akun ini tidak memiliki akses ke superadmin.',
                'backoffice' => 'Akun ini tidak memiliki akses ke backoffice.',
                default => 'Akun ini tidak memiliki akses ke POS.',
            };
        }

        return null;
    }

    private function portalDashboard(string $portal): string
    {
        if ($portal === 'superadmin') {
            return '/superadmin/dashboard';
        }

        if ($portal === 'backoffice') {
            return '/backoffice/dashboard';
        }

        // For POS portal: if the user already has an open shift,
        // go directly to the checkout page for faster transaction flow.
        return pos_default_url(Auth::guard('web')->user());
    }

    private function shouldStartOnboarding(): bool
    {
        $tenantId = $this->resolveTenantId();

        if (! $tenantId || ! Schema::hasTable('users')) {
            return false;
        }

        return ! DB::table('users')
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    private function portalLoginViewData(?string $portal = null): array
    {
        return match ($portal) {
            'pos' => [
                'loginAction' => '/login',
                'loginTitle' => 'Login POS',
                'loginSubtitle' => 'Area kerja kasir untuk transaksi dan operasional harian.',
                'portalLabel' => 'POS',
                'portalTone' => 'primary',
                'showRegisterLink' => true,
            ],
            'backoffice' => [
                'loginAction' => '/login',
                'loginTitle' => 'Login Backoffice',
                'loginSubtitle' => 'Area kerja owner dan admin untuk laporan, data master, dan pengaturan toko.',
                'portalLabel' => 'BACKOFFICE',
                'portalTone' => 'slate',
                'showRegisterLink' => false,
            ],
            default => [
                'loginAction' => '/login',
                'loginTitle' => 'Masuk ke KalaKasir',
                'loginSubtitle' => 'Gunakan satu akun untuk masuk ke POS atau Backoffice sesuai peran Anda.',
                'portalLabel' => null,
                'portalTone' => 'primary',
                'showRegisterLink' => true,
            ],
        };
    }

    private function resolveTenantId(): ?string
    {
        return TenantContext::id() ?: Tenant::query()->value('id');
    }

    private function syncAuthenticatedTenant(Request $request, User $user): void
    {
        $tenantId = (string) ($user->tenant_id ?? '');
        if ($tenantId === '') {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return;
        }

        TenantContext::set($tenant);
        $request->session()->put('current_tenant_id', $tenantId);

        if ((string) ($user->current_tenant_id ?? '') !== $tenantId) {
            $user->forceFill(['current_tenant_id' => $tenantId])->save();
        }
    }
}
