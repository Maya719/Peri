<?php

namespace App\Filament\Client\Resources\ShiftResource\Pages;

use App\Filament\Client\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;
    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function afterCreate(): void
    {
        \App\Facades\Helper::record_feature_usage('shifts');
    }
}
