<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Models\PaymentGateway;
use App\Filament\Resources\PlanResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function afterCreate(): void
    {
        $gateways = PaymentGateway::where('status', 1)->get();
        foreach ($gateways as $gateway){
            $new = "App\\Services\\Drivers\\{$gateway->alias}";
            $data = $new::createPrice($this->record);
            if ($data){
                Product::create([
                    'plan_id' => $this->record->id,
                    'payment_gateway_id' => $gateway->id,
                    'alias' => $gateway->alias,
                    'price_id' => $data->id,
                    'product_id' => $data->product,
                ]);
            }
        }
    }
}
