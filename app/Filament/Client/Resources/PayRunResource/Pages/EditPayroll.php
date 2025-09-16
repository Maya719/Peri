<?php

namespace App\Filament\Client\Resources\PayRunResource\Pages;

use App\Facades\Helper;
use App\Filament\Client\Resources\PayRunResource;
use App\Models\PayRun;
use App\Models\Payroll;
use App\Models\SalaryComponent;
use App\Models\Team;
use App\Services\PayrollCalculationService;
use App\Traits\HasPayrollCalculations;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;

class EditPayroll extends Page implements HasForms
{
    use InteractsWithForms, HasPayrollCalculations, InteractsWithFormActions;
    public ?Payroll $payroll = null;
    public PayRun $payRun;
    public $payroll_date;
    public Team $team;
    public ?string $currency = '$';
    protected static string $resource = PayRunResource::class;
    protected static string $view = 'filament.client.resources.pay-run-resource.pages.edit-payroll';
    public ?array $data = [];

    public function mount($payrun, $record): void
    {
        $this->payroll = Payroll::findOrFail($record);
        $this->payRun = PayRun::findOrFail($payrun);
        $this->payroll_date = Carbon::parse($this->payRun->pay_period_start_date);
        $this->team = Filament::getTenant();
        $payrollService = app(PayrollCalculationService::class);
        $this->currency = $payrollService->getCurrencySymbolForAdmin($this->team->id);
        $this->form->fill($this->getFormData());
    }

