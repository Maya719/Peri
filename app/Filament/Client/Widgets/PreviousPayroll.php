<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use Filament\Widgets\Widget;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;

class PreviousPayroll extends Widget
{
    protected static string $view = 'filament.widgets.previous-payroll-card';
    protected static ?int $sort = 7;

    public ?string $selectedMonth = null;

    public function getColumnSpan(): int|string|array
    {
        return ['default' => 12];
    }
    public static function canView(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $hasAdminRole = $user->hasRole('Admin');
        $hasPayrollRole = $user->hasRole('Payroll Manager');
        $hasPayrollPermission = $user->can('payroll.manage');

        if (
            ($hasAdminRole ||
            $hasPayrollRole ||
            $hasPayrollPermission) && Helper::has_feature('payroll')
        ) {
            return true;
        }

        return false;
    }

    #[Computed]
    public function availableMonths(): array
    {
        $months = Filament::getTenant()->payRuns()
            ->select(DB::raw('DISTINCT month, year'))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->mapWithKeys(function ($payRun) {
                $date = Carbon::create($payRun->year, $payRun->month, 1);
                return [$date->format('Y-m') => $date->format('F Y')];
            })
            ->toArray();

        return $months;
    }


    #[Computed]
    public function getViewData(): array
    {
        $latestOverallPayRun = Filament::getTenant()->payRuns()->latest('pay_period_start_date')->first();

        $defaultSelectedMonth = null;
        if ($latestOverallPayRun) {
            $defaultSelectedMonth = Carbon::parse($latestOverallPayRun->pay_period_start_date)->subMonth()->format('Y-m');
        }

        // If selectedMonth is not set, or if the selectedMonth is the current month, default to the month before the latest payrun
        if (is_null($this->selectedMonth) || Carbon::parse($this->selectedMonth)->format('Y-m') === now()->format('Y-m')) {
            $this->selectedMonth = $defaultSelectedMonth;
        }

        $selectedMonth = $this->selectedMonth;

        $selectedDate = Carbon::parse($selectedMonth);
        $month = $selectedDate->month;
        $year = $selectedDate->year;

        $payRun = Filament::getTenant()->payRuns()
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $hasData = false;
        $totalNetPay = 0;
        $status = 'Not Run';
        $employeeCount = 0;
        $totalTax = 0;
        $fundsBreakdown = [];
        $payRunMonthYear = $selectedDate->format('F Y');
        $currencySymbol = $this->getCurrencySymbol();

        if ($payRun) {
            $hasData = true;
            $payrolls = $payRun->payrolls;
            $totalNetPay = $payrolls->sum('net_payable_salary');
            $status = $payRun->paid ? 'Paid' : strtoupper($payRun->status);
            $employeeCount = $payrolls->count();
            foreach ($payrolls as $payroll) {
                if (isset($payroll->tax_data['monthly_tax_calculated'])) {
                    $totalTax += $payroll->tax_data['monthly_tax_calculated'];
                }
                if (isset($payroll->fund_data) && is_array($payroll->fund_data)) {
                    foreach ($payroll->fund_data as $fund) {
                        $fundName = $fund['title'] ?? $fund['fund_name'] ?? 'Unknown Fund';
                        $fundAmount = $fund['calculated_amount'] ?? $fund['amount_input'] ?? 0;
                        $fundsBreakdown[$fundName] = ($fundsBreakdown[$fundName] ?? 0) + $fundAmount;
                    }
                }
            }
        }

        $offCyclePayrolls = Filament::getTenant()->OffCyclePayroll()
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        return [
            'hasData' => $hasData,
            'totalNetPay' => $totalNetPay,
            'status' => $status,
            'employeeCount' => $employeeCount,
            'totalTax' => $totalTax,
            'fundsBreakdown' => $fundsBreakdown,
            'offCyclePayrolls' => $offCyclePayrolls,
            'payRunMonthYear' => $payRunMonthYear,
            'currencySymbol' => $currencySymbol,
            'selectedMonth' => $this->selectedMonth,
        ];
    }

    protected function getCurrencySymbol(): string
    {
        $country = Filament::getTenant()->country_id;

        return $country
            ? DB::table('tax_slabs')->where('country_id', $country)->value('salary_currency') ?? ''
            : '';
    }

    protected function formatCurrency(string $symbol, float|int $amount): string
    {
        return $symbol . ' ' . number_format(round($amount));
    }
}
