<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceTableExport;
use App\Facades\Helper;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;
use Illuminate\Support\Facades\Auth;

class Attendance extends Page implements HasKnowledgeBase
{
    protected static ?string $navigationGroup = 'Attendance Management';
    protected static ?string $navigationLabel = 'Attendance';
    protected static string $view = 'filament.client.pages.attendance';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') || $user->attendance_config == 1 || $user->hasRole('CEO') || $user->hasRole('AMS Manager'))  && Helper::has_feature('attendance');
    }

    public int | string $perPage = 10;

    public static function getDocumentation(): array
    {
        return [
            'attendance.introduction',
            KnowledgeBase::model()::find('attendance.working'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Attendance')
                ->icon('heroicon-s-arrow-down-tray')
                ->tooltip('Export as Excel File')
                ->action('export')
                ->color('primary'),
        ];
    }

    public function export()
    {
        // $this->attendanceService = app(AppService::class);
        $policy = Helper::policy();
        $from = $this->data['fromDate'] ?? now()->startOfMonth()->toDateString();
        $to = $this->data['toDate'] ?? now()->toDateString();
        $userId = $this->data['userId'] ?? '';
        $attendanceType = $this->data['attendance_type'] ?? '';
        $departmentId = $this->data['department_id'] ?? '';
        $shiftId = $this->data['shift_id'] ?? '';

        $users = Helper::GetAttendanceUsers(
            $userId,
            $attendanceType,
            $departmentId,
            $shiftId,
            $from,
            $to,
            $this->perPage
        );

        $attendances = Helper::GetAttendanceWithinDateRange($from, $to, $userId);

        $userAttendanceData = $this->buildAttendanceData($users, $attendances, $from, $to, $policy);

        return Excel::download(new AttendanceTableExport($userAttendanceData, $users), 'attendance_table.xlsx');
    }
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return Auth::check() && (Auth::user()->hasRole('Admin') || $user->attendance_config == 1 || $user->hasRole('CEO') || $user->hasRole('AMS Manager')) && Helper::has_feature('attendance');
    }
    public function buildAttendanceData($users, $attendances, $from, $to, $policy)
    {
        return Helper::buildAttendanceData($users, $attendances, $from, $to, $policy);
    }
}
