<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use App\Models\Role;
use EightyNine\FilamentAdvancedWidget\AdvancedTableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class UserMonthlyAttendanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Monthly Attendance';
    protected static ?int $sort = 3;
    protected static string $view = 'filament.widgets.widget-loader';
    protected static ?string $maxHeight = '380px';

    public function getColumnSpan(): int|string|array
    {
        return [
            'default' => 12,
            'md' => 6,
        ];
    }

    public $selectedMonth;

    protected $listeners = [
        'refresh-dashboard-widgets' => 'onMonthChanged',
    ];

    public function mount(): void
    {
        $this->selectedMonth = Session::get('dashboard_selected_month', now()->format('Y-m'));
    }

    public function onMonthChanged(): void
    {
        $this->selectedMonth = Session::get('dashboard_selected_month', now()->format('Y-m'));
        $this->dispatch('$refresh');
    }

    public static function canView(): bool
    {
        $user = Auth::user();

        if (!$user || $user->attendance_config != 1) {
            return false;
        }

        if ($user->hasAnyRole(['superadmin', 'Admin'])) {
            return false;
        }

        $role = Role::find($user->role);
        if (!empty($role->assigned_users)) {
            return false;
        }

        if (!Helper::has_feature('attendance')) {
            return false;
        }
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->grow(false)
                    ->width('20%'),
                Tables\Columns\TextColumn::make('day_name')
                    ->label('Day')
                    ->grow(false)
                    ->width('20%'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->grow(false)
                    ->width('20%')
                    ->color(fn($state) => match ($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Leave' => 'warning',
                        'Holiday' => 'info',
                        'Weekend' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('timings')
                    ->label('Timing')
                    ->grow(false)
                    ->width('25%')
                    ->state(function ($record) {
                        if ($record->status === 'Absent' || empty($record->timings)) {
                            return '-';
                        }

                        $times = explode(',', $record->timings);
                        if (count($times) === 1) {
                            return $times[0]; // Only one time available
                        }

                        return "{$times[0]} - " . end($times);
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('All Statuses')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Leave' => 'On Leave',
                        'Holiday' => 'Holiday',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            $query->where('status', $data['value']);
                        }

                        return $query;
                    }),
            ])
            ->paginated([3, 7, 14, 21, 31])
            ->defaultPaginationPageOption(3)
            ->recordUrl(fn($record) => url('/client/' . Filament::getTenant()->slug . '/attendance?highlight_user=' . $record->id))
            ->recordClasses('cursor-pointer')
            ->query(fn() => $this->getTableQuery());
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        $user->loadMissing('assignedShift.shift', 'assignedDepartment.department');
        $userId = $user->id;
        $adminId = Filament::getTenant()->id;

        // Parse the selected month
        [$year, $month] = explode('-', $this->selectedMonth);
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // If selected month is not current month, use end of month
        if ($startDate->format('Y-m') !== Carbon::now()->format('Y-m')) {
            $endDate = $startDate->copy()->endOfMonth();
        }

        // Get the maximum day (either today or end of month for past months)
        $maxDay = min($endDate->day, $endDate->copy()->endOfMonth()->day);

        // Create a model query
        return User::query()
            ->fromSub(function ($query) use ($year, $month, $maxDay, $userId, $adminId, $user) {
                $baseQuery = null;
                for ($day = 1; $day <= $maxDay; $day++) {
                    $currentDate = Carbon::createFromDate($year, $month, $day);

                    // Skip future dates
                    if ($currentDate->isAfter(Carbon::now())) {
                        continue;
                    }

                    $dateString = $currentDate->toDateString();
                    $dayName = $currentDate->format('l');

                    $dayQuery = DB::table('users as u')
                        ->select('u.id', 'u.name', 'du.department_id', 'su.shift_id')
                        ->leftJoin('department_users as du', 'du.user_id', '=', 'u.id')
                        ->leftJoin('shift_users as su', 'su.user_id', '=', 'u.id')
                        ->where('u.id', $userId)
                        ->selectRaw("? as date", [$dateString])
                        ->selectRaw("? as day_name", [$dayName])
                        ->selectRaw("
                            CASE
                                WHEN EXISTS (
                                    SELECT 1 FROM attendances as a
                                    WHERE a.user_id = u.id
                                    AND DATE(a.finger) = ?
                                ) THEN 'Present'
                                WHEN EXISTS (
                                    SELECT 1 FROM leaves as l
                                    WHERE l.user_id = u.id
                                    AND l.status = 'approved'
                                    AND ? BETWEEN l.starting_date AND l.ending_date
                                ) THEN 'Leave'
                                WHEN EXISTS (
                                    SELECT 1 FROM holidays as h
                                    WHERE h.team_id = ?
                                    AND ? BETWEEN h.starting_date AND h.ending_date
                                    AND (
                                        h.apply = 'all'
                                        OR (h.apply = 'user' AND JSON_CONTAINS(h.users, JSON_QUOTE(CAST(u.id AS CHAR))))
                                        OR (h.apply = 'shift' AND JSON_CONTAINS(h.shifts, JSON_QUOTE(CAST(? AS CHAR))))
                                        OR (h.apply = 'department' AND JSON_CONTAINS(h.departments, JSON_QUOTE(CAST(? AS CHAR))))
                                    )
                                ) THEN 'Holiday'
                                ELSE 'Absent'
                            END as status
                        ", [
                            $dateString,
                            $dateString,
                            $adminId,
                            $dateString,
                            $user->assignedShift?->shift?->id,
                            $user->assignedDepartment?->department?->id
                        ])
                        ->selectRaw("
                            (
                                SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(a.finger, '%H:%i') ORDER BY a.finger ASC)
                                FROM attendances as a
                                WHERE a.user_id = u.id
                                AND DATE(a.finger) = ?
                            ) as timings
                        ", [$dateString])
                        ->selectRaw("
                            (
                                SELECT 
                                    TIMESTAMPDIFF(HOUR, MIN(a.finger), MAX(a.finger)) +
                                    TIMESTAMPDIFF(MINUTE, MIN(a.finger), MAX(a.finger)) / 60.0
                                FROM attendances as a
                                WHERE a.user_id = u.id
                                AND DATE(a.finger) = ?
                                HAVING COUNT(a.id) > 1
                            ) as hours_worked
                        ", [$dateString]);

                    if ($baseQuery === null) {
                        $baseQuery = $dayQuery;
                    } else {
                        $baseQuery->union($dayQuery);
                    }
                }

                // Handle the case where no days should be shown (e.g., selected month is in the future)
                if ($baseQuery === null) {
                    // Create an empty query with the same structure
                    $baseQuery = DB::table('users')
                        ->select(
                            'id',
                            'name',
                            'department_id',
                            DB::raw('NULL as date'),
                            DB::raw('NULL as day_name'),
                            DB::raw('NULL as status'),
                            DB::raw('NULL as timings'),
                            DB::raw('NULL as hours_worked')
                        )
                        ->whereRaw('1 = 0'); // Ensure no results
                }

                $query->fromSub($baseQuery, 'attendance_days');
            }, 'monthly_attendance')
            ->orderBy('date', 'asc');
    }
}
