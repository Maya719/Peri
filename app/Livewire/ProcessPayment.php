<?php

namespace App\Livewire;

use App\Events\{AfterChangePlan, AfterRenewPlan, AfterSubscribePlan};
use App\Facades\{FilamentPayments, FilamentSubscriptions};
use App\Filament\Components\Forms\PayPalButtons;
use App\Models\{Payment, PaymentGateway, Plan, Product};
use App\Services\Drivers\StripeV3;
use Filament\Facades\Filament;
use Filament\Forms\Components\{Radio, Select};
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\{Cache, Event, Log};
use Livewire\Component;
use Livewire\Attributes\{Computed, Locked};

class ProcessPayment extends Component implements HasForms
{
    use InteractsWithForms;

    // Locked properties to prevent tampering
    #[Locked]
    public string $trx;

    #[Locked]
    public Payment $payment;

    #[Locked]
    public Plan $plan;

    // Public properties
    public ?string $selectedPaymentMethod = null;
    public string $selectedGateway = 'Paypal';
    public array $paymentMethods = [];
    public float $taxesFees = 0;
    public float $basePrice = 0;
    public string $planName = '';
    public ?string $paypalPlan = null;
    public ?string $paypalClient = null;
    public ?string $customerId = null;
    public $tenant;
    public $currentSubscription;

    public function mount(?string $trx = null): void
    {
        $this->trx = $trx;

        // Use firstOrFail with select to reduce query load
        $this->payment = Payment::select(['id', 'trx', 'status', 'amount', 'model_id', 'method_currency'])
            ->where('trx', $trx)
            ->where('status', 0)
            ->firstOrFail();

        $this->basePrice = $this->payment->amount;
        $this->tenant = Filament::getTenant();

        // Eager load plan to reduce queries
        $this->plan = Plan::findOrFail($this->payment->model_id);
        $this->planName = $this->plan->name;

        $this->currentSubscription = $this->tenant
            ->planSubscriptions()
            ->with('plan')
            ->first();

        $this->initializePaymentGateways();
        $this->calculateFee();
    }

    protected function initializePaymentGateways(): void
    {
        // Initialize PayPal
        $paypalGateway = Cache::remember(
            "paypal_gateway_{$this->plan->id}",
            now()->addHour(),
            fn() => PaymentGateway::with(['products' => fn($q) => $q->where('plan_id', $this->plan->id)])
                ->where('alias', 'Paypal')
                ->where('status', 1)
                ->first()
        );

        if ($paypalGateway) {
            $this->paypalClient = $paypalGateway->gateway_parameters['client_id'] ?? null;
            $this->paypalPlan = $paypalGateway->products->first()?->price_id;
        }

        // Initialize Stripe payment methods
        $this->initializeStripePaymentMethods();
    }

