<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\PayRunResource\Pages;
use App\Filament\Client\Resources\PayRunResource\RelationManagers;
use App\Filament\Client\Resources\PayRunResource\RelationManagers\PayrollsRelationManager;
use App\Models\PayRun;
use App\Models\TaxSlabs;
use App\Models\Team;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayRunResource extends Resource
{
    protected static ?string $model = PayRun::class;
    protected static ?string $navigationGroup = 'Payroll Management';
    protected static ?string $navigationLabel = 'Pay Runs';
    protected static ?string $modelLabel = 'Pay Runs';
    protected static ?int $navigationSort = 1;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('month_year')
                    ->label('Select Payroll Month')
                    ->options(function () {

                        $lastFinalizedPayRun = Filament::getTenant()
                            ->payRuns()
                            ->where('status', 'finalized')
                            ->orderBy('year', 'desc')
                            ->orderBy('month', 'desc')
                            ->first();

                        $options = [];
                        $currentDate = Carbon::now();

                        if ($lastFinalizedPayRun) {
                            $nextMonth = Carbon::create($lastFinalizedPayRun->year, $lastFinalizedPayRun->month, 1)->addMonth();
                            $options[$nextMonth->format('Y-m')] = $nextMonth->format('F Y');
                        } else {

                            $fiscalYearStartMonth = 1;
                            $startYearForLoop = $currentDate->year;

                            $companyDetail = Filament::getTenant();

                            if ($companyDetail && $companyDetail->country_id) {
                                $countryId = $companyDetail->country_id;

                                $sampleTaxSlab = TaxSlabs::where('country_id', $countryId)
                                    ->orderBy('financial_year_start', 'desc')
                                    ->first();

                                if ($sampleTaxSlab && $sampleTaxSlab->financial_year_start) {
                                    $fiscalYearStartMonth = Carbon::parse($sampleTaxSlab->financial_year_start)->month;

                                    if ($currentDate->month < $fiscalYearStartMonth) {
                                        $startYearForLoop = $currentDate->year - 1;
                                    } else {
                                        $startYearForLoop = $currentDate->year;
                                    }
                                }
                            }

                            $loopDate = Carbon::create($startYearForLoop, $fiscalYearStartMonth, 1)->startOfMonth();
                            $endDate = Carbon::now()->startOfMonth();

                            while ($loopDate->lte($endDate)) {
                                $options[$loopDate->format('Y-m')] = $loopDate->format('F Y');
                                $loopDate->addMonth();
                            }
                        }

                        return $options;
                    })
                    ->required()
                    ->visible(fn(string $operation) => $operation === 'create')
                    ->helperText(function () {
                        if (PayRun::where('status', 'finalized')->exists()) {
                            return 'Next payroll month is automatically determined.';
                        }
                        return 'The payroll cycle will be initiated from this month. Once created, it cannot be changed.';
                    })
                    ->live()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            [$year, $month] = explode('-', $state);
                            $set('month', (int)$month);
                            $set('year', (int)$year);;
                        }
                    })
                    ->dehydrated(false),
                Forms\Components\Hidden::make('month')->required(),
                Forms\Components\Hidden::make('year')->required(),
                Forms\Components\TextInput::make('status')
                    ->default('draft')
                    ->disabled()
                    ->dehydrated(true)
                    ->columnSpanFull()
                    ->visibleOn('view'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Regular Payroll')
            ->emptyStateHeading('No payroll pending')
            ->columns([
                Tables\Columns\TextColumn::make('period_display')
                    ->label('Period')
                    ->getStateUsing(fn(PayRun $record) => Carbon::createFromDate($record->year, $record->month, 1)->format('F Y'))
                    ->width('20%'),
                Tables\Columns\TextColumn::make('payrolls_count')
                    ->counts('payrolls')
                    ->label('No. of Employees')
                    ->width('20%'),
                Tables\Columns\TextColumn::make('total_tax')
                    ->label('Total Tax')
                    ->getStateUsing(fn(PayRun $record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id),
                        $record->payrolls->sum('tax_data.monthly_tax_calculated')
                    ))
                    ->width('20%'),
                Tables\Columns\TextColumn::make('total_net_pay')
                    ->label('Total Net Pay')
                    ->getStateUsing(fn(PayRun $record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id),
                        $record->payrolls->sum('net_payable_salary')
                    ))
                    ->width('20%'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_approval' => 'warning',
                        'rejected' => 'danger',
                        'finalized' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'rejected' => 'Rejected',
                        'finalized' => 'Approved',
                        default => ucfirst($state),
                    })
                    ->description(fn(PayRun $record): ?string => $record->status === 'rejected' ? $record->rejection_reason : null)
                    ->width('20%'),
            ])
            ->paginated(false)
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('view_finalized_payroll')
                    ->label('View')
                    ->url(fn(PayRun $record): string => static::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->visible(fn(PayRun $record): bool => $record->status === 'finalized'),
                Tables\Actions\EditAction::make()->label('Manage')->visible(fn(PayRun $record) => $record->status === 'draft' || $record->status === 'pending_approval' || $record->status === 'rejected'),
                Tables\Actions\Action::make('mark_as_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-s-check-circle')
                    ->color('success')
                    ->visible(fn(PayRun $record): bool => $record->status === 'finalized' && !$record->paid)
                    ->form([
                        Forms\Components\DatePicker::make('paid_date')
                            ->label('Date Paid')
                            ->default(now())
                            ->minDate(fn(PayRun $record) => $record->created_at->startOfDay())
                            ->maxDate(now())
                            ->required(),
                    ])
                    ->action(function (PayRun $record, array $data) {
                        $record->update([
                            'paid' => true,
                            'paid_date' => $data['paid_date'],
                        ]);
                        Notification::make()
                            ->title('Pay Run Marked as Paid')
                            ->body('Payment recorded for ' . Carbon::parse($data['paid_date'])->format('M d, Y'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalDescription('Select a date to record the payment.'),
            ])
            ->actionsColumnLabel('Actions')
            ->recordUrl(null)
            ->defaultSort('year', 'desc')
            ->defaultSort('month', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PayrollsRelationManager::class,
        ];
    }

    protected static function getCurrencySymbol(int $adminId): string
    {
        $country = Filament::getTenant()->country_id;

        return $country
            ? DB::table('tax_slabs')->where('country_id', $country)->value('salary_currency') ?? ''
            : '';
    }

    protected static function formatCurrency(string $symbol, float|int $amount): string
    {
        return $symbol . ' ' . number_format(round($amount));
    }


    /**
     * UPDATED: This now points to our new ListPayRuns page, which contains the widget.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayRuns::route('/'),
            'create' => Pages\CreatePayRun::route('/create'),
            'edit' => Pages\EditPayRun::route('/{record}/edit'),
            'view' => Pages\EditPayRun::route('/{record}/view'),
            'edit-payroll' => Pages\EditPayroll::route('/{payrun}/payrolls/{record}/edit'),
            'fund-reburst' => Pages\FundReburst::route('/fund/reburst/')
        ];
    }

    public static function canCreate(): bool
    {
        $adminId = Filament::getTenant()->id; // Assuming the tenant ID is the admin ID
        return !PayRun::where('team_id', $adminId)->whereIn('status', ['draft', 'pending_approval', 'rejected'])->exists();
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole('Admin') ||
            Auth::user()->hasRole('CEO') ||
            Auth::user()->hasRole('Payroll Manager') ||
            Auth::user()->can('payroll.manage') ||
            Auth::user()->can('payroll.approve')
        ) && Helper::has_feature('payroll');
    }
}
