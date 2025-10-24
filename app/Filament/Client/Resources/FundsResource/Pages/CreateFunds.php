<?php

namespace App\Filament\Client\Resources\FundsResource\Pages;

use App\Filament\Client\Resources\FundsResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFunds extends CreateRecord
{
    protected static string $resource = FundsResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function afterCreate(): void
    {
        \App\Facades\Helper::record_feature_usage('funds');
    }
}
