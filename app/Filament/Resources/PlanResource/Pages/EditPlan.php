<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\PaymentGateway;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function afterSave(): void
    {
        $gateways = PaymentGateway::where('status', 1)->get();
        foreach ($gateways as $gateway){
            $new = "App\\Services\\Drivers\\{$gateway->alias}";
            $data = $new::createPrice($this->record);
            if ($data){
                $product = Product::where('payment_gateway_id', $gateway->id)->where('plan_id',$this->record->id)->first();
                $product->price_id = $data->id;
                $product->product_id = $data->product;
                $product->save();
            }
        }
    }
}