    protected function initializeStripePaymentMethods(): void
    {
        $paymentMethod = $this->tenant
            ->payment_methods()
            ->select(['stripe_customer_id'])
            ->first();

        if (!$paymentMethod || !$paymentMethod->stripe_customer_id) {
            return;
        }

        $this->customerId = $paymentMethod->stripe_customer_id;

        try {
            $this->paymentMethods = StripeV3::getPaymentMethods($this->customerId);

            // Set default payment method
            $defaultMethod = collect($this->paymentMethods)->firstWhere('is_default', true);
            $this->selectedPaymentMethod = $defaultMethod['id'] ?? $this->paymentMethods[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Stripe payment methods', [
                'customer_id' => $this->customerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Radio::make('selectedGateway')
                ->label('Select Payment Gateway')
                ->options($this->paymentGateways)
                ->live(),

            Select::make('selectedPaymentMethod')
                ->label('Payment Method')
                ->options($this->formatPaymentMethodOptions())
                ->visible(fn() => $this->selectedGateway === 'StripeV3' && !empty($this->paymentMethods))
                ->searchable()
                ->required(),

            PayPalButtons::make('paypal_subscription_id')
                ->client($this->paypalClient)
                ->plan($this->paypalPlan)
                ->payTrx($this->trx)
                ->visible(fn() => $this->selectedGateway === 'Paypal')
        ];
    }

    protected function formatPaymentMethodOptions(): array
    {
        return collect($this->paymentMethods)->mapWithKeys(function ($method) {
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
        })->toArray();
    }

    #[Computed(persist: true)]
    public function paymentGateways(): array
    {
        return Cache::remember('payment_gateways_list', now()->addDay(), function () {
            return PaymentGateway::where('status', 1)
                ->pluck('name', 'alias')
                ->toArray();
        });
    }

    #[Computed]
    public function selectedGatewayData(): ?PaymentGateway
    {
        return Cache::remember(
            "gateway_{$this->selectedGateway}",
            now()->addHour(),
            fn() => PaymentGateway::where('status', 1)
                ->where('alias', $this->selectedGateway)
                ->first()
        );
    }

    #[Computed]
    public function total(): float
    {
        return round($this->basePrice + $this->taxesFees, 2);
    }

    public function completePayment(): void
    {
        if ($this->selectedGateway === 'Paypal') {
            return;
        }

        $this->form->getState();

        $gateway = $this->selectedGatewayData;

        if (!$gateway) {
            $this->showErrorNotification('Payment gateway not found');
            return;
        }

        // Calculate and validate fees
        $feeData = $this->calculateGatewayFees($gateway);

        if (!$feeData) {
            $this->showErrorNotification('Currency not supported');
            return;
        }

        // Update payment record
        $this->payment->update([
            'method_id' => $gateway->id,
            'method_name' => $gateway->name,
            'charge' => $feeData['fee'],
            'rate' => $feeData['rate'],
            'final_amount' => $this->basePrice + $feeData['fee'],
        ]);

        $this->processGatewayPayment($gateway);
    }

    protected function calculateGatewayFees(PaymentGateway $gateway): ?array
    {
        $currencyData = collect($gateway->supported_currencies)
            ->firstWhere('currency', $this->payment->method_currency);

        if (!$currencyData) {
            return null;
        }

        $fixedFee = (float) ($currencyData['fixed_charge'] ?? 0);
        $percentageFee = (float) ($currencyData['percent_charge'] ?? 0);
        $feeAmount = $fixedFee + ($this->payment->amount * $percentageFee / 100);

        return [
            'fee' => round($feeAmount, 2),
            'rate' => $currencyData['rate'] ?? 1,
        ];
    }

    protected function processGatewayPayment(PaymentGateway $gateway): void
    {
        $driverClass = $this->resolveDriverClass($gateway->alias);

        if (!class_exists($driverClass)) {
            $this->showErrorNotification('Payment driver not found');
            return;
        }

        try {
            if ($gateway->alias === 'StripeV3' && !empty($this->paymentMethods)) {
                $response = $driverClass::process($this->payment);
                $data = json_decode($response);

                if (isset($data->redirect)) {
                    $this->redirect($data->redirect);
                    return;
                }

                Log::error('Payment processing failed', [
                    'payment_id' => $this->payment->id,
                    'response' => $data
                ]);
            }

            $this->showErrorNotification('Payment processing failed');
        } catch (\Exception $e) {
            Log::error('Payment exception', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->showErrorNotification('An error occurred while processing your payment');
        }
    }

    protected function resolveDriverClass(string $alias): string
    {
        $drivers = config('filament-payments.drivers', []);

        foreach ($drivers as $driver) {
            if (str_contains($driver, $alias)) {
                return $driver;
            }
        }

        return "App\\Services\\Drivers\\{$alias}";
    }

    protected function showErrorNotification(string $message): void
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    protected function handleSubscriptionRenewal(): mixed
    {
        $this->payment->update(['detail' => 'Subscription Renewed']);

        Event::dispatch(new AfterRenewPlan([
            'old' => $this->currentSubscription->plan,
            'new' => $this->plan,
            'subscription' => $this->currentSubscription
        ]));

        return call_user_func(FilamentSubscriptions::getAfterRenew(), [
            'old' => $this->currentSubscription->plan,
            'new' => $this->plan,
            'subscription' => $this->currentSubscription,
            'team_id' => $this->tenant->id
        ]);
    }

    protected function handleSubscriptionChange(): mixed
    {
        $this->payment->update(['detail' => 'Subscription Changed']);

        Event::dispatch(new AfterChangePlan([
            'old' => $this->currentSubscription->plan,
            'new' => $this->plan,
            'subscription' => $this->currentSubscription
        ]));

        return call_user_func(FilamentSubscriptions::getAfterChange(), [
            'old' => $this->currentSubscription->plan,
            'new' => $this->plan,
            'subscription' => $this->currentSubscription,
            'team_id' => $this->tenant->id
        ]);
    }

    protected function handleNewSubscription(): mixed
    {
        $this->payment->update(['detail' => 'Subscription']);

        Event::dispatch(new AfterSubscribePlan([
            'old' => null,
            'new' => $this->plan,
            'subscription' => null
        ]));

        return call_user_func(FilamentSubscriptions::getAfterSubscription(), [
            'old' => null,
            'new' => $this->plan,
            'subscription' => null,
            'team_id' => $this->tenant->id
        ]);
    }

    public function calculateFee(): void
    {
        $gateway = $this->selectedGatewayData;

        if (!$gateway) {
            $this->taxesFees = 0;
            return;
        }

        $feeData = $this->calculateGatewayFees($gateway);

        if ($feeData) {
            $this->taxesFees = $feeData['fee'];
        }
    }

    public function updatedSelectedGateway(string $value): void
    {
        $this->selectedGateway = $value;
        $this->calculateFee();
        $this->dispatch('rerender-paypal-button');
    }

    public function render()
    {
        return view('livewire.process-payment');
    }
}