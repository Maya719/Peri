<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class PayPalController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        \Log::info('PayPal Webhook:', $payload);
        // Example: subscription renewal event
        if (isset($payload['event_type']) && $payload['event_type'] === 'PAYMENT.SALE.COMPLETED') {
            $resource = $payload['resource'];
            $parent_payment = $resource['parent_payment'] ?? null;
        }

        return response()->json(['status' => 'ok']);
    }
}
