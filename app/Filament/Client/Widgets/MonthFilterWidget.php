<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use App\Models\Role;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MonthFilterWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'md' => 12,
    ];

    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user || !Helper::has_feature('attendance')) {
            return false;
        }
        if (($user->hasRole('Admin') || Helper::isAssignUsers()) && $user) {
            return false;
        }

        return true;
    }

    protected static string $view = 'filament.widgets.month-filter-widget';

    public $selectedMonth;

    public function mount()
    {
        // Default to current month (YYYY-MM)
        $this->selectedMonth = Session::get('dashboard_selected_month', now()->format('Y-m'));
    }

    public function updated($property)
    {
        Session::put('dashboard_selected_month', $this->selectedMonth);
        $this->dispatch('refresh-dashboard-widgets');
    }

    protected function getViewData(): array
    {
        return [
            'selectedMonth' => $this->selectedMonth,
        ];
    }
}
