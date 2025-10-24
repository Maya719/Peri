<?php

namespace App\Filament\Client\Concerns;

use App\Facades\Helper;
use Filament\Notifications\Notification;

trait HasModuleAuthorization
{
    public function authorizeModule(): void
    {
        if (!Helper::is_module_allowed($this->moduleName)) {
            Notification::make()
                ->title('Quota Reached')
                ->body("You have reached your {$this->moduleName} creation limit. Please upgrade your plan.")
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
