<?php

namespace App\Http\Middleware;

use App\Filament\Client\Pages\Subscriptions\Subscriptions;
use App\Models\Notice;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBillableIsSubscribed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();
        if ($tenant && $tenant->activePlanSubscriptions()->isEmpty()) {
            return redirect(Subscriptions::getUrl());
        }
        return $next($request);
    }
    protected function notice(): bool
    {
        return Filament::getTenant()->notices()
            ->where('name', 'Subscription Expiring Soon', )
            ->exists();
    }
    protected function subscriptionExpiringSoon(): bool
    {
        $tenant = Filament::getTenant();
        $subscription = $tenant->activePlanSubscriptions()->first();
        return $subscription && $subscription->ends_at && $subscription->ends_at->isBetween(now(), now()->addDays(7));
    }
}
