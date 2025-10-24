<?php

namespace App\Filament\Client\Resources\DeviceResource\Pages;

use App\Filament\Client\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;
    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function afterCreate(): void
    {
        \App\Facades\Helper::record_feature_usage('biometric-devices');
    }
}
