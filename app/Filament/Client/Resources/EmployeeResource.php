<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Models\User;
use App\Filament\Client\Resources\EmployeeResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Facades\Filament;
use JaOcero\RadioDeck\Forms\Components\RadioDeck;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class EmployeeResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $tenantOwnershipRelationshipName = 'teams';
    protected static ?string $model = User::class;
    protected static ?string $panel = 'client';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Employees';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationBadgeTooltip = 'Active Staff';
    protected static ?string $modelLabel = 'Employees';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['assignedShift.shift', 'assignedDepartment.department', 'approvalSteps.role'])
            ->visibleToCurrentUser();
    }

    public static function getActiveNavigationIcon(): string|Htmlable|null
    {
        return str(self::getNavigationIcon())->replace('heroicon-o', 'heroicon-s')->toString();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('active', 1)
            ->whereDoesntHave(
                'roles',
                fn($q) =>
                $q->whereIn('name', ['Admin', 'AMS Manager', 'Payroll Manager', 'CEO'])
            )
            ->count();
    }

    public static function getDocumentation(): array
    {
        return [
            'employees.introduction',
            KnowledgeBase::model()::find('employees.working'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Card::make()
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Tabs::make('Employee Details')
                        ->tabs([
                            // Tab 1: Information
                            Forms\Components\Tabs\Tab::make('Basic Information')
                                ->icon('heroicon-c-pencil-square')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('id')
                                                ->label('Employee ID')
                                                ->disabled()
                                                ->visible(fn(callable $get) => !empty($get('id'))),
                                            Forms\Components\TextInput::make('name')
                                                ->label('Employee Name')
                                                ->required(),
                                            Forms\Components\TextInput::make('father_name')
                                                ->label('Father Name'),
                                            Forms\Components\Select::make('gender')
                                                ->label('Gender')
                                                ->required()
                                                ->options([
                                                    'male' => 'Male',
                                                    'female' => 'Female',
                                                ]),
                                            DatePicker::make('date_of_birth')
                                                ->label('Date Of Birth')
                                                ->native(false)
                                                ->closeOnDateSelection()
                                                ->required(),
                                            Forms\Components\TextInput::make('blood_group')
                                                ->label('Blood Group'),
                                            Forms\Components\TextInput::make('cnic')
                                                ->label('NIC Number'),
                                            Forms\Components\TextInput::make('phone_number')
                                                ->label('Phone Number'),
                                            DatePicker::make('joining_date')
                                                ->label('Joining Date')
                                                ->native(false)
                                                ->closeOnDateSelection()
                                                ->required(),
                                            DatePicker::make('probation')
                                                ->label('Probation End Date')
                                                ->native(false)
                                                ->closeOnDateSelection()
                                                ->hint('Leave blank if not applicable')
                                                ->reactive(),
                                            Forms\Components\TextInput::make('emergency_person')
                                                ->label('Emergency Contact Name'),
                                            Forms\Components\TextInput::make('emergency_contact')
                                                ->label('Emergency Phone Number'),
                                            Forms\Components\Select::make('martial_status')
                                                ->label('Marital Status')
                                                ->required()
                                                ->options([
                                                    'single' => 'Single',
                                                    'married' => 'Married',
                                                ]),

                                            Forms\Components\TextInput::make('designation')
                                                ->label('Designation')
                                                ->required(),

                                            // Department Selection
                                            Forms\Components\Select::make('department_id')
                                                ->options(fn() => Filament::getTenant()->departments()->pluck('name', 'id'))
                                                ->label('Department')
                                                ->searchable()
                                                ->preload()
                                                ->required(),

                                            Forms\Components\Textarea::make('address')
                                                ->label('Address'),
                                        ]),
                                ]),
                            // Tab 2: Attendance Configuration
                            Forms\Components\Tabs\Tab::make('Attendance Configuration')
                                ->icon('heroicon-m-finger-print')
                                ->schema([
                                    Forms\Components\Toggle::make('attendance_config')
                                        ->onIcon('heroicon-s-check')
                                        ->offIcon('heroicon-s-x-mark')
                                        ->label('Enable Attendance')
                                        ->default(false)
                                        ->reactive(),

                                    Forms\Components\Radio::make('attendance_type')
                                        ->label('')
                                        ->options([
                                            'onsite' => 'On Site',
                                            'offsite' => 'Remote ',
                                            'hybrid' => 'Hybrid',
                                        ])
                                        ->columnSpanFull()
                                        ->visible(fn(callable $get) => $get('attendance_config'))
                                        ->reactive()
                                        ->afterStateUpdated(function (callable $set, $state) {
                                            if ($state === 'offsite') {
                                                $set('shift_id', null);
                                                $set('devices', null);
                                            }
                                        }),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Select::make('shift_id')
                                                ->label('Shift')
                                                ->options(fn() => Filament::getTenant()->shifts()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(fn(callable $get) => $get('attendance_config') && in_array($get('attendance_type'), ['onsite', 'hybrid']))
                                                ->visible(fn(callable $get) => $get('attendance_config') && in_array($get('attendance_type'), ['onsite', 'hybrid']))
                                                ->dehydrated(),

                                            // Device Selection - visible for Onsite
                                            Forms\Components\Select::make('devices')
                                                ->label('Devices')
                                                ->multiple()
                                                ->options(fn() => Filament::getTenant()->devices()->pluck('device_name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn(callable $get) => $get('attendance_config') && $get('attendance_type') === 'onsite')
                                                ->dehydrated(),

                                            // Hours Input - visible for Offsite and Hybrid
                                            Forms\Components\TextInput::make('hours_required')
                                                ->label('Number of Work Hours')
                                                ->hint('Total work hours of remote attendance per day')
                                                ->numeric()
                                                ->minValue(1)
                                                ->required()
                                                ->visible(fn(callable $get) => $get('attendance_config') && in_array($get('attendance_type'), ['offsite', 'hybrid'])),

                                            // Weekdays Selection - visible for Hybrid only
                                            Forms\Components\Select::make('work_days')
                                                ->label('Onsite Days')
                                                ->hint('Days for which onsite attendance is required')
                                                ->multiple()
                                                ->options([
                                                    'monday' => 'Monday',
                                                    'tuesday' => 'Tuesday',
                                                    'wednesday' => 'Wednesday',
                                                    'thursday' => 'Thursday',
                                                    'friday' => 'Friday',
                                                    'saturday' => 'Saturday',
                                                    'sunday' => 'Sunday',
                                                ])
                                                ->required()
                                                ->visible(fn(callable $get) => $get('attendance_config') && $get('attendance_type') === 'hybrid'),
                                        ]),
                                ]),

                            // Tab 3: Leave Approval Hierarchy
                            Forms\Components\Tabs\Tab::make('Leave Approval Hierarchy')
                                ->icon('heroicon-m-check-circle')
                                ->visible(fn(callable $get) => $get('attendance_config') && !Auth::user()->hasRole('Payroll Manager'))
                                ->schema([
                                    Forms\Components\Repeater::make('approval_steps')
                                        ->label('Hierarchy')
                                        ->relationship('approvalSteps')
                                        ->schema([
                                            Forms\Components\Hidden::make('team_id')
                                                ->default(fn() => Filament::getTenant()->id),
                                            Forms\Components\Hidden::make('user_id')
                                                ->default(fn(callable $get) => $get('id')),

                                            Forms\Components\Select::make('role_id')
                                                ->label('Role')
                                                ->options(fn() => Filament::getTenant()->roles()->pluck('name', 'id'))
                                                ->required(),

                                            Forms\Components\Select::make('permission')
                                                ->label('Permission')
                                                ->options([
                                                    'recommend' => 'Recommender',
                                                    'approve' => 'Approver',
                                                ])
                                                ->required(),

                                            Forms\Components\TextInput::make('level')
                                                ->label('Approval Level')
                                                ->numeric()
                                                ->minValue(1)
                                                ->required()
                                                ->helperText('The minimum level is 1'),
                                        ])
                                        ->columns(3)
                                        ->createItemButtonLabel('Add Level')
                                        ->reorderable(false),
                                ]),
                            // Tab 4: Important Documents
                            Forms\Components\Tabs\Tab::make('Important Documents')
                                ->icon('heroicon-m-document-arrow-down')
                                ->schema([
                                    Forms\Components\FileUpload::make('documents')
                                        ->label('Upload Files')
                                        ->hint('Max size: 2MB each')
                                        ->directory('uploads/employee_documents')
                                        ->downloadable()
                                        ->openable()
                                        ->appendFiles()
                                        ->maxSize(2024)
                                        ->maxFiles(5)
                                        ->multiple()
                                        ->imagePreviewHeight('250')
                                        ->removeUploadedFileButtonPosition('right')
                                        ->preserveFilenames()
                                        ->panelLayout('grid'),
                                ]),

                            Forms\Components\Tabs\Tab::make('Salary Information')
                                ->icon('heroicon-m-currency-dollar')
                                ->visible(fn() => Auth::user()->hasRole(['Admin', 'Payroll Manager']) || Auth::user()->can('payroll.manage'))
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('bank_details.salary_currency')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                                if (filled($state))
                                                    return;

                                                $currency = null;
                                                $companyDetail = Filament::getTenant();
                                                if ($companyDetail && $companyDetail->country_id) {
                                                    $taxSlab = \App\Models\TaxSlabs::where('country_id', $companyDetail->country_id)->first();
                                                    if ($taxSlab && !empty($taxSlab->salary_currency)) {
                                                        $currency = $taxSlab->salary_currency;
                                                    }
                                                }
                                                $component->state($currency);
                                            })
                                            ->hidden()
                                            ->dehydrated(fn($state) => filled($state))
                                            ->default(function () {
                                                $companyDetail = Filament::getTenant();
                                                $currency = null;
                                                if ($companyDetail && $companyDetail->country_id) {
                                                    $taxSlab = \App\Models\TaxSlabs::where('country_id', $companyDetail->country_id)->first();
                                                    if ($taxSlab && !empty($taxSlab->salary_currency)) {
                                                        $currency = $taxSlab->salary_currency;
                                                    }
                                                }
                                                return $currency;
                                            }),

                                        Forms\Components\TextInput::make('bank_details.base_salary')
                                            ->label('Gross Salary')
                                            ->numeric()
                                            ->prefix(fn(callable $get) => $get('bank_details.salary_currency'))
                                            ->minValue(0)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $percentage = $get('bank_details.statutory_component_percentage');
                                                if (is_numeric($state) && is_numeric($percentage)) {
                                                    $divisor = ($percentage + 100) / 100;
                                                    if ($divisor != 0) {
                                                        $adjusted_gross = $state / $divisor;
                                                        $set('bank_details.statutory_component_amount', round($state - $adjusted_gross));
                                                    }
                                                }
                                            }),
                                    ]),

                                    Forms\Components\Fieldset::make('Statutory Component')
                                        ->schema([
                                            Forms\Components\Select::make('bank_details.statutory_component_category_id')
                                                ->label('Component Name')
                                                ->options(\App\Models\SalaryComponentCategory::pluck('name', 'id'))
                                                ->createOptionForm([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('Category Name')
                                                        ->required()
                                                        ->unique(table: 'salary_component_categories', column: 'name'),
                                                    Forms\Components\Hidden::make('team_id')->default(fn() => Filament::getTenant()->id),
                                                    Forms\Components\Hidden::make('is_default')->default(false),
                                                ])
                                                ->createOptionUsing(function (array $data): int {
                                                    $category = \App\Models\SalaryComponentCategory::create($data);
                                                    return $category->id;
                                                })
                                                ->preload(),

                                            Forms\Components\TextInput::make('bank_details.statutory_component_percentage')
                                                ->label('Percentage')
                                                ->numeric()
                                                ->suffix('% of gross salary')
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $grossSalary = $get('bank_details.base_salary');
                                                    if (is_numeric($grossSalary) && is_numeric($state) && $state > 0) {
                                                        $divisor = ($state + 100) / 100;
                                                        if ($divisor != 0) {
                                                            $adjusted_gross = $grossSalary / $divisor;
                                                            $set('bank_details.statutory_component_amount', round($grossSalary - $adjusted_gross));
                                                        }
                                                    }
                                                }),

                                            Forms\Components\TextInput::make('bank_details.statutory_component_amount')
                                                ->label('Amount')
                                                ->prefix(fn(callable $get) => $get('bank_details.salary_currency'))
                                                ->numeric()
                                                ->readOnly()
                                                ->dehydrated(true)
                                                ->formatStateUsing(fn($state) => $state !== null ? round($state) : null)
                                        ])
                                        ->columns(3)
                                        ->afterStateHydrated(function (callable $set, callable $get) {
                                            // Calculate amount when fieldset is hydrated
                                            $grossSalary = $get('bank_details.base_salary');
                                            $percentage = $get('bank_details.statutory_component_percentage');

                                            if (is_numeric($grossSalary) && is_numeric($percentage) && $percentage > 0) {
                                                $divisor = ($percentage + 100) / 100;
                                                if ($divisor != 0) {
                                                    $adjusted_gross = $grossSalary / $divisor;
                                                    $set('bank_details.statutory_component_amount', round($grossSalary - $adjusted_gross));
                                                }
                                            }
                                        }),

                                    RadioDeck::make('bank_details.payment_method')
                                        ->label('Payment Method')
                                        ->options([
                                            'bank_transfer' => 'Bank Transfer',
                                            'cheque' => 'Cheque',
                                            'cash' => 'Cash',
                                        ])
                                        ->icons([
                                            'bank_transfer' => 'heroicon-c-building-library',
                                            'cheque' => 'heroicon-s-pencil',
                                            'cash' => 'heroicon-m-banknotes',
                                        ])
                                        ->columns(3)
                                        ->live(),

                                    Forms\Components\Fieldset::make('Bank Information')
                                        ->schema([
                                            Forms\Components\TextInput::make('bank_details.account_holder_name')
                                                ->label('Account Holder Name')
                                                ->required(),

                                            Forms\Components\TextInput::make('bank_details.bank_name')
                                                ->label('Bank Name')
                                                ->required(),

                                            Forms\Components\TextInput::make('bank_details.account_number')
                                                ->label('Bank Account Number')
                                                ->required(),
                                        ])
                                        ->columns(2)
                                        ->visible(fn($get) => $get('bank_details.payment_method') === 'bank_transfer'),

                                    Forms\Components\Fieldset::make('Funds')
                                        ->schema([
                                            Forms\Components\CheckboxList::make('funds_ids')
                                                ->label('')
                                                ->options(function () {
                                                    $team = Filament::getTenant();
                                                    if (!$team)
                                                        return [];

                                                    return \App\Models\Fund::where('team_id', $team->id)
                                                        ->where('is_active', true)
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->columns(2)
                                        ])->visible(fn($get) => self::checkFunds()),
                                ]),

                            // Tab 6: Account Setting
                            Forms\Components\Tabs\Tab::make('Account Setting')
                                ->icon('heroicon-m-home')
                                ->visible(fn() => !Auth::user()->hasRole('Payroll Manager'))
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('email')
                                                ->label('Email')
                                                ->email()
                                                ->required()
                                                ->unique(ignoreRecord: true),
                                            Forms\Components\TextInput::make('password')
                                                ->label('Password')
                                                ->password()
                                                ->revealable()
                                                ->rule('min:8')
                                                ->nullable()
                                                ->dehydrated(fn($state) => filled($state))
                                                ->dehydrateStateUsing(fn($state) => bcrypt($state))
                                                ->helperText('Leave blank to keep the current password.')
                                                ->afterStateHydrated(fn($set) => $set('password', '')),
                                            Forms\Components\Select::make('role')
                                                ->label('Role')
                                                ->options(fn() => Filament::getTenant()->roles()->where('is_default', false)->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                        ]),
                                    Forms\Components\Toggle::make('active')
                                        ->onIcon('heroicon-s-check')
                                        ->offIcon('heroicon-s-x-mark')
                                        ->label('Account Active')
                                        ->default(true),
                                ]),
                            // Tab 7: Resignation / Termination
                            Forms\Components\Tabs\Tab::make('Resignation / Termination')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->visible(fn() => !Auth::user()->hasRole('Payroll Manager') && request()->routeIs('filament.client.resources.employees.edit'))
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Toggle::make('resigned')
                                                ->onIcon('heroicon-s-check')
                                                ->offIcon('heroicon-s-x-mark')
                                                ->label('Resign / Terminate')
                                                ->helperText('Mark this if the employee has resigned or been terminated')
                                                ->reactive(),
                                            DatePicker::make('resign_date')
                                                ->label('Resignation / Termination Date')
                                                ->required()
                                                ->native(false)->closeOnDateSelection()
                                                ->hidden(fn(callable $get) => !$get('resigned')),
                                        ]),
                                    Forms\Components\Grid::make(1)
                                        ->schema([
                                            Forms\Components\Textarea::make('remarks')
                                                ->label('Remarks')
                                                ->required()
                                                ->hidden(fn(callable $get) => !$get('resigned')),
                                        ]),
                                ]),
                        ])
                ])
        ]);
    }
    protected static function checkFunds(): bool
    {
        return Filament::getTenant()->funds()->count() > 0;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordUrl(null)
            ->query(
                static::getEloquentQuery()
                    ->whereDoesntHave(
                        'roles',
                        fn($q) => $q->whereIn('name', ['Admin', 'AMS Manager', 'Payroll Manager', 'CEO'])
                    )
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->size(50)
                    ->circular(),

                Tables\Columns\TextColumn::make('name')->searchable(),

                Tables\Columns\TextColumn::make('attendance_type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'onsite' => 'On Site',
                            'offsite' => 'Remote',
                            'hybrid' => 'Hybrid',
                            default => ucfirst($state),
                        };
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),

                Tables\Columns\TextColumn::make('assignedShift.shift.name')
                    ->label('Shift'),

                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->formatStateUsing(fn($state, $record) => $record->fresh()->getRoleNames()->join(', '))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assignedDepartment.department.name')
                    ->label('Department')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('designation'),

                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Contact Number'),

                Tables\Columns\BooleanColumn::make('active')
                    ->label('Status'),
            ])
            ->searchPlaceholder('Search Employee')
            ->filters([
                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->default(1)->indicateUsing(fn() => null),

                SelectFilter::make('attendance_type')
                    ->label('Attendance Type')
                    ->options([
                        'onsite' => 'On Site',
                        'offsite' => 'Remote',
                        'hybrid' => 'Hybrid',
                    ])->indicateUsing(fn() => null),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->placeholder('All Departments')
                    ->options(fn() => Filament::getTenant()->departments()->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value']) || $data['value'] === '') {
                            return $query;
                        }

                        return $query->whereHas('assignedDepartment', fn(Builder $q) => $q->where('department_id', $data['value']));
                    })->indicateUsing(fn() => null),

                SelectFilter::make('role')
                    ->label('Role')
                    ->placeholder('All Roles')
                    ->options(fn() => Filament::getTenant()->roles()->pluck('name', 'id')->toArray())->indicateUsing(fn() => null),
            ])
            ->defaultSort('id', 'asc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => Auth::user()->can('employees.manage') || Auth::user()->hasRole('Admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
            'view' => Pages\ViewEmployee::route('/{record}'),
        ];
    }

    // Authorization Methods
    public static function canViewAny(): bool
    {
        return Auth::check() &&
            (
                Auth::user()->hasRole('Admin', 'web')
                || Auth::user()->can('employees.view')
                || Auth::user()->can('employees.manage')
                || Auth::user()->hasRole('CEO')
                || Auth::user()->hasRole('AMS Manager')
            ) && Helper::has_feature('employees');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (
                Auth::user()->hasRole('Admin', 'web') ||
                Auth::user()->can('employees.manage') ||
                Auth::user()->hasRole('CEO') ||
                Auth::user()->hasRole('AMS Manager')
            );
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin', 'web') || Auth::user()->can('employees.manage') || Auth::user()->hasRole('CEO') || Auth::user()->hasRole('AMS Manager'));
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin', 'web') || Auth::user()->can('employees.manage') || Auth::user()->hasRole('CEO') || Auth::user()->hasRole('AMS Manager'));
    }
}
