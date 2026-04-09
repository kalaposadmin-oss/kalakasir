<?php

namespace App\Providers;

use App\Listeners\SetCurrentTenantOnLogin;
use App\Events\AdjustmentPosted;
use App\Events\PurchaseReceived;
use App\Events\PurchaseReturned;
use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Events\SubscriptionPlanChanged;
use App\Events\TransferReceived;
use App\Events\TransferShipped;
use App\Listeners\AggregateDailySales;
use App\Listeners\AggregateDailySalesVoid;
use App\Listeners\AuditAdjustmentPosted;
use App\Listeners\AuditPurchaseReceived;
use App\Listeners\AuditPurchaseReturned;
use App\Listeners\AuditSaleCompleted;
use App\Listeners\AuditSaleVoided;
use App\Listeners\AuditTransferReceived;
use App\Listeners\AuditTransferShipped;
use App\Listeners\HandleSubscriptionPlanChanged;
use App\Listeners\RefreshInventorySummaryFromAdjustment;
use App\Listeners\RefreshInventorySummaryFromPurchase;
use App\Listeners\RefreshInventorySummaryFromReturn;
use App\Listeners\RefreshInventorySummaryFromTransferReceive;
use App\Listeners\RefreshInventorySummaryFromTransferShip;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $maxBackdateDays = (int) config('app.max_backdate_days', 1);
        if ($maxBackdateDays < 0) {
            config(['app.max_backdate_days' => 0]);
            Log::warning('Invalid APP_MAX_BACKDATE_DAYS detected. Fallback to 0.', [
                'provided_value' => $maxBackdateDays,
            ]);
        }

        RateLimiter::for('platform-danger', static function ($request) {
            $actorId = $request->user()?->id ?: $request->ip();

            return Limit::perHour(10)->by((string) $actorId)->response(static function () {
                return response()->json([
                    'code' => 'RATE_LIMITED',
                    'message' => 'Terlalu banyak permintaan aksi berbahaya.',
                    'details' => [],
                ], 429);
            });
        });

        Event::listen(SaleCompleted::class, [AuditSaleCompleted::class, 'handle']);
        Event::listen(SaleCompleted::class, [AggregateDailySales::class, 'handle']);
        Event::listen(SaleVoided::class, [AuditSaleVoided::class, 'handle']);
        Event::listen(SaleVoided::class, [AggregateDailySalesVoid::class, 'handle']);
        Event::listen(PurchaseReceived::class, [AuditPurchaseReceived::class, 'handle']);
        Event::listen(PurchaseReceived::class, [RefreshInventorySummaryFromPurchase::class, 'handle']);
        Event::listen(PurchaseReturned::class, [AuditPurchaseReturned::class, 'handle']);
        Event::listen(PurchaseReturned::class, [RefreshInventorySummaryFromReturn::class, 'handle']);
        Event::listen(TransferShipped::class, [AuditTransferShipped::class, 'handle']);
        Event::listen(TransferShipped::class, [RefreshInventorySummaryFromTransferShip::class, 'handle']);
        Event::listen(TransferReceived::class, [AuditTransferReceived::class, 'handle']);
        Event::listen(TransferReceived::class, [RefreshInventorySummaryFromTransferReceive::class, 'handle']);
        Event::listen(AdjustmentPosted::class, [AuditAdjustmentPosted::class, 'handle']);
        Event::listen(AdjustmentPosted::class, [RefreshInventorySummaryFromAdjustment::class, 'handle']);
        Event::listen(SubscriptionPlanChanged::class, [HandleSubscriptionPlanChanged::class, 'handle']);
        Event::listen(Login::class, [SetCurrentTenantOnLogin::class, 'handle']);
    }
}
