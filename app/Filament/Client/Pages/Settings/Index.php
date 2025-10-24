<?php

namespace App\Filament\Client\Pages\Settings;

use App\Facades\Helper;
use App\Filament\Client\Pages\Policy;
use App\Filament\Client\Pages\Tenancy\EditTeamProfile;
use App\Filament\Client\Resources\EmployeeResource;
use App\Filament\Client\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Client\Resources\RoleResource\Pages\ListRoles;
use Kanuni\FilamentCards\Filament\Pages\CardsPage;
use Kanuni\FilamentCards\CardItem;

class Index extends CardsPage
{
    protected static ?string $slug = 'settings';
    protected static ?string $title = 'Settings';
    protected static bool $shouldRegisterNavigation = false;

    protected static function getCards(): array
    {
        $cards = [];

        $cards[] = CardItem::make(EditTeamProfile::class)
            ->title('Company')
            ->description('Manage Company Profile')
            ->icon('heroicon-o-building-office-2')
            ->group('General');

        $cards[] = CardItem::make(Admins::class)
            ->title('Admins')
            ->description('System administrators')
            ->icon('heroicon-o-bolt')
            ->group('Users');

        $cards[] = CardItem::make(CEO::class)
            ->title('CEO')
            ->description('Chief Executive Officer')
            ->icon('heroicon-o-briefcase')
            ->group('Users');
        if (Helper::has_feature('attendance')) {
            $cards[] = CardItem::make(AttendanceManagers::class)
                ->title('AMS Managers')
                ->description('Attendance Managers')
                ->icon('heroicon-o-finger-print')
                ->group('Users');
            $cards[] = CardItem::make(Policy::class)
                ->title('Attendance Policy')
                ->description('Manage Attendance Policies')
                ->icon('heroicon-o-calendar-days')
                ->group('Organization');
        }
        if (Helper::has_feature('payroll')) {
            $cards[] = CardItem::make(PayrollManagers::class)
                ->title('Payroll Managers')
                ->description('Salary Managers')
                ->icon('heroicon-o-document-currency-dollar')
                ->group('Users');
        }

        $cards[] = CardItem::make(ListEmployees::class)
            ->title('Employees')
            ->description('Employees')
            ->icon('heroicon-o-users')
            ->group('Users');

        $cards[] = CardItem::make(ListRoles::class)
            ->title('Roles And Permissions')
            ->description('Manage Roles And Permissions')
            ->icon('heroicon-o-shield-check')
            ->group('Organization');





        return $cards;
    }
}
