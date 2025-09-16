<?php

namespace App\Livewire;

use App\Events\AfterChangePlan;
use App\Events\AfterRenewPlan;
use App\Events\AfterSubscribePlan;
use App\Facades\FilamentSubscriptions;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\Drivers\StripeV3;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Livewire\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
class ProcessPayment extends Component implements HasForms
{
    use InteractsWithForms;

    public $selectedDuration = '1_month';
    public $selectedPaymentMethod;
    public $trx;
    public $couponCode = '';
    public $payment;
    public $userIp;
    public $selectedGateway = 'StripeV3';
    public $paymentMethods = [];
    public $taxesFees;
    public $basePrice;
    public $plan;
    public $tenant;
    public $currentSubscription;
    public $planName;
    public $customer_id;


    public function mount(?string $trx = null): void
    {
        $this->trx = $trx;
        $this->payment = Payment::where('trx', $trx)->where('status', 0)->firstOrFail();
        $this->userIp = request()->ip();
        $this->basePrice = $this->payment->amount;

        // Fetch payment methods and set the default one.
        $this->customer_id = Filament::getTenant()->payment_methods()->first()?->stripe_customer_id;
        if ($this->customer_id) {
            $this->paymentMethods = StripeV3::getPaymentMethods($this->customer_id);
            $defaultMethod = collect($this->paymentMethods)->firstWhere('is_default', true);
            if ($defaultMethod) {
                $this->selectedPaymentMethod = $defaultMethod['id'];
            } elseif (!empty($this->paymentMethods)) {
                $this->selectedPaymentMethod = $this->paymentMethods[0]['id'];
            }
        }

        $this->tenant = Filament::getTenant();
        $this->currentSubscription = $this->tenant->planSubscriptions()->first();
        $this->plan = Plan::find($this->payment->model_id);
        $this->planName = $this->plan->name;
        $this->calculateFee();
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedPaymentMethod')
                ->label('Payment method')
                ->options(
                    collect($this->paymentMethods)->mapWithKeys(function ($method) {
                        $label = $method['brand'];
                        if ($method['last4']) {
                            $label .= ' •••• ' . $method['last4'];
                        }
                        if ($method['exp_month'] && $method['exp_year']) {
                            $label .= ' Expires ' . $method['exp_month'] . '/' . $method['exp_year'];
                        }
                        if ($method['is_default']) {
                            $label .= ' (Default)';
                        }
                        return [$method['id'] => $label];
                    })
                )
                ->searchable()
                ->required(),
        ];
    }
    public function afterDurationUpdate()
    {
        return $this->basePrice;
    }
    public function getTotalProperty()
    {
        $months = match ($this->selectedDuration) {
            '12_months' => 12,
            '6_months' => 6,
            '1_month' => 1,
            default => 1,
        };

        return ($this->basePrice * $months) + $this->taxesFees;
    }
    public function getExpirationDateProperty()
    {
        $months = match ($this->selectedDuration) {
            '12_months' => 12,
            '6_months' => 6,
            default => 1,
        };

        $plan = $this->plan;
        $baseDate = now();

        if ($this->currentSubscription) {
            if ($this->currentSubscription->plan_id === $plan->id && $this->currentSubscription->expires_at?->isFuture()) {
                $baseDate = $this->currentSubscription->expires_at;
            }
        }

        return $baseDate->addMonths($months)->format('F j, Y');
    }
    public function completePayment()
    {
        $data = $this->form->getState();
        $selectedGateway = PaymentGateway::where('status', 1)->where('alias', $this->selectedGateway)->first();
        $this->payment->method_name = $selectedGateway->name;
        $this->payment->method_id = $selectedGateway->id;
        $this->payment->amount = $this->afterDurationUpdate();
        $this->payment->duration = $this->selectedDuration;
        StripeV3::processIntent($this->payment, $this->customer_id, $this->selectedPaymentMethod);
        if ($this->currentSubscription) {
            if (!$this->currentSubscription->active()) {
                $this->payment->detail = "Subscription Renewed";
                $this->payment->save();
                Event::dispatch(new AfterRenewPlan([
                    "old" => $this->currentSubscription->plan,
                    "new" => $this->plan,
                    "subscription" => $this->currentSubscription
                ]));
                return call_user_func(FilamentSubscriptions::getAfterRenew(), [
                    "old" => $this->currentSubscription->plan,
                    "new" => $this->plan,
                    "subscription" => $this->currentSubscription,
                    "team_id" => Filament::getTenant()->id
                ]);
            }
            $this->payment->detail = "Subscription Changed";
            $this->payment->save();
            Event::dispatch(new AfterChangePlan([
                "old" => $this->currentSubscription->plan,
                "new" => $this->plan,
                "subscription" => $this->currentSubscription
            ]));
            return call_user_func(FilamentSubscriptions::getAfterChange(), [
                "old" => $this->currentSubscription->plan,
                "new" => $this->plan,
                "subscription" => $this->currentSubscription,
                "team_id" => Filament::getTenant()->id
            ]);
        }
        $this->payment->detail = "Subscription";
        $this->payment->save();
        Event::dispatch(new AfterSubscribePlan([
            "old" => null,
            "new" => $this->plan,
            "subscription" => null
        ]));
        return call_user_func(FilamentSubscriptions::getAfterSubscription(), [
            "old" => null,
            "new" => $this->plan,
            "subscription" => null,
            "team_id" => Filament::getTenant()->id
        ]);
    }

    public function calculateFee()
    {
        $selectedGateway = PaymentGateway::where('status', 1)->where('alias', $this->selectedGateway)->first();
        if ($selectedGateway) {
            $supportedCurrencies = $selectedGateway->supported_currencies;
            $currencyCode = $this->payment->method_currency;
            $currencyData = collect($supportedCurrencies)->firstWhere('currency', $currencyCode);

            if ($currencyData) {
                $fixedFee = (float) $currencyData['fixed_charge'];
                $percentageFee = (float) $currencyData['percent_charge'];
                $feeAmount = $fixedFee + ($this->payment->amount * $percentageFee / 100);
                $this->taxesFees = $feeAmount;
                $this->payment->taxesFees = $feeAmount;
                $this->payment->final_amount = $this->payment->amount + $feeAmount;
            }
        }
    }

    public function updatedSelectedDuration()
    {
        $this->calculateFee();
    }
    public function render()
    {
        return view('livewire.process-payment');
    }
}
