<?php

namespace App\Http\Controllers\PaymentControllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Team;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Webhook;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $gateway = \App\Models\PaymentGateway::where('alias', 'stripeV3')->firstOrFail();
        \Stripe\Stripe::setApiKey($gateway->gateway_parameters['secret_key']);
        $endpoint_secret = $gateway->gateway_parameters['webhook_key'];
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            Log::error("Stripe Webhook error: " . $e->getMessage(), [
                'payload' => $payload,
                'headers' => $request->headers->all(),
            ]);
            return response()->json(['status' => 'ignored'], 200);
        }
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $invoice = $event->data->object;
                $detail = $invoice->subscription_details["metadata"];
                $team_id = $detail->team_id;
                $plan_id = $detail->plan_id;
                $team = Team::find($team_id);
                $plan = Plan::find($plan_id);
                $subscription = $team->planSubscriptions()->first();
                $team->planSubscription($subscription->slug)->renew();
                Log::info("Stripe Webhook received", [
                    'event' => $event,
                    'team' => $team,
                    'plan' => $plan,
                    'subscription' => $subscription,
                ]);
                break;
            case 'customer.subscription.updated':
                break;
        }

        return response()->json([
            'status' => 'success',
            'event'  => $event,
        ], 200);
    }
}
