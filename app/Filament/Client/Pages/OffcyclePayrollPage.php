<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Resources\PayRunResource;
use Filament\Pages\Page;
use App\Models\Payroll;
use App\Models\User;
use App\Models\OffCyclePayroll;
use App\Models\OffCyclePayRun;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class OffcyclePayrollPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.client.pages.save-offcycle-payroll';
    protected static ?string $navigationGroup = 'Payroll Management';
    protected static ?string $navigationLabel = 'One-Time Payments';
    protected static ?int $navigationSort = 3;

    public ?array $selectedEmployeeIds = [];

    public mixed $date_range = null;
    public array $earnings = [];
    public array $deductions = [];
    public ?float $tax = 0;
    public ?float $net_pay = 0;
    public $currencySymbol = null;

    public function getTitle(): string
    {
        return 'One-Time Payment';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasRole('Admin') || $user->can('payroll.manage');
    }

    public function mount(PayrollCalculationService $payrollCalculationService): void
    {
        $this->form->fill();
        $this->fetchCompanyCurrency($payrollCalculationService);
    }

    protected function fetchCompanyCurrency(PayrollCalculationService $payrollCalculationService): void
    {
        $currentAdminId = Filament::getTenant()->id; // Assuming the tenant ID is the admin ID
        $this->currencySymbol = $payrollCalculationService->getCurrencySymbolForAdmin($currentAdminId);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    Group::make([
                        Section::make('Employee Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('selectedEmployeeIds')
                                            ->label('Employee(s)')
                                            ->placeholder('Select Employees')
                                            ->options(
                                                Filament::getTenant()
                                                    ->users()
                                                    ->where('active', true)
                                                    ->whereHas('bankDetails', function ($q) {
                                                        $q->where('team_id', Filament::getTenant()->id)
                                                            ->where('base_salary', '>', 0);
                                                    })
                                                    ->pluck('name', 'id')
                                            )
                                            ->searchable()
                                            ->multiple() // Allows multiple selection
                                            ->required(),
                                        DateRangePicker::make('date_range')
                                            ->label('Period')
                                            ->format('d/m/Y')
                                            ->placeholder('From - To')
                                            ->required(),

                                        // If tax is fixed for all selected employees, keep it.
                                        // If tax can vary per employee, this needs a different approach (e.g., table repeater with employee and tax).
                                        TextInput::make('tax')
                                            ->label('Tax Amount (Applied to Each Employee)') // Clarified label
                                            ->numeric()
                                            ->prefix(fn() => $this->currencySymbol ?? '')
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                            ]),

                        Section::make('Earnings')
                            ->schema([
                                TableRepeater::make('earnings')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('title')->label('Title')->required()->columnSpan('full'),
                                        TextInput::make('amount')->label('Amount')->numeric()->minValue(0)->prefix(fn() => $this->currencySymbol ?? '')->required()->columnSpan('full'),
                                    ])
                                    ->reorderable(false)
                                    ->addable()
                                    ->deletable()
                                    ->createItemButtonLabel('Add More')
                                    ->default([['title' => '', 'amount' => 0]]),
                            ]),

                        Section::make('Deductions')
                            ->schema([
                                TableRepeater::make('deductions')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('title')->label('Title')->required()->columnSpan('full'),
                                        TextInput::make('amount')->label('Amount')->numeric()->minValue(0)->prefix(fn() => $this->currencySymbol ?? '')->required()->columnSpan('full'),
                                    ])
                                    ->reorderable(false)
                                    ->addable()
                                    ->deletable()
                                    ->createItemButtonLabel('Add More')
                                    ->default([['title' => '', 'amount' => 0]]),
                            ]),
                    ])->columnSpan(2),

                    // Empty columns to push sections to the left
                    Group::make([])->columnSpan(1),
                ])
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

        if (!$startDate || !$endDate) {
            Notification::make()->title('Date Error')->body('Invalid date range. Payroll not processed.')->warning()->send();
            return;
        }

        if (empty($data['selectedEmployeeIds'])) {
            Notification::make()->title('Error')->body('At least one employee must be selected.')->danger()->send();
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
        foreach ($data['selectedEmployeeIds'] as $userId) {
            $user = User::find($userId);
            if (!$user) {
                Notification::make()->title('Error')->body("Employee with ID {$userId} not found.")->danger()->send();
                $conflictsFound = true;
                break;
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
                break;
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
                break;
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
        }

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
