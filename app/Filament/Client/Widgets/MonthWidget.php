<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget as BaseWidget;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class MonthWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = [
        'default' => 12,
        'md' => 12,
    ];

    public static function canView(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $hasAdminRole = $user->hasRole('Admin');
        $hasPayrollRole = $user->hasRole('Payroll Manager');
        $hasPayrollPermission = $user->can('payroll.manage');
        $hasAssignedUsers = Helper::isAssignUsers();
        $attendanceConfigDisabled = $user->attendance_config == 0;

        if (
            $hasAdminRole ||
            $hasPayrollRole &&
            $hasPayrollPermission ||
            $attendanceConfigDisabled ||
            $hasAssignedUsers
        ) {
            return false;
        }

        return true;
    }


    public $selectedMonth;

    protected $listeners = ['refresh-dashboard-widgets' => 'refreshStats'];

    public function mount()
    {
        $this->selectedMonth = Session::get('dashboard_selected_month', now()->format('Y-m'));
    }

    public function refreshStats()
    {
        $this->selectedMonth = Session::get('dashboard_selected_month', now()->format('Y-m'));
        $this->dispatch('$refresh');
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $user->loadMissing('assignedShift.shift', 'assignedDepartment.department');

        $month = $this->selectedMonth ?? now()->format('Y-m');
        $startDate = Carbon::parse("{$month}-01")->startOfDay()->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        // Fetch holidays
        $holidays = Filament::getTenant()->holidays()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('starting_date', [$startDate, $endDate])
                    ->orWhereBetween('ending_date', [$startDate, $endDate])
                    ->orWhereRaw('? BETWEEN starting_date AND ending_date', [$startDate]);
            })
            ->where(function ($query) use ($user) {
                $query->where('apply', 'all')
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('apply', 'user')->whereJsonContains('users', (string) $user->id);
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('apply', 'shift')->whereJsonContains('shifts', (string) $user->assignedShift?->shift?->id);
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('apply', 'department')->whereJsonContains('departments', (string) $user->assignedDepartment?->department?->id);
                    });
            })
            ->get();

        // Build array of all holiday dates
        $holidayDates = collect();
        foreach ($holidays as $holiday) {
            $hStart = Carbon::parse($holiday->starting_date)->startOfDay();
            $hEnd = Carbon::parse($holiday->ending_date)->endOfDay();
            for ($date = $hStart->copy(); $date->lte($hEnd); $date->addDay()) {
                $holidayDates->push($date->toDateString());
            }
        }

        // Calculate working days (excluding holidays)
        $workingDays = 0;
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (! $holidayDates->contains($date->toDateString())) {
                $workingDays++;
            }
        }

        // Attendance records
        $attendances = DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereBetween('finger', [$startDate, $endDate])
            ->orderBy('finger')
            ->get();

        $presentDates = $attendances->pluck('finger')
            ->map(fn($finger) => Carbon::parse($finger)->toDateString())
            ->unique()
            ->reject(fn($date) => $holidayDates->contains($date)); // Exclude holiday dates

        $presentCountUser = $presentDates->count();

        // Leaves
        $leaves = DB::table('leaves')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('type', 'regular')
            ->whereDate('starting_date', '<=', $endDate)
            ->whereDate('ending_date', '>=', $startDate)
            ->get();

        $leaveCountUser = 0;
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->starting_date)->greaterThan($startDate) ? Carbon::parse($leave->starting_date) : $startDate;
            $end = Carbon::parse($leave->ending_date)->lessThan($endDate) ? Carbon::parse($leave->ending_date) : $endDate;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if (! $holidayDates->contains($date->toDateString())) {
                    $leaveCountUser++;
                }
            }
        }

        // Late count
        $lateCountUser = 0;
        foreach ($presentDates as $dateStr) {
            $firstBiometric = $attendances->first(fn($a) => Carbon::parse($a->finger)->toDateString() === $dateStr);
            if ($firstBiometric) {
                $shiftStart = Carbon::parse($dateStr . ' ' . $user->assignedShift?->shift?->starting_time);
                $punchTime = Carbon::parse($firstBiometric->finger);
                if ($punchTime->gt($shiftStart)) {
                    $lateCountUser++;
                }
            }
        }

        // Calculate absent
        $effectiveWorkingDays = 0;
        $today = now()->toDateString();
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (
                ! $holidayDates->contains($date->toDateString()) &&
                $date->toDateString() <= $today
            ) {
                $effectiveWorkingDays++;
            }
        }

        // Attendance by date (grouped by day)
        $attendanceByDate = $attendances->groupBy(fn($att) => Carbon::parse($att->finger)->toDateString());

        $missingHoursUser = 0;

        if (in_array($user->attendance_type, ['offsite', 'hybrid'])) {
            // Calculate missing hours for each day
            foreach ($attendanceByDate as $date => $records) {
                // Skip holidays
                if ($holidayDates->contains($date)) {
                    continue;
                }

                // Sort attendance times for the day
                $times = $records->pluck('finger')->map(fn($ts) => Carbon::parse($ts))->sort()->values();

                // Only proceed if there are at least two records (IN and OUT)
                if ($times->count() >= 2) {
                    $firstTime = $times->first();
                    $lastTime = $times->last();

                    // Calculate the total hours worked on that day
                    $totalMinutes = $firstTime->diffInMinutes($lastTime);

                    // Calculate the missing hours based on required hours
                    $requiredHours = (float) $user->hours_required;
                    $workedHours = $totalMinutes / 60; // Convert to hours
                    $missingHoursUser += round(max($requiredHours - $workedHours, 0), 2);
                }
            }
        }

        $absentCountUser = max((int) ($effectiveWorkingDays - $presentCountUser - $leaveCountUser), 0);

        $stats = [
            Stat::make('', (string) $workingDays)
                ->description("Working Days")
                ->backgroundColor('info')
                ->extraAttributes(['class' => 'stat-total']),

            Stat::make('', (string) $presentCountUser)
                ->description("Present")
                ->backgroundColor('success')
                ->extraAttributes(['class' => 'stat-present']),

            Stat::make('', (string) $leaveCountUser)
                ->description("Leaves")
                ->backgroundColor('warning')
                ->extraAttributes(['class' => 'stat-leave']),

            Stat::make('', (string) $absentCountUser)
                ->description("Absent")
                ->backgroundColor('danger')
                ->extraAttributes(['class' => 'stat-absent']),
        ];

        // Add either Late Days or Missing Hours based on attendance type
        if ($user->attendance_type === 'onsite') {
            $stats[] = Stat::make('', (string) $lateCountUser)
                ->description("Late Days")
                ->extraAttributes(['class' => 'stat-late']);
        } elseif (in_array($user->attendance_type, ['offsite', 'hybrid'])) {
            $stats[] = Stat::make('', (string) $missingHoursUser)
                ->description("Missing Hours")
                ->extraAttributes(['class' => 'stat-late']);
        }

        return $stats;
    }
}
