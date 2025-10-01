<?php

namespace App\Services;

use App\Filament\Client\Pages\Tenancy\RegisterTeam;
use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\Fund;
use App\Models\Leave;
use App\Models\Role;
use App\Models\ShiftLog;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HelperService
{
    public function getGenralSettings()
    {
        $settings = \App\Models\Setting::getByType('general');
        return $settings;
    }
    /**
     * Check if the user has assigned users.
     *
     * @return bool
     */
    public function isAssignUsers(): bool
    {
        $userRole = Filament::getTenant()->roles()->where('id', Auth::user()->role)->first();
        if ($userRole && $userRole->assigned_users) {
            return true;
        }
        return false;
    }

    public function getAssignUsersIds(): array
    {
        $role = Auth::user()->roles->first();
        $assignUsers = $role?->assigned_users ?? [];
        $currentUserId = Auth::id();

        if (empty($assignUsers)) {
            return [$currentUserId];
        }

        if (!in_array($currentUserId, $assignUsers)) {
            $assignUsers[] = $currentUserId;
        }

        return $assignUsers;
    }

    public function getImageUrl(): string
    {
        if (url()->current() === url('/client/login')) {
            return 'https://images.unsplash.com/photo-1498049860654-af1a5c566876?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';
        }
        if (url()->current() === url('client/new')) {
            return 'https://assets.lummi.ai/assets/QmRrrxhnsT4B5jN8SBuVNid4Z4qxdvjrDi2CDJhfsYgU46?auto=format&w=1500';
        }
        if (url()->current() === url('client/profile')) {
            return asset('storage/uploads/logos/peri_building.jpg');
        }
        if (url()->current() === url('client/password-reset/request') || url()->current() === url('client/password-reset/reset')) {
            return asset('storage/uploads/login/reset-password.jpg');
        }

        return 'https://assets.lummi.ai/assets/QmRVZN8jcsPfrQJFWtjSbkNAmSUTqoNeeeJSqcyFeBvQeA?auto=format&w=1500';
    }

    /* 
    *
    *       Attendance Management System Functions
    *
    */
    /**
     * Build attendance data for users within a specified date range.
     *
     * @param Collection $users
     * @param Collection $attendances
     * @param string $from
     * @param string $to
     * @param AttendancePolicy $policy
     * @return array
     */
    public function buildAttendanceData($users, $attendances, $from, $to, $policy)
    {
        $userAttendanceData = [];
        $graceDaysUsed = [];

        foreach ($users as $user) {
            $stats = $this->initializeUserStats();
            $currentDate = Carbon::parse($from);
            $join_date = Carbon::parse($user->joining_date);

            // Process each day for the user
            while ($currentDate->lte($to)) {
                $recordsForDay = $this->getAttendanceRecordsForDay($attendances, $user, $currentDate);
                if ($user->attendance_type == 'onsite') {
                    $cellContent = $this->generateDayCellContentOnSite(
                        $user,
                        $currentDate,
                        $recordsForDay,
                        $join_date,
                        $policy,
                        $stats,
                        $graceDaysUsed
                    );
                } elseif ($user->attendance_type == 'offsite') {
                    $cellContent = $this->generateDayCellContentOffSite(
                        $user,
                        $currentDate,
                        $recordsForDay,
                        $join_date,
                        $policy,
                        $stats,
                        $graceDaysUsed
                    );
                } elseif ($user->attendance_type == 'hybrid') {
                    $cellContent = $this->generateDayCellContentHybrid(
                        $user,
                        $currentDate,
                        $recordsForDay,
                        $join_date,
                        $policy,
                        $stats,
                        $graceDaysUsed
                    );
                } else {
                    $cellContent = '';
                }
                $userAttendanceData[$user->id][$currentDate->toDateString()] = $cellContent;
                $currentDate->addDay();
            }

            // Add summary data
            $userAttendanceData[$user->id] = array_merge(
                $this->createUserSummary($user, $stats, $policy),
                $userAttendanceData[$user->id]
            );
        }

        return $userAttendanceData;
    }

    /**
     * Initialize statistics counters for a user
     */
    public function initializeUserStats()
    {
        return [
            'totalLate' => 0,
            'totalAbsents' => 0,
            'totalLeaves' => 0,
            'totalOvertime' => 0,
            'currentMonth' => null,
            'monthlyOvertime' => 0,
        ];
    }

    /**
     * Get attendance records for a specific day and user
     */
    private function getAttendanceRecordsForDay($attendances, $user, $currentDate)
    {
        return $attendances->filter(function ($attendance) use ($user, $currentDate) {
            return $attendance->user_id === $user->id &&
                $attendance->finger->toDateString() === $currentDate->toDateString();
        });
    }

    /**
     * Generate the HTML content for a day's cell for onsite attendance
     * 
     * @param $user
     * @param $currentDate
     * @param $recordsForDay
     * @param $join_date
     * @param $policy
     * @param $stats
     * @param $graceDaysUsed
     * @return string
     */
    private function generateDayCellContentOnSite($user, $currentDate, $recordsForDay, $join_date, $policy, &$stats, &$graceDaysUsed)
    {
        // If current date is before joining date
        if ($currentDate < $join_date) {
            return '<small class="text-xs text-gray-500 font-medium">-</small><br>';
        }

        // If no attendance records for the day
        if ($recordsForDay->isEmpty()) {
            return $this->handleEmptyAttendanceForOnSite($user, $currentDate, $stats, $policy);
        }

        $allFingers = $recordsForDay->pluck('finger')->sort()->values();
        $minFinger = $recordsForDay->min('finger');
        $maxFinger = $recordsForDay->max('finger');

        if ($this->checkHoliday($user, $currentDate)) {
            $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');
            return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
        // If only one punch or current day
        if ($minFinger->eq($maxFinger)) {
            return $this->handleSinglePunch($user, $currentDate, $allFingers, $policy, $stats);
        }

        // Handle short-leave scenarios
        if ($this->hasShortLeave($user, $currentDate)) {
            return $this->handleShortLeave($user, $currentDate, $allFingers, $policy, $stats, $graceDaysUsed);
        }

        // Handle half-day scenarios
        if ($this->CheckFirstTimeHalfDay($allFingers, $user, $currentDate)) {
            return $this->handleFirstHalfDayAttendance($user, $currentDate, $allFingers, $policy, $stats);
        }

        if ($this->CheckSecondTimeHalfDay($allFingers, $user, $currentDate)) {
            return $this->handleSecondHalfDayAttendance($user, $currentDate, $allFingers, $policy, $stats, $graceDaysUsed);
        }

        // Handle normal attendance
        return $this->handleNormalAttendance($user, $currentDate, $allFingers, $policy, $stats, $graceDaysUsed);
    }
    /**
     * Check if a holiday applies to the user for a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkHoliday($user, $date)
    {
        $holidays = Filament::getTenant()->holidays()->whereDate('starting_date', '<=', $date->toDateString())
            ->whereDate('ending_date', '>=', $date->toDateString())
            ->get();
        foreach ($holidays as $holiday) {
            $users = $holiday->users ?? [];
            $departments = $holiday->departments ?? [];
            $shifts = $holiday->shifts ?? [];

            if (
                $holiday->apply === 'all' ||
                in_array($user->id, $users) ||
                in_array($user->department_id, $departments) ||
                in_array($user->shift_id, $shifts)
            ) {
                return true;
            }
        }

        return false;
    }
    /**
     * Handle case where there's no attendance record for the day for on site users
     */
    private function handleEmptyAttendanceForOnSite($user, $currentDate, &$stats, $policy)
    {
        if ($this->checkHoliday($user, $currentDate)) {
            if ($this->checkSandwichRuleLeaveCheck($user, $currentDate, $policy) && $policy->sandwich_rule_policy_enabled == 1) {
                $stats['totalLeaves'] += 1;
                return '<small class="text-xs text-success-500 font-medium">SR(L)</small><br>';
            } elseif ($this->checkSandwichRuleAbsentCheck($user, $currentDate, $policy) && $policy->sandwich_rule_policy_enabled == 1) {
                $stats['totalAbsents'] += 1;
                return '<small class="text-xs text-danger-500 font-medium">SR(A)</small><br>';
            }
            return '<small class="text-xs text-info-500 font-medium">H</small><br>';
        }

        if ($this->checkFullDayLeave($user, $currentDate)) {
            $stats['totalLeaves'] += 1;
            return '<small class="text-xs text-success-500 font-medium">L</small><br>';
        }

        $stats['totalAbsents'] += 1;
        return '<small class="text-xs text-danger-500 font-medium">A</small><br>';
    }

    /**
     * Handle case where there's only a single punch for the day
     */
    private function handleSinglePunch($user, $currentDate, $allFingers, $policy, &$stats)
    {
        $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');

        if ($currentDate->isToday()) {
            return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }

        if ($policy->single_biometric_policy_enabled == 1) {
            if ($policy->single_biometric_behavior == 'half_day') {
                $stats['totalAbsents'] += 0.5;
                return '<small class="text-xs text-warning-500 font-medium">HD</small><br>' .
                    '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
            }

            if ($policy->single_biometric_behavior == 'biometric_missing') {
                return '<small class="text-xs text-warning-500 font-medium">BM</small><br>' .
                    '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
            }
        }

        return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
    }
    
    /**
     * Handle first half day attendance
     */
    private function handleFirstHalfDayAttendance($user, $currentDate, $allFingers, $policy, &$stats)
    {
        $lateMinutes = 0;
        $isLeave = $this->CheckFirstTimeLeave($user, $currentDate);
        $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');

        if ($policy->late_policy_enabled == 1) {
            $lateMinutes = $this->CheckFirstTimeHalfDayLateMin($allFingers, $user, $currentDate);
            $stats['totalLate'] += $lateMinutes;
        }

        if ($isLeave) {
            $stats['totalLeaves'] += 0.5;
            $prefix = $lateMinutes > 0
                ? '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>'
                : '';

            return $prefix .
                '<small class="text-xs text-success-500 font-medium">HD L</small><br>' .
                '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        } else {
            $stats['totalAbsents'] += 0.5;
            $prefix = $lateMinutes > 0
                ? '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>'
                : '';

            return $prefix .
                '<small class="text-xs text-warning-500 font-medium">HD</small><br>' .
                '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
    }

    /**
     * Handle second half day attendance
     */
    private function handleSecondHalfDayAttendance($user, $currentDate, $allFingers, $policy, &$stats, &$graceDaysUsed)
    {
        $lateMinutes = 0;
        $graceMinTag = '';
        $isLeave = $this->CheckSecondTimeLeave($user, $currentDate);
        $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');

        // Calculate late minutes if policy enabled
        if ($policy->late_policy_enabled == 1) {
            $lateMinutes = $this->CheckSecondTimeHalfDayLateMin($allFingers, $user, $currentDate);
            $stats['totalLate'] += $lateMinutes;
        }

        // Apply grace period if enabled
        if ($policy->grace_policy_enabled == 1) {
            $graceMinTag = $this->applyGracePeriod($user, $currentDate, $allFingers, $policy, $lateMinutes, $graceDaysUsed);
        }

        if ($isLeave) {
            $stats['totalLeaves'] += 0.5;
            $prefix = $lateMinutes > 0
                ? $graceMinTag . '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>'
                : '';

            return $prefix .
                '<small class="text-xs text-success-500 font-medium">HD L</small><br>' .
                '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        } else {
            $stats['totalAbsents'] += 0.5;
            $prefix = $lateMinutes > 0
                ? $graceMinTag . '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>'
                : '';

            return $prefix .
                '<small class="text-xs text-warning-500 font-medium">HD</small><br>' .
                '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
    }

    /**
     * Handle normal attendance (not half day) for onsite users
     */
    private function handleNormalAttendance($user, $currentDate, $allFingers, $policy, &$stats, &$graceDaysUsed)
    {
        $value = '';
        $lateMinutes = 0;
        $overTimeMin = 0;

        // Calculate late minutes if policy enabled
        if ($policy->late_policy_enabled == 1) {
            $lateMinutes = $this->calculateLateMin($allFingers, $user, $currentDate);
            $stats['totalLate'] += $lateMinutes;
        }

        // Calculate overtime if policy enabled
        if ($policy->overtime_policy_enabled == 1) {
            $overTimeMin = $this->calculateOvertime($currentDate, $allFingers, $user, $policy, $stats);
        }

        // Apply grace period if enabled
        if ($policy->grace_policy_enabled == 1) {
            $graceTag = $this->applyGracePeriod($user, $currentDate, $allFingers, $policy, $lateMinutes, $graceDaysUsed);
            if ($graceTag) {
                $value .= $graceTag;
            }
        }

        // Add overtime information
        if ($overTimeMin > 0) {
            $value .= '<small class="text-xs text-gray-300 font-medium">' . $overTimeMin . ' OT</small><br>';
        }

        // Add late minutes information
        if ($lateMinutes > 0) {
            $value .= '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>';
        }

        // Add finger timestamps
        $value .= '<small class="text-xs text-gray-500 font-medium">' .
            $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>') .
            '</small>';

        return $value;
    }

    /**
     * Handle short leave attendance
     */
    private function handleShortLeave($user, $currentDate, $allFingers, $policy, &$stats, &$graceDaysUsed)
    {
        $lateMinutes = 0;
        $graceMinTag = '';
        $isLeave = $this->hasShortLeave($user, $currentDate);
        $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');

        // Calculate late minutes if policy enabled
        if ($policy->late_policy_enabled == 1) {
            $lateMinutes = $this->calculateLateMin($allFingers, $user, $currentDate);
            $stats['totalLate'] += $lateMinutes;
        }

        // Apply grace period if enabled
        if ($policy->grace_policy_enabled == 1) {
            $graceMinTag = $this->applyGracePeriod($user, $currentDate, $allFingers, $policy, $lateMinutes, $graceDaysUsed);
        }

        if ($isLeave) {
            $stats['totalLeaves'] += 0.25;
            $prefix = $lateMinutes > 0
                ? $graceMinTag . '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>'
                : '';

            return $prefix .
                '<small class="text-xs text-success-500 font-medium">SL</small><br>' .
                '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
    }

    /**
     * Calculate overtime based on policy
     */
    private function calculateOvertime($currentDate, $allFingers, $user, $policy, &$stats)
    {
        $dailyOvertime = $this->calculateOvertimePerDay($allFingers, $user, $currentDate);
        $overTimeMin = 0;

        switch ($policy->overtime_duration) {
            case 'per_day':
                $maxMinutes = $policy->overtime_max_minutes;
                $overTimeMin = min($dailyOvertime, $maxMinutes);
                break;

            case 'per_month':
                $monthNumber = $currentDate->month;
                if ($stats['currentMonth'] !== $monthNumber) {
                    $stats['currentMonth'] = $monthNumber;
                    $stats['monthlyOvertime'] = 0;
                }

                $remaining = $policy->overtime_max_minutes - $stats['monthlyOvertime'];
                $overTimeMin = min($dailyOvertime, $remaining);
                $stats['monthlyOvertime'] += $overTimeMin;
                break;

            default:
                $overTimeMin = $dailyOvertime;
                break;
        }

        $stats['totalOvertime'] += $overTimeMin;
        return $overTimeMin;
    }

    /**
     * Apply grace period policy to late minutes
     */
    private function applyGracePeriod($user, $currentDate, $allFingers, $policy, &$lateMinutes, &$graceDaysUsed)
    {
        $graceMinutes = $this->calculateGraceMin($allFingers, $user, $currentDate);
        if ($graceMinutes <= 0) {
            return '';
        }

        $allowGraceMin = $policy->late_penalty;
        $graceTag = '';

        switch ($policy->grace_duration) {
            case 'day':
                $lateMinutes = max(0, $lateMinutes - $allowGraceMin);
                $graceTag = '<small class="text-xs text-info-500 font-medium">GM</small><br>';
                break;

            case 'month':
                $monthKey = $user->id . '-' . $currentDate->format('Y-m');

                if (!isset($graceDaysUsed[$monthKey])) {
                    $graceDaysUsed[$monthKey] = $policy->days_counter;
                }

                if ($graceDaysUsed[$monthKey] > 0) {
                    $lateMinutes = max(0, $lateMinutes - $allowGraceMin);
                    $graceDaysUsed[$monthKey] -= 1;
                    $graceTag = '<small class="text-xs text-info-500 font-medium">GM</small><br>';
                }
                break;
        }

        return $graceTag;
    }

    /**
     * Create user summary information
     */
    private function createUserSummary($user, $stats, $policy)
    {
        $attendanceType = match ($user->attendance_type) {
            'onsite' => 'On Site',
            'offsite' => 'Remote',
            'hybrid' => 'Hybrid',
            default => '',
        };
        $summary = [
            'user_id' => $user->id,
            'employee_id' => '<small class="text-xs text-gray-500 font-medium">' . $user->id . '</small>',
            'attendance_type' => '<small class="text-xs text-gray-500 font-medium">' . $attendanceType . '</small>',
            'name' => '<small class="text-xs text-gray-500 font-medium">' . $user->name . '</small>'
        ];

        $summaryParts = [$stats['totalAbsents'], $stats['totalLeaves']];

        if ($policy->late_policy_enabled) {
            $summaryParts[] = $stats['totalLate'];
        }

        if ($policy->overtime_policy_enabled) {
            $summaryParts[] = $stats['totalOvertime'];
        }

        $summary['total_late_minutes'] = '<small class="text-xs text-gray-500 font-medium">' .
            implode('/', $summaryParts) .
            '</small>';

        return $summary;
    }
    /**
     * Generate the HTML content for a day's cell for onsite attendance
     * 
     * @param $user
     * @param $currentDate
     * @param $recordsForDay
     * @param $join_date
     * @param $policy
     * @param $stats
     * @param $graceDaysUsed
     * @return string
     */
    private function generateDayCellContentOffSite($user, $currentDate, $recordsForDay, $join_date, $policy, &$stats, &$graceDaysUsed)
    {
        // If current date is before joining date
        if ($currentDate < $join_date) {
            return '<small class="text-xs text-gray-500 font-medium">-</small><br>';
        }

        // If no attendance records for the day
        if ($recordsForDay->isEmpty()) {
            return $this->handleEmptyAttendanceForOffSite($user, $currentDate, $stats, $policy);
        }

        $allFingers = $recordsForDay->pluck('finger')->sort()->values();
        $minFinger = $recordsForDay->min('finger');
        $maxFinger = $recordsForDay->max('finger');

        if ($this->checkHoliday($user, $currentDate)) {
            $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');
            return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
        // If only one punch or current day
        if ($minFinger->eq($maxFinger)) {
            return $this->handleSinglePunchOffSite($user, $currentDate, $allFingers, $policy, $stats);
        }

        // Handle normal attendance
        return $this->handleNormalAttendanceOffSite($user, $currentDate, $allFingers, $policy, $stats, $graceDaysUsed);
    }

    /**
     * Handle case where there's no attendance record for the day for off site users
     */
    private function handleEmptyAttendanceForOffSite($user, $currentDate, &$stats, $policy)
    {
        if ($this->checkHoliday($user, $currentDate)) {
            if ($this->checkSandwichRuleLeaveCheck($user, $currentDate, $policy) && $policy->sandwich_rule_policy_enabled == 1) {
                $stats['totalLeaves'] += 1;
                return '<small class="text-xs text-success-500 font-medium">SR(L)</small><br>';
            } elseif ($this->checkSandwichRuleAbsentCheck($user, $currentDate, $policy) && $policy->sandwich_rule_policy_enabled == 1) {
                $stats['totalAbsents'] += 1;
                return '<small class="text-xs text-danger-500 font-medium">SR(A)</small><br>';
            }
            return '<small class="text-xs text-info-500 font-medium">H</small><br>';
        }

        if ($this->checkFullDayLeave($user, $currentDate)) {
            $stats['totalLeaves'] += 1;
            return '<small class="text-xs text-success-500 font-medium">L</small><br>';
        }

        $stats['totalAbsents'] += 1;
        return '<small class="text-xs text-danger-500 font-medium">A</small><br>';
    }
    /**
     * Handle normal attendance (not half day) for onsite users
     */
    private function handleNormalAttendanceOffSite($user, $currentDate, $allFingers, $policy, &$stats, &$graceDaysUsed)
    {
        $value = '';
        $lateMinutes = 0;

        $lateMinutes = $this->calculateHoursForOffSite($allFingers, $user, $currentDate);
        $stats['totalLate'] += $lateMinutes;
        if ($lateMinutes > 0) {
            $value .= '<small class="text-xs text-gray-500 font-medium">' . $lateMinutes . ' M</small><br>';
        }
        // Add finger timestamps
        $value .= '<small class="text-xs text-gray-500 font-medium">' .
            $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>') .
            '</small>';

        return $value;
    }

    /**
     * Handle case where there's only a single punch for the day for off site users
     */
    private function handleSinglePunchOffSite($user, $currentDate, $allFingers, $policy, &$stats)
    {
        $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');
        return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
    }
    /**
     * Generate the HTML content for a day's cell for Hybrid users
     * 
     * @param $user
     * @param $currentDate
     * @param $recordsForDay
     * @param $join_date
     * @param $policy
     * @param $stats
     * @param $graceDaysUsed
     * @return string
     */
    private function generateDayCellContentHybrid($user, $currentDate, $recordsForDay, $join_date, $policy, &$stats, &$graceDaysUsed)
    {
        // If current date is before joining date
        if ($currentDate < $join_date) {
            return '<small class="text-xs text-gray-500 font-medium">-</small><br>';
        }

        // If no attendance records for the day
        if ($recordsForDay->isEmpty()) {
            return $this->handleEmptyAttendanceForHybrid($user, $currentDate, $stats, $policy);
        }

        $allFingers = $recordsForDay->pluck('finger')->sort()->values();
        $minFinger = $recordsForDay->min('finger');
        $maxFinger = $recordsForDay->max('finger');

        if ($this->checkHoliday($user, $currentDate)) {
            $fingersFormatted = $allFingers->map(fn($f) => $f->format('H:i'))->implode('<br>');
            return '<small class="text-xs text-gray-500 font-medium">' . $fingersFormatted . '</small>';
        }
        // If only one punch or current day
        if ($minFinger->eq($maxFinger)) {
            return $this->handleSinglePunchOffSite($user, $currentDate, $allFingers, $policy, $stats);
        }

        // Handle normal attendance
        return $this->handleNormalAttendanceOffSite($user, $currentDate, $allFingers, $policy, $stats, $graceDaysUsed);
    }
    /**
     * Handle case where there's no attendance record for the day for off site users
     */
    private function handleEmptyAttendanceForHybrid($user, $currentDate, &$stats, $policy)
    {
        if ($this->CheckHybridDay($user, $currentDate)) {
            if ($this->checkFullDayLeave($user, $currentDate)) {
                $stats['totalLeaves'] += 1;
                return '<small class="text-xs text-success-500 font-medium">L</small><br>';
            }
            $stats['totalAbsents'] += 1;
            return '<small class="text-xs text-danger-500 font-medium">A</small><br>';
        }
        return '<small class="text-xs text-gray-500 font-medium">-</small><br>';
    }
    /**
     * Get the attendance policy.
     *
     * @return AttendancePolicy|\stdClass
     */
    public function policy()
    {
        $tenant = filament()->getTenant();
        $policy = $tenant->policies()->first();
        if (!$policy) {
            $policy = new \stdClass();
            $policy->full_day_counter = 1;
            $policy->half_day_counter = 0.5;
            $policy->late_policy_enabled = 1;
            $policy->single_biometric_policy_enabled = 1;
            $policy->enable_late_come = 1;
            $policy->enable_early_leave = 1;
            $policy->show_half_day = 1;
            $policy->time_offset_allowance = 0;
            $policy->grace_policy_enabled = 0;
            $policy->leave_balance_policy_enabled = 1;
            $policy->sandwich_rule_policy_enabled = 0;
            $policy->overtime_policy_enabled = 0;
            $policy->leaves_policy_option = null;
            $policy->single_biometric_behavior = 'half_day';
        }

        return $policy;
    }
    /**
     * Get all active users with attendance config.
     *
     * @param int|null $userId
     * @param string|null $attendanceType
     * @param int|null $department
     * @param int|null $shift
     * @param string|null $from
     * @param string|null $to
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAttendanceUsers($userId = null, $Type = null, $department = null, $shift = null, $from = null, $to = null, $perPage = 10)
    {
        $query = $this->buildAttendanceUserQuery($userId, $Type, $department, $shift);

        if ($from) {
            $query->whereDate('joining_date', '<=', $to);
        }

        return $query->paginate((int) $perPage);
    }

    /**
     * Build the query for attendance users.
     *
     * @param int|null $userId
     * @param string|null $attendanceType
     * @param int|null $department
     * @param int|null $shift
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildAttendanceUserQuery($userId = null, $Type = null, $department = null, $shift = null)
    {
        if (Auth::user()->hasRole('Admin')) {
            $usersQuery = Filament::getTenant()->members();
        } elseif ($this->isAssignUsers()) {
            $usersQuery = Filament::getTenant()->members()->whereIn('id', $this->getAssignUsersIds());
        } else {
            $usersQuery = Filament::getTenant()->members()->where('id', Auth::user()->id);
        }
        if ($userId) {
            $usersQuery->where('id', $userId);
        }
        if ($Type) {
            $usersQuery->where('attendance_type', $Type);
        }
        if ($department) {
            $usersQuery->whereHas('assignedDepartment', function ($q) use ($department) {
                $q->where('department_id', $department);
            });
        }
        if ($shift) {
            $usersQuery->whereHas('assignedShift', function ($q) use ($shift) {
                $q->where('shift_id', $shift);
            });
        }
        $usersQuery->where('active', 1);
        $usersQuery->orderBy('id', 'asc');
        $usersQuery->where('attendance_config', 1);
        return $usersQuery;
    }
    /**
     * Get attendances within a date range for active users with attendance config.
     *
     * @param Carbon|string $from
     * @param Carbon|string $to
     * @param int|null $userId
     * @return Collection
     */
    public function getAttendanceWithinDateRange($from, $to, $userId = null): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        return Filament::getTenant()->attendances()
            ->whereBetween('finger', [$from, $to])
            ->whereHas('user', function ($q) use ($userId) {
                $q->where('active', 1)
                    ->where('attendance_config', 1);

                if ($userId) {
                    $q->where('id', $userId);
                }
            })
            ->with('user')
            ->get();
    }
    /**
     * Check if the user is required to work on a hybrid day.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function CheckHybridDay($user, $date)
    {
        $userRequiredDays = $user->work_days ?? [];
        $dayName = strtolower($date->format('l'));
        return in_array($dayName, $userRequiredDays);
    }

    /**
     * Get the attendance policy.
     *
     * @return AttendancePolicy|null
     */
    public function getAttendancePolicy()
    {
        return Filament::getTenant()->policies()->first();
    }

    /**
     * Calculate the total late minutes for a user on a specific date.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function calculateLateMin($fingers, $user, $date)
    {
        $shiftLog = $this->getUserShiftLog($user, $date);
        $policy = $this->policy();

        if (!$shiftLog) {
            return 0;
        }

        $shiftStart = $this->parseShiftTime($date, $shiftLog->starting_time);
        $shiftEnd = $this->parseShiftTime($date, $shiftLog->ending_time);
        $in = $fingers->first()->copy()->setSeconds(0);
        $out = $fingers->last()->copy()->setSeconds(0);

        $lateIn = 0;
        $earlyOut = 0;
        $earlyArrival = 0;
        $extraOut = 0;

        if ($in->gt($shiftStart) && $policy->enable_late_come) {
            $lateDiff = $in->diffInMinutes($shiftStart);
            $lateIn = - ($this->checkShortLeave($user, $date, $shiftStart, $in, $lateDiff));
        }

        if ($out->lt($shiftEnd) && $policy->enable_early_leave) {
            $earlyDiff = $shiftEnd->diffInMinutes($out);
            $earlyOut = - ($this->checkShortLeave($user, $date, $out, $shiftEnd, $earlyDiff));
        }

        if ($policy->time_offset_allowance) {
            if ($out->gt($shiftEnd)) {
                $extraOut = - ($out->diffInMinutes($shiftEnd));
            }

            if ($in->lt($shiftStart)) {
                $earlyArrival = - ($shiftStart->diffInMinutes($in));
            }

            $netLate = max(0, $lateIn - $extraOut);
            $netEarly = max(0, $earlyOut - $earlyArrival);

            return $netLate + $netEarly;
        }

        return $lateIn + $earlyOut;
    }

    /**
     * Parse shift time for a specific date.
     *
     * @param Carbon $date
     * @param string $time
     * @return Carbon
     */
    protected function parseShiftTime(Carbon $date, string $time): Carbon
    {
        return Carbon::parse($date->format('Y-m-d') . ' ' . $time)->setSeconds(0);
    }

    /**
     * Check if a short leave applies to the time difference.
     *
     * @param User $user
     * @param Carbon $date
     * @param Carbon $start
     * @param Carbon $end
     * @param int $diff
     * @return int
     */
    public function checkShortLeave($user, $date, $start, $end, $diff)
    {
        $leave = Filament::getTenant()->leaves()
            ->where('user_id', $user->id)
            ->where('type', 'short_leave')
            ->where('status', 'approved')
            ->whereDate('starting_date', '<=', $date->toDateString())
            ->whereDate('ending_date', '>=', $date->toDateString())
            ->where(function ($q) use ($start, $end) {
                $q->where(DB::raw('TIME_FORMAT(starting_time, "%H:%i")'), '<=', $start->format('H:i'))
                    ->where(DB::raw('TIME_FORMAT(ending_time, "%H:%i")'), '>=', $end->format('H:i'));
            })
            ->first();
        return $leave ? 0 : $diff;
    }

    /**
     * Check if a short leave applies to the first time.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function hasShortLeave($user, $date)
    {
        return Filament::getTenant()->leaves()
            ->where('user_id', $user->id)
            ->where('type', 'short_leave')
            ->where('status', 'approved')
            ->whereDate('starting_date', '<=', $date->toDateString())
            ->whereDate('ending_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Calculate late minutes for first time half day.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function checkFirstTimeHalfDayLateMin($fingers, $user, $date)
    {
        $shiftLog = $this->getUserShiftLog($user, $date);
        $policy = $this->policy();

        if (!$shiftLog) {
            return 0;
        }

        $shiftStart = $this->parseShiftTime($date, $shiftLog->half_day_check_in);
        $shiftEnd = $this->parseShiftTime($date, $shiftLog->ending_time);
        $in = $fingers->first()->copy()->setSeconds(0);
        $out = $fingers->last()->copy()->setSeconds(0);

        $lateIn = 0;
        $earlyOut = 0;

        if ($in->gt($shiftStart) && $policy->enable_late_come) {
            $lateDiff = $in->diffInMinutes($shiftStart);
            $lateIn = - ($this->checkShortLeave($user, $date, $shiftStart, $in, $lateDiff));
        }

        if ($out->lt($shiftEnd) && $policy->enable_early_leave) {
            $earlyDiff = $shiftEnd->diffInMinutes($out);
            $earlyOut = - ($this->checkShortLeave($user, $date, $out, $shiftEnd, $earlyDiff));
        }

        return $earlyOut + $lateIn;
    }

    /**
     * Calculate late minutes for second time half day.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function checkSecondTimeHalfDayLateMin($fingers, $user, $date)
    {
        $shiftLog = $this->getUserShiftLog($user, $date);

        if (!$shiftLog) {
            return 0;
        }

        $shiftStart = $this->parseShiftTime($date, $shiftLog->starting_time);
        $shiftEnd = $this->parseShiftTime($date, $shiftLog->half_day_check_out);
        $in = $fingers->first()->copy()->setSeconds(0);
        $out = $fingers->last()->copy()->setSeconds(0);
        $policy = $this->policy();

        $lateIn = 0;
        $earlyOut = 0;

        if (!$this->hasShortLeave($user, $date) && $policy->enable_late_come) {
            $lateIn = $in->gt($shiftStart) ? $in->diffInMinutes($shiftStart) : 0;
        }

        if (!$this->checkShortLeaveEndTime($fingers, $user, $date) && $policy->enable_early_leave) {
            $earlyOut = $out->lt($shiftEnd) ? $shiftEnd->diffInMinutes($out) : 0;
        }

        return - ($lateIn + $earlyOut);
    }

    /**
     * Check if a short leave applies to the end time.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkShortLeaveEndTime($fingers, $user, $date)
    {
        return Leave::where('user_id', $user->id)
            ->where('type', 'short_leave')
            ->where('status', 'approved')
            ->whereDate('starting_date', '<=', $date->toDateString())
            ->whereDate('ending_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Check if first time half day applies.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkFirstTimeHalfDay($fingers, $user, $date): bool
    {
        $shiftLog = $this->getUserShiftLog($user, $date);

        if (!$shiftLog) {
            return false;
        }

        $halfDayWill = $this->parseShiftTime($date, $shiftLog->half_day_check_in);
        $in = $fingers->first()->copy()->setSeconds(0);

        return $in->gt($halfDayWill);
    }

    /**
     * Check if second time half day applies.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkSecondTimeHalfDay($fingers, $user, $date): bool
    {
        $shiftLog = $this->getUserShiftLog($user, $date);

        if (!$shiftLog) {
            return false;
        }

        $shiftEnd = $this->parseShiftTime($date, $shiftLog->half_day_check_out);
        $out = $fingers->last()->copy()->setSeconds(0);

        return $out->lt($shiftEnd);
    }

    /**
     * Check if the user has a first time leave for a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkFirstTimeLeave($user, $date): bool
    {
        return $this->checkLeave($user, $date, 'half_day', 'First Time');
    }

    /**
     * Check if the user has a second time leave for a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkSecondTimeLeave($user, $date): bool
    {
        return $this->checkLeave($user, $date, 'half_day', 'Second Time');
    }

    /**
     * Check if the user has a full-day leave for a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @return bool
     */
    public function checkFullDayLeave($user, $date): bool
    {
        return $this->checkLeave($user, $date, 'regular');
    }

    /**
     * Check if the user has a leave of a specific type for a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @param string $type
     * @param string|null $halfDayTiming
     * @return bool
     */
    protected function checkLeave($user, $date, $type, $halfDayTiming = null): bool
    {
        $query = Leave::where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', 'approved')
            ->whereDate('starting_date', '<=', $date)
            ->whereDate('ending_date', '>=', $date);

        if ($halfDayTiming) {
            $query->where('half_day_timing', $halfDayTiming);
        }

        return $query->exists();
    }

    /**
     * Calculate overtime per day.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function calculateOvertimePerDay($fingers, $user, $date): int
    {
        $shiftLog = $this->getUserShiftLog($user, $date);
        $policy = $this->policy();

        if (!$shiftLog) {
            return 0;
        }

        $shiftEnd = $this->parseShiftTime($date, $shiftLog->ending_time);
        $out = $fingers->last()->copy()->setSeconds(0);
        $overTimeStart = $shiftEnd->copy()->addMinutes($policy->overtime_start_delay ?? 0);

        if ($out->gt($overTimeStart)) {
            return $shiftEnd->diffInMinutes($out, false);
        }

        return 0;
    }
    /**
     * Get the shift log for a user on a specific date.
     *
     * @param User $user
     * @param Carbon $date
     * @return ShiftLog|null
     */
    public function getUserShiftLog(User $user, Carbon $date)
    {
        $assignedShift = $user->assignedShift()->first();

        if (!$assignedShift) {
            return null;
        }

        $shiftLog = ShiftLog::where('shift_id', $assignedShift->shift_id)
            ->whereDate('created_at', '<=', $date->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$shiftLog) {
            $shiftLog = Filament::getTenant()->shifts()
                ->where('id', $assignedShift->shift_id)
                ->first();
        }
        return $shiftLog;
    }

    /**
     * Calculate grace minutes.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function calculateGraceMin($fingers, $user, $date): int
    {
        $shiftLog = $this->getUserShiftLog($user, $date);

        if (!$shiftLog) {
            return 0;
        }

        $shiftStart = $this->parseShiftTime($date, $shiftLog->starting_time);
        $in = $fingers->first()->copy()->setSeconds(0);

        if ($in->gt($shiftStart)) {
            $lateDiff = $in->diffInMinutes($shiftStart);
            return - ($this->checkShortLeave($user, $date, $shiftStart, $in, $lateDiff));
        }

        return 0;
    }

    /**
     *  Check SandwichRule On Absent
     *  @param User $user
     *  @param Carbon $date
     *  @return Bool
     */
    public function checkSandwichRuleAbsentCheck($user, $currentDate, $policy): Bool
    {
        $beforeAbsent = false;
        $afterAbsent = false;

        if (in_array($policy->leaves_policy_option, ['before', 'after_and_before', 'after_or_before'])) {
            $beforeAbsent = $this->checkWorkingDayAbsentBefore($user, $currentDate);
        }

        if (in_array($policy->leaves_policy_option, ['after', 'after_and_before', 'after_or_before'])) {
            $afterAbsent = $this->checkWorkingDayAbsentAfter($user, $currentDate);
        }

        switch ($policy->leaves_policy_option) {
            case 'before':
                return $beforeAbsent;
            case 'after':
                return $afterAbsent;
            case 'after_and_before':
                return $beforeAbsent && $afterAbsent;
            case 'after_or_before':
                return $beforeAbsent || $afterAbsent;
            default:
                return false;
        }
    }
    protected function checkWorkingDayAbsentBefore($user, $currentDate): Bool
    {
        $date = Carbon::parse($currentDate)->subDay();

        while ($this->checkHoliday($user, Carbon::parse($date))) {
            $date->subDay();
        }

        return !Attendance::where('user_id', $user->id)
            ->whereDate('finger', $date)
            ->exists();
    }

    protected function checkWorkingDayAbsentAfter($user, $currentDate): Bool
    {
        $date = Carbon::parse($currentDate)->addDay();

        while ($this->checkHoliday($user, Carbon::parse($date))) {
            $date->addDay();
        }

        return !Attendance::where('user_id', $user->id)
            ->whereDate('finger', $date)
            ->exists();
    }
    /**
     *  Check SandwichRule On Leave
     *  @param User $user
     *  @param Carbon $date
     *  @return Bool
     */
    public function checkSandwichRuleLeaveCheck($user, $currentDate, $policy): Bool
    {

        $beforeAbsent = false;
        $afterAbsent = false;

        if (in_array($policy->leaves_policy_option, ['before', 'after_and_before', 'after_or_before'])) {
            $beforeAbsent = $this->checkWorkingDayLeaveBefore($user, $currentDate);
        }

        if (in_array($policy->leaves_policy_option, ['after', 'after_and_before', 'after_or_before'])) {
            $afterAbsent = $this->checkWorkingDayLeaveAfter($user, $currentDate);
        }

        switch ($policy->leaves_policy_option) {
            case 'before':
                return $beforeAbsent;
            case 'after':
                return $afterAbsent;
            case 'after_and_before':
                return $beforeAbsent && $afterAbsent;
            case 'after_or_before':
                return $beforeAbsent || $afterAbsent;
            default:
                return false;
        }
    }
    protected function checkWorkingDayLeaveBefore($user, $currentDate): Bool
    {
        $date = Carbon::parse($currentDate)->subDay();

        while ($this->checkHoliday($user, Carbon::parse($date))) {
            $date->subDay();
        }

        return $this->checkFullDayLeave($user, $date);
    }

    protected function checkWorkingDayLeaveAfter($user, $currentDate): Bool
    {
        $date = Carbon::parse($currentDate)->addDay();

        while ($this->checkHoliday($user, Carbon::parse($date))) {
            $date->addDay();
        }
        return $this->checkFullDayLeave($user, $date);
    }
    /**
     * Calculate hours for off-site Hours.
     *
     * @param Collection $fingers
     * @param User $user
     * @param Carbon $date
     * @return int
     */
    public function calculateHoursForOffSite($fingers, $user, $date)
    {
        $requiredMinutes = ($user->hours_required ?? 0) * 60;
        $in = $fingers->first()->copy()->setSeconds(0);
        $out = $fingers->last()->copy()->setSeconds(0);
        $workedMinutes = $in->diffInMinutes($out);
        $missingMinutes = $requiredMinutes - $workedMinutes;
        return $missingMinutes > 0 ? $missingMinutes : 0;
    }

    /**
     * 
     *   Fund Calculations
     * 
     */

    /**
     *  @param User $user
     *  @return Collection
     * 
     */

    public function getEmployeeFund(User $user): Collection
    {
        $user_funds = $user->funds()
            ->wherePivot('team_id', Filament::getTenant()->id)
            ->get();
        return $user_funds;
    }
    /**
     * 
     *  @param User $user
     *  @param Fund $fund
     *  @return int
     * 
     */
    public function getEmployeeDeductedFund(User $user, Fund $fund): int
    {
        $payrolls = Filament::getTenant()
            ->payrolls()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $collectedSinceLastWithdrawal = 0;
        $hasWithdrawalOccurred = false;

        foreach ($payrolls as $payroll) {
            $fundData = $payroll->fund_data;
            $earningsData = $payroll->earnings_data;

            if (isset($fundData) && is_array($fundData)) {
                foreach ($fundData as $fundItem) {
                    if (($fundItem['id'] ?? null) == $fund->id) {
                        $collectedSinceLastWithdrawal += (int) ($fundItem['calculated_amount'] ?? 0);
                    }
                }
            }

            if (isset($earningsData['ad_hoc_earnings']) && is_array($earningsData['ad_hoc_earnings'])) {
                $withdrawn = collect($earningsData['ad_hoc_earnings'])
                    ->firstWhere('id', 'adhoc_earning_fund_id' . $fund->id);

                if ($withdrawn) {
                    $hasWithdrawalOccurred = true;
                    $collectedSinceLastWithdrawal = 0;
                }
            }
        }

        return $collectedSinceLastWithdrawal;
    }

    public function getActiveSubscriptionDetails()
    {
        $tenant = Filament::getTenant();
        $subscription = $tenant?->activePlanSubscriptions()->first();

        if (!$subscription) {
            return (object) [
                'subscription' => null,
                'isEndingSoon' => false,
                'endsAt' => null,
            ];
        }

        $endsAt = $subscription->ends_at;
        $isEndingSoon = $endsAt && $endsAt->isBetween(now(), now()->addDays(7));

        return (object) [
            'subscription' => $subscription,
            'isEndingSoon' => $isEndingSoon,
            'endsAt' => $endsAt,
        ];
    }
    /**
     * Check if a module is allowed for the current tenant.
     *
     * @param string $slug
     * @return bool
     */
    public function is_module_allowed($slug): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return false;
        }
        $subscription = $tenant->activePlanSubscriptions()->first();
        if ($subscription) {
            return $subscription->canUseFeature($slug);
        }
        return false;
    }
    /**
     * Check if a module is allowed for the current tenant.
     *
     * @param string $slug
     * @return bool
     */
    public function has_feature($slug): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return false;
        }
        $subscription = $tenant->activePlanSubscriptions()->first();
        if ($subscription) {
            return $subscription->hasFeature($slug);
        }
        return false;
    }
    /**
     * Record feature usage for the current tenant.
     *
     * @param string $slug
     * @return bool
     */
    public function record_feature_usage($slug)
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return false;
        }
        $subscription = $tenant->activePlanSubscriptions()->first();
        if ($subscription) {
            return $subscription->recordFeatureUsage($slug, 1);
        }
        return false;
    }
}
