<?php

namespace App\Livewire;

use App\Filament\Client\Pages\Subscriptions\PaymentMethods;
use App\Services\Drivers\StripeV3;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Filament\Facades\Filament;

class AddPaymentMethod extends Component
{
    public ?string $clientSecret = null;
    public ?string $clientPublish = null;
    public ?string $redirectUrl = null;
    public ?string $customer_id = null;
    public ?string $default_payment_method = null;
    public ?string $default_payment_method_id = null;

    public function mount(StripeV3 $stripe): void
    {
        Stripe::setApiKey($stripe->secretKey());
        $this->clientPublish = $stripe->publishableKey();

        $tenant = Filament::getTenant();
        $this->customer_id = $tenant->payment_methods()->where('type', 'stripe')->first()?->stripe_customer_id;

        if (!$this->customer_id) {
            $customer = \Stripe\Customer::create([
                'email' => Auth::user()->email ?? null,
                'name' => Auth::user()->name ?? null,
            ]);

            $this->customer_id = $customer->id;
            $tenant->payment_methods()->create(['stripe_customer_id' => $this->customer_id, 'type' => 'stripe']);
        }

        try {
            $intent = SetupIntent::create([
                'customer' => $this->customer_id,
                'payment_method_types' => ['card', 'bancontact', 'sepa_debit'],
            ]);

            $this->clientSecret = $intent->client_secret;

            $this->js(<<<JS
                dispatchEvent(new CustomEvent('stripeSetupIntent', {
                    detail: {
                        client_secret: '{$this->clientSecret}'
                    }
                }))
            JS);
        } catch (\Exception $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Something went wrong, please try again later.');
        }
    }

    public function savePaymentMethod(string $id)
    {
        $tenant = Filament::getTenant();
        $tenant->payment_methods()->update(['stripe_payment_method_id' => $id]);
        $this->makeDefault($id);
        Notification::make()
            ->success()
            ->title('Payment Method Added')
            ->body('Your payment method has been added successfully.')
            ->send();
        return redirect(PaymentMethods::getUrl());
    }
    public function makeDefault($paymentMethodId)
    {
        $stripe = new \Stripe\StripeClient(StripeV3::secretKey());

        $stripe->customers->update($this->customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);

        $this->default_payment_method = $paymentMethodId;
    }
    public function render()
    {
        return view('livewire.add-payment-method');
    }
}