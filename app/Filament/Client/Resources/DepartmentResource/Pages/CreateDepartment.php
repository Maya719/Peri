<?php

namespace App\Filament\Client\Resources\DepartmentResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function afterCreate(): void {
        Helper::record_feature_usage('departments');
    }
}
