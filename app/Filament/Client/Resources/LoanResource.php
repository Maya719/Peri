<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\LoanResource\Pages;
use App\Models\Loan;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Set;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PayRun;
use Closure;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Auth;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationGroup = 'Payroll Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Card::make()
                            ->columnSpan(2)
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('loan_name')
                                            ->label('Loan Name')
                                            ->required()
                                            ->hintAction(
                                                Action::make('info')
                                                    ->label(false)
                                                    ->icon('heroicon-m-question-mark-circle')
                                                    ->color('gray')
                                                    ->tooltip('This name will appear on the employee\'s payslip.')
                                            ),
                                        Forms\Components\Select::make('user_id')
                                            ->label('Employee')
                                            ->options(Filament::getTenant()->users()->where('active', 1)->pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('issue_date')
                                            ->label('Disbursement Date')
                                            ->native(false)
                                            ->prefixIcon('heroicon-m-calendar')
                                            ->required()
                                            ->live(),
                                        Forms\Components\TextInput::make('loan_amount')
                                            ->label('Loan Amount')
                                            ->required()
                                            ->numeric()
                                            ->prefix(self::getCurrencySymbol())
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                self::updateRemainingMonths($get, $set);
                                            }),
                                    ]),
                                Forms\Components\Textarea::make('reason')
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(2)
                                    ->visible(fn(Get $get) => $get('issue_date') && (float) $get('loan_amount') > 0)
                                    ->schema([
                                        Forms\Components\DatePicker::make('deduction_start_date')
                                            ->label('Deduction Start Month')
                                            ->native(false)
                                            ->prefixIcon('heroicon-m-calendar')
                                            ->required()
                                            ->hintAction(
                                                Action::make('info')
                                                    ->label(false)
                                                    ->icon('heroicon-m-question-mark-circle')
                                                    ->color('gray')
                                                    ->tooltip('Monthly loan deductions occur at the time of payroll processing for that month. For example, if the January payroll is processed on January 31, the loan for January will also be deducted on that same date.')
                                            )
                                            ->minDate(fn(Get $get) => $get('issue_date'))
                                            ->format('Y-m-01')
                                            ->displayFormat('F Y')
                                            ->rules([
                                                function (Get $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $deductionDate = Carbon::parse($value);
                                                        $currentTenantId = Filament::getTenant()->id;

                                                        $payRunExists = PayRun::where('team_id', $currentTenantId)
                                                            // ->where('status', 'finalized')
                                                            ->where('year', $deductionDate->year)
                                                            ->where('month', $deductionDate->month)
                                                            ->exists();

                                                        if ($payRunExists) {
                                                            $fail('Payroll for this month has already been processed. Please select a different month.');
                                                        }
                                                    };
                                                },
                                            ]),
                                        Forms\Components\TextInput::make('installment_amount')
                                            ->label('Installment Amount')
                                            ->required()
                                            ->numeric()
                                            ->prefix(self::getCurrencySymbol())
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                self::updateRemainingMonths($get, $set);
                                            }),
                                    ]),
                                Placeholder::make('recovery_months')
                                    ->label('Recovery Months')
                                    ->content(
                                        fn(Get $get) =>
                                        $get('recovery_months')
                                            ? 'This loan will be fully paid off in ' . $get('recovery_months') . ' months.'
                                            : 'N/A'
                                    )
                                    ->visible(function (Get $get, $livewire) {
                                        return $livewire instanceof \Filament\Resources\Pages\CreateRecord &&
                                            (float) $get('loan_amount') > 0 &&
                                            (float) $get('installment_amount') > 0;
                                    }),
                                Forms\Components\Hidden::make('remaining_amount')
                                    ->default(0),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'paid' => 'Paid',
                                    ])
                                    ->required()
                                    ->default('active')
                                    ->visible(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\Hidden::make('team_id')
                                    ->default(Filament::getTenant()->id),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_name')
                    ->label('Loan Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_amount')
                    ->label('Loan Amount')
                    ->numeric()
                    ->money(self::getCurrencySymbol()),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Disbursement Date')
                    ->date(),
                Tables\Columns\TextColumn::make('deduction_start_date')
                    ->label('Deduction Start Month')
                    ->date('M Y'),
                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Installment Amount')
                    ->numeric()
                    ->money(self::getCurrencySymbol()),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining Amount')
                    ->numeric()
                    ->money(self::getCurrencySymbol()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->formatStateUsing(fn(string $state) => ucfirst($state))
                    ->color(fn(string $state) => match ($state) {
                        'active' => 'info',
                        'paid' => 'success',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    private static function updateRemainingMonths(Get $get, Set $set): void
    {
        $loanAmount = (float) $get('loan_amount');
        $installmentAmount = (float) $get('installment_amount');

        if ($loanAmount > 0 && $installmentAmount > 0) {
            $months = ceil($loanAmount / $installmentAmount);
            $set('recovery_months', $months);
            $set('remaining_amount', $loanAmount);
        }
    }

    private static function getCurrencySymbol(): string
    {
        $country = Filament::getTenant()->country_id;

        return DB::table('tax_slabs')
            ->where('country_id', $country)
            ->value('salary_currency') ?? '';
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Payroll Manager') ||
            Auth::user()->can('payroll.manage')
        ) && Helper::has_feature('payroll');
    }

    public static function canCreate(): bool
    {
        return !Filament::getTenant()->payruns()->whereIn('status', ['draft', 'pending_approval', 'rejected'])->exists();
    }
}
