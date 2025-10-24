<?php

namespace App\Filament\Client\Resources\RoleResource\Pages;

use App\Filament\Client\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function afterCreate(): void
    {
        \App\Facades\Helper::record_feature_usage('roles');
    }
}
