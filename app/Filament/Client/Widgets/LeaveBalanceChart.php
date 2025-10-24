<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use App\Models\Leave;
use App\Models\LeaveType;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class LeaveBalanceChart extends ChartWidget
{
    public ?int $userId = null;

    protected static ?int $sort = 5;

    public function getColumnSpan(): int|string|array
    {
        $user = $this->userId ? \App\Models\User::find($this->userId) : Auth::user();

        if (
            $user->hasAnyRole('Admin') ||
            $user->hasPermissionTo('employees.manage') ||
            $user->hasPermissionTo('employees.view')
        ) {
            return [
                'default' => 12,
                // 'md' => 6,
            ];
        }

        return [
            'default' => 12,
            'md' => 6,
        ];
    }

    protected static ?string $heading = 'Leave Balance';
    public function getMaxHeight(): ?string
    {
        $user = $this->userId ? \App\Models\User::find($this->userId) : Auth::user();

        if (
            $user && ($user->hasAnyRole('Admin') || $user->hasPermissionTo('employees.manage')) || $user->hasPermissionTo('employees.view')
        ) {
            return '380px'; // For admins or users with permission
        } elseif (
            Helper::isAssignUsers()
        ) {
            return '317px'; // For users with assigned employees
        }

        return '325px'; // For regular users
    }


    public static function canView(): bool
    {
        $user = Auth::user();
        if ($user->attendance_config === 1 && Helper::has_feature('attendance')) {
            return true;
        }
        return false;
    }

    protected function getData(): array
    {
        $user = $this->userId ? \App\Models\User::find($this->userId) : Auth::user();
        $gender = strtolower(trim($user->gender ?? ''));
        $martialStatus = strtolower(trim($user->martial_status ?? ''));

        $applyOn = (!$gender || !$martialStatus) ? 'all' : "{$gender}_{$martialStatus}";

        $leaveTypes = Filament::getTenant()->leaveTypes()
            ->whereIn('team_id', $user->teams->pluck('id'))
            ->where(function ($query) use ($applyOn) {
                $query->where('apply_on', 'all')
                    ->orWhere('apply_on', $applyOn);
            })
            ->get();


        $usedLeaves = [];
        $remainingLeaves = [];
        $labels = [];

        foreach ($leaveTypes as $leaveType) {
            $allowed = $leaveType->leaves_count ?? 0;
            $name = $leaveType->name;
            $duration = $leaveType->duration ?? 'annual';

            // Determine the start date for the leave period
            $now = Carbon::now();
            switch ($duration) {
                case '1 month':
                    $startDate = $now->copy()->startOfMonth();
                    break;
                case '3 months':
                    $startMonth = $now->month - (($now->month - 1) % 3);
                    $startDate = $now->copy()->month($startMonth)->startOfMonth();
                    break;
                case '4 months':
                    $startMonth = $now->month - (($now->month - 1) % 4);
                    $startDate = $now->copy()->month($startMonth)->startOfMonth();
                    break;
                case '6 months':
                    $startDate = $now->month <= 6
                        ? $now->copy()->startOfYear()
                        : $now->copy()->month(7)->startOfMonth();
                    break;
                case 'annual':
                default:
                    $startDate = $now->copy()->startOfYear();
                    break;
            }

            // Sum used leave days
            $used = Leave::where('user_id', $user->id)
                ->where('leave_type', $name)
                ->where('paid', 1)
                ->where('status', 'approved')
                ->where('starting_date', '>=', $startDate)
                ->get()
                ->sum(function ($leave) use ($user) {
                    if ($leave->type === 'regular') {
                        $start = Carbon::parse($leave->starting_date);
                        $end = Carbon::parse($leave->ending_date);

                        $dates = collect();
                        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                            $dates->push($date->format('Y-m-d'));
                        }

                        $holidays = Filament::getTenant()->holidays()
                            ->where(function ($query) use ($user) {
                                $query->where('apply', 'all')
                                    ->orWhere(function ($q) use ($user) {
                                        $q->where('apply', 'user')
                                            ->whereRaw('JSON_CONTAINS(users, JSON_QUOTE(?))', [(string) $user->id]);
                                    })
                                    ->orWhere(function ($q) use ($user) {
                                        $q->where('apply', 'shift')
                                            ->whereRaw('JSON_CONTAINS(shifts, JSON_QUOTE(?))', [(string) $user->shift_id]);
                                    })
                                    ->orWhere(function ($q) use ($user) {
                                        $q->where('apply', 'department')
                                            ->whereRaw('JSON_CONTAINS(departments, JSON_QUOTE(?))', [(string) $user->department_id]);
                                    });
                            })
                            ->where(function ($query) use ($start, $end) {
                                $query->whereBetween('starting_date', [$start, $end])
                                    ->orWhereBetween('ending_date', [$start, $end])
                                    ->orWhere(function ($q) use ($start, $end) {
                                        $q->where('starting_date', '<=', $start)
                                            ->where('ending_date', '>=', $end);
                                    });
                            })
                            ->get();

                        $holidayDates = collect();
                        foreach ($holidays as $holiday) {
                            $holidayStart = Carbon::parse($holiday->starting_date);
                            $holidayEnd = Carbon::parse($holiday->ending_date);
                            for ($date = $holidayStart->copy(); $date->lte($holidayEnd); $date->addDay()) {
                                $holidayDates->push($date->format('Y-m-d'));
                            }
                        }

                        $effectiveDates = $dates->diff($holidayDates->unique());
                        return $effectiveDates->count();
                    }

                    if ($leave->type === 'half_day') {
                        return 0.5;
                    }

                    if ($leave->type === 'short_leave') {
                        return 0.25;
                    }

                    return 0;
                });


            $usedLeaves[] = $used;
            $remainingLeaves[] = max(0, $allowed - $used);
            $labels[] = $name;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Used',
                    'data' => $usedLeaves,
                    'backgroundColor' => '#EF4444',
                    'stack' => 'leaves',
                ],
                [
                    'label' => 'Remaining',
                    'data' => $remainingLeaves,
                    'backgroundColor' => '#3b82f6',
                    'stack' => 'leaves',
                ],
            ],
            'labels' => $labels,
            'options' => [
                'scales' => [
                    'x' => [
                        'stacked' => true,
                    ],
                    'y' => [
                        'stacked' => true,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
