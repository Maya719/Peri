<?php

namespace App\Providers;

use App\Events\AfterChangePlan;
use App\Events\BeforeChangePlan;
use App\Events\BeforeRenewPlan;
use App\Events\RenewPlan;
use App\Events\BeforeSubscribePlan;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use App\Facades\FilamentPayments;
use App\Services\Contracts\PaymentBillingInfo;
use App\Services\Contracts\PaymentCustomer;
use App\Services\Contracts\PaymentRequest;
use App\Services\Contracts\PaymentShippingInfo;
use App\Facades\FilamentSubscriptions;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;

class FilamentPaymentsServiceProvider extends ServiceProvider
{
    public $currentPanel;
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('filament-payments', function () {
            return new \App\Services\FilamentPaymentsServices();
        });
    }

    /**
     * Bootstrap services.
     */

    public function boot(): void
    {
        $this->currentPanel = Filament::getCurrentPanel()->getId();

        FilamentSubscriptions::beforeSubscription(function ($data) {
            $this->PaymentPage($data, BeforeSubscribePlan::class);
        });

        FilamentSubscriptions::beforeRenew(function ($data) {
            $this->PaymentPage($data, BeforeRenewPlan::class);
        });
        FilamentSubscriptions::beforeChange(function ($data) {
            $this->PaymentPage($data, BeforeChangePlan::class);
        });

        FilamentSubscriptions::afterSubscription(function (array $data) {
            $plan = $data['new'];             // App\Models\Plan
            $team = Team::find($data['team_id']); // subscriber
            $team->newPlanSubscription('main', $plan);
            $sub = $team->planSubscriptions()->first();
            $sub->gateway_subscription_id = $data['gateway_subscription'];
            $sub->save();
            Notification::make()
                ->title('Subscription Successfully')
                ->success()
                ->send();
            return redirect()->to($this->currentPanel);
        });

        FilamentSubscriptions::afterRenew(function (array $data) {
            $plan = $data['new'];
            $subscription = $data['subscription'];
            $subscription->renew($plan);
            Notification::make()->title('Renew Successfully')->success()->send();
            return redirect()->to($this->currentPanel);
        });
        FilamentSubscriptions::afterChange(function (array $data) {
            $plan = $data['new'];
            $subscription = $data['subscription'];
            $subscription->changePlan($plan);
            Notification::make()->title('Changed Successfully')->success()->send();
            return redirect()->to($this->currentPanel);
        });
    }

    private function PaymentPage($data, $event)
    {
        return redirect()->to(
            FilamentPayments::pay(
                data: PaymentRequest::make(Plan::class)
                    ->model_id($data['new']->id)
                    ->team_id($data['team_id'])
                    ->event($event)
                    ->currency('USD')
                    ->amount($data['new']->price)
                    ->details('Subscription Payment')
                    ->success_url(url('/success'))
                    ->cancel_url(url('/cancel'))
                    ->customer(
                        PaymentCustomer::make(Auth::user()->name)
                            ->email(Auth::user()->email)
                            ->mobile(Auth::user()->phone_number ??'+92 222 222 2222')
                    )
            )
        );
    }
}
