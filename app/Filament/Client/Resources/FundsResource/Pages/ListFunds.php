<?php

namespace App\Filament\Client\Resources\FundsResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Concerns\HasModuleAuthorization;
use App\Filament\Client\Resources\FundsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFunds extends ListRecords
{
    use HasModuleAuthorization;
    protected static string $resource = FundsResource::class;

    protected string $moduleName = 'funds';

    public function mount(): void
    {
        parent::mount();
        $this->authorizeModule();
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add')
                ->icon('heroicon-o-plus'),
        ];
    }
}
