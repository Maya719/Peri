<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use EightyNine\FilamentAdvancedWidget\AdvancedTableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AttendanceTableWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static string $view = 'filament.widgets.widget-loader';
    protected static ?string $maxHeight = '455px';

    public function getColumnSpan(): int|string|array
    {
        $user = Auth::user();

        if (
            $user->hasAnyRole('Admin') ||
            $user->hasPermissionTo('employees.manage') ||
            $user->hasPermissionTo('employees.view')
        ) {
            return [
                'default' => 12,
                'md' => 8,
            ];
        }

        return [
            'default' => 12,
            'md' => 6,
        ];
    }

    public $selectedDate;

    protected $listeners = [
        'refresh-dashboard-widgets' => 'onDateChanged',
    ];

    public function mount(): void
    {
        $this->selectedDate = Session::get('dashboard_selected_date', now()->toDateString());
    }

    public function onDateChanged(): void
    {
        $this->selectedDate = Session::get('dashboard_selected_date', now()->toDateString());
        $this->dispatch('$refresh');
    }

    public static function canView(): bool
    {
        $user = Auth::user();

        if (($user->hasRole('Admin') || Helper::isAssignUsers()) && $user && Helper::has_feature('attendance')) {
            return true;
        }

        return false;
    }
    public function table(Table $table): Table
    {
        return $table
            ->heading('Attendance')
            ->emptyStateHeading('No employees yet')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->grow(false)->width('10%'),
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->grow(false)
                    ->circular()
                    ->url(fn($record) => $record->avatar_url),
                Tables\Columns\TextColumn::make('name')->label('Employee')->searchable()->grow(false)->width('25%'),
                Tables\Columns\TextColumn::make('department_name')->label('Department')->searchable()->grow(false)->width('25%'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->grow(false)
                    ->width('15%')
                    ->color(fn($state) => match ($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Leave' => 'warning',
                        'Holiday' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('timings')
                    ->label('Timing')
                    ->grow(false)
                    ->width('15%')
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
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            $query->where('status', $data['value']);
                        }

                        return $query;
                    }),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(fn() => Department::pluck('name', 'id')->toArray())
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['value'], fn($q, $value) => $q->where('department_id', $value))
                    )
                    ->placeholder('All Departments'),

            ])
            ->searchPlaceholder('Search Employee')
            ->query(fn() => $this->getTableQuery())
            ->paginated(
                (Helper::isAssignUsers() && Auth::user()?->can('employee.manage'))
                    ? [2, 5, 10, 25, 50]
                    : [5, 10, 25, 50]
            )
            ->defaultPaginationPageOption(
                (Helper::isAssignUsers() && Auth::user()?->can('employee.manage'))
                    ? 2
                    : 5
            )
            ->recordUrl(fn($record) => url('/client/' . Filament::getTenant()->slug . '/attendance?highlight_user=' . $record->id))
            ->recordClasses('cursor-pointer');
    }

    protected function getTableQuery(): Builder
    {
        $date = Carbon::parse($this->selectedDate)->startOfDay();
        $adminId = Filament::getTenant()->id;

        return User::query()
            ->fromSub(function ($query) use ($date, $adminId) {
                $query->select(
                    'u.id',
                    'u.name',
                    'd.name as department_name',
                    'su.shift_id',
                    'du.department_id',
                    'u.avatar_url'
                )
                    ->from('users as u')
                    ->where('u.active', 1)
                    ->where('u.attendance_config', 1)
                    ->join('team_user as tu', 'tu.user_id', '=', 'u.id')
                    ->where('tu.team_id', $adminId)
                    ->leftJoin('shift_users as su', function ($join) use ($adminId) {
                        $join->on('su.user_id', '=', 'u.id')
                            ->where('su.team_id', '=', $adminId);
                    })
                    ->leftJoin('department_users as du', function ($join) use ($adminId) {
                        $join->on('du.user_id', '=', 'u.id')
                            ->where('du.team_id', '=', $adminId);
                    })
                    ->leftJoin('departments as d', 'd.id', '=', 'du.department_id');

                if (Helper::isAssignUsers()) {
                    $query->whereIn('u.id', Helper::getAssignUsersIds());
                }

                $selectRawSql = '
                    (
                        SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(a_gc.finger, "%H:%i") ORDER BY a_gc.finger ASC)
                        FROM attendances as a_gc
                        WHERE a_gc.user_id = u.id
                        AND DATE(a_gc.finger) = ?
                    ) as timings,
                    CASE
                        WHEN EXISTS (
                            SELECT 1 FROM attendances as a_ex
                            WHERE a_ex.user_id = u.id
                            AND DATE(a_ex.finger) = ?
                        ) THEN "Present"
                        WHEN EXISTS (
                            SELECT 1 FROM leaves as l
                            WHERE l.user_id = u.id
                            AND l.status = "approved"
                            AND ? BETWEEN l.starting_date AND l.ending_date
                        ) THEN "Leave"
                        WHEN EXISTS (
                            SELECT 1 FROM holidays as h
                            WHERE h.team_id = ?
                            AND ? BETWEEN h.starting_date AND h.ending_date
                            AND (
                                h.apply = "all"
                                OR (h.apply = "user" AND JSON_CONTAINS(h.users, JSON_QUOTE(CAST(u.id AS CHAR))))
                                OR (h.apply = "shift" AND JSON_CONTAINS(h.shifts, JSON_QUOTE(CAST(su.shift_id AS CHAR))))
                                OR (h.apply = "department" AND JSON_CONTAINS(h.departments, JSON_QUOTE(CAST(du.department_id AS CHAR))))
                            )
                        ) THEN "Holiday"
                        ELSE "Absent"
                    END as status,
                    (
                        SELECT MAX(a_max.finger)
                        FROM attendances AS a_max
                        WHERE a_max.user_id = u.id
                        AND DATE(a_max.finger) = ?
                    ) AS latest_timing
                ';

                $bindings = [
                    $date->toDateString(),
                    $date->toDateString(),
                    $date->toDateString(),
                    $adminId,
                    $date->toDateString(),
                    $date->toDateString(),
                ];

                $query->selectRaw($selectRawSql, $bindings)
                    ->groupBy('u.id', 'u.name', 'd.name', 'su.shift_id', 'du.department_id', 'u.avatar_url');
            }, 'attendance_summary')
            ->select('*')
            ->orderBy(DB::raw("CASE WHEN attendance_summary.status = 'Present' THEN 0 ELSE 1 END"))
            ->orderBy('attendance_summary.latest_timing', 'DESC')
            ->limit(5);
    }
}
