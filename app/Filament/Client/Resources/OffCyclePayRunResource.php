<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\OffCyclePayRunResource\Pages;
use App\Filament\Client\Resources\OffCyclePayRunResource\RelationManagers;
use App\Models\OffCyclePayRun;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class OffCyclePayRunResource extends Resource
{
    protected static ?string $model = OffCyclePayRun::class;

    protected static ?string $navigationGroup = 'Payroll Management';
    protected static ?string $navigationLabel = 'One-Time Pay Runs';
    protected static ?string $modelLabel = 'One-Time Pay Runs';
    protected static ?int $navigationSort = 2;
    protected static ?string $tenantOwnershipRelationshipName = 'team';


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No ongoing payment')
            ->columns([
                Tables\Columns\TextColumn::make('period_display')
                    ->label('Period')
                    ->getStateUsing(fn(OffCyclePayRun $record) => Carbon::createFromDate($record->year, $record->month, 1)->format('F Y')),
                Tables\Columns\TextColumn::make('off_cycle_payrolls_count')
                    ->counts('offCyclePayrolls')
                    ->label('No. of Employees'),
                Tables\Columns\TextColumn::make('total_net_pay')
                    ->label('Total Net Pay')
                    ->getStateUsing(fn(OffCyclePayRun $record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id),
                        $record->offCyclePayrolls->sum('net_pay')
                    )),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'rejected' => 'danger',
                        'approved' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending_approval' => 'Pending Approval',
                        'rejected' => 'Rejected',
                        'approved' => 'Approved',
                        default => ucfirst($state),
                    })
                    ->description(fn(OffCyclePayRun $record): ?string => $record->status === 'rejected' ? $record->rejection_reason : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn(OffCyclePayRun $record): string => Pages\ViewOffCyclePayRun::getUrl(['record' => $record]))
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->visible(fn(OffCyclePayRun $record): bool => $record->status === 'approved'),

                Tables\Actions\EditAction::make()
                    ->label('Manage')
                    ->visible(fn(OffCyclePayRun $record) => $record->status === 'draft' || $record->status === 'pending_approval' || $record->status === 'rejected'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(OffCyclePayRun $record): bool => $record->status === 'pending_approval')
                        ->requiresConfirmation()
                        ->action(function (OffCyclePayRun $record) {
                            $record->update(['status' => 'approved']);
                            $record->offCyclePayrolls()->update(['status' => 'approved']);
                            Notification::make()->title('One-Time Pay Run Approved')->success()->send();
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(OffCyclePayRun $record): bool => $record->status === 'pending_approval')
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->requiresConfirmation()
                        ->action(function (OffCyclePayRun $record, array $data) {
                            $record->update([
                                'status' => 'rejected',
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                            $record->offCyclePayrolls()->update(['status' => 'rejected']);
                            Notification::make()->title('One-Time Pay Run Rejected')->danger()->send();
                        }),
                ]),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(OffCyclePayRun $record) => $record->status !== 'approved'),
                Tables\Actions\Action::make('mark_as_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-s-check-circle')
                    ->color('success')
                    ->visible(fn(OffCyclePayRun $record): bool => $record->status === 'approved' && !$record->paid)
                    ->form([
                        DatePicker::make('paid_date')
                            ->label('Date Paid')
                            ->default(now())
                            ->minDate(fn(OffCyclePayRun $record) => $record->created_at->startOfDay())
                            ->maxDate(now())
                            ->required(),
                    ])
                    ->action(function (OffCyclePayRun $record, array $data) {
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        // This should be the relation manager, not a page
        return [
            RelationManagers\OffCyclePayrollsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffCyclePayRuns::route('/'),
            'view' => Pages\ViewOffCyclePayRun::route('/{record}'),
            'edit' => Pages\EditOffCyclePayRun::route('/{record}/edit'),
        ];
    }

    // Helper methods for currency from PayRunResource
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

    public static function canCreate(): bool
    {
        return false; // Creation is handled by OffcyclePayrollPage
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasRole('Admin') || $user->can('payroll.approve') || $user->can('payroll.manage');
    }
}
