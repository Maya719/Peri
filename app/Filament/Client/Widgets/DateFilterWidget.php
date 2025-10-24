<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use App\Models\Role;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class DateFilterWidget extends Widget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = [
        'default' => 12,
        'md' => 12,
    ];

    public static function canView(): bool
    {
        $user = Auth::user();
        
        if (($user->hasRole('Admin') || Helper::isAssignUsers()) && $user && Helper::has_feature('attendance')) {
            return true;
        }

        return false;
    }

    protected static string $view = 'filament.widgets.date-filter-widget';

    public $date;

    public function mount()
    {
        $this->date = Session::get('dashboard_selected_date', now()->toDateString());
    }

    public function updated($property)
    {
        Session::put('dashboard_selected_date', $this->date);
        $this->dispatch('refresh-dashboard-widgets');
    }

    protected function getViewData(): array
    {
        return [
            'date' => $this->date,
        ];
    }
}
