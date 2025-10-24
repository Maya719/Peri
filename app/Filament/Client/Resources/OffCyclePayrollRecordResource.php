<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\OffCyclePayrollRecordResource\Pages;
use App\Filament\Client\Resources\OffCyclePayrollRecordResource\RelationManagers;
use App\Models\OffCyclePayroll;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OffCyclePayrollRecordResource extends Resource
{
    protected static ?string $model = OffCyclePayroll::class;

    protected static ?string $navigationGroup = 'Payroll Management';
    protected static ?string $navigationLabel = 'One-Time Payments';
    protected static ?string $modelLabel = 'One-Time Payments';
    protected static ?int $navigationSort = 4;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['user'])
            ->orderByDesc('period_start');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query->whereHas('user', fn($q) => $q->where('team_id', Filament::getTenant()->id));
        }

        if ($user->can('payroll.approve') || $user->can('payroll.manage')) {
            return $query->whereHas('user', fn($q) => $q->where('team_id', Filament::getTenant()->id));
        }

        if ($user->can('payroll.viewRecords')) {
            return $query
                ->where('user_id', $user->id)
                ->where('status', 'approved');
        }

        return $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No ongoing payment')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Employee'),
                Tables\Columns\TextColumn::make('period')->label('Period'),
                Tables\Columns\TextColumn::make('total_earnings')->label('Earnings')->formatStateUsing(fn($state) => self::formatCurrency($state)),
                Tables\Columns\TextColumn::make('total_deductions')->label('Deductions')->formatStateUsing(fn($state) => self::formatCurrency($state)),
                Tables\Columns\TextColumn::make('net_pay')->label('Net Pay')->formatStateUsing(fn($state) => self::formatCurrency($state)),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending_approval' => 'Pending Approval',
                        'rejected' => 'Rejected',
                        'approved' => 'Approved',
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'rejected' => 'danger',
                        'approved' => 'success',
                    })
                    ->description(fn(OffCyclePayroll $record): ?string => $record->status === 'rejected' ? '' . ($record->rejection_reason ?? 'N/A') : null),
            ])
            ->defaultSort('period_start', 'desc')
            ->paginated(false)
            ->actions([
                Tables\Actions\ViewAction::make('viewPayroll')
                    ->label('View')
                    ->slideOver()
                    ->modalHeading('Pay Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(
                        fn($record) =>
                        view('livewire.view-offcycle-payslip', [
                            'payroll' => $record,
                        ])
                    )
                    ->visible(function (OffCyclePayroll $record) {
                        $user = Auth::user();

                        if (in_array($record->status, ['pending_approval', 'rejected'])) {
                            return $user->hasRole('Admin') ||
                                $user->can('payroll.manage') ||
                                $user->can('payroll.approve');
                        }

                        if ($record->status === 'approved') {
                            return $user->can('payroll.viewRecords') ||
                                $user->hasRole('Admin') ||
                                $user->can('payroll.manage');
                        }

                        return false;
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-c-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (OffCyclePayroll $record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()
                            ->title('Off-Cycle Payroll Approved')
                            ->success()
                            ->send();
                    })
                    ->visible(function (OffCyclePayroll $record) {
                        $user = Auth::user();
                        return $record->status === 'pending_approval' && ($user->can('payroll.approve') || $user->hasRole('Admin'));
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-c-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->placeholder('Enter the reason for rejecting this off-cycle payroll.')
                            ->maxLength(255),
                    ])
                    ->action(function (OffCyclePayroll $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()
                            ->title('Off-Cycle Payroll Rejected')
                            ->body('Reason: ' . $data['rejection_reason'])
                            ->warning()
                            ->send();
                    })
                    ->visible(function (OffCyclePayroll $record) {
                        $user = Auth::user();
                        return $record->status === 'pending_approval' && ($user->can('payroll.approve') || $user->hasRole('Admin'));
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete One-Time Payment')
                    ->visible(function (OffCyclePayroll $record) {
                        $user = Auth::user();

                        if ($user->hasRole('Admin') || $user->can('payroll.manage')) {
                            return in_array($record->status, ['pending_approval', 'rejected']);
                        }
                        return false;
                    })->after(function () {
                        return redirect(PayRunResource::getUrl('index'));
                    }),

            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getCurrencySymbol(): string
    {
        $country = Filament::getTenant()->country_id;
        return DB::table('tax_slabs')
            ->where('country_id', $country)
            ->value('salary_currency') ?? '';
    }

    public static function formatCurrency(float|int|null $amount): string
    {
        $symbol = self::getCurrencySymbol();
        return $symbol . ' ' . number_format(round($amount ?? 0));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Customize if needed (optional)
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffCyclePayrollRecords::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Admins, approvers, and managers always see the resource
        if (
            $user->hasRole('Admin') ||
            $user->can('payroll.manage') ||
            $user->can('payroll.approve')
        ) {
            return true;
        }

        if ($user->can('payroll.viewRecords')) {
            return Cache::remember("user:{$user->id}:has_offcycle", 60, function () use ($user) {
                return OffCyclePayroll::where('user_id', $user->id)->exists();
            });
        }

        return false;
    }
}
