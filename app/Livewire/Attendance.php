<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use App\Facades\Helper;
use Filament\Facades\Filament;

class Attendance extends Component implements HasForms
{
    use WithPagination;
    use InteractsWithForms;

    public $data = [
        'userId' => '',
        'attendance_type' => '',
        'department_id' => '',
        'shift_id' => '',
        'range' => '',
    ];

    public int | string $perPage = 10;

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $from = now()->startOfMonth();
        $to = now();
        $this->data['fromDate'] = $from->toDateString();
        $this->data['toDate'] = $to->toDateString();
        $this->data['range'] = $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y');
        if (request()->has('highlight_user')) {
            $requestUserId = request('highlight_user');
            $this->data['userId'] = $requestUserId;
        }
    }

    public function updatedData($value, $key): void
    {
        if ($key === 'range' && !empty($value)) {
            [$start, $end] = explode(' - ', $value);
            $this->data['fromDate'] = \Carbon\Carbon::createFromFormat('d/m/Y', $start)->format('Y-m-d');
            $this->data['toDate'] = \Carbon\Carbon::createFromFormat('d/m/Y', $end)->format('Y-m-d');
        }
        $this->resetPage();
    }
    // In Livewire component
    public function getLegendsProperty()
    {
        return [
            [
                'label' => 'Pending',
                'color' => 'bg-yellow-400',
            ],
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(5)
                    ->schema([
                        Forms\Components\Select::make('userId')
                            ->label(false)
                            ->searchable()
                            ->placeholder('Search Employee')
                            ->options(fn() => Filament::getTenant()->filteredUsers()->where('active', 1)->where('attendance_config', 1)->pluck('name', 'id'))
                            ->live(onBlur: true)
                            ->visible(fn() => Helper::isAssignUsers() || Auth::user()->hasRole('Admin')),

                        Forms\Components\Select::make('attendance_type')
                            ->label(false)
                            ->options([
                                'onsite' => 'On Site',
                                'offsite' => 'Remote',
                                'hybrid' => 'Hybrid',
                            ])
                            ->searchable()
                            ->placeholder('Select Attendance Type')
                            ->live(onBlur: true)
                            ->visible(fn() => Helper::isAssignUsers() || Auth::user()->hasRole('Admin')),


                        Forms\Components\Select::make('department_id')
                            ->label(false)
                            ->options(fn() => Filament::getTenant()->departments()->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Select Department')
                            ->live(onBlur: true)
                            ->visible(fn() => Helper::isAssignUsers() || Auth::user()->hasRole('Admin')),

                        Forms\Components\Select::make('shift_id')
                            ->label(false)
                            ->options(fn() => Filament::getTenant()->shifts()->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Select Shift')
                            ->live(onBlur: true)
                            ->visible(fn() => Helper::isAssignUsers() || Auth::user()->hasRole('Admin')),

                        DateRangePicker::make('range')
                            ->label(false)
                            ->ranges([
                                'Today' => [now(), now()],
                                'Yesterday' => [now()->subDay(), now()->subDay()],
                                'Last 7 days' => [now()->subDays(6), now()],
                                'Last 30 days' => [now()->subDays(29), now()],
                                'This Month' => [now()->startOfMonth(), now()],
                                'Last Month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                                'This Year' => [now()->startOfYear(), now()],
                                'Last Year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
                            ])
                            ->maxDate(now()),
                    ]),
            ])
            ->statePath('data');
    }

    public function render()
    {
        $policy = Helper::policy();
        $userId = $this->data['userId'] ?? '';
        $attendanceType = $this->data['attendance_type'] ?? '';
        $departmentId = $this->data['department_id'] ?? '';
        $shiftId = $this->data['shift_id'] ?? '';
        $from = $this->data['fromDate'] ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $this->data['toDate'] ?: Carbon::today()->toDateString();
        $to = Carbon::parse($to);
        // Get data
        $attendanceUsers =  Helper::GetAttendanceUsers($userId, $attendanceType, $departmentId, $shiftId, $from, $to, $this->perPage);
        $attendances =  Helper::GetAttendanceWithinDateRange($from, $to, $userId);
        $userAttendanceData = Helper::buildAttendanceData($attendanceUsers, $attendances, $from, $to, $policy);
        return view('livewire.attendance', [
            'userAttendanceData' => $userAttendanceData,
            'attendanceUsers' => $attendanceUsers,
            'policy' => $policy,
            'users' => User::query()->paginate($this->perPage),
        ]);
    }
}
