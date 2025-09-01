<?php

namespace App\Services\Drivers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\Contracts\PaymentCurrency;
use App\Services\Contracts\PaymentGateway;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

class StripeV3 extends Driver
{
    public static function process(Payment $payment): false|string
    {
        $stripeData = $payment->gateway->gateway_parameters;
        $alias = $payment->gateway->alias;
        \Stripe\Stripe::setApiKey($stripeData['secret_key']);

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'unit_amount' => round($payment->amount + $payment->charge, 2) * 100,
                            'currency' => "$payment->method_currency",
                            'product_data' => [
                                'name' => config('app.name', 'Default Product Name'),
                                'description' => 'Payment with Stripe',
                            ]
                        ],
                        'quantity' => 1,
                    ]
                ],
                'mode' => 'payment',
                'cancel_url' => route('payment.cancel', $payment->trx),
                'success_url' => route('payments.callback', ['gateway' => $alias]) . "?session={CHECKOUT_SESSION_ID}",
            ]);

        } catch (\Exception $e) {
            $send['error'] = true;
            $send['message'] = $e->getMessage();
            return json_encode($send);
        }

        $send['redirect'] = $session->url;
        $send['session'] = $session->id;
        $send['publishable_key'] = $stripeData['publishable_key'];
        $payment->method_code = json_decode(json_encode($session))->id;
        $payment->save();
        return json_encode($send);
    }

    public static function verify(Request $request): \Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
    {
        $StripeAcc = \App\Models\PaymentGateway::where('alias', 'StripeV3')->orderBy('id', 'desc')->firstOrFail();
        $gateway_parameter = $StripeAcc->gateway_parameters;

        \Stripe\Stripe::setApiKey($gateway_parameter['secret_key']);
        $stripeSession = $request->get('session');

        $session = \Stripe\Checkout\Session::retrieve($stripeSession);

        $payment = Payment::where('method_code', $session->id)->where('status', 0)->firstOrFail();
        $payment_log = PaymentLog::where('payment_id', $payment->id)->firstOrFail();
        $payment_log->status = $session->status === 'complete' ? 1 : 2;
        $payment_log->response = $session;
        $payment_log->save();
        if ($session->status === 'complete') {
            self::paymentDataUpdate($payment);
            self::subscription($payment_log);
            return redirect($payment->success_url);
        }
        self::paymentDataUpdate($payment, true);
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
                "publishable_key" => ""
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
    public static function processIntent(Payment $payment, $customerId, $paymentMethodId): false|string
    {
        $stripe = new \Stripe\StripeClient(self::secretKey());
        try {
            $response = $stripe->paymentIntents->create([
                'amount' => (int) round(($payment->amount + $payment->charge) * 100),
                'currency' => strtolower($payment->method_currency),
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
            ]);
            if ($response->status === 'succeeded') {
                $payment->method_code = $response->id;
                self::paymentDataUpdate($payment);
            }
            $send = [
                'error' => false,
                'response' => $response,
            ];
        } catch (\Stripe\Exception\CardException $e) {
            $send = [
                'error' => true,
                'message' => $e->getError()->message,
                'code' => $e->getError()->code,
            ];
        } catch (\Exception $e) {
            $send = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        return json_encode($send);
    }

}
