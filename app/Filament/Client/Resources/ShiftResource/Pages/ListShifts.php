<?php

namespace App\Filament\Client\Resources\ShiftResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Concerns\HasModuleAuthorization;
use App\Filament\Client\Resources\ShiftResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    use HasModuleAuthorization;

    protected static string $resource = ShiftResource::class;

    protected string $moduleName = 'shifts';

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
