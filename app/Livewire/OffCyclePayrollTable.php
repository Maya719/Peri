<?php

namespace App\Livewire;

use App\Filament\Client\Resources\OffCyclePayRunResource;
use App\Models\OffCyclePayRun;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Livewire\Component;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyDetail;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OffCyclePayrollTable extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                OffCyclePayRun::query()
                    ->where('team_id', Filament::getTenant()->id)
                    ->whereIn('status', ['pending_approval', 'rejected', 'approved'])
                    ->where('paid', false)
                    ->orderByDesc('created_at')
            )
            ->heading('One-Time Payment')
            ->emptyStateHeading('No one-time payment pending')
            ->columns([
                Tables\Columns\TextColumn::make('period_display')
                    ->label('Due Date')
                    ->getStateUsing(fn(OffCyclePayRun $record) => Carbon::createFromDate($record->year, $record->month, 1)->format('F Y'))
                    ->width('20%'),
                Tables\Columns\TextColumn::make('off_cycle_payrolls_count')
                    ->counts('offCyclePayrolls')
                    ->label('No. of Employees')
                    ->width('20%'),
                Tables\Columns\TextColumn::make('total_tax')
                    ->label('Total Tax')
                    ->getStateUsing(fn(OffCyclePayRun $record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id),
                        $record->offCyclePayrolls->sum('tax')
                    ))
                    ->width('20%'),
                Tables\Columns\TextColumn::make('total_net_pay')
                    ->label('Total Earning')
                    ->getStateUsing(fn(OffCyclePayRun $record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id),
                        $record->offCyclePayrolls->sum('net_pay')
                    ))
                    ->width('20%'),
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
                    ->description(fn(OffCyclePayRun $record): ?string => $record->status === 'rejected' ? $record->rejection_reason : null)
                    ->width('20%'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_finalized_payroll')
                    ->label('View')
                    ->url(fn(OffCyclePayRun $record): string => OffCyclePayRunResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->visible(fn(OffCyclePayRun $record): bool => $record->status === 'approved'),
                Tables\Actions\Action::make('manage')
                    ->label('Manage')
                    ->url(fn(OffCyclePayRun $record): string => OffCyclePayRunResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-pencil-square')
                    ->visible(fn(OffCyclePayRun $record) => $record->status === 'pending_approval' || $record->status === 'rejected'),
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
                            ->title('One-Time Payment Marked as Paid')
                            ->body('Payment recorded for ' . Carbon::parse($data['paid_date'])->format('M d, Y'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalDescription('Select a date to record the payment.'),
            ])
            ->paginated(false);
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

    public function render()
    {
        return view('livewire.off-cycle-payroll-table');
    }
}
