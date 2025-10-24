<?php

namespace App\Filament\Client\Resources\PayRunResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Resources\PayRunResource;
use App\Models\OffCyclePayroll;
use App\Models\OffCyclePayRun;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions;

class FundReburst extends Page implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    public $employee;
    public mixed $date_range = null;
    public array $earnings = [];
    public array $deductions = [];
    public ?float $tax = 0;
    public ?float $net_pay = 0;
    public $currencySymbol = null;
    public $fund;
    protected static string $resource = PayRunResource::class;

    protected static string $view = 'filament.client.resources.pay-run-resource.pages.fund-reburst';

    public function getTitle(): string
    {
        return 'Fund Reimbursement';
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn() => $this->getResource()::getUrl())
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
        ];
    }
    public function mount(PayrollCalculationService $payrollCalculationService): void
    {
        $this->form->fill();
        $this->fetchCompanyCurrency($payrollCalculationService);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Filament::getTenant()
                    ->payrolls()
                    ->where('user_id', $this->employee)
                    ->whereJsonLength('fund_data', '>', 0);
            })
            ->columns([
                TextColumn::make('base_salary')
                    ->label('Base Salary')
                    ->formatStateUsing(fn($state) => $this->currencySymbol . ' ' . number_format($state)),
                TextColumn::make('date_range_start')
                    ->label('Month')
                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('M')),
                TextColumn::make('fund_data')
                    ->label('Fund Amount')
                    ->formatStateUsing(function ($state) {
                        $data = json_decode($state, true);
                        $fundId = $this->fund;
                        if (!is_array($data)) {
                            return '0';
                        }
                        if (array_is_list($data)) {
                            foreach ($data as $item) {
                                if (($item['id'] ?? null) == $fundId) {
                                    return $this->currencySymbol . ' ' . number_format($item['calculated_amount'] ?? 0);
                                }
                            }
                        } elseif (isset($data['id']) && $data['id'] == $fundId) {
                            return $this->currencySymbol . ' ' . number_format($data['calculated_amount'] ?? 0);
                        }
                        return $this->currencySymbol . ' ' . number_format('0');
                    }),
                TextColumn::make('earnings_data')
                    ->label('Rebursed Amount')
                    ->formatStateUsing(function ($state, $record) {
                        $fundId = $this->fund ?? null;
                        if (!$fundId) return '0';
                        $rebursedKey = 'adhoc_earning_fund_id' . $fundId;
                        $earningsData = $record->earnings_data;
                        if (!is_array($earningsData)) return '0';
                        $adHocEarnings = $earningsData['ad_hoc_earnings'] ?? [];
                        foreach ($adHocEarnings as $earning) {
                            if (($earning['id'] ?? null) === $rebursedKey) {
                                return  $this->currencySymbol . ' ' . number_format($earning['calculated_amount'] ?? 0);
                            }
                        }

                        return '-';
                    })
            ])->emptyStateHeading('No Fund Data yet');
    }

    protected function fetchCompanyCurrency(PayrollCalculationService $payrollCalculationService): void
    {
        $currentAdminId = Filament::getTenant()->id;
        $this->currencySymbol = $payrollCalculationService->getCurrencySymbolForAdmin($currentAdminId);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Group::make([
                        Section::make('Employee Information')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('employee')
                                        ->label('Employee')
                                        ->placeholder('Select Employee')
                                        ->options(
                                            fn() => Filament::getTenant()?->users()
                                                ->where('active', true)
                                                ->whereHas('bankDetails', function ($q) {
                                                    $q->where('team_id', Filament::getTenant()->id)
                                                        ->where('base_salary', '>', 0);
                                                })
                                                ->pluck('name', 'id') ?? []
                                        )
                                        ->reactive()
                                        ->searchable()
                                        ->required()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            $user = Filament::getTenant()?->users()?->find($state);
                                            if ($user) {
                                                $funds = Helper::getEmployeeFund($user);

                                                if ($funds->isEmpty()) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title('No Funds Available')
                                                        ->body('This employee has no available fund records.')
                                                        ->warning()
                                                        ->send();
                                                }
                                            }
                                        }),
                                    DateRangePicker::make('date_range')
                                        ->label('Period')
                                        ->format('d/m/Y')
                                        ->placeholder('From - To')
                                        ->required(),
                                    TextInput::make('tax')
                                        ->label('Tax Amount')
                                        ->numeric()
                                        ->prefix(fn() => $this->currencySymbol ?? '')
                                        ->formatStateUsing(fn($state) => $state !== null ? round($state) : null)
                                        ->minValue(0)
                                        ->default(0)
                                        ->required(),
                                ]),
                            ]),

                        Section::make('Fund Earnings')
                            ->schema([
                                TableRepeater::make('earnings')
                                    ->label('')
                                    ->schema([
                                        Select::make('fund_id')
                                            ->label('Fund(s)')
                                            ->placeholder('Select Fund')
                                            ->options(
                                                fn() =>
                                                $this->employee
                                                    ? Helper::getEmployeeFund(Filament::getTenant()?->users()?->find($this->employee))?->pluck('name', 'id') ?? []
                                                    : []
                                            )
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $this->fund = $state;
                                                $userId = $this->employee;
                                                $fundId = $state;
                                                if ($userId && $fundId) {
                                                    $user = Filament::getTenant()->users()->find($userId);
                                                    $fund = Filament::getTenant()->funds()->find($fundId);
                                                    $amount = Helper::getEmployeeDeductedFund($user, $fund);
                                                    $set('amount', $amount);
                                                }
                                            }),

                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix(fn() => $this->currencySymbol ?? '')
                                            ->formatStateUsing(fn($state) => $state !== null ? round($state) : null)
                                            ->required()
                                            ->reactive()
                                    ])
                                    ->reorderable(false)
                                    ->createItemButtonLabel('Add More'),
                            ])
                            ->visible(fn() => $this->employee)
                    ])->columnSpan(2),
                ]),
            ]);
    }

    protected function parseDateRange($state): array
    {
        $start = null;
        $end = null;
        if (!$state) return [$start, $end];
        try {
            if (is_string($state) && str_contains($state, ' - ')) {
                [$startRaw, $endRaw] = explode(' - ', $state, 2);
                $start = Carbon::createFromFormat('d/m/Y', trim($startRaw))->startOfDay();
                $end = Carbon::createFromFormat('d/m/Y', trim($endRaw))->endOfDay();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Date Range Error')->body('Invalid date format. Please use DD/MM/YYYY.')->danger()->send();
            return [null, null];
        }
        return [$start, $end];
    }

    public function save()
    {
        $data = $this->form->getState();
        if (empty($data['date_range'])) {
            Notification::make()->title('Error')->body('Date range is required.')->danger()->send();
            return;
        }

        [$startDate, $endDate] = $this->parseDateRange($data['date_range']);
        $userId = $data["employee"];
        if (!$startDate || !$endDate) {
            Notification::make()->title('Date Error')->body('Invalid date range. Payroll not processed.')->warning()->send();
            return;
        }

        if (empty($data['employee'])) {
            Notification::make()->title('Error')->body('employee must be selected.')->danger()->send();
            return;
        }

        $totalEarningsFromForm = 0;
        if (!empty($data['earnings'])) {
            foreach ($data['earnings'] as $earning) {
                $totalEarningsFromForm += (float)($earning['amount'] ?? 0);
            }
        }

        $totalDeductionsFromForm = 0;
        if (!empty($data['deductions'])) {
            foreach ($data['deductions'] as $deduction) {
                $totalDeductionsFromForm += (float)($deduction['amount'] ?? 0);
            }
        }

        $taxAmountFromForm = (float)($data['tax'] ?? 0);

        $offCyclePayrollsToCreate = [];
        $conflictsFound = false;
        $data['team_id'] = Filament::getTenant()->id;
        $user = Filament::getTenant()?->users()?->find($userId);
        if (!$user) {
            Notification::make()->title('Error')->body("Employee with ID {$userId} not found.")->danger()->send();
            $conflictsFound = true;
        }

        $baseSalary = (float)($user->base_salary ?? 0);

        $overlappingOffCyclePayroll = Filament::getTenant()->OffCyclePayroll()->where('user_id', $userId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('period_start', '<=', $endDate)
                        ->where('period_end', '>=', $startDate);
                });
            })
            ->exists();

        if ($overlappingOffCyclePayroll) {
            Notification::make()
                ->title('Off-Cycle Payroll Conflict')
                ->body("An existing off-cycle payroll record overlaps for {$user->name} within the selected date range.")
                ->danger()
                ->send();
            $conflictsFound = true;
        }

        $offCycleMonth = $startDate->month;
        $offCycleYear = $startDate->year;

        $existingMonthlyPayroll = Filament::getTenant()
            ->payrolls()
            ->where('user_id', $userId)
            ->whereMonth('date_range_start', $offCycleMonth)
            ->whereYear('date_range_start', $offCycleYear)
            ->exists();

        if ($existingMonthlyPayroll) {
            Notification::make()
                ->title('Payroll Conflict')
                ->body("An on-cycle payroll record already exists for {$user->name} within the selected date range.")
                ->danger()
                ->send();
            $conflictsFound = true;
        }
        $netPay = $baseSalary + $totalEarningsFromForm - $totalDeductionsFromForm - $taxAmountFromForm;
        $offCyclePayrollsToCreate[] = [
            'user_id' => $userId,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'earnings' => $data['earnings'] ?? [],
            'deductions' => $data['deductions'] ?? [],
            'tax' => $taxAmountFromForm,
            'net_pay' => $netPay,
            'status' => 'pending_approval',
        ];

        if ($conflictsFound) {
            return;
        }

        try {
            DB::beginTransaction();

            $offCyclePayRun = OffCyclePayRun::create([
                'team_id' => Filament::getTenant()->id,
                'month' => $startDate->month,
                'year' => $startDate->year,
                'status' => 'pending_approval',
            ]);

            foreach ($offCyclePayrollsToCreate as $payrollData) {
                OffCyclePayroll::create(array_merge($payrollData, [
                    'team_id' => Filament::getTenant()->id,
                    'off_cycle_pay_run_id' => $offCyclePayRun->id,
                ]));
            }

            DB::commit();

            Notification::make()
                ->title('One-Time Payment Saved')
                ->body('The payment is pending approval.')
                ->success()
                ->send();

            return redirect(PayRunResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Error')
                ->body('An error occurred while saving the one-time payment. ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
