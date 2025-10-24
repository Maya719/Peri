<?php

namespace App\Filament\Client\Resources\RoleResource\Pages;

use App\Filament\Client\Concerns\HasModuleAuthorization;
use App\Filament\Client\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use HasModuleAuthorization;
    protected static string $resource = RoleResource::class;

    protected string $moduleName = 'roles';

    public function mount(): void
    {
        parent::mount();
        $this->authorizeModule();
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add')
                ->icon('heroicon-o-plus'),
        ];
    }
}
