<?php

namespace App\Filament\Client\Resources\DeviceResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Concerns\HasModuleAuthorization;
use App\Filament\Client\Resources\DeviceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    use HasModuleAuthorization;
    
    protected string $moduleName = 'departments';

    public function mount(): void
    {
        parent::mount();
        $this->authorizeModule();
    }
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add')
                ->icon('heroicon-o-plus'),
        ];
    }
}
