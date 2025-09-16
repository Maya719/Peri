<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\LeaveResource\Pages;
use App\Models\Leave;
use App\Models\LeaveLog;
use App\Models\Role;
use Carbon\Carbon;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Forms\Get;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Str;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class LeaveResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Leave::class;

    protected static ?string $navigationLabel = 'Leave Requests';
    protected static ?string $modelLabel = 'Leave Requests';
    protected static ?string $navigationGroup = 'Attendance Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') || $user->attendance_config == 1 || $user->hasRole('CEO') || $user->hasRole('AMS Manager')) && Helper::has_feature('attendance');
    }

    // LeaveResource.php

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Add the 'leaveLogs.role' eager loading here
        $query = parent::getEloquentQuery()->with(['user.assignedDepartment.department', 'user.assignedShift.shift', 'leaveLogs.role']);
        $user = Auth::user();

        if ($user->hasRole('Admin')) {
            return $query;
        } elseif (Helper::isAssignUsers()) {
            $assignedUserIds = Helper::getAssignUsersIds();
            return $query->whereIn('user_id', $assignedUserIds);
        } else {
            return $query->where('user_id', $user->id);
        }
    }

    public static function getDocumentation(): array
    {
        return [
            'leaves.introduction',
            KnowledgeBase::model()::find('leaves.working'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('approval_wizard')
                    ->label('')
                    ->content(function (Get $get) {
                        $leaveId = $get('id');
                        $leave = null;
                        if ($leaveId) {
                            $leave = \App\Models\Leave::find($leaveId);
                        }
                        $leaveUser = $leave?->user ?? Auth::user();
                        $leaveUserId = $leaveUser?->id ?? Auth::id();
                        $team_id = Filament::getTenant()->id;

                        // Fetch hierarchy steps
                        $hierarchySteps = DB::table('approval_steps')
                            ->where('team_id', $team_id)
                            ->where('user_id', $leaveUserId)
                            ->orderBy('level', 'asc')
                            ->get();

                        // Fetch approval logs if the leave exists
                        $logs = $leave ? $leave->leaveLogs()->orderBy('level', 'asc')->get()->keyBy('level') : collect();

                        return view('filament.client.components.approval-wizard', [
                            'leave' => $leave,
                            'leaveUser' => $leaveUser,
                            'hierarchySteps' => $hierarchySteps,
                            'logs' => $logs,
                        ]);
                    })->columnSpanFull(),
                Forms\Components\Placeholder::make('changelog')
                    ->label('')
                    ->content(function (?Leave $record) {
                        if (!$record) {
                            return null;
                        }

                        // Fetch only the logs that represent modifications made by an editor.
                        $changelogs = $record->leaveLogs()
                            ->with(['role', 'user']) // Modified: Added 'user' to eager loading
                            ->where('remarks', 'like', 'Updated by%')
                            ->orderBy('created_at', 'desc')
                            ->get();

                        if ($changelogs->isEmpty()) {
                            return null;
                        }

                        return view('filament.client.components.leave-changelog', [
                            'changelogs' => $changelogs,
                        ]);
                    })
                    ->columnSpanFull()
                    ->visible(fn(?Leave $record) => $record?->exists),
                Grid::make(2)
                    ->schema([
                        Grid::make(4)->schema([
                            Forms\Components\Section::make('Leave Details')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Select::make('type')
                                                ->label('Duration')
                                                ->options(function () {
                                                    $user = Auth::user();
                                                    if (in_array($user->attendance_type, ['offsite', 'hybrid'])) {
                                                        return ['regular' => 'Full Day Leave'];
                                                    }
                                                    return [
                                                        'regular' => 'Full Day Leave',
                                                        'half_day' => 'Half Day Leave',
                                                        'short_leave' => 'Short Leave',
                                                    ];
                                                })
                                                ->required()
                                                ->default(function () {
                                                    return 'regular';
                                                })
                                                ->live()
                                                ->searchable()
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    $set('leave_type', null);
                                                    $set('paid', 1);
                                                }),

                                            Forms\Components\Select::make('leave_type')
                                                ->label('Type')
                                                ->required()
                                                ->searchable()
                                                ->options(function () {
                                                    $user = Auth::user();
                                                    $gender = strtolower(trim($user->gender ?? ''));
                                                    $marital = strtolower(trim($user->martial_status ?? ''));
                                                    $applyOn = $gender && $marital ? "{$gender}_{$marital}" : 'all';

                                                    return Filament::getTenant()->leaveTypes()
                                                        ->where(function ($query) use ($applyOn) {
                                                            $query->where('apply_on', 'all')
                                                                ->orWhere('apply_on', $applyOn);
                                                        })
                                                        ->pluck('name', 'name')
                                                        ->toArray();
                                                }),
                                        ]),


                                    Forms\Components\Select::make('paid')
                                        ->options([
                                            1 => 'Paid',
                                            0 => 'Unpaid',
                                        ])
                                        ->searchable()
                                        ->label('Payment')
                                        ->default(1)
                                        ->required()
                                        ->disabled(function (Forms\Get $get) {
                                            if (static::isLocked($get)) {
                                                return true;
                                            }
                                            $leaveType = $get('leave_type');
                                            if (!$leaveType) {
                                                return false;
                                            }
                                            $leaveTypeRecord = Filament::getTenant()->leaveTypes()
                                                ->where('name', $leaveType)
                                                ->first();
                                            if (!$leaveTypeRecord || !$leaveTypeRecord->leaves_count) {
                                                return false;
                                            }
                                            $userId = $get('user_id') ?? Auth::id();

                                            $allowedCount = $leaveTypeRecord->leaves_count;
                                            $duration = $leaveTypeRecord->duration ?? 'annual';
                                            $now = Carbon::now();
                                            // Determine the starting date of the period.
                                            switch ($duration) {
                                                case '1 month':
                                                    $startDate = $now->copy()->startOfMonth();
                                                    break;
                                                case '3 months':
                                                    $currentMonth = $now->month;
                                                    $startMonthOfQuarter = $currentMonth - (($currentMonth - 1) % 3);
                                                    $startDate = $now->copy()->month($startMonthOfQuarter)->startOfMonth();
                                                    break;
                                                case '4 months':
                                                    $currentMonth = $now->month;
                                                    $startMonthOfTrimester = $currentMonth - (($currentMonth - 1) % 4);
                                                    $startDate = $now->copy()->month($startMonthOfTrimester)->startOfMonth();
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
                                            // Sum up the number of days used while weighting half day and short leave requests.
                                            $usedPaidLeaves = \App\Models\Leave::where('user_id', $userId)
                                                ->where('leave_type', $leaveType)
                                                ->where('paid', 1)
                                                ->where('status', 'approved')
                                                ->where('starting_date', '>=', $startDate)
                                                ->get()
                                                ->sum(function ($leave) {
                                                // Determine leave duration based on the type.
                                                if ($leave->type === 'regular') {
                                                    $start = \Carbon\Carbon::parse($leave->starting_date);
                                                    $end = \Carbon\Carbon::parse($leave->ending_date);
                                                    return $start->diffInDays($end) + 1;
                                                } elseif ($leave->type === 'half_day') {
                                                    return 0.5;
                                                } elseif ($leave->type === 'short_leave') {
                                                    return 0.25;
                                                }
                                                return 0;
                                            });
                                            return $usedPaidLeaves >= $allowedCount;
                                        })
                                        ->live()
                                        ->hint(function (Forms\Get $get) {
                                            $leaveType = $get('leave_type');
                                            if (!$leaveType) {
                                                return null;
                                            }

                                            $user = Auth::user();
                                            $leaveTypeRecord = Filament::getTenant()->leaveTypes()
                                                ->where('name', $leaveType)
                                                ->first();
                                            if (!$leaveTypeRecord) {
                                                return null;
                                            }

                                            $userId = $get('user_id') ?? $user->id;
                                            $targetUser = \App\Models\User::with(['assignedShift.shift', 'assignedDepartment.department'])->find($userId);
                                            if (!$targetUser) {
                                                return null;
                                            }

                                            $allowedCount = $leaveTypeRecord->leaves_count ?? 0;
                                            $duration = $leaveTypeRecord->duration ?? 'annual';
                                            $now = Carbon::now();

                                            switch ($duration) {
                                                case '1 month':
                                                    $startDate = $now->copy()->startOfMonth();
                                                    $periodText = 'month';
                                                    break;
                                                case '3 months':
                                                    $currentMonth = $now->month;
                                                    $startMonthOfQuarter = $currentMonth - (($currentMonth - 1) % 3);
                                                    $startDate = $now->copy()->month($startMonthOfQuarter)->startOfMonth();
                                                    $periodText = '3 months';
                                                    break;
                                                case '4 months':
                                                    $currentMonth = $now->month;
                                                    $startMonthOfTrimester = $currentMonth - (($currentMonth - 1) % 4);
                                                    $startDate = $now->copy()->month($startMonthOfTrimester)->startOfMonth();
                                                    $periodText = '4 months';
                                                    break;
                                                case '6 months':
                                                    $startDate = $now->month <= 6
                                                        ? $now->copy()->startOfYear()
                                                        : $now->copy()->month(7)->startOfMonth();
                                                    $periodText = '6 months';
                                                    break;
                                                case 'annual':
                                                default:
                                                    $startDate = $now->copy()->startOfYear();
                                                    $periodText = 'year';
                                                    break;
                                            }

                                            $leaves = \App\Models\Leave::where('user_id', $userId)
                                                ->where('leave_type', $leaveType)
                                                ->where('paid', 1)
                                                ->where('status', 'approved')
                                                ->where('starting_date', '>=', $startDate)
                                                ->get();

                                            $usedPaidLeaves = $leaves->sum(function ($leave) use ($targetUser) {
                                                if ($leave->type === 'regular') {
                                                    $start = \Carbon\Carbon::parse($leave->starting_date);
                                                    $end = \Carbon\Carbon::parse($leave->ending_date);

                                                    $dates = collect();
                                                    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                                                        $dates->push($date->format('Y-m-d'));
                                                    }

                                                    $holidays = Filament::getTenant()->holidays()
                                                        ->where(function ($query) use ($targetUser) {
                                                            $query->where('apply', 'all')
                                                                ->orWhere(function ($q) use ($targetUser) {
                                                                    $q->where('apply', 'user')
                                                                        ->whereRaw('JSON_CONTAINS(users, JSON_QUOTE(?))', [(string) $targetUser->id]);
                                                                })
                                                                ->orWhere(function ($q) use ($targetUser) {
                                                                    $q->where('apply', 'shift')
                                                                        // This line correctly uses $targetUser, which is a single object
                                                                        ->whereRaw('JSON_CONTAINS(shifts, JSON_QUOTE(?))', [(string) $targetUser->shift_id]);
                                                                })
                                                                ->orWhere(function ($q) use ($targetUser) {
                                                                    $q->where('apply', 'department')
                                                                        // This line also correctly uses $targetUser
                                                                        ->whereRaw('JSON_CONTAINS(departments, JSON_QUOTE(?))', [(string) $targetUser->department_id]);
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
                                                        $holidayStart = \Carbon\Carbon::parse($holiday->starting_date);
                                                        $holidayEnd = \Carbon\Carbon::parse($holiday->ending_date);
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

                                            if ($allowedCount == 0) {
                                                return "No paid leaves allowed for this leave type";
                                            }

                                            return "Used {$usedPaidLeaves} of {$allowedCount} paid leaves this {$periodText}";
                                        })->columns(2),


                                ])
                                ->live()
                                ->disabled(fn(Forms\Get $get) => static::isLocked($get))
                                ->columnSpan(2),

                            // Date and Time Section - Dynamically changes based on leave type
                            Forms\Components\Section::make('Date and Time')
                                ->schema([
                                    // Regular Leave Fields
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Flatpickr::make('starting_date')
                                                ->required()
                                                ->reactive()
                                                ->label('Starting Date')
                                                ->native(false)->closeOnDateSelection()
                                                ->prefixIcon('heroicon-m-calendar')
                                                ->minDate(function () {
                                                    $user = Auth::user();
                                                    $user->loadMissing('assignedShift.shift', 'assignedDepartment.department');
                                                    if ($user && $user->joining_date) {
                                                        return Carbon::parse($user->joining_date);
                                                    }
                                                    return null;
                                                }),

                                            Flatpickr::make('ending_date')
                                                ->required()
                                                ->label('Ending Date')
                                                ->afterOrEqual('starting_date')
                                                ->native(false)->closeOnDateSelection()
                                                ->prefixIcon('heroicon-m-calendar')
                                                ->minDate(function () {
                                                    $user = Auth::user();
                                                    $user->loadMissing('assignedShift.shift', 'assignedDepartment.department');
                                                    if ($user && $user->joining_date) {
                                                        return Carbon::parse($user->joining_date);
                                                    }
                                                    return null;
                                                })
                                                ->reactive() // <<< VERY IMPORTANT to make it auto-refresh
                                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                                    $startingDate = $get('starting_date');
                                                    if ($startingDate && $state) {
                                                        // Check if Ending Date is earlier than Starting Date
                                                        if (Carbon::parse($state)->lt(Carbon::parse($startingDate))) {
                                                            // Clear the invalid ending date
                                                            $set('ending_date', null);
                                                            $set('ending_date_error', 'Ending Date must be after or same as Starting Date.');
                                                        }
                                                    }
                                                })
                                                ->rule(function (Forms\Get $get) {
                                                    return function ($attribute, $value, $fail) use ($get) {
                                                        $startingDate = $get('starting_date');
                                                        $leaveType = $get('leave_type');
                                                        if (!$startingDate || !$value || !$leaveType) {
                                                            return;
                                                        }

                                                        $start = \Carbon\Carbon::parse($startingDate)->startOfDay();
                                                        $end = \Carbon\Carbon::parse($value)->endOfDay();
                                                        $user = Auth::user();
                                                        $user->loadMissing('assignedShift.shift', 'assignedDepartment.department');

                                                        // STEP 1: Build current leave dates
                                                        $currentLeaveDates = collect();
                                                        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                                                            $currentLeaveDates->push($date->format('Y-m-d'));
                                                        }

                                                        // STEP 2: Get holidays for filtering
                                                        $holidays = Filament::getTenant()->holidays()
                                                            ->where(function ($query) use ($user) {
                                                            $query->where('apply', 'all')
                                                                ->orWhere(function ($q) use ($user) {
                                                                    $q->where('apply', 'user')
                                                                        ->whereRaw('JSON_CONTAINS(users, JSON_QUOTE(?))', [(string) $user->id]);
                                                                })
                                                                ->orWhere(function ($q) use ($user) {
                                                                    $q->where('apply', 'shift')
                                                                        ->whereRaw('JSON_CONTAINS(shifts, JSON_QUOTE(?))', [(string) $user->assignedShift?->shift?->id]);
                                                                })
                                                                ->orWhere(function ($q) use ($user) {
                                                                    $q->where('apply', 'department')
                                                                        ->whereRaw('JSON_CONTAINS(departments, JSON_QUOTE(?))', [(string) $user->assignedDepartment?->department?->id]);
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
                                                            $holidayStart = \Carbon\Carbon::parse($holiday->starting_date);
                                                            $holidayEnd = \Carbon\Carbon::parse($holiday->ending_date);
                                                            for ($date = $holidayStart->copy(); $date->lte($holidayEnd); $date->addDay()) {
                                                                $holidayDates->push($date->format('Y-m-d'));
                                                            }
                                                        }

                                                        $uniqueHolidayDates = $holidayDates->unique();
                                                        $effectiveCurrentLeave = $currentLeaveDates->diff($uniqueHolidayDates)->values();

                                                        // STEP 3: Count past paid leaves of same type (exclude holidays)
                                                        $pastLeaves = \App\Models\Leave::where('user_id', $user->id)
                                                            ->where('leave_type', $leaveType)
                                                            ->where('paid', 1)
                                                            ->where('status', 'approved') // Optional: only count approved
                                                            ->get();

                                                        $pastLeaveDates = collect();
                                                        foreach ($pastLeaves as $leave) {
                                                            $leaveStart = \Carbon\Carbon::parse($leave->starting_date);
                                                            $leaveEnd = \Carbon\Carbon::parse($leave->ending_date);
                                                            for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                                                                $pastLeaveDates->push($date->format('Y-m-d'));
                                                            }
                                                        }
                                                        $effectivePastLeave = $pastLeaveDates->diff($uniqueHolidayDates)->unique()->values();

                                                        // STEP 4: Compare with leave type limit
                                                        $leaveTypeRecord = Filament::getTenant()->leaveTypes()
                                                            ->where('name', $leaveType)
                                                            ->first();

                                                        if ($leaveTypeRecord) {
                                                            $totalUsed = $effectivePastLeave->count();
                                                            $remaining = $leaveTypeRecord->leaves_count - $totalUsed;
                                                            $requested = $effectiveCurrentLeave->count();

                                                            if ($requested > $remaining) {
                                                                $fail("You have {$totalUsed} paid {$leaveType} already. Allowed: {$leaveTypeRecord->leaves_count}, Remaining: {$remaining}, Requested: {$requested}.");
                                                            }
                                                        }
                                                    };
                                                })

                                        ])
                                        ->visible(fn(Forms\Get $get) => $get('type') === 'regular')
                                        ->disabled(fn(Forms\Get $get) => static::isLocked($get))
                                        ->columns(1),

                                    // Half Day Fields
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Flatpickr::make('starting_date')
                                                ->required()
                                                ->label('Date')
                                                ->native(false)->closeOnDateSelection()
                                                ->prefixIcon('heroicon-m-calendar')
                                                ->minDate(function () {
                                                    $user = Auth::user();
                                                    if ($user && $user->joining_date) {
                                                        return Carbon::parse($user->joining_date);
                                                    }
                                                    return null;
                                                })
                                                ->live()
                                                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('ending_date', $state)),

                                            Forms\Components\Select::make('half_day_timing')
                                                ->options([
                                                    'First Time' => 'First Time',
                                                    'Second Time' => 'Second Time',
                                                ])
                                                ->required(),
                                        ])
                                        ->visible(fn(Forms\Get $get) => $get('type') === 'half_day')
                                        ->disabled(fn(Forms\Get $get) => static::isLocked($get))
                                        ->columns(1),

                                    // Short Leave Fields
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Flatpickr::make('starting_date')
                                                ->required()
                                                ->label('Date')
                                                ->native(false)->closeOnDateSelection()
                                                ->prefixIcon('heroicon-m-calendar')
                                                ->minDate(function () {
                                                    $user = Auth::user();
                                                    if ($user && $user->joining_date) {
                                                        return Carbon::parse($user->joining_date);
                                                    }
                                                    return null;
                                                })
                                                ->columnSpanFull()
                                                ->live()
                                                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('ending_date', $state)),

                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Flatpickr::make('starting_time')
                                                        ->timePicker()
                                                        ->time24hr(false)
                                                        ->required()
                                                        ->native(false)->closeOnDateSelection()
                                                        ->label('Starting Time')
                                                        ->prefixIcon('heroicon-m-clock')
                                                        ->withoutSeconds()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $endingTime = $get('ending_time');
                                                            if ($endingTime && $state >= $endingTime) {
                                                                $set('starting_time', null);
                                                                throw ValidationException::withMessages([
                                                                    'starting_time' => 'Starting time must be earlier than ending time.',
                                                                ]);
                                                            }
                                                        }),

                                                    Flatpickr::make('ending_time')
                                                        ->label('Ending Time')
                                                        ->timePicker()
                                                        ->time24hr(false)
                                                        ->required()
                                                        ->native(false)->closeOnDateSelection()
                                                        ->prefixIcon('heroicon-m-clock')
                                                        ->withoutSeconds()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $startingTime = $get('starting_time');
                                                            if ($startingTime && $startingTime >= $state) {
                                                                $set('ending_time', null);
                                                                throw ValidationException::withMessages([
                                                                    'ending_time' => 'Ending time must be later than starting time.',
                                                                ]);
                                                            }
                                                        }),
                                                ])

                                        ])
                                        ->visible(fn(Forms\Get $get) => $get('type') === 'short_leave')
                                        ->disabled(fn(Forms\Get $get) => static::isLocked($get)),
                                ])->columnSpan(2),
                        ]),

                        //Reason and Document
                        Forms\Components\Section::make('Additional Information')
                            ->schema([
                                Forms\Components\Textarea::make('leave_reason')
                                    ->label('Leave Reason')
                                    ->required()
                                    ->placeholder('Please provide details about your leave request')
                                    ->columnSpan('half'),

                                Forms\Components\FileUpload::make('document')
                                    ->directory('uploads/leave-documents')
                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                    ->maxSize(5120)
                                    ->downloadable()
                                    ->label('Attach Document (Optional)')
                                    ->helperText('Upload a document like medical certificate or approval (Max: 5MB, Supported formats: PDF, JPEG, PNG)')
                                    ->columnSpan('half')
                                    ->visible(function (Forms\Get $get) {
                                        // NEW: if user_id is empty, assume it's in creation mode
                                        if (empty($get('user_id'))) {
                                            return true;
                                        }

                                        // Otherwise normal check
                                        return Auth::id() === $get('user_id') || filled($get('document'));
                                    })
                            ])
                            ->disabled(fn(Forms\Get $get) => static::isLocked($get))
                            ->columns(2),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                // First, let's create a section that only appears for cancelled/pending/rejected cancellation leaves
                                Section::make('Cancellation Details')
                                    ->schema([
                                        Textarea::make('cancellation_reason')  // Changed from TextInput to TextEntry for read-only display
                                            ->label('Reason')
                                            ->columnSpan('half')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$record || !$record->exists) {
                                                    return null;
                                                }

                                                // Get the latest cancellation log with 'Pending Cancellation' status
                                                $cancellationLog = $record->leaveLogs()
                                                    ->where('status', 'Pending Cancellation')
                                                    ->orderByDesc('created_at')
                                                    ->first();

                                                return $cancellationLog ? $cancellationLog->remarks : 'No reason provided';
                                            }),

                                        TextInput::make('cancellation_requested_at')  // Changed from TextInput to TextEntry
                                            ->label('Requested At')
                                            ->columnSpan('half')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$record || !$record->exists) {
                                                    return null;
                                                }

                                                $cancellationLog = $record->leaveLogs()
                                                    ->where('status', 'Pending Cancellation')
                                                    ->orderByDesc('created_at')
                                                    ->first();

                                                return $cancellationLog ? $cancellationLog->created_at->format('M d, Y H:i') : '-';
                                            }),
                                    ])
                                    ->columns(2)
                                    ->disabled(fn(Forms\Get $get) => static::isLocked($get))
                                    ->visible(function ($record) {
                                        // Check if we're in edit mode and the record exists
                                        if (!$record || !$record->exists) {
                                            return false;
                                        }

                                        // Check if the status is one of the cancellation statuses
                                        if (!in_array(strtolower($record->status), ['cancelled', 'pending_cancellation', 'rejected_cancellation'])) {
                                            return false;
                                        }

                                        // Additional check for cancellation logs if needed
                                        return $record->leaveLogs()
                                            ->whereIn('status', ['Pending Cancellation', 'rejected_cancellation', 'cancelled'])
                                            ->exists();
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('last_approved_leaves')
                                    ->label('')
                                    ->content(function (Forms\Get $get, $record = null) {
                                        $userId = $record?->user_id ?? request()->get('user_id') ?? Auth::id();

                                        $leaves = \App\Models\Leave::where('user_id', $userId)
                                            ->where('status', 'approved')
                                            ->orderByDesc('updated_at')
                                            ->limit(3)
                                            ->get()
                                            ->map(function ($leave) {
                                                $duration = match ($leave->type) {
                                                    'regular' => \Carbon\Carbon::parse($leave->starting_date)->diffInDays(\Carbon\Carbon::parse($leave->ending_date)) + 1 . ' day(s) (' .
                                                    \Carbon\Carbon::parse($leave->starting_date)->format('M d, Y') . ' - ' .
                                                    \Carbon\Carbon::parse($leave->ending_date)->format('M d, Y') . ')',
                                                    'half_day' => 'Half day on ' . \Carbon\Carbon::parse($leave->starting_date)->format('M d, Y'),
                                                    'short_leave' => 'Short leave on ' . \Carbon\Carbon::parse($leave->starting_date)->format('M d, Y'),
                                                    default => '',
                                                };

                                                $approvalDate = optional(
                                                    $leave->leaveLogs()
                                                        ->where('status', 'approved')
                                                        ->orderByDesc('created_at')
                                                        ->first()
                                                )->created_at?->format('M d, Y H:i') ?? $leave->updated_at?->format('M d, Y H:i');

                                                return (object) [
                                                    'leave_type' => $leave->leave_type,
                                                    'duration' => $duration,
                                                    'leave_reason' => $leave->leave_reason,
                                                    'approved_at' => $approvalDate,
                                                ];
                                            });

                                        return view('filament.client.components.last-approved-leave', [
                                            'leaves' => $leaves,
                                        ]);
                                    })
                                    ->columnSpan('half')
                                    ->visible(function ($record) {
                                        $userId = $record?->user_id ?? request()->get('user_id') ?? Auth::id();
                                        return \App\Models\Leave::where('user_id', $userId)->where('status', 'approved')->exists();
                                    }),


                                Forms\Components\Placeholder::make('leave_balance_table')
                                    ->label('')
                                    ->content(function ($record, Get $get) {
                                        // Get the user ID from the record if it exists, otherwise use the currently logged-in user
                                        $userId = null;

                                        if ($record && $record->exists) {
                                            // For existing leaves, get the user_id from the record
                                            $userId = $record->user_id;
                                        } else {
                                            // For new leaves, use the user_id from the form or default to current user
                                            $userId = $get('user_id') ?? Auth::id();
                                        }

                                        // Get the user to retrieve their details
                                        $user = \App\Models\User::find($userId);

                                        if (!$user) {
                                            return 'User not found';
                                        }

                                        // Gender/marital status filtering logic (optional)
                                        $gender = strtolower(trim($user->gender ?? ''));
                                        $marital = strtolower(trim($user->martial_status ?? ''));
                                        $applyOn = $gender && $marital ? "{$gender}_{$marital}" : 'all';

                                        // Get eligible leave types
                                        $leaveTypes = Filament::getTenant()->leaveTypes()
                                            ->where(function ($query) use ($applyOn) {
                                            $query->where('apply_on', 'all')
                                                ->orWhere('apply_on', $applyOn);
                                        })
                                            ->get();

                                        $now = now();
                                        $balances = [];

                                        foreach ($leaveTypes as $type) {
                                            $startDate = match ($type->duration) {
                                                '1 month' => $now->copy()->startOfMonth(),
                                                '3 months' => $now->copy()->month($now->quarter * 3 - 2)->startOfMonth(),
                                                '4 months' => $now->copy()->month(ceil($now->month / 4) * 4 - 3)->startOfMonth(),
                                                '6 months' => $now->month <= 6 ? $now->copy()->startOfYear() : $now->copy()->month(7)->startOfMonth(),
                                                default => $now->copy()->startOfYear(),
                                            };

                                            $used = \App\Models\Leave::where('user_id', $userId)
                                                ->where('leave_type', $type->name)
                                                ->where('paid', 1)
                                                ->where('status', 'approved')
                                                ->where('starting_date', '>=', $startDate)
                                                ->get()
                                                ->sum(function ($leave) {
                                                    return match ($leave->type) {
                                                        'regular' => \Carbon\Carbon::parse($leave->starting_date)->diffInDays(\Carbon\Carbon::parse($leave->ending_date)) + 1,
                                                        'half_day' => 0.5,
                                                        'short_leave' => 0.25,
                                                        default => 0,
                                                    };
                                                });

                                            $total = $type->leaves_count ?? 0;
                                            $balances[] = [
                                                'name' => $type->name,
                                                'total' => $total,
                                                'used' => $used,
                                                'remaining' => max(0, $total - $used),
                                            ];
                                        }

                                        // Add user information to the view data
                                        return view('filament.client.components.leave-balance-table', [
                                            'balances' => $balances,
                                            'user' => $user,  // Pass the user to the view in case you want to display user info
                                        ]);
                                    })->columnSpan('half'),

                            ]),

                        Select::make('approval_status')
                            ->label('Approval')
                            ->required()
                            ->options(function (Forms\Get $get) {
                                $leaveId = $get('id');
                                if (!$leaveId) {
                                    return ['pending' => 'Pending'];
                                }

                                $leave = \App\Models\Leave::find($leaveId);
                                if (!$leave) {
                                    return ['pending' => 'Pending'];
                                }

                                // Handle cancellation request
                                if ($leave->status === 'pending_cancellation') {
                                    $currentUserRoleId = Auth::user()->roles->first()->id ?? null;
                                    $approvalSteps = DB::table('approval_steps')
                                        ->where('team_id', Filament::getTenant()->id)
                                        ->where('user_id', $leave->user_id)
                                        ->pluck('role_id')
                                        ->toArray();

                                    return in_array($currentUserRoleId, $approvalSteps)
                                        ? [
                                            'cancelled' => 'Approve Cancellation',
                                            'rejected_cancellation' => 'Reject Cancellation',
                                        ]
                                        : [];
                                }

                                // Standard approval flow for other leave requests
                                $currentUserRoleId = Auth::user()->roles->first()->id ?? null;
                                $currentLevel = (int) ($leave->leaveLogs()->max('level') ?? 0);
                                $currentLevel = $currentLevel === 0 ? 1 : $currentLevel;

                                // Get the approval steps for the current level for this specific user's leave
                                $approvalSteps = DB::table('approval_steps')
                                    ->where('team_id', Filament::getTenant()->id)
                                    ->where('user_id', $leave->user_id) // Ensure we match the leave's user
                                    ->where('level', $currentLevel)
                                    ->get();

                                // Permission and next level handling
                                $permission = null;
                                $nextLevel = null;
                                foreach ($approvalSteps as $step) {
                                    if ((int) $step->role_id === $currentUserRoleId) {
                                        $permission = $step->permission;
                                    }

                                    // Find the next level
                                    if ($step->level > $currentLevel) {
                                        $nextLevel = $step->level;
                                    }
                                }

                                // Determine the options based on permission
                                if ($permission === 'recommend') {
                                    return [
                                        'forwarded' => 'Forward',
                                        'rejected' => 'Reject',
                                    ];
                                } elseif ($permission === 'approve') {
                                    return [
                                        'approved' => 'Approve',
                                        'rejected' => 'Reject',
                                    ];
                                }

                                // If no specific permission, just show pending
                                if ($nextLevel) {
                                    return ['pending' => 'Pending'];
                                }

                                return ['pending' => 'Pending'];
                            })
                            ->default('pending')
                            ->reactive()
                            ->disabled(function (Forms\Get $get) {
                                $leaveId = $get('id');
                                if (!$leaveId) {
                                    return true; // Disable if no leave ID exists
                                }

                                $leave = \App\Models\Leave::find($leaveId);
                                if (!$leave) {
                                    return true; // Disable if no leave record is found
                                }

                                // If it's a cancellation request, only allow approvers to see the dropdown
                                if ($leave->status === 'pending_cancellation') {
                                    $currentUserRoleId = Auth::user()->roles->first()->id ?? null;
                                    $approvalSteps = DB::table('approval_steps')
                                        ->where('team_id', Filament::getTenant()->id)
                                        ->where('user_id', $leave->user_id) // Ensure it's for the correct user
                                        ->pluck('role_id')
                                        ->toArray();

                                    return !in_array($currentUserRoleId, $approvalSteps);
                                }

                                // Standard approval flow: Check if the current user is authorized to approve at the current level
                                $userRole = Auth::user()->roles->first();
                                if (!$userRole) {
                                    return true; // Disable if user has no role
                                }

                                $currentUserRoleId = $userRole->id;
                                $currentLevel = (int) ($leave->leaveLogs()->max('level') ?? 0);
                                $currentLevel = $currentLevel === 0 ? 1 : $currentLevel;

                                // Check if the user is authorized to approve at the current level for this specific user's leave
                                $approvalSteps = DB::table('approval_steps')
                                    ->where('team_id', Filament::getTenant()->id)
                                    ->where('user_id', $leave->user_id) // Ensure it's for the correct user
                                    ->where('level', $currentLevel)
                                    ->get();

                                // Ensure that the user is authorized for the current level
                                foreach ($approvalSteps as $step) {
                                    if ((int) $step->role_id === $currentUserRoleId) {
                                        return false; // User is authorized to approve, so enable the dropdown
                                    }
                                }

                                return true; // Disable the dropdown if user is not authorized
                            })
                            ->visible(function (Forms\Get $get) {
                                $leaveId = $get('id');
                                if (!$leaveId) {
                                    return false;
                                }

                                $leave = \App\Models\Leave::find($leaveId);
                                if (!$leave) {
                                    return false;
                                }

                                // If it's a cancellation request, only allow approvers to see the dropdown
                                if ($leave->status === 'pending_cancellation') {
                                    $currentUserRoleId = Auth::user()->roles->first()->id ?? null;
                                    $approvalSteps = DB::table('approval_steps')
                                        ->where('team_id', Filament::getTenant()->id)
                                        ->where('user_id', $leave->user_id) // Ensure it's for the correct user
                                        ->pluck('role_id')
                                        ->toArray();

                                    return in_array($currentUserRoleId, $approvalSteps); // Only show for approvers
                                }

                                // Standard approval flow: Check if the current user is authorized to approve at the current level
                                $userRole = Auth::user()->roles->first();
                                if (!$userRole) {
                                    return false; // Hide if user has no role
                                }

                                $currentUserRoleId = $userRole->id;
                                $currentLevel = (int) ($leave->leaveLogs()->max('level') ?? 0);
                                $currentLevel = $currentLevel === 0 ? 1 : $currentLevel;

                                // Check if the current user is authorized to approve at the current level for this specific user's leave
                                $approvalSteps = DB::table('approval_steps')
                                    ->where('team_id', Filament::getTenant()->id)
                                    ->where('user_id', $leave->user_id) // Ensure it's for the correct user
                                    ->where('level', $currentLevel)
                                    ->get();

                                // Ensure that the user is authorized for the current level
                                foreach ($approvalSteps as $step) {
                                    if ((int) $step->role_id === $currentUserRoleId) {
                                        return true; // Show if user is authorized
                                    }
                                }

                                return false; // Hide if user is not authorized
                            })
                            ->columns(1),
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->visible(fn(Get $get) => $get('approval_status') === 'rejected')
                            ->columnSpanFull(),
                    ])

            ]);
    }
    public static function isLocked(Forms\Get $get): bool
    {
        $leaveId = $get('id');
        // Not locked during creation
        if (!$leaveId) {
            return false;
        }

        $leave = \App\Models\Leave::with('leaveLogs')->find($leaveId);
        if (!$leave) {
            // Record not found, should be non-editable
            return true;
        }

        // Rule 1: Final statuses are always locked for everyone.
        $finalStatuses = ['cancelled', 'approved', 'rejected', 'rejected_cancellation'];
        if (in_array($leave->status, $finalStatuses)) {
            return true;
        }

        // Rule 2: The creator can edit only when the status is 'pending'.
        // Once forwarded or under cancellation, the creator cannot edit.
        if (Auth::id() === $leave->user_id) {
            return $leave->status !== 'pending';
        }

        // Rule 3: Check if the current user is an authorized approver/recommender for the current step.
        $userRole = Auth::user()->roles->first();
        if (!$userRole) {
            return true; // Lock if the viewing user has no assigned role.
        }
        $currentUserRoleId = $userRole->id;

        $levelForAction = (int) ($leave->leaveLogs()->max('level') ?? 0);

        // Check if the current user's role is in the approval hierarchy for this leave's user at the correct level.
        $isAuthorized = DB::table('approval_steps')
            ->where('team_id', Filament::getTenant()->id)
            ->where('user_id', $leave->user_id) // For the user who requested the leave
            ->where('level', $levelForAction)
            ->where('role_id', $currentUserRoleId)
            ->exists();

        // If the user is authorized for the current approval step, the form is NOT locked.
        if ($isAuthorized) {
            return false;
        }

        // Rule 4: Default to locked for anyone else.
        return true;
    }
    public static function table(Table $table): Table
    {
        return $table
            // ->query(Leave::query()->with('leaveLogs.role'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function ($record) {
                        if ($record->type === 'regular') {
                            $start = Carbon::parse($record->starting_date);
                            $end = Carbon::parse($record->ending_date);
                            $days = $start->diffInDays($end) + 1;
                            return "{$days} Full Day" . ($days > 1 ? 's' : '');
                        }
                        if ($record->type === 'half_day') {
                            return 'Half Day';
                        }
                        if ($record->type === 'short_leave') {
                            $startTime = Carbon::parse($record->starting_time);
                            $endTime = Carbon::parse($record->ending_time);
                            $minutes = round($startTime->diffInMinutes($endTime));
                            return "{$minutes} minutes";
                        }
                        return 'N/A';
                    })->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('leave_type')
                    ->label('Type')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'Paid' : 'Unpaid')
                    ->color(fn(bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('starting_time')
                    ->label('Date')
                    ->getStateUsing(function ($record) {
                        $date = $record->starting_date
                            ? \Carbon\Carbon::parse($record->starting_date)->format('M d, Y')
                            : null;

                        $time = $record->starting_time
                            ? \Carbon\Carbon::parse($record->starting_time)->format('H:i A')
                            : null;

                        if ($date && $time) {
                            return "$date $time";
                        }

                        if ($date) {
                            return $date;
                        }

                        return '-';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('leave_reason')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->leave_reason),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created On')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->description(
                        fn(Leave $record): string =>
                        Str::words($record->rejection_reason ?? '', 5, '...')
                    )
                    ->formatStateUsing(function ($state, $record) {
                        return self::leaveStatus($state, $record);
                    })
                    ->badge()
                    ->sortable()
                    ->color(
                        fn(string $state): string =>
                        match ($state) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        }
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->placeholder('Employee')
                    ->searchable()
                    ->options(fn() => Filament::getTenant()->filteredUsers()->where('active', 1)->where('attendance_config', 1)->pluck('name', 'id'))->indicateUsing(
                        fn() => null
                    ),


                Tables\Filters\SelectFilter::make('type')
                    ->placeholder('Leave Type')
                    ->searchable()
                    ->options([
                        'regular' => 'Full Day Leave',
                        'half_day' => 'Half Day',
                        'short_leave' => 'Short Leave',
                    ])->indicateUsing(
                        fn() => null
                    ),
                Tables\Filters\SelectFilter::make('paid')
                    ->placeholder('Paid/Unpaid')
                    ->searchable()
                    ->options([
                        1 => 'Paid',
                        0 => 'Unpaid',
                    ])->indicateUsing(
                        fn() => null
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->placeholder('Status')
                    ->searchable()
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'pending_cancellation' => 'Pending Cancellation',
                    ])->indicateUsing(
                        fn() => null
                    ),
                DateRangeFilter::make('starting_date')
                    ->icon('heroicon-o-arrow-path')
                    ->startDate(Carbon::now()->startOfMonth())
                    ->endDate(Carbon::now())
                    ->maxDate(Carbon::now())->indicateUsing(
                        fn() => null
                    ),

            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(Leave $record): string => static::getUrl('edit', ['record' => $record]))
                    ->visible(fn($record) => in_array($record->status, ['cancelled', 'approved', 'rejected', 'rejected_cancellation'])),
                Tables\Actions\Action::make('manage')
                    ->icon('heroicon-s-pencil-square')
                    ->url(
                        fn(Leave $record): string =>
                        static::getUrl('edit', ['record' => $record]) .
                        (request()->getQueryString() ? '?' . request()->getQueryString() : '')
                    )
                    ->visible(fn($record) => in_array($record->status, ['pending', 'forwarded', 'pending_cancellation'])),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Leave $record) {
                        $pendingLog = $record->leaveLogs()->where('status', 'pending')->orderBy('level', 'asc')->first();
                        if (!$pendingLog)
                            return;

                        $currentUser = Auth::user();
                        $currentRole = $currentUser->roles->first();
                        $currentLevel = $pendingLog->level;

                        // Determine permission (recommend/approve)
                        $approvalStep = DB::table('approval_steps')
                            ->where('team_id', Filament::getTenant()->id)
                            ->where('user_id', $record->user_id)
                            ->where('level', $currentLevel)
                            ->where('role_id', $currentRole->id)
                            ->first();

                        $actionStatus = ($approvalStep && $approvalStep->permission === 'approve') ? 'approved' : 'forwarded';

                        // Update the pending log
                        $pendingLog->update([
                            'role_id' => $currentRole->id,
                            'status' => $actionStatus,
                        ]);

                        // Check for next level
                        $nextLevel = $currentLevel + 1;
                        $nextStep = DB::table('approval_steps')
                            ->where('team_id', Filament::getTenant()->id)
                            ->where('user_id', $record->user_id)
                            ->where('level', $nextLevel)
                            ->first();

                        if ($nextStep && $actionStatus === 'forwarded') {
                            $record->update(['status' => 'forwarded']);
                            LeaveLog::create([
                                'leave_id' => $record->id,
                                'role_id' => $nextStep->role_id,
                                'level' => $nextLevel,
                                'status' => 'pending',
                            ]);
                        } else {
                            $record->update(['status' => 'approved']);
                        }
                    })
                    ->visible(function (Leave $record): bool {
                        $user = Auth::user();
                        if (!$user->hasRole(['Admin', 'CEO', 'AMS Manager']) && !$user->is_approver) {
                            return false;
                        }
                        if (!in_array($record->status, ['pending', 'forwarded'])) {
                            return false;
                        }
                        $pendingLog = $record->leaveLogs()->where('status', 'pending')->orderBy('level', 'asc')->first();
                        if (!$pendingLog) {
                            return false;
                        }
                        return $pendingLog->role_id === $user->roles->first()->id;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->status === 'pending' && Auth::id() === $record->user_id),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-c-x-circle')
                    ->action(function ($record, $data) {
                        // Handle cancellation reason input
                        $cancellationReason = $data['cancellation_reason'] ?? null;

                        // Update the record status
                        $record->update([
                            'status' => 'pending_cancellation',
                        ]);

                        // Get the current user's role ID
                        $roleId = Auth::user()->roles->first()->id ?? 0;

                        // Store the cancellation log with the reason
                        $record->leaveLogs()->create([
                            'leave_id' => $record->id,
                            'role_id' => $roleId,
                            'status' => 'Pending Cancellation',
                            'level' => ($record->leaveLogs()->max('level') ?? 0) + 1,
                            'remarks' => $cancellationReason,  // Store cancellation reason in remarks
                        ]);
                    })
                    ->requiresConfirmation(function (Tables\Actions\Action $action, $record) {
                        $startDate = \Carbon\Carbon::parse($record->starting_date)->format('d M Y');
                        $endDate = \Carbon\Carbon::parse($record->ending_date)->format('d M Y');

                        $action->modalHeading('Cancel Leave Request');
                        $action->modalDescription("Leave Duration: {$startDate} to {$endDate}");
                        $action->modalSubmitActionLabel('Request Cancellation');

                        return $action;
                    })

                    ->color('warning')
                    ->visible(function ($record) {
                        $hasRejectedCancellation = $record->leaveLogs()
                            ->where('status', 'rejected_cancellation')
                            ->exists();

                        return strtolower($record->status) === 'approved'
                            && Auth::id() === $record->user_id
                            && !$hasRejectedCancellation;
                    })
                    ->form([
                        TextInput::make('cancellation_reason')
                            ->label('Reason')
                            ->placeholder('Enter the reason for cancellation')
                            ->required()
                            ->maxLength(255),
                    ])
            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaves::route('/'),
            'create' => Pages\CreateLeave::route('/create'),
            'edit' => Pages\EditLeave::route('/{record}/edit'),
        ];
    }
    private static function leaveStatus($state, $record): string
    {
        if ($record->status == 'approved') {
            return 'Approved';
        }

        $team_id = Filament::getTenant()->id;
        $latestLog = $record->leaveLogs()->latest('created_at')->first();
        if ($latestLog->status == 'Pending Cancellation') {
            return 'Pending Cancellation';
        }
        if ($latestLog->status == 'cancelled') {
            return 'Cancelled';
        }
        if ($latestLog->status == 'rejected_cancellation') {
            return 'Rejected Cancellation';
        }
        // Fetch hierarchy steps
        $hierarchyStep = DB::table('approval_steps')
            ->where('team_id', $team_id)
            ->where('user_id', $record->user_id)
            ->where('level', $latestLog->level)
            ->orderBy('level', 'asc')
            ->first();
        $role = Role::find($hierarchyStep->role_id);
        if ($latestLog) {
            $roleName = $role->name ?? 'Unknown Role';
            return "{$roleName}";
        }
        return 'N/A';
    }
}
