<?php

namespace App\Filament\Client\Resources\FundsResource\Pages;

use App\Filament\Client\Resources\FundsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFunds extends EditRecord
{
    protected static string $resource = FundsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $subscription = $tenant->activePlanSubscriptions()->first();
                    if ($subscription) {
                        $subscription->reduceFeatureUsage('funds');
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
