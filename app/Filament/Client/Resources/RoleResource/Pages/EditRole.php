<?php

namespace App\Filament\Client\Resources\RoleResource\Pages;

use App\Filament\Client\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $subscription = $tenant->activePlanSubscriptions()->first();
                    if ($subscription) {
                        $subscription->reduceFeatureUsage('roles');
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['assigned_users'])) {
            $data['assigned_users'] = collect($data['assigned_users'])->map(fn($id) => (int) $id)->toArray();
        }

        return $data;
    }
}
