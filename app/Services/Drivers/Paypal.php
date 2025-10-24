<?php

namespace App\Services\Drivers;

use App\Models\PaymentLog;
use App\Models\Plan;
use Exception;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\Contracts\PaymentCurrency;
use App\Services\Contracts\PaymentGateway;
use GuzzleHttp\Client;

class Paypal extends Driver
{
    public static function process(Payment $payment): false|string
    {
        return false;
    }
    protected static function getSubscription($subscriptionId)
    {
        [$accessToken, $baseUrl] = self::getAccessToken();
        $client = new Client(['base_uri' => $baseUrl]);

        $response = $client->get("/v1/billing/subscriptions/{$subscriptionId}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }


    public static function verify(Request $request): \Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
    {
        $subscriptionId = $request->input('subscription_id');
        $trx = $request->input('trx');
        $payment = Payment::where('trx', $trx)->where('status', 0)->firstOrFail();
        $subscription = self::getSubscription($subscriptionId);
        if ($subscription['status'] === 'ACTIVE') {
            $paypalAcc = \App\Models\PaymentGateway::where('alias', 'PayPal')
                ->orderBy('id', 'desc')
                ->firstOrFail();
            $payment->method_code = $subscriptionId;
            $payment->method_id = $paypalAcc->id;
            $payment->method_name = $paypalAcc->name;
            $payment->save();
            self::paymentDataUpdate($payment);
            return redirect($payment->success_url . '?trx=' . $payment->trx . '&subscription=' . $subscriptionId);
        }
        return redirect($payment->failed_url);
    }
    public function integration(): array
    {
        return PaymentGateway::make('Paypal')
            ->alias('Paypal')
            ->status(true)
            ->crypto(false)
            ->gateway_parameters([
                "client_id" => "",
                "secret" => "",
                "mode" => ""
            ])
            ->supported_currencies([
                PaymentCurrency::make('USD')
                    ->symbol('$')
                    ->rate(1)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.2)
                    ->percent_charge(2.9)
                    ->toArray(),

                PaymentCurrency::make('EUR')
                    ->symbol('€')
                    ->rate(0.93)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.35)
                    ->percent_charge(2.9)
                    ->toArray(),

                PaymentCurrency::make('GBP')
                    ->symbol('£')
                    ->rate(0.75)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.35)
                    ->percent_charge(2.9)
                    ->toArray(),

                PaymentCurrency::make('AUD')
                    ->symbol('A$')
                    ->rate(1.50)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.30)
                    ->percent_charge(2.6)
                    ->toArray(),

                PaymentCurrency::make('CAD')
                    ->symbol('C$')
                    ->rate(1.35)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.30)
                    ->percent_charge(2.9)
                    ->toArray(),

                PaymentCurrency::make('JPY')
                    ->symbol('¥')
                    ->rate(140)
                    ->minimum_amount(100)
                    ->maximum_amount(100000)
                    ->fixed_charge(40)
                    ->percent_charge(3.6)
                    ->toArray(),

                PaymentCurrency::make('CNY')
                    ->symbol('¥')
                    ->rate(7.00)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(2.50)
                    ->percent_charge(2.9)
                    ->toArray(),

                PaymentCurrency::make('INR')
                    ->symbol('₹')
                    ->rate(83)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(3.00)
                    ->percent_charge(3.0)
                    ->toArray(),

                PaymentCurrency::make('BRL')
                    ->symbol('R$')
                    ->rate(5.20)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(0.60)
                    ->percent_charge(3.4)
                    ->toArray(),

                PaymentCurrency::make('MXN')
                    ->symbol('$')
                    ->rate(18.00)
                    ->minimum_amount(1)
                    ->maximum_amount(1000)
                    ->fixed_charge(4.00)
                    ->percent_charge(3.0)
                    ->toArray(),
            ])
            ->toArray();
    }
    protected static function getClientConfig()
    {
        $paypalAcc = \App\Models\PaymentGateway::where('alias', 'PayPal')
            ->orderBy('id', 'desc')
            ->firstOrFail();

        $gateway_parameter = $paypalAcc->gateway_parameters;

        $clientId = $gateway_parameter['client_id'];
        $secret = $gateway_parameter['secret'];
        $baseUrl = $gateway_parameter['mode'] === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        return compact('clientId', 'secret', 'baseUrl');
    }

    protected static function getAccessToken()
    {
        extract(self::getClientConfig());

        $client = new Client(['base_uri' => $baseUrl]);

        $response = $client->post('/v1/oauth2/token', [
            'auth' => [$clientId, $secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $data = json_decode($response->getBody(), true);

        return [$data['access_token'], $baseUrl];
    }

    public static function createPrice(Plan $plan)
    {
        [$accessToken, $baseUrl] = self::getAccessToken();
        $client = new Client(['base_uri' => $baseUrl]);

        // 1️⃣ Create Product
        $productResponse = $client->post('/v1/catalogs/products', [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => $plan->name,
                'description' => $plan->description ?? 'Subscription Plan',
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ],
        ]);

        $productData = json_decode($productResponse->getBody(), true);
        $productId = $productData['id'];

        // 2️⃣ Create Plan
        $planResponse = $client->post('/v1/billing/plans', [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'product_id' => $productId,
                'name' => $plan->name . ' Plan',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => $plan->invoice_interval === 'month' ? 'MONTH' : 'YEAR',
                            'interval_count' => $plan->invoice_period,
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => $plan->price,
                                'currency_code' => $plan->currency ?? 'USD',
                            ],
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 1,
                ],
            ],
        ]);

        $planData = json_decode($planResponse->getBody(), true);
        $planId = $planData['id'];
        return (object) [
            'id' => $planId,
            'product' => $productId,
        ];
    }
    public static function autoRenewEnable($subscriptionId): bool
    {
        [$accessToken, $baseUrl] = self::getAccessToken();
        $client = new Client(['base_uri' => $baseUrl]);
        $subscription = self::getSubscription($subscriptionId);
        if ($subscription['status'] == 'SUSPENDED') {
            $response = $client->post("/v1/billing/subscriptions/{$subscriptionId}/activate", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'reason' => "Reactivating on customer request"
                ],
            ]);
            return true;
        }
        return false;
    }

    public static function autoRenewDisable($subscriptionId): bool
    {
        [$accessToken, $baseUrl] = self::getAccessToken();
        $client = new Client(['base_uri' => $baseUrl]);
        $subscription = self::getSubscription($subscriptionId);
        if ($subscription['status'] == 'ACTIVE') {
            $response = $client->post("/v1/billing/subscriptions/{$subscriptionId}/suspend", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'reason' => 'Customer-requested pause',
                ],
            ]);
            return true;
        }
        return false;
    }
    public static function updatePrice(Plan $plan)
    {
        return false;
    }
}