    public function form(Form $form): Form
    {
        $currency = $this->currency;
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('')
                            ->schema([
                                // Salary Breakdown fieldset
                                Forms\Components\Fieldset::make('Salary Breakdown')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Forms\Components\Placeholder::make('gross_salary')
                                                ->label('Gross Salary')
                                                ->content(fn() => new HtmlString(
                                                    '<span class="font-bold">' . $currency . ' ' . number_format($this->payroll?->base_salary ?? 0, 0) . '</span>'
                                                )),

                                            Forms\Components\Placeholder::make('statutory_component')
                                                ->label('Statutory Amount')
                                                ->content(function () use ($currency) {
                                                    $user = $this->payroll?->user;
                                                    $currentPeriodBaseSalary = $this->payroll?->base_salary ?? 0;
                                                    $statutoryPercentage = $user->bankDetails->first()->statutory_component_percentage ?? 0;
                                                    $statutoryAdjustment = ($statutoryPercentage + 100) / 100;
                                                    $statutoryAmount = ($currentPeriodBaseSalary) / $statutoryAdjustment;
                                                    $statutoryBase = $currentPeriodBaseSalary - $statutoryAmount;
                                                    return $currency . ' ' . number_format($statutoryBase, 0);
                                                }),

                                            Forms\Components\Placeholder::make('base_salary')
                                                ->label('Base Salary')
                                                ->content(function () use ($currency) {
                                                    $user = $this->payroll?->user;
                                                    $currentPeriodBaseSalary = $this->payroll?->base_salary ?? 0;
                                                    $statutoryPercentage = $user->bankDetails->first()->statutory_component_percentage ?? 0;
                                                    $statutoryAdjustment = ($statutoryPercentage + 100) / 100;
                                                    $statutoryAmount = ($currentPeriodBaseSalary) / $statutoryAdjustment;
                                                    return $currency . ' ' . number_format($statutoryAmount, 0);
                                                }),
                                        ])
                                    ]),
                                // -- Increment Logic --
                                Forms\Components\Fieldset::make('Increment')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\Checkbox::make('apply_increment')
                                                ->label('Apply')
                                                ->disabled(fn() => isset($this->payroll) && $this->payroll->applied_increment_amount > 0)
                                                ->reactive()
                                                ->columnSpan(2)
                                                ->afterStateUpdated(fn() => $this->calculateSalary()),


                                            Forms\Components\Select::make('increment_type')
                                                ->label('Type')
                                                ->options([
                                                    'number' => 'Fixed Amount',
                                                    'percentage' => 'Percentage of Gross Salary',
                                                ])
                                                ->disabled(fn($get) => !$get('apply_increment') && !(
                                                    isset($this->payroll) && $this->payroll->applied_increment_amount > 0
                                                ))
                                                ->required(fn($get) => $get('apply_increment'))
                                                ->reactive()
                                                ->afterStateUpdated(fn() => $this->calculateSalary()),


                                            Forms\Components\TextInput::make('increment_value')
                                                ->label('Amount')
                                                ->numeric()
                                                ->minValue(0)
                                                ->prefix(function ($get) use ($currency) {
                                                    $isIncrementApplied = isset($this->payroll) && $this->payroll->applied_increment_amount > 0;
                                                    $type = $get('increment_type');

                                                    return $isIncrementApplied ? $currency . ' ' : (
                                                        $type === 'number' ? $currency . ' ' : ($type === 'percentage' ? '% ' : null)
                                                    );
                                                })
                                                ->disabled(fn($get) => !$get('apply_increment') && !(
                                                    isset($this->payroll) && $this->payroll->applied_increment_amount > 0
                                                ))
                                                ->reactive()
                                                ->afterStateUpdated(fn() => $this->calculateSalary()),

                                        ])
                                    ])->columnSpan(2), // Takes 2 columns
                                Forms\Components\Fieldset::make('Loan Details')
                                    ->schema([
                                        Forms\Components\Placeholder::make('loan_deduction_amount')
                                            ->label('Amount Deducted')
                                            ->content(fn() => $currency . ' ' . number_format($this->payroll?->loan_amount ?? 0, 0)),

                                        Forms\Components\Placeholder::make('installments_left')
                                            ->label('Installments Left')
                                            ->content(function () {
                                                $totalInstallmentsLeft = 0;

                                                if (!empty($this->payroll->loan_data)) {
                                                    foreach ($this->payroll->loan_data as $loanDeduction) {
                                                        $loan = \App\Models\Loan::find($loanDeduction['loan_id']);
                                                        if ($loan && $loan->installment_amount > 0) {
                                                            $installments = ceil($loan->remaining_amount / $loan->installment_amount);
                                                            $totalInstallmentsLeft += max(0, $installments - 1);
                                                        }
                                                    }
                                                }
                                                return $totalInstallmentsLeft > 0 ? $totalInstallmentsLeft : 0;
                                            }),
                                    ])
                                    ->visible(fn() => ($this->payroll?->loan_amount ?? 0) > 0)
                                    ->columnSpan(2), // Takes 2 columns
                                // -- Fund Section --
                                Forms\Components\Fieldset::make('Fund')
                                    ->label('Active Funds')
                                    ->schema([
                                        Repeater::make('fund_data')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\TextInput::make('amount_input')
                                                    ->label(fn(callable $get) => $get('title') ?: 'Amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->reactive()
                                                    ->disabled()
                                                    ->prefix(fn($state) => $currency . ' ')
                                                    ->formatStateUsing(fn($state) => $state !== null ? round($state) : null)
                                                    ->afterStateUpdated(fn() => $this->calculateSalary()),

                                            ])
                                            ->columns(2)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false),
                                    ])
                                    ->columns(2)
                                    ->visible(fn() => $this->getFunds())
                                    ->columnSpan(2), // Takes 2 columns

                                Forms\Components\Fieldset::make('Fund Earnings')
                                    ->label('Fund Reimbursement')
                                    ->schema(array_merge(
                                        collect(Helper::getEmployeeFund($this->payroll->user))->map(function ($fund) use ($currency) {
                                            return Forms\Components\Checkbox::make('fund_toggle_' . $fund->id)
                                                ->label($fund->name)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) use ($fund) {
                                                    $reimbursements = $get('fund_reimbursements') ?? [];
                                                    $existing = collect($reimbursements)->firstWhere('id', 'adhoc_earning_fund_id' . $fund->id);
                                                    if ($state && !$existing) {
                                                        $reimbursements[] = [
                                                            'id' => 'adhoc_earning_fund_id' . $fund->id,
                                                            'title' => $fund->name . ' Reimbursement',
                                                            'value_type' => 'number',
                                                            'amount_input' => Helper::getEmployeeDeductedFund($this->payroll->user, $fund),
                                                            'tax_status' => 'taxable',
                                                        ];
                                                        $set('fund_reimbursements', $reimbursements);
                                                    }

                                                    if (!$state && $existing) {
                                                        $reimbursements = collect($reimbursements)
                                                            ->reject(fn($item) => $item['id'] === 'adhoc_earning_fund_id' . $fund->id)
                                                            ->values()
                                                            ->toArray();
                                                        $set('fund_reimbursements', $reimbursements);
                                                    }
                                                    $this->calculateSalary();
                                                });
                                        })->toArray(),
                                        [
                                            Forms\Components\Repeater::make('fund_reimbursements')
                                                ->label('')
                                                ->schema([
                                                    Forms\Components\Hidden::make('id'),
                                                    Forms\Components\TextInput::make('title')
                                                        ->readOnly(),
                                                    Forms\Components\Select::make('value_type')
                                                        ->label('Type')
                                                        ->options([
                                                            'number' => 'Fixed Amount',
                                                            'percentage' => 'Percentage',
                                                        ])
                                                        ->disabled(),
                                                    Forms\Components\TextInput::make('amount_input')
                                                        ->label('Amount')
                                                        ->prefix(fn(callable $get) => $get('value_type') === 'percentage' ? '% ' : $currency . ' ')
                                                        ->numeric()
                                                        ->afterStateUpdated(fn() => $this->calculateSalary()),

                                                    Forms\Components\Select::make('tax_status')
                                                        ->label('Tax Status')
                                                        ->reactive()
                                                        ->options([
                                                            'taxable' => 'Taxable',
                                                            'non-taxable' => 'Non-Taxable',
                                                        ]),
                                                ])
                                                ->columns(4)
                                                ->reorderable(false)
                                                ->addable(false)
                                                ->deletable(false)
                                                ->columnSpan('full'),
                                        ]
                                    ))
                                    ->columns(3)
                                    ->visible(fn() => $this->getFunds())
                                    ->columnSpan(2), // Takes 2 columns

                                // -- Attendance Adjustments --
                                Forms\Components\Fieldset::make('Attendance Adjustments')
                                    ->schema([
                                        Forms\Components\TextInput::make('overtime_earning_amount')
                                            ->label('Overtime Minutes Earning')
                                            ->numeric()
                                            ->disabled()
                                            ->prefix(fn($state) => $currency . ' ')
                                            ->visible(fn() => Helper::policy()->overtime_policy_enabled == 1)
                                            ->formatStateUsing(fn($state) => round($state)),

                                        Forms\Components\Toggle::make('apply_overtime_earnings')
                                            ->label('Apply')
                                            ->inline(false)
                                            ->onColor('primary')
                                            ->offColor('gray')
                                            ->reactive()
                                            ->visible(fn() => Helper::policy()->overtime_policy_enabled == 1)
                                            ->afterStateUpdated(fn() => $this->calculateSalary()),


                                        Forms\Components\TextInput::make('late_deduction_amount')
                                            ->label('Late Minutes Deduction')
                                            ->numeric()
                                            ->disabled()
                                            ->prefix(fn($state) => $currency . ' ')
                                            ->visible(fn() => Helper::policy()->late_policy_enabled == 1)
                                            ->formatStateUsing(fn($state) => round($state))
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Only applies if the late minutes policy is enabled.'),

                                        Forms\Components\Toggle::make('deduct_late_penalties')
                                            ->label('Apply')
                                            ->inline(false)
                                            ->onColor('primary')
                                            ->offColor('gray')
                                            ->reactive()
                                            ->visible(fn() => Helper::policy()->late_policy_enabled == 1)
                                            ->afterStateUpdated(fn() => $this->calculateSalary()),


                                        Forms\Components\TextInput::make('absent_deduction_amount')
                                            ->label('Absent Days Deduction')
                                            ->numeric()
                                            ->disabled()
                                            ->prefix(fn($state) => $currency . ' ')
                                            ->formatStateUsing(fn($state) => round($state))
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Only applies if the over time policy is enabled.'),

                                        Forms\Components\Toggle::make('deduct_absent_penalties')
                                            ->label('Apply')
                                            ->inline(false)
                                            ->onColor('primary')
                                            ->offColor('gray')
                                            ->reactive()
                                            ->afterStateUpdated(fn() => $this->calculateSalary()),

                                    ])
                                    ->visible(fn() => Helper::has_feature('attendance'))
                                    ->columns(2)
                                    ->columnSpan(2), // Takes 2 columns

                                Forms\Components\Fieldset::make('Earnings')
                                    ->schema([
                                        Repeater::make('earnings')
                                            ->label(false)
                                            ->schema([
                                                Forms\Components\Hidden::make('id'),
                                                Forms\Components\TextInput::make('title')->disabled(),
                                                Forms\Components\TextInput::make('amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->prefix(fn(callable $get) => $get('value_type') === 'percentage' ? '% ' : $currency . ' ')
                                                    ->reactive(),
                                            ])
                                            ->columns(2)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->afterStateUpdated(fn() => $this->calculateSalary())
                                            ->columnSpan(2), // Takes 2 columns

                                        Forms\Components\Repeater::make('ad_hoc_earnings')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Hidden::make('id')->default(uniqid('adhoc_earning_custom_id')),
                                                Forms\Components\TextInput::make('title')
                                                    ->required()
                                                    ->reactive()
                                                    ->afterStateUpdated(fn() => $this->calculateSalary())
                                                    ->readOnly(fn(callable $get) => str($get('id'))->startsWith('adhoc_earning_fund_id')),

                                                Forms\Components\Select::make('value_type')
                                                    ->label('Type')
                                                    ->options([
                                                        'number' => 'Fixed Amount',
                                                        'percentage' => 'Percentage of gross salary',
                                                    ])
                                                    ->default('number')
                                                    ->required()
                                                    ->reactive()
                                                    ->disabled(fn(callable $get) => str($get('id'))->startsWith('adhoc_earning_fund_id')),

                                                Forms\Components\TextInput::make('amount_input')
                                                    ->label('Amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->afterStateUpdated(fn() => $this->calculateSalary())
                                                    ->reactive()
                                                    ->readOnly(fn(callable $get) => str($get('id'))->startsWith('adhoc_earning_fund_id')),

                                                Forms\Components\Select::make('tax_status')
                                                    ->label('Tax Status')
                                                    ->options([
                                                        'taxable' => 'Taxable',
                                                        'non-taxable' => 'Non-Taxable',
                                                    ])
                                                    ->required()
                                                    ->reactive()
                                                    ->default('taxable')
                                                    ->columnSpan(1),
                                            ])
                                            ->columns(4)
                                            ->reorderable(false)
                                            ->addActionLabel('+ Add an Earning')
                                            ->deleteAction(
                                                fn(\Filament\Forms\Components\Actions\Action $action) => $action->hidden(fn(array $arguments, \Filament\Forms\Components\Repeater $component) => str($component->getRawItemState($arguments['item'])['id'])->startsWith('adhoc_earning_fund_id')),
                                            )
                                            ->columnSpan(2), // Takes 2 columns
                                    ])->columnSpan(2),

                                // -- Deductions --
                                Forms\Components\Fieldset::make('Deductions')
                                    ->schema([
                                        Repeater::make('deductions')
                                            ->label(false)
                                            ->schema([
                                                Forms\Components\Hidden::make('id')->reactive(),
                                                Forms\Components\TextInput::make('title')->disabled(),
                                                Forms\Components\TextInput::make('amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->afterStateUpdated(fn() => $this->calculateSalary())
                                                    ->prefix(fn(callable $get) => $get('value_type') === 'percentage' ? '% ' : $currency . ' ')
                                                    ->reactive(),
                                            ])
                                            ->columns(2)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpan(2), // Takes 2 columns

                                        Repeater::make('ad_hoc_deductions')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Hidden::make('id')->default(uniqid('adhoc_deduction_custom_id')),
                                                Forms\Components\TextInput::make('title')->required(),
                                                Forms\Components\Select::make('value_type')
                                                    ->label('Type')
                                                    ->options([
                                                        'number' => 'Fixed Amount',
                                                        'percentage' => 'Percentage of gross salary',
                                                    ])
                                                    ->default('number')
                                                    ->required()
                                                    ->reactive(),

                                                Forms\Components\TextInput::make('amount_input')
                                                    ->label('Amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->afterStateUpdated(fn() => $this->calculateSalary())
                                                    ->prefix(function (callable $get) use ($currency) {
                                                        return $get('value_type') === 'percentage' ? '%' : $currency;
                                                    })
                                                    ->reactive(),

                                                Forms\Components\Select::make('tax_status')
                                                    ->label('Tax Status')
                                                    ->options([
                                                        'taxable' => 'Taxable',
                                                        'non-taxable' => 'Non-Taxable',
                                                    ])
                                                    ->required()
                                                    ->reactive()
                                                    ->default('taxable')
                                                    ->columnSpan(1),
                                            ])
                                            ->columns(4)
                                            ->reorderable(false)
                                            ->addActionLabel('+ Add a Deduction')
                                            ->columnSpan(2),
                                    ])->columnSpan(2),
                            ])
                            ->columnSpan(2),
                        Section::make('')
                            ->schema([
                                                                Placeholder::make('fiscal_year_month_remainings')
                                    ->label('Fiscal Year Month (Remaining)')
                                    ->content(fn() => number_format($this->getFiscalYearMonthsRemaining(), 0)),

                                Placeholder::make('annual_expected_salary')
                                    ->label('Annual Expected Salary')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Annual Expected Salary = Base Salary × ' . $this->getFiscalYearMonthsRemaining())
                                    ->content(fn() => number_format($this->getAnnualExpectedSalary(), 0)),

                                Placeholder::make('total_taxable_earnings')
                                    ->label('Total Taxable Earnings')
                                    ->content(fn() => $currency . ' ' . number_format($this->getTotalTaxableEarnings(), 0)),

                                Placeholder::make('total_non_taxable_earnings')
                                    ->label('Total Non Taxable Earnings')
                                    ->content(fn() => $currency . ' ' . number_format($this->getTotalNonTaxableEarnings(), 0)),

                                Placeholder::make('total_taxable_deductions')
                                    ->label('Total Taxable Deductions')
                                    ->content(fn() => $currency . ' ' . number_format($this->getTotalTaxableDeductions(), 0)),

                                Placeholder::make('total_non_taxable_deductions')
                                    ->label('Total Non Taxable Deductions')
                                    ->content(fn() => $currency . ' ' . number_format($this->getTotalNonTaxableDeductions(), 0)),

                                Placeholder::make('taxable_base')
                                    ->label('Taxable Base (This Period)')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Taxable Base = Base Salary + Taxable Earnings - Taxable Deductions')
                                    ->content(fn() => $currency . ' ' . number_format($this->getTaxableBase(), 0)),

                                Placeholder::make('annula_taxable_base')
                                    ->label('Annual Taxable Base')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Annual Taxable Base = Sum of Previous Periods Taxable Base of This FY + This Period Annual Expected + Total Taxable Earnings - Total Taxable Deductions')
                                    ->content(fn() => $currency . ' ' . number_format($this->getAnnualTaxableBase(), 0)),

                                Placeholder::make('tax_slabs_lies')
                                    ->label('Tax Slab Bracket Applied')
                                    ->content(fn() => $this->getTaxSlabBraketMin() . ' To ' . $this->getTaxSlabBraketMax() . ' => ' . $this->getTaxSlabPercentage() . '% + ' . $this->getTaxSlabAdditional()),

                                Placeholder::make('annual_tax')
                                    ->label('Annual Tax')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Annual tax = (Annual Taxable Base - Tax Slab Bracket Min) * Tax Slab Percentage + Tax Slab Additional')
                                    ->content(fn() => $currency . ' ' . number_format($this->getAnnualTax(), 0)),

                                Placeholder::make('monthly_tax')
                                    ->label('Monthly Tax')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Monthly tax = (Annual tax - Previous Paid Tax) / ' . $this->getFiscalYearMonthsRemaining())
                                    ->content(fn() => $currency . ' ' . number_format($this->getMonthlyTax(), 0))
                                    ->extraAttributes([
                                        'class' => 'font-bold text-lg text-green-600'
                                    ]),
                                Placeholder::make('net_pay')
                                    ->label('Net Pay')
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Net Pay = Gross Salary + Total Earnings - Total Deductions - Monthly Tax')
                                    ->content(fn() => $currency . ' ' . number_format($this->getNetPay(), 0))
                                    ->extraAttributes([
                                        'class' => 'font-bold text-lg text-green-600'
                                    ]),
                            ])
                            ->columnSpan(1),
                    ])
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (!$this->payroll) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No payroll record found.')
                ->send();
            return;
        }

        $predefinedEarningsInput = collect($data['earnings'] ?? [])
            ->map(function ($item) {
                $component = SalaryComponent::find($item['id']);
                if ($component) {
                    return [
                        'id' => $component->id,
                        'name' => $component->name,
                        'title' => $component->title,
                        'type' => $component->component_type,
                        'amount_input' => (float) $item['amount'],
                    ];
                }
            })->toArray();
        $predefinedDeductionsInput = collect($data['deductions'] ?? [])
            ->map(function ($item) {
                $component = SalaryComponent::find($item['id']);
                if ($component) {
                    return [
                        'id' => $component->id,
                        'name' => $component->name,
                        'title' => $component->title,
                        'type' => $component->component_type,
                        'amount_input' => (float) $item['amount'],
                    ];
                }
            })->toArray();

        $fundReimbursements = $data['fund_reimbursements'] ?? [];
        $adHocEarningsInput = $data['ad_hoc_earnings'] ?? [];
        $adHocEarningsInput = array_merge($adHocEarningsInput, $fundReimbursements);

        $adHocDeductionsInput = $data['ad_hoc_deductions'] ?? [];

        $payrollCalculationService = app(PayrollCalculationService::class);
        $updatedPayrollData = $payrollCalculationService->recalculateEmployeePayrollData(
            $this->payroll,
            $this->payroll->base_salary ?? 0,
            $predefinedEarningsInput,
            $predefinedDeductionsInput,
            $adHocEarningsInput,
            $adHocDeductionsInput,
            $data['apply_increment'] ?? false,
            $data['increment_type'] ?? 'number',
            (float) ($data['increment_value'] ?? 0),
            $data['deduct_late_penalties'] ?? true,
            $data['deduct_absent_penalties'] ?? true,
            $data['apply_overtime_earnings'] ?? true
        );

        $this->payroll->update($updatedPayrollData);

        Notification::make()
            ->success()
            ->title('Payroll Updated Successfully')
            ->send();

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->payRun]));
    }

    public function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->url(static::getResource()::getUrl('view', ['record' => $this->payRun]))
            ->color('gray');
    }

    public function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Save Changes')
            ->action('save')
            ->color('primary');
    }
    public function calculateSalary(): void
    {
        // This method is called whenever form values change
        // The calculations are handled by computed properties
    }


    protected function getFormData(): array
    {
        if (!$this->payroll) {
            return [];
        }

        $components = $this->fetchApplicableComponents($this->payroll);

        $adHocEarnings = collect($this->payroll->earnings_data['ad_hoc_earnings'] ?? []);

        $fundReimbursements = $adHocEarnings->filter(fn($item) => str($item['id'])->startsWith('adhoc_earning_fund_id'))->values()->all();
        $adHocEarnings = $adHocEarnings->reject(fn($item) => str($item['id'])->startsWith('adhoc_earning_fund_id'))->values()->all();

        $fundReimbursements = collect($fundReimbursements)->map(function ($item) {
            $item['amount_input'] = $item['amount_input'] ?? null;
            $item['tax_status'] = $item['tax_status'] ?? 'taxable'; // Ensure tax_status is always present
            return $item;
        })->all();

        $adHocEarnings = collect($adHocEarnings)->map(function ($item) {
            $item['amount_input'] = $item['amount_input'] ?? null;
            return $item;
        })->all();

        $adHocDeductions = collect($this->payroll->deductions_data['ad_hoc_deductions'] ?? [])->map(function ($item) {
            $item['amount_input'] = $item['amount_input'] ?? null;
            return $item;
        })->all();

        $formData = [
            'apply_increment' => $this->payroll->applied_increment_amount > 0,
            'increment_type' => $this->payroll->increment_type,
            'increment_value' => $this->payroll->applied_increment_amount,
            'earnings' => $components['earnings'],
            'deductions' => $components['deductions'],
            'fund_reimbursements' => $fundReimbursements,
            'ad_hoc_earnings' => $adHocEarnings,
            'ad_hoc_deductions' => $adHocDeductions,
            'overtime_earning_amount' => $this->payroll->attendance_data['overtime_earning_amount'] ?? 0,
            'late_deduction_amount' => $this->payroll->attendance_data['late_minutes_deduction_amount'] ?? 0,
            'absent_deduction_amount' => $this->payroll->attendance_data['absent_deduction_amount'] ?? 0,
            'deduct_late_penalties' => $this->payroll->deduct_late_penalties ?? true,
            'deduct_absent_penalties' => $this->payroll->deduct_absent_penalties ?? true,
            'apply_overtime_earnings' => $this->payroll->apply_overtime_earnings ?? true,
            'fund_data' => $this->payroll->fund_data,
        ];

        // Logic to set fund toggle states
        $adHocEarningItems = $formData['fund_reimbursements'];
        $adHocEarningIds = collect($adHocEarningItems)->pluck('id');
        $employeeFunds = Helper::getEmployeeFund($this->payroll->user);

        foreach ($employeeFunds as $fund) {
            $toggleName = 'fund_toggle_' . $fund->id;
            $fundEarningId = 'adhoc_earning_fund_id' . $fund->id;
            $formData[$toggleName] = $adHocEarningIds->contains($fundEarningId);
        }

        return $formData;
    }

    protected function getFunds(): bool
    {
        return $this->payroll->user->funds()
            ->wherePivot('team_id', Filament::getTenant()->id)
            ->exists();
    }

    protected function fetchApplicableComponents(Payroll $payroll): array
    {
        if ($payroll->status === 'finalized') {
            return [
                'earnings' => $payroll->earnings_data['custom_earnings_applied'] ?? [],
                'deductions' => $payroll->deductions_data['custom_deductions_applied'] ?? [],
            ];
        }

        // Draft payroll: calculate from active components
        $employee = $payroll->user;
        if (!$employee) {
            return ['earnings' => [], 'deductions' => []];
        }

        $previouslyAppliedOneTimeDeductionIds = \App\Models\Payroll::where('user_id', $employee->id)
            ->where('id', '!=', $payroll->id)
            ->get()
            ->flatMap(fn($p) => $p->applied_one_time_deductions ?? [])
            ->filter()
            ->unique()
            ->toArray();

        $components = Filament::getTenant()
            ->salaryComponents()
            ->where('is_active', true)
            ->get();

        $earnings = [];
        $deductions = [];

        $savedEarnings = collect($payroll->earnings_data['custom_earnings_applied'] ?? [])->keyBy('id');
        $savedDeductions = collect($payroll->deductions_data['custom_deductions_applied'] ?? [])->keyBy('id');

        foreach ($components as $component) {
            $isEarning = $component->component_type === 'earning';

            if (!$isEarning && $component->is_one_time_deduction) {
                if (in_array($component->id, $previouslyAppliedOneTimeDeductionIds)) {
                    continue;
                }
            }

            $savedComponent = $isEarning
                ? $savedEarnings->get($component->id)
                : $savedDeductions->get($component->id);

            $amount = isset($savedComponent['amount_input'])
                ? (float) $savedComponent['amount_input']
                : (float) $component->amount;

            $data = [
                'id' => $component->id,
                'title' => $component->title,
                'value_type' => $component->value_type,
                'amount' => $amount,
                'tax_status' => $component->tax_status,
            ];

            if ($isEarning) {
                $earnings[] = $data;
            } else {
                $deductions[] = $data;
            }
        }

        $customEarnings = $payroll->earnings_data['ad_hoc_earnings'] ?? [];
        $customDeductions = $payroll->deductions_data['ad_hoc_deductions'] ?? [];

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'custom_earnings' => $customEarnings,
            'custom_deductions' => $customDeductions,
        ];
    }

    public function getProvidentFundAmount(): float
    {
        $basicSalary = (float) ($this->data['basic_salary'] ?? 0);
        $providentFundRate = (float) ($this->data['provident_fund'] ?? 0);

        return ($basicSalary * $providentFundRate) / 100;
    }

    public function getTotalDeductions(): float
    {
        $monthlyTax = $this->getMonthlyTax();
        $insurance = (float) ($this->data['insurance'] ?? 0);
        $providentFund = $this->getProvidentFundAmount();

        return $monthlyTax + $insurance + $providentFund;
    }



    public function getTitle(): string
    {
        return ($this->payroll?->user?->name ?? 'Unknown Employee');
    }



    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
        ];
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes(['onclick' => 'history.back()']),
        ];
    }
}