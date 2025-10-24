<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use Carbon\Carbon;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget as BaseWidget;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class DaysWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = [
        'default' => 12,
        'md' => 12,
    ];

    public $date;

    protected $listeners = ['refresh-dashboard-widgets' => 'refreshStats'];

    public static function canView(): bool
    {
        $user = Auth::user();
        if (($user->hasRole('Admin') || Helper::isAssignUsers()) && $user && Helper::has_feature('attendance')) {
            return true;
        }
        return false;
    }

    public function mount()
    {
        $this->date = Session::get('dashboard_selected_date', now()->toDateString());
    }

    public function refreshStats()
    {
        $this->date = Session::get('dashboard_selected_date', now()->toDateString());
        $this->dispatch('$refresh');
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // Parse the selected date (fallback to today)
        $date = Carbon::parse($this->date ?? now())->startOfDay();
        $endDate = $date->copy()->endOfDay();

        // Active users
        $activeUsers = Filament::getTenant()->filteredUsers()
            ->where('active', 1)
            ->where('attendance_config', 1)
            ->get();

        // Leaves on that day
        $leaves = Filament::getTenant()->leaves()
            ->where('type', 'regular')
            ->where('status', 'approved')
            ->whereDate('starting_date', '<=', $endDate)
            ->whereDate('ending_date', '>=', $date)
            ->get();

        // "Absent" if there's no punch-in
        $absentCount = 0;
        foreach ($activeUsers as $activeUser) {
            $hasPunch = DB::table('attendances')
                ->where('user_id', $activeUser->id)
                ->whereBetween('finger', [$date, $endDate])
                ->exists();

            $onLeave = $leaves->where('user_id', $activeUser->id)->isNotEmpty();
            if (!$hasPunch && !$onLeave) {
                $absentCount++;
            }
        }

        // Calculate Late
        $lateCount = 0;
        foreach ($activeUsers as $activeUser) {
            $onLeave = $leaves->where('user_id', $activeUser->id)->isNotEmpty();

            // Only consider lateness for users who are not on leave AND have a biometric punch
            $firstBiometric = DB::table('attendances')
                ->where('user_id', $activeUser->id)
                ->whereBetween('finger', [$date, $endDate])
                ->orderBy('finger', 'asc')
                ->first();

            if ($firstBiometric && !$onLeave) {
                $shiftLog = Helper::getUserShiftLog($activeUser, $date);
                if ($shiftLog) {
                    $shiftStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shiftLog->starting_time);
                    $punchTime = Carbon::parse($firstBiometric->finger);

                    if ($punchTime->gt($shiftStart)) {
                        $lateCount++;
                    }
                }
            }
        }

        $leaveCount = $leaves->count();
        $totalUsers = $activeUsers->count();
        $presentCount = $totalUsers - $leaveCount - $absentCount;

        $showAllUsers = $user->hasRole('Admin') || Helper::isAssignUsers();

        //Non-admin stats
        $startDate = $date->copy()->startOfMonth();
        $endDateNonAdmin = $date->copy()->endOfMonth();

        // Get holidays in the range
        $holidays = Filament::getTenant()->holidays()
            ->where(function ($query) use ($startDate, $endDateNonAdmin) {
                $query->whereBetween('starting_date', [$startDate, $endDateNonAdmin])
                    ->orWhereBetween('ending_date', [$startDate, $endDateNonAdmin])
                    ->orWhereRaw('? BETWEEN starting_date AND ending_date', [$startDate]);
            })
            ->where(function ($query) use ($user, $startDate) {
                $query->where('apply', 'all')
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('apply', 'user')
                            ->whereJsonContains('users', (string) $user->id);
                    })
                    ->orWhere(function ($sub) use ($user, $startDate) {
                        $userShift = Helper::getUserShiftLog($user, $startDate);
                        if ($userShift) {
                            $sub->where('apply', 'shift')
                                ->whereJsonContains('shifts', (string) $userShift->id);
                        }
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('apply', 'department')
                            ->whereJsonContains('departments', (string) $user->department_id);
                    });
            })
            ->get();

        $holidayDays = 0;
        foreach ($holidays as $holiday) {
            $holidayStart = Carbon::parse($holiday->starting_date);
            $holidayEnd = Carbon::parse($holiday->ending_date);

            $effectiveStart = $holidayStart->greaterThan($startDate) ? $holidayStart : $startDate;
            $effectiveEnd = $holidayEnd->lessThan($endDateNonAdmin) ? $holidayEnd : $endDateNonAdmin;

            if ($effectiveEnd->gte($effectiveStart)) {
                $holidayDays += $effectiveStart->diffInDays($effectiveEnd) + 1;
            }
        }

        // Total days in the month
        $totalWorkingDays = 0;
        for ($d = $startDate->copy(); $d->lte($endDateNonAdmin); $d->addDay()) {
            $totalWorkingDays++;
        }

        $workingDays = $totalWorkingDays - $holidayDays;

        // Individual user attendance stats (for non-admin)
        // Attendance records
        $holidayDates = collect();
        foreach ($holidays as $holiday) {
            $hStart = Carbon::parse($holiday->starting_date)->startOfDay();
            $hEnd = Carbon::parse($holiday->ending_date)->endOfDay();
            for ($dateLoop = $hStart->copy(); $dateLoop->lte($hEnd); $dateLoop->addDay()) {
                $holidayDates->push($dateLoop->toDateString());
            }
        }

        // Calculate working days (excluding holidays)
        $workingDays = 0;
        for ($dateLoop = $startDate->copy(); $dateLoop->lte($endDateNonAdmin); $dateLoop->addDay()) {
            if (!$holidayDates->contains($dateLoop->toDateString())) {
                $workingDays++;
            }
        }

        // Attendance records
        $attendances = DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereBetween('finger', [$startDate, $endDateNonAdmin])
            ->orderBy('finger')
            ->get();

        $presentDates = $attendances->pluck('finger')
            ->map(fn($finger) => Carbon::parse($finger)->toDateString())
            ->unique()
            ->reject(fn($date) => $holidayDates->contains($date)); // Exclude holiday dates

        $presentCountUser = $presentDates->count();

        // Leaves
        $leavesUser = DB::table('leaves')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('type', 'regular')
            ->whereDate('starting_date', '<=', $endDateNonAdmin)
            ->whereDate('ending_date', '>=', $startDate)
            ->get();

        $leaveCountUser = 0;
        foreach ($leavesUser as $leave) {
            $start = Carbon::parse($leave->starting_date)->startOfDay()->greaterThan($startDate) ? Carbon::parse($leave->starting_date)->startOfDay() : $startDate;
            $end = Carbon::parse($leave->ending_date)->endOfDay()->lessThan($endDateNonAdmin) ? Carbon::parse($leave->ending_date)->endOfDay() : $endDateNonAdmin;
            $leaveCountUser += $start->diffInDays($end) + 1;
        }
        $leaveCountUser = (int) $leaveCountUser;

        // Late count
        $lateCountUser = 0;
        foreach ($presentDates as $dateStr) {
            $firstBiometric = $attendances->first(fn($a) => Carbon::parse($a->finger)->toDateString() === $dateStr);

            if ($firstBiometric) {
                $shiftLog = Helper::getUserShiftLog($user, Carbon::parse($dateStr));
                if ($shiftLog) {
                    $shiftStart = Carbon::parse($dateStr . ' ' . $shiftLog->starting_time);
                    $punchTime = Carbon::parse($firstBiometric->finger);
                    if ($punchTime->gt($shiftStart)) {
                        $lateCountUser++;
                    }
                }
            }
        }

        // Absent = working days - present - leave
        $absentCountUser = max((int) ($workingDays - $presentCountUser - $leaveCountUser), 0);

        if ($showAllUsers) {
            return [
                Stat::make('', (string) $totalUsers)
                    ->description("Employees")
                    ->backgroundColor('info')
                    ->extraAttributes(['class' => 'stat-total']),

                Stat::make('', (string) $presentCount)
                    ->description("Present")
                    ->backgroundColor('success')
                    ->extraAttributes(['class' => 'stat-present']),

                Stat::make('', (string) $leaveCount)
                    ->description("On Leave")
                    ->backgroundColor('warning')
                    ->extraAttributes(['class' => 'stat-leave']),

                Stat::make('', (string) $absentCount)
                    ->description("Absent")
                    ->backgroundColor('danger')
                    ->extraAttributes(['class' => 'stat-absent']),

                Stat::make('', (string) $lateCount)
                    ->description("Late")
                    ->extraAttributes(['class' => 'stat-late']),
            ];
        } else {
            return [
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

                Stat::make('', (string) $lateCountUser)
                    ->description("Late Days")
                    ->extraAttributes(['class' => 'stat-late']),
            ];
        }
    }
}
