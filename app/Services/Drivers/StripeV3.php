<?php

namespace App\Services\Drivers;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\Contracts\PaymentCurrency;
use App\Services\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Crypt;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

class StripeV3 extends Driver
{
    public static function process(Payment $payment): false|string
    {
        $stripeData = $payment->gateway->gateway_parameters;
        \Stripe\Stripe::setApiKey($stripeData['secret_key']);
        $team = Team::find($payment->team_id);
        $customer_id = $team->payment_methods()->first()?->stripe_customer_id;
        $paymentMethods = self::getPaymentMethods($customer_id);
        $defaultMethod = collect($paymentMethods)->firstWhere('is_default', true)['id'] ?? null;
        $price_id = Product::where('plan_id', $payment->model_id)->where('payment_gateway_id', $payment->method_id)->first()->price_id;
        try {
            $stripe = new \Stripe\StripeClient($stripeData['secret_key']);

            $subscription = $stripe->subscriptions->create([
                'customer' => $customer_id,
                'items' => [['price' => $price_id]],
                'default_payment_method' => $defaultMethod,
                'expand' => ['latest_invoice.payment_intent'],
                'cancel_at_period_end' => false,
                'metadata' => [
                    'plan_id' => $payment->model_id,
                    'team_id' => $payment->team_id,
                ]
            ]);
            PaymentLog::create([
                'payment_id' => $payment->id,
                'team_id' => $team->id,
                'status' => 0,
                'payload' => [
                    'customer' => $customer_id,
                    'team_id' => $payment->team_id,
                    'plan_id' => $payment->model_id,
                    'price_id' => $price_id,
                    'default_payment_method'=> $defaultMethod,
                    'cancel_at_period_end' => false,
                ],
                'response' => $stripe->subscriptions->retrieve($subscription->id),
            ]);
            $payment->method_code = $subscription->latest_invoice->id;
            $payment->save();

            $send['redirect'] = route('subscription.verify', [
                'subscription' => $subscription->id,
                'invoice' => $subscription->latest_invoice->id,
                'gateway' => 'StripeV3',
            ]);

            return json_encode($send);
        } catch (\Exception $e) {
            return json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function autoRenewEnable($subscriptionId)
    {
        $gateway = \App\Models\PaymentGateway::where('alias', 'stripeV3')->firstOrFail();
        \Stripe\Stripe::setApiKey($gateway->gateway_parameters['secret_key']);
        $result = \Stripe\Subscription::update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);
        return true;
    }
    public static function autoRenewDisable($subscriptionId):bool
    {
        $gateway = \App\Models\PaymentGateway::where('alias', 'stripeV3')->firstOrFail();
        \Stripe\Stripe::setApiKey($gateway->gateway_parameters['secret_key']);
        $result = \Stripe\Subscription::update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
        return true;
    }
    public static function verify(Request $request): \Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
    {
        dd($request->all());
        $StripeAcc = \App\Models\PaymentGateway::where('alias', 'StripeV3')->orderBy('id', 'desc')->firstOrFail();
        $gateway_parameter = $StripeAcc->gateway_parameters;
        $invoice_id = $request->get('invoice');
        $subscription = $request->get('subscription');
        \Stripe\Stripe::setApiKey($gateway_parameter['secret_key']);
        $payment = Payment::where('method_code', $invoice_id)->where('status', 0)->firstOrFail();
        try {
            $payment_log = PaymentLog::where('payment_id', $payment->id)->firstOrFail();
            $response = $payment_log->response;
            if ($response["status"] == 'active')
            {
                self::paymentDataUpdate($payment);
                $paymentLog = PaymentLog::where('payment_id', $payment->id)->where('team_id', $payment->team_id)->where('status',0)->first();
                $paymentLog->status = 1;
                $paymentLog->save();
                return redirect($payment->success_url.'?trx='.$payment->trx.'&subscription='.$subscription);
            }
        } catch (\Exception $e) {
            return redirect($payment->failed_url);
        }
        return redirect($payment->failed_url);
    }

    public static function secretKey()
    {
        $stripeAcc = \App\Models\PaymentGateway::where('alias', 'StripeV3')->orderBy('id', 'desc')->firstOrFail();
        return $stripeAcc->gateway_parameters['secret_key'];
    }
    public static function publishableKey()
    {
        $stripeAcc = \App\Models\PaymentGateway::where('alias', 'StripeV3')->orderBy('id', 'desc')->firstOrFail();
        return $stripeAcc->gateway_parameters['publishable_key'];
    }
    public static function createCustomer($email, $name)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        return $stripe->customers->create([
            'email' => $email,
            'name' => $name,
        ]);
    }
    public static function createSetupIntent($customerId)
    {
        Stripe::setApiKey(self::secretKey());
        return SetupIntent::create([
            'customer' => $customerId,
            'payment_method_types' => ['card', 'bancontact', 'sepa_debit'],
        ]);
    }
    public static function getPaymentMethods($customerId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        $customer = $stripe->customers->retrieve($customerId);
        $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;
        $methods = $stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ]);

        return collect($methods->data)->map(function ($method) use ($defaultPaymentMethodId) {
            return [
                'id' => $method->id,
                'brand' => strtoupper($method->card->brand),
                'last4' => $method->card->last4,
                'exp_month' => sprintf('%02d', $method->card->exp_month),
                'exp_year' => $method->card->exp_year,
                'is_default' => $method->id === $defaultPaymentMethodId,
            ];
        })->toArray();
    }
    public static function detachPaymentMethod($paymentMethodId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        $stripe->paymentMethods->detach($paymentMethodId);
    }
    public static function getCustomer($customerId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        return $stripe->customers->retrieve($customerId);
    }
    public static function getDefaultPaymentMethodId($customerId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        $customer = $stripe->customers->retrieve($customerId);
        return $customer->invoice_settings->default_payment_method;
    }
    public static function getAllPaymentMethods($customerId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        return $stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ]);
    }

    public static function setDefaultPaymentMethod($customerId, $paymentMethodId)
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        $stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);
    }
    public function integration(): array
    {
        return PaymentGateway::make('Stripe')
            ->alias('StripeV3')
            ->status(true)
            ->crypto(false)
            ->gateway_parameters([
                "secret_key" => "",
                "publishable_key" => "",
                "webhook_key" => ""
            ])
            ->supported_currencies([
                PaymentCurrency::make('USD')
                    ->symbol('$')
                    ->rate(1)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.2)
                    ->percent_charge(2)
                    ->toArray()
            ])
            ->toArray();
    }

    public static function createPrice(Plan $plan)
    {
        $StripeAcc = \App\Models\PaymentGateway::where('alias', 'StripeV3')->orderBy('id', 'desc')->firstOrFail();
        $gateway_parameter = $StripeAcc->gateway_parameters;

        \Stripe\Stripe::setApiKey($gateway_parameter['secret_key']);
        $price = \Stripe\Price::create([
            'currency' => 'usd',
            'unit_amount' => $plan->price * 100,
            'recurring' => ['interval' => $plan->invoice_interval,'interval_count' => $plan->invoice_period,],
            'product_data' => ['name' => $plan->name],
        ]);
        return $price;
    }
}
