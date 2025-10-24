<?php

namespace App\Filament\Client\Resources\PayRunResource\RelationManagers;

use App\Filament\Client\Resources\PayRunResource;
use App\Models\Payroll;
use App\Services\PayrollCalculationService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';
    protected static ?string $recordTitleAttribute = 'user.name';

    protected function getTableQuery(): Builder
    {
        return $this->getRelationship()->getQuery()->withTrashed();
    }

    public function table(Table $table): Table
    {
        $adminId = $this->getOwnerRecord()->team_id;
        $payrollService = app(PayrollCalculationService::class);
        $currency = $payrollService->getCurrencySymbolForAdmin($adminId);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')->label('Employee ID'),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('base_salary')->label('Gross Salary')->formatStateUsing(fn($state) => $currency . ' ' . number_format($state ?? 0)),
                Tables\Columns\TextColumn::make('total_earnings')->label('Earnings')->formatStateUsing(fn($state) => $currency . ' ' . number_format($state ?? 0)),
                Tables\Columns\TextColumn::make('total_deductions')->label('Deductions')->formatStateUsing(fn($state) => $currency . ' ' . number_format($state ?? 0)),
                Tables\Columns\TextColumn::make('tax_data.monthly_tax_calculated')->label('Tax')->formatStateUsing(fn($state) => $currency . ' ' . number_format($state ?? 0)),
                Tables\Columns\TextColumn::make('net_payable_salary')
                    ->label('Net Pay')
                    ->formatStateUsing(fn($state) => $currency . ' ' . number_format($state ?? 0))
                    ->color(fn($state) => ($state ?? 0) < 0 ? 'danger' : null),
            ])
            ->searchPlaceholder('Search Employee')
            ->defaultSort('user_id', 'asc')
            ->actions([
                ViewAction::make('viewPayroll')
                    ->label('View')
                    ->url(fn($record) => route('payslip.show', $record), shouldOpenInNewTab: true)
                    ->visible(
                        fn($record): bool =>
                        !$record->trashed() &&
                            in_array($this->getOwnerRecord()->status, [
                                'pending_approval',
                                'draft',
                                'rejected',
                                'finalized',
                            ])
                    ),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn(Payroll $record) => PayRunResource::getUrl('edit-payroll', [
                        'payrun' => $record->pay_run_id,
                        'record' => $record->id,
                    ]))
                    ->visible(
                        fn($record): bool =>
                        !$record->trashed() &&
                            in_array($this->getOwnerRecord()->status, [
                                'draft',
                                'rejected',
                            ])
                    ),

                // Static "Skipped" label for trashed records
                Action::make('skipped')
                    ->color('danger')
                    ->disabled()
                    ->visible(fn($record): bool => $record->trashed() && !in_array($this->getOwnerRecord()->status, ['draft', 'rejected'])),

                Tables\Actions\DeleteAction::make()
                    ->label('Skip')
                    ->tooltip('Skip employee from this payroll')
                    ->icon('heroicon-c-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Skip employee from this payroll?')
                    ->modalSubheading('This action is reversible.')
                    ->modalButton('Yes, Skip')
                    ->visible(fn(): bool => !in_array($this->getOwnerRecord()->status, ['pending_approval', 'finalized'])),
                Tables\Actions\RestoreAction::make()
                    ->label('Add to Payroll')
                    ->tooltip('Add employee to this payroll')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Add employee to this payroll?')
                    ->modalButton('Yes, Add Back')
                    ->visible(
                        fn($record): bool =>
                        $record->trashed()
                            && !in_array($this->getOwnerRecord()->status, ['pending_approval', 'finalized'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkSkipPayroll')
                    ->label('Skip Selected')
                    ->icon('heroicon-c-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Skip selected employee(s) from payroll?')
                    ->modalSubheading('This action is reversible.')
                    ->modalButton('Yes, Skip')
                    ->action(function ($records) {
                        $skippedCount = 0;
                        foreach ($records as $record) {
                            if (!in_array($record->payRun->status ?? null, ['pending_approval', 'finalized'])) {
                                $record->delete();
                                $skippedCount++;
                            }
                        }
                        Notification::make()->success()->title('Skipped Employees')->body("Successfully skipped {$skippedCount} employee(s) from this payroll.")->send();
                    })
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn(): bool => !in_array($this->getOwnerRecord()->status, ['pending_approval', 'finalized'])),
                // Tables\Actions\RestoreBulkAction::make()
                //     ->label('Add Back Selected')
                //     ->color('info')
                //     ->requiresConfirmation()
                //     ->modalHeading('Add selected employee(s) to payroll?')
                //     ->modalButton('Yes, Add Back')
                //     ->visible(fn(): bool => !in_array($this->getOwnerRecord()->status, ['pending_approval', 'finalized']))
                //     ->action(function ($records) {
                //         $addedCount = 0;
                //         foreach ($records as $record) {
                //             if (!in_array($record->payRun->status ?? null, ['pending_approval', 'finalized'])) {
                //                 $record->restore();
                //                 $addedCount++;
                //             }
                //         }
                //         Notification::make()->success()->title('Restored Employees')->body("Successfully added {$addedCount} employee(s) to this payroll.")->send();
                //     })
                //     ->deselectRecordsAfterCompletion(),

            ]);
    }
}
