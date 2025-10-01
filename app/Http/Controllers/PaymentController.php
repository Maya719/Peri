<?php

namespace App\Http\Controllers;

use App\Events\AfterChangePlan;
use App\Events\AfterRenewPlan;
use App\Events\AfterSubscribePlan;
use App\Facades\FilamentSubscriptions;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function cancel($trx)
    {
        $payment = Payment::where('trx', $trx)->where('status', 0)->firstOrFail();
        // Update Status
        $payment->status = 2;
        $payment->save();
        return redirect($payment->failed_url);
    }
    public function success(Request $request)
    {
        $trx = $request->query('trx');
        $gateway_subscription = $request->query('subscription');
        $payment = Payment::where('trx', $trx)->firstOrFail();
        $tenant = Team::find($payment->team_id);
        $plan = Plan::find($payment->model_id);
        $old = $tenant->planSubscriptions()->first()?->plan;
        $subscription = $tenant->planSubscriptions()->first();
        if ($subscription)
        {
            if ($plan->id == $old->id)
            {
                Event::dispatch(new AfterRenewPlan([
                    "old" => null,
                    "new" => $plan,
                    "subscription" => $subscription,
                    "gateway_subscription" => $gateway_subscription,
                    "team_id" => $tenant->id
                ]));
                return call_user_func(FilamentSubscriptions::getAfterRenew(), [
                    "old" => null,
                    "new" => $plan,
                    "subscription" => $subscription,
                    "gateway_subscription" => $gateway_subscription,
                    "team_id" => $tenant->id
                ]);
            }
            Event::dispatch(new AfterChangePlan([
                "old" => $old,
                "new" => $plan,
                "subscription" => $subscription,
                "gateway_subscription" => $gateway_subscription,
                "team_id" => $tenant->id
            ]));
            return call_user_func(FilamentSubscriptions::getAfterChange(), [
                "old" => $old,
                "new" => $plan,
                "subscription" => $subscription,
                "gateway_subscription" => $gateway_subscription,
                "team_id" => $tenant->id
            ]);
        }
        Event::dispatch(new AfterSubscribePlan([
            "old" => null,
            "new" => $plan,
            "subscription" => $subscription,
            "gateway_subscription" => $gateway_subscription,
            "team_id" => $tenant->id
        ]));
        return call_user_func(FilamentSubscriptions::getAfterSubscription(), [
            "old" => null,
            "new" => $plan,
            "subscription" => $subscription,
            "gateway_subscription" => $gateway_subscription,
            "team_id" => $tenant->id
        ]);
    }

    public function initiate(Request $request)
    {
        $rules = [
            'public_key' => 'required|string|max:50',
            'plan_id' => 'required|integer|exists:plans,id',
            'currency' => 'required|string|size:3|uppercase|in:USD',
            'amount' => 'required|numeric|min:1',
            'details' => 'required|string|max:100',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',

            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email',
            'customer.mobile' => 'required|string|max:20',
            'customer.tenant' => 'required|string|max:20',
        ];

        $validator = Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $team = Team::where('public_key', $validated['public_key'])->where('status', 1)->first();

        $team = Team::where('public_key', $validated['public_key'])->first();

        if (!$team) {
            return response()->json([
                'error' => 'Invalid public key'
            ], 400);
        }

        $requestHost = $request->getHost(); // Gets the host (e.g., 'example.com')

        if ($team->website !== $requestHost) {
            return response()->json([
                'error' => 'Website does not match the request origin',
            ], 400);
        }

        if ($team->status === 1) {
            return response()->json([
                'error' => 'Website is inactive'
            ], 400);
        }

        // Create the Payment
        $payment = Payment::create([
            'team_id' => $team->id,
            'plan_id' => $validated['plan_id'],
            'method_currency' => $validated['currency'],
            'amount' => $validated['amount'],
            'detail' => $validated['details'],
            'trx' => Str::random(22),
            'status' => 0,
            'from_api' => true,
            'success_url' => $validated['success_url'],
            'failed_url' => $validated['cancel_url'],
            'customer' => $validated['customer'],
        ]);

        return response()->json(['status' => 'success', 'message' => 'Payment created successfully', 'data' => [
            'id' => $payment->trx,
            'url' => route('payment.index', $payment->trx),
        ]], 201);
    }

    public function info(Request $request)
    {
        $rules = [
            'public_key' => 'required|string|max:50',
            'id' => 'required|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $team = Team::where('public_key', $validated['public_key'])->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found'
            ], 404);
        }

        $payment = Payment::where('team_id', $team->id)->where('trx', $validated['id'])->first();

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found'
            ], 404);
        }

        $status = 'unknown';

        switch ($payment->status) {
            case 0:
                $status = 'processing';
                break;
            case 1:
                $status = 'completed';
                break;
            case 2:
                $status = 'cancelled';
                break;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'status' => $status,
                'currency' => $payment->method_currency,
                'amount' => Number::trim($payment->amount),
                'success_url' => $payment->success_url,
                'cancel_url' => $payment->failed_url,
                'customer' => $payment->customer,
                'shipping_info' => $payment->shipping_info,
                'billing_info' => $payment->billing_info
            ]
        ]);
    }

    public function verify(Request $request, string $gatewayAlias)
    {
        $gateway = \App\Models\PaymentGateway::where('alias', $gatewayAlias)->firstOrFail();
        $driverClass = config('filament-payments.path') . "\\".$gateway->alias;

        if (!class_exists($driverClass)) {
            $drivers = config('filament-payments.drivers');
            foreach ($drivers as $driver) {
                if (str($driver)->contains($gateway->alias)) {
                    $driverClass = $driver;
                    break;
                }
            }
        }
        if (!class_exists($driverClass)) {
            abort(404, 'Payment gateway driver not found.');
        }

        $driverInstance = new $driverClass($gateway);

        return $driverInstance->verify($request);
    }

    protected function handleSubscriptionRenewal($currentSubscription, $plan, $team)
    {
        Event::dispatch(new AfterRenewPlan([
            "old" => $currentSubscription->plan,
            "new" => $plan,
            "subscription" => $currentSubscription,
            "team_id" => $team->id
        ]));

        return call_user_func(FilamentSubscriptions::getAfterRenew(), [
            "old" => $currentSubscription->plan,
            "new" => $plan,
            "subscription" => $currentSubscription,
            "team_id" => $team->id
        ]);
    }

    protected function handleSubscriptionChange($currentSubscription, $plan, $team)
    {
        Event::dispatch(new AfterChangePlan([
            "old" => $currentSubscription->plan,
            "new" => $plan,
            "subscription" => $currentSubscription,
            "team_id" => $team->id
        ]));

        return call_user_func(FilamentSubscriptions::getAfterChange(), [
            "old" => $currentSubscription->plan,
            "new" => $plan,
            "subscription" => $currentSubscription,
            "team_id" => $team->id
        ]);
    }

    protected function handleNewSubscription($currentSubscription, $plan, $team)
    {
        Event::dispatch(new AfterSubscribePlan([
            "old" => null,
            "new" => $plan,
            "subscription" => null,
            "team_id" => $team->id
        ]));

        return call_user_func(FilamentSubscriptions::getAfterSubscription(), [
            "old" => null,
            "new" => $plan,
            "subscription" => null,
            "team_id" => $team->id
        ]);
    }

    public function paypalSubscriptionApproved(Request $request)
    {
        $payment = Payment::where('trx', $request->get('trx'))->firstOrFail();
        $tenant = Team::find($payment->team_id);
        $plan = Plan::find($request->get('plan_id'));
        $subscription = $tenant->planSubscriptions()->first();
        $gateway_subscription = $request->get('subscription_id');

        if ($subscription) {
            if ($plan->id == $subscription->plan->id) {
                $this->handleSubscriptionRenewal($subscription, $plan, $tenant);
            } else {
                $this->handleSubscriptionChange($subscription, $plan, $tenant);
            }
        } else {
            $this->handleNewSubscription($subscription, $plan, $tenant);
        }

        $payment->status = 1;
        $payment->save();

        return response()->json(['redirect_url' => route('filament.client.pages.dashboard')]);
    }
}
