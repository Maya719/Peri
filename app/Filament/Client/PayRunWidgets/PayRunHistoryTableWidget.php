<?php

namespace App\Filament\Client\PayRunWidgets;

use App\Filament\Client\Resources\OffCyclePayRunResource;
use App\Filament\Client\Resources\PayRunResource;
use App\Models\CompanyDetail; // Import CompanyDetail
use App\Models\OffCyclePayRun;
use App\Models\PayRun;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Import DB

class PayRunHistoryTableWidget extends BaseWidget
{
    protected static ?string $heading = '';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getPayRunHistoryQuery())
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Period')
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee_count')
                    ->label('No. of Employees')
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_tax')
                    ->label('Total Tax')
                    ->getStateUsing(fn($record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id ?? Filament::getTenant()->id),
                        (float) ($record->total_tax ?? 0)
                    )),
                Tables\Columns\TextColumn::make('total_net_pay')
                    ->label('Total Net Pay')
                    ->getStateUsing(fn($record) => self::formatCurrency(
                        self::getCurrencySymbol($record->team_id ?? Filament::getTenant()->id),
                        (float) ($record->total_net_pay ?? 0)
                    )),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'regular' => 'info',
                        'one_time' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'regular' => 'Regular',
                        'one_time' => 'One-Time',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn($record): string => match ($record->type ?? 'unknown') {
                        'regular' => match ($record->status_raw ?? 'unknown') {
                            'draft' => 'Draft',
                            'pending_approval' => 'Pending Approval',
                            'finalized' => ($record->paid_status ?? false) ? 'Paid' : 'Approved',
                            'rejected' => 'Rejected',
                            default => ucfirst($record->status_raw ?? 'Unknown'),
                        },
                        'one_time' => 'Paid',
                        default => 'N/A',
                    })
                    ->color(fn($record): string => match ($record->type ?? 'unknown') {
                        'regular' => match ($record->status_raw ?? 'unknown') {
                            'draft' => 'gray',
                            'pending_approval' => 'warning',
                            'finalized' => ($record->paid_status ?? false) ? 'success' : 'info',
                            'rejected' => 'danger',
                            default => 'gray',
                        },
                        'one_time' => 'success',
                        default => 'gray',
                    })
                    ->description(
                        fn($record): ?string =>
                        isset($record->paid_date) ? Carbon::parse($record->paid_date)->format('M d, Y') : null
                    ),
            ])
            ->searchPlaceholder('Search Month')
            ->actions([
                Tables\Actions\Action::make('view_record')
                    ->label('View')
                    ->url(function ($record): string {
                        if (($record->type ?? '') === 'regular') {
                            return PayRunResource::getUrl('edit', ['record' => $record->id]);
                        } elseif (($record->type ?? '') === 'one_time') {
                            return OffCyclePayRunResource::getUrl('edit', ['record' => $record->id]);
                        }
                        return '#';
                    })
                    ->icon('heroicon-s-eye')
                    ->color('gray'),
            ])
            ->filters([])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    protected function getPayRunHistoryQuery(): Builder
    {
        $team_id = Filament::getTenant()->id;

        return PayRun::query()
            ->fromSub(function ($query) use ($team_id) {
                $query->from('pay_runs')
                    ->select([
                        'id',
                        DB::raw("'regular' as type"),
                        // Use the actual payroll period (month/year) instead of paid_date
                        DB::raw("DATE_FORMAT(CONCAT(year, '-', LPAD(month, 2, '0'), '-01'), '%M %Y') as period"),
                        DB::raw("(SELECT COUNT(*) FROM payrolls WHERE payrolls.pay_run_id = pay_runs.id) as employee_count"),
                        DB::raw("COALESCE((SELECT SUM(JSON_EXTRACT(tax_data, '$.monthly_tax_calculated')) FROM payrolls WHERE payrolls.pay_run_id = pay_runs.id), 0) as total_tax"),
                        DB::raw("COALESCE((SELECT SUM(net_payable_salary) FROM payrolls WHERE payrolls.pay_run_id = pay_runs.id), 0) as total_net_pay"),
                        DB::raw("status as status_raw"),
                        DB::raw("paid as paid_status"),
                        DB::raw("paid_date as paid_date"),
                        DB::raw("CONCAT(year, '-', LPAD(month, 2, '0'), '-01') as sort_date"),
                        'team_id',
                        'created_at',
                        'updated_at'
                    ])
                    ->where('team_id', $team_id)
                    ->where('paid', true)
                    ->unionAll(
                        DB::table('off_cycle_pay_runs')
                            ->select([
                                'id',
                                DB::raw("'one_time' as type"),
                                DB::raw("DATE_FORMAT(CONCAT(year, '-', month, '-01'), '%M %Y') as period"),
                                DB::raw("(SELECT COUNT(*) FROM off_cycle_payrolls WHERE off_cycle_payrolls.off_cycle_pay_run_id = off_cycle_pay_runs.id) as employee_count"),
                                DB::raw("COALESCE((SELECT SUM(tax) FROM off_cycle_payrolls WHERE off_cycle_payrolls.off_cycle_pay_run_id = off_cycle_pay_runs.id), 0) as total_tax"),
                                DB::raw("COALESCE((SELECT SUM(net_pay) FROM off_cycle_payrolls WHERE off_cycle_payrolls.off_cycle_pay_run_id = off_cycle_pay_runs.id), 0) as total_net_pay"),
                                DB::raw("status as status_raw"),
                                DB::raw("1 as paid_status"),
                                DB::raw("paid_date as paid_date"),
                                DB::raw("CONCAT(year, '-', LPAD(month, 2, '0'), '-01') as sort_date"),
                                'team_id',
                                'created_at',
                                'updated_at'
                            ])
                            ->where('team_id', $team_id)
                            ->where('status', 'approved')
                    );
            }, 'combined_pay_runs')
            ->orderBy('sort_date', 'desc');
    }

    protected static function getCurrencySymbol(int $team_id): string
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
}
