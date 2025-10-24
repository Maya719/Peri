<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Events\BeforeChangePlan;
use App\Events\BeforeRenewPlan;
use App\Events\BeforeSubscribePlan;
use App\Facades\FilamentSubscriptions;
use App\Http\Middleware\VerifyBillableIsSubscribed;
use App\Models\PaymentGateway;
use App\Models\Plan;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
class Subscriptions extends Page
{
    public $tenant;
    public $currentSubscription;
    public $recommendedPlan;
    public $currentPlan;
    public $plans;
    public bool $isFreePlan = false;
    protected static ?string $navigationGroup = 'Subscription';
    protected static string $view = 'filament.client.pages.subscriptions.subscription';
    protected static ?string $title = 'Subscription';
    protected static ?string $description = 'Manage your subscription plan and billing details.';
    protected static string|array $withoutRouteMiddleware = VerifyBillableIsSubscribed::class;

    public static function shouldRegisterNavigation(): bool
    {
        return (Auth::check() && Auth::user()->hasRole('Admin'));
    }
    public function mount()
    {
        $this->tenant = Filament::getTenant();
        $this->plans = Plan::where('price', '>', 0)->where('is_active',true)->get();
        $this->currentSubscription = $this->getActiveSubscription();
        if ($this->currentSubscription)
        {
            $this->currentPlan = $this->getCurrentPlan();
            $this->recommendedPlan = $this->getRecommendedPlan();
            $this->isFreePlan = $this->currentPlan->isFree();
        }
    }

    public function getActiveSubscription()
    {
        return $this->tenant->planSubscriptions()->first();
    }
    public function autoRenew(): Action
    {
        return Action::make('autoRenew')
            ->label(fn() => $this->currentSubscription->auto_renew ? 'Off' : 'On')
            ->requiresConfirmation()
            ->modalHeading($this->currentSubscription->auto_renew ? 'AutoRenew Off' : 'AutoRenew On')
            ->modalDescription('Are you sure you want to')
            ->modalSubmitActionLabel($this->currentSubscription->auto_renew ? 'Off' : 'On')
            ->modalCancelActionLabel('Cancel')
            ->action(function () {
                $this->currentSubscription->auto_renew = !$this->currentSubscription->auto_renew;
                if ($this->currentSubscription->auto_renew && !Filament::getTenant()->payment_methods()->count() > 0) {
                    return redirect(PaymentMethods::getUrl());
                }
                $this->currentSubscription->save();
                $status = $this->currentSubscription->auto_renew ? 'enabled' : 'disabled';
                $this->currentSubscription = $this->getActiveSubscription();
                $this->currentPlan = $this->getCurrentPlan();
                $this->isFreePlan = $this->currentPlan->isFree();
                $subscription_id = $this->currentSubscription->gateway_subscription_id;
                $default_gateway = $this->currentSubscription->default_gateway;
                $gateway = PaymentGateway::find($default_gateway);
                $class = "App\\Services\\Drivers\\{$gateway->alias}";
                if ($status === 'enabled') {
                    $class::autoRenewEnable($subscription_id);
                } else {
                    $class::autoRenewDisable($subscription_id);
                }
                Notification::make()
                    ->title('Auto-Renew ' . ucfirst($status))
                    ->body("Auto-renew has been {$status} for your current subscription.")
                    ->success()
                    ->send();

                $this->dispatch('$refresh');
            })
            ->color(fn() => $this->currentSubscription->auto_renew ? 'danger' : 'success')
            ->icon(fn() => $this->currentSubscription->auto_renew ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->tooltip(fn() => $this->currentSubscription->auto_renew ? 'Disable Auto-Renew' : 'Enable Auto-Renew');
    }
    private function textByPlan(?Plan $plan = null): ?string
    {
        if (!$plan) {
            return null;
        }

        $subscription = $this->tenant->planSubscriptions()->first();

        if (!$subscription) {
            return 'Subscribe';
        }

        if ($subscription->plan()->is($plan)) {
            return match (true) {
                $subscription->active() => 'Current Subscription',
                $subscription->canceled() => 'Re-Subscribe',
                $subscription->ended() => 'Renew Subscription',
            };
        }

        return 'Change Subscription';
    }
    public function getCurrentPlan()
    {
        return $this->currentSubscription->plan;
    }
    public function getRecommendedPlan()
    {
        return Plan::where('is_active', true)
            ->where('price', '>', $this->currentPlan->price)
            ->orderBy('sort_order', 'desc')
            ->with('features')
            ->first();
    }
    public function subscribe(int $planId, bool $main = false)
    {
        if (Filament::getTenant()->payment_methods()->count() == 0 || Filament::getTenant()->payment_methods()->first()?->stripe_payment_method_id == null) {
            return redirect(PaymentMethods::getUrl());
        }
        if (!$planId) {
            return Notification::make()
                ->title('Invalid Plan')
                ->body('The selected plan is invalid or does not exist.')
                ->danger()
                ->send();
        }

        $plan = Plan::find($planId);

        if (!$plan) {
            return Notification::make()
                ->title('Plan Not Found')
                ->body('The selected plan was not found.')
                ->danger()
                ->send();
        }
        if ($this->currentSubscription)
        {
            Event::dispatch(new BeforeChangePlan([
                "old" => $this->currentSubscription->plan,
                "new" => $plan,
                "subscription" => $this->currentSubscription,
                "team_id" => Filament::getTenant()->id
            ]));
            return call_user_func(FilamentSubscriptions::getBeforeChange(), [
                "old" => $this->currentSubscription->plan,
                "new" => $plan,
                "subscription" => $this->currentSubscription,
                "team_id" => Filament::getTenant()->id
            ]);
        }

        Event::dispatch(new BeforeSubscribePlan([
            "old" => null,
            "new" => $plan,
            "subscription" => null,
            "team_id" => Filament::getTenant()->id
        ]));
        return call_user_func(FilamentSubscriptions::getBeforeSubscription(), [
            "old" => null,
            "new" => $plan,
            "subscription" => null,
            "team_id" => Filament::getTenant()->id
        ]);
    }
    public function renewPlan($planId, bool $main = false)
    {
        $plan = Plan::find($planId);

        if (!$plan) {
            return Notification::make()
                ->title('Plan Not Found')
                ->body('The current plan was not found.')
                ->danger()
                ->send();
        }
        Event::dispatch(new BeforeRenewPlan([
            "old" => $this->currentSubscription->plan,
            "new" => $plan,
            "subscription" => $this->currentSubscription,
            "team_id" => Filament::getTenant()->id
        ]));
        return call_user_func(FilamentSubscriptions::getBeforeRenew(), [
            "old" => $this->currentSubscription->plan,
            "new" => $plan,
            "subscription" => $this->currentSubscription,
            "team_id" => Filament::getTenant()->id
        ]);
    }
}
