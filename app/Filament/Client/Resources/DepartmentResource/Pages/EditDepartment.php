<?php

namespace App\Filament\Client\Resources\DepartmentResource\Pages;

use App\Filament\Client\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $subscription = $tenant->activePlanSubscriptions()->first();
                    if ($subscription) {
                        $subscription->reduceFeatureUsage('departments');
                    }
                }),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
