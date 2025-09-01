<?php

namespace App\Providers;

use App\Events\ChangePlan;
use App\Events\RenewPlan;
use App\Events\SubscribePlan;
use Carbon\Carbon;
use Filament\Notifications\Notification;
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
        FilamentSubscriptions::beforeSubscription(function ($data) {
            $this->PaymentPage($data, SubscribePlan::class);
        });

        FilamentSubscriptions::beforeRenew(function ($data) {
            $this->PaymentPage($data, RenewPlan::class);
        });
        FilamentSubscriptions::beforeChange(function ($data) {
            $this->PaymentPage($data, ChangePlan::class);
        });
        FilamentSubscriptions::afterSubscription(function ($data) {
            $team = Team::find($data['team_id']) ?? 'Unknown Team';
            User::where('is_super_admin', true)->get()->each(function ($user) use ($team) {
                Notification::make()
                    ->title('New Subscription')
                    ->success()
                    ->body("New subscription for " . $team->name . " has been successfully created.")
                    ->sendToDatabase($user, isEventDispatched: true);
            });
            Notification::make()
                ->title('Subscription Completed')
                ->body("Your subscription has been successfully created.")
                ->success();
        });
        FilamentSubscriptions::afterRenew(function ($data) {
            $tenant = Team::find($data['team_id']);
            $currentSubscription = $tenant->planSubscriptions()->first();
            $currentSubscription->canceled_at = Carbon::parse($currentSubscription->cancels_at)->addDays(1);
            $currentSubscription->cancels_at = Carbon::parse($currentSubscription->cancels_at)->addDays(1);
            $currentSubscription->ends_at = Carbon::parse($currentSubscription->cancels_at)->addDays(1);
            $currentSubscription->save();
            $currentSubscription->renew($currentSubscription->plan);
            Notification::make()
                ->title('Subscription Renewed')
                ->body("Your subscription has been successfully renewed.")
                ->success();
            return redirect("/client");
        });
        FilamentSubscriptions::afterCanceling(function ($data) {

        });

        FilamentSubscriptions::afterChange(function ($data) {
            $data['subscription']->changePlan($data['new']);
            return redirect("/client");
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
                    ->success_url(url('/client'))
                    ->cancel_url(url('/client'))
                    ->customer(
                        PaymentCustomer::make('John Doe')
                            ->email('john@gmail.com')
                            ->mobile('+201207860084')
                    )
                    ->billing_info(
                        PaymentBillingInfo::make('123 Main St')
                            ->area('Downtown')
                            ->city('Cairo')
                            ->state('Cairo')
                            ->postcode('12345')
                            ->country('EG')
                    )
                    ->shipping_info(
                        PaymentShippingInfo::make('123 Main St')
                            ->area('Downtown')
                            ->city('Cairo')
                            ->state('Cairo')
                            ->postcode('12345')
                            ->country('EG')
                    )
            )
        );
    }
}
