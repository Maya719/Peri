<?php

namespace App\Filament\Client\Resources\DeviceResource\Pages;

use App\Filament\Client\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $subscription = $tenant->activePlanSubscriptions()->first();
                    if ($subscription) {
                        $subscription->reduceFeatureUsage('biometric-devices');
                    }
                }),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
