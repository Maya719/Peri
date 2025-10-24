<?php

namespace App\Filament\Client\Resources\DepartmentResource\Pages;

use App\Filament\Client\Resources\DepartmentResource;
use App\Filament\Client\Concerns\HasModuleAuthorization;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    use HasModuleAuthorization;

    protected string $moduleName = 'departments';

    public function mount(): void
    {
        parent::mount();
        $this->authorizeModule();
    }
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add')
                ->icon('heroicon-o-plus'),
        ];
    }
}
