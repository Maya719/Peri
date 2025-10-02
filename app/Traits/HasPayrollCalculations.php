<?php

namespace App\Traits;

use App\Models\Payroll;
use App\Models\TaxSlabs;
use Carbon\Carbon;

trait HasPayrollCalculations
{
    private function getTaxSlab(): ?TaxSlabs
    {
        return TaxSlabs::where('country_id', $this->team->country_id)
            ->where('financial_year_start', '<=', $this->payroll_date->toDateString())
            ->where('financial_year_end', '>=', $this->payroll_date->toDateString())
            ->first();
    }

    private function getFiscalYearMonthsRemaining(): int
    {
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) {
            return 12; // Default to 12 if no slab found
        }

        $fyEndDate = Carbon::parse($taxSlab->financial_year_end);
        $currentDate = $this->payroll_date;

        // If the current date is after the FY end date, it means we are in the next FY, but for calculation of remaining months, it should be 0 or 1.
        // Let's adjust the year of FY end date to be current or next year.
        $fyEndDate->year($currentDate->year);
        if ($currentDate->gt($fyEndDate)) {
            $fyEndDate->addYear();
        }

        $monthsRemaining = $currentDate->diffInMonths($fyEndDate);

        // diffInMonths gives full months, add 1 to include the current month.
        return $monthsRemaining + 1;
    }

    private function getGrossSalary(): float
    {
        $baseSalary = $this->payroll?->base_salary ?? 0;

        if (isset($this->data['apply_increment']) && $this->data['apply_increment']) {
            $incrementType = $this->data['increment_type'] ?? 'number';
            $incrementValue = (float) ($this->data['increment_value'] ?? 0);

            if ($incrementType === 'percentage') {
                $incrementAmount = $baseSalary * ($incrementValue / 100);
            } else {
                $incrementAmount = $incrementValue;
            }

            return $baseSalary + $incrementAmount;
        }

        return $baseSalary;
    }

    private function getBaseSalary(): float
    {
        $grossSalary = $this->getGrossSalary();
        $statutoryPercentage = $this->payroll->user->bankDetails->first()->statutory_component_percentage ?? 0;
        $statutoryAdjustment = ($statutoryPercentage + 100) / 100;

        if ($statutoryAdjustment == 0) return $grossSalary; // Avoid division by zero

        return round($grossSalary / $statutoryAdjustment);
    }

    private function getPreviousPayrollData(): array
    {
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) {
            return ['base_sum' => 0, 'tax_paid_sum' => 0, 'taxable_earnings_sum' => 0, 'non_taxable_deductions_sum' => 0];
        }

        $fyStartDate = Carbon::parse($taxSlab->financial_year_start);

        $previousPayrolls = Payroll::where('user_id', $this->payroll->user->id)
            ->where('date_range_start', '>=', $fyStartDate->toDateString())
            ->where('date_range_start', '<', $this->payroll->date_range_start)
            ->get();

        $base_sum = $previousPayrolls->sum(fn($p) => (float) ($p->tax_data['monthly_taxable_base'] ?? 0));
        $tax_paid_sum = $previousPayrolls->sum(fn($p) => (float) ($p->tax_data['monthly_tax_calculated'] ?? 0));
        $taxable_earnings_sum = $previousPayrolls->sum(fn($p) => (float) ($p->tax_data['total_taxable_earnings_for_period'] ?? 0));
        $non_taxable_deductions_sum = $previousPayrolls->sum(fn($p) => (float) ($p->tax_data['total_non_taxable_deductions_for_period'] ?? 0));

        return compact('base_sum', 'tax_paid_sum', 'taxable_earnings_sum', 'non_taxable_deductions_sum');
    }

    private function getAnnualTaxableBase(): float
    {
        $previousData = $this->getPreviousPayrollData();
        $monthsRemaining = $this->getFiscalYearMonthsRemaining();

        $projectedBase = $this->getBaseSalary() * $monthsRemaining;
        $projectedAnnualTaxableBase = $previousData['base_sum'] + $projectedBase;

        $totalYTDEarnings = $previousData['taxable_earnings_sum'] + $this->getTotalTaxableEarnings();
        $totalYTDDeductions = $previousData['non_taxable_deductions_sum'] + $this->getTotalNonTaxableDeductions();

        return $projectedAnnualTaxableBase + $totalYTDEarnings - $totalYTDDeductions;
    }

    private function getAnnualTax(): float
    {
        $annualTaxableBase = $this->getAnnualTaxableBase();
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) return 0;

        $slabsData = collect($taxSlab->slabs_data)->sortBy('min_annual_salary')->values();
        $totalAnnualTax = 0.0;

        $applicableSlab = $slabsData->first(function ($slab) use ($annualTaxableBase) {
            $min = (float) ($slab['min_annual_salary'] ?? 0);
            $max = isset($slab['max_annual_salary']) && $slab['max_annual_salary'] !== '' ? (float) $slab['max_annual_salary'] : PHP_INT_MAX;
            return $annualTaxableBase >= $min && $annualTaxableBase <= $max;
        });

        if ($applicableSlab) {
            $min = (float) ($applicableSlab['min_annual_salary'] ?? 0);
            $tax_percentage = (float) ($applicableSlab['tax_percentage'] ?? 0);
            $additional_tax = (float) ($applicableSlab['additional_tax'] ?? 0);
            $totalAnnualTax = (($annualTaxableBase - $min) * ($tax_percentage / 100)) + $additional_tax;
        }

        return max(0, $totalAnnualTax);
    }

    private function getMonthlyTax(): float
    {
        $annualTax = $this->getAnnualTax();
        $previousData = $this->getPreviousPayrollData();
        $monthsRemaining = $this->getFiscalYearMonthsRemaining();

        $remainingTax = $annualTax - $previousData['tax_paid_sum'];

        if ($monthsRemaining <= 0) return max(0, $remainingTax); // Pay all remaining tax in the last month

        return max(0, $remainingTax / $monthsRemaining);
    }

    private function getNetPay(): float
    {
        $grossSalary = $this->getGrossSalary();
        $totalEarnings = $this->getTotalTaxableEarnings() + $this->getTotalNonTaxableEarnings();
        $totalDeductions = $this->getTotalTaxableDeductions() + $this->getTotalNonTaxableDeductions();
        $monthlyTax = $this->getMonthlyTax();

        return $grossSalary + $totalEarnings - $totalDeductions - $monthlyTax;
    }

    // Helper methods to get totals from form data, these seem mostly correct but ensure they handle all cases.
    private function getTotalTaxableEarnings(): float
    {
        $total = 0;
        $earnings = array_merge(
            $this->data['earnings'] ?? [],
            $this->data['ad_hoc_earnings'] ?? [],
            $this->data['fund_reimbursements'] ?? []
        );

        foreach ($earnings as $earning) {
            if (isset($earning['tax_status']) && $earning['tax_status'] === 'taxable') {
                $amount = $this->getComponentAmount($earning);
                $total += $this->calculateComponentValue($amount, $earning['value_type'] ?? 'number');
            }
        }

        if (isset($this->data['apply_overtime_earnings']) && $this->data['apply_overtime_earnings']) {
            $total += (float) ($this->data['overtime_earning_amount'] ?? 0);
        }

        return $total;
    }

    private function getTotalNonTaxableEarnings(): float
    {
        $total = 0;
        $earnings = array_merge(
            $this->data['earnings'] ?? [],
            $this->data['ad_hoc_earnings'] ?? [],
            $this->data['fund_reimbursements'] ?? []
        );

        foreach ($earnings as $earning) {
            if (isset($earning['tax_status']) && $earning['tax_status'] === 'non-taxable') {
                $amount = $this->getComponentAmount($earning);
                $total += $this->calculateComponentValue($amount, $earning['value_type'] ?? 'number');
            }
        }
        return $total;
    }

    private function getTotalTaxableDeductions(): float
    {
        $total = 0;
        $deductions = array_merge(
            $this->data['deductions'] ?? [],
            $this->data['ad_hoc_deductions'] ?? [],
            $this->data['fund_data'] ?? []
        );

        foreach ($deductions as $deduction) {
            if (isset($deduction['tax_status']) && $deduction['tax_status'] === 'taxable') {
                $amount = $this->getComponentAmount($deduction);
                $total += $this->calculateComponentValue($amount, $deduction['value_type'] ?? 'number');
            }
        }

        return $total;
    }

    private function getTotalNonTaxableDeductions(): float
    {
        $total = 0;
        $deductions = array_merge(
            $this->data['deductions'] ?? [],
            $this->data['ad_hoc_deductions'] ?? [],
            $this->data['fund_data'] ?? []
        );

        foreach ($deductions as $deduction) {
            if (isset($deduction['tax_status']) && $deduction['tax_status'] === 'non-taxable') {
                $amount = $this->getComponentAmount($deduction);
                $total += $this->calculateComponentValue($amount, $deduction['value_type'] ?? 'number');
            }
        }

        if (isset($this->data['deduct_late_penalties']) && $this->data['deduct_late_penalties']) {
            $total += (float) ($this->data['late_deduction_amount'] ?? 0);
        }

        if (isset($this->data['deduct_absent_penalties']) && $this->data['deduct_absent_penalties']) {
            $total += (float) ($this->data['absent_deduction_amount'] ?? 0);
        }
        
        $total += (float) ($this->payroll->loan_amount ?? 0);

        return $total;
    }

    private function getComponentAmount($component): float
    {
        return (float) ($component['amount'] ?? $component['amount_input'] ?? 0);
    }

    private function calculateComponentValue(float $amount, string $valueType): float
    {
        if ($valueType === 'percentage') {
            return $this->getBaseSalary() * ($amount / 100);
        }
        return $amount;
    }

    // These methods are for display in the placeholders and can be simplified or adjusted as needed.
    private function getAnnualExpectedSalary()
    {
        return $this->getBaseSalary() * $this->getFiscalYearMonthsRemaining();
    }

    private function getTaxableBase()
    {
        return $this->getBaseSalary() + $this->getTotalTaxableEarnings() - $this->getTotalNonTaxableDeductions();
    }

    private function getTaxSlabBraketMin()
    {
        $annualTaxableBase = $this->getAnnualTaxableBase();
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) return 0;
        $slabsData = $taxSlab->slabs_data;

        $applicableSlab = collect($slabsData)->first(function ($slab) use ($annualTaxableBase) {
            $min = (float) ($slab['min_annual_salary'] ?? 0);
            $max = isset($slab['max_annual_salary']) && $slab['max_annual_salary'] !== '' ? (float) $slab['max_annual_salary'] : PHP_INT_MAX;
            return $annualTaxableBase >= $min && $annualTaxableBase <= $max;
        });

        return $applicableSlab ? (float) ($applicableSlab['min_annual_salary'] ?? 0) : 0;
    }

    private function getTaxSlabBraketMax()
    {
        $annualTaxableBase = $this->getAnnualTaxableBase();
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) return 0;
        $slabsData = $taxSlab->slabs_data;

        $applicableSlab = collect($slabsData)->first(function ($slab) use ($annualTaxableBase) {
            $min = (float) ($slab['min_annual_salary'] ?? 0);
            $max = isset($slab['max_annual_salary']) && $slab['max_annual_salary'] !== '' ? (float) $slab['max_annual_salary'] : PHP_INT_MAX;
            return $annualTaxableBase >= $min && $annualTaxableBase <= $max;
        });

        return $applicableSlab ? (isset($applicableSlab['max_annual_salary']) && $applicableSlab['max_annual_salary'] !== '' ? (float) $applicableSlab['max_annual_salary'] : 'Above') : 0;
    }

    private function getTaxSlabPercentage()
    {
        $annualTaxableBase = $this->getAnnualTaxableBase();
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) return 0;
        $slabsData = $taxSlab->slabs_data;

        $applicableSlab = collect($slabsData)->first(function ($slab) use ($annualTaxableBase) {
            $min = (float) ($slab['min_annual_salary'] ?? 0);
            $max = isset($slab['max_annual_salary']) && $slab['max_annual_salary'] !== '' ? (float) $slab['max_annual_salary'] : PHP_INT_MAX;
            return $annualTaxableBase >= $min && $annualTaxableBase <= $max;
        });

        return $applicableSlab ? (float) ($applicableSlab['tax_percentage'] ?? 0) : 0;
    }

    private function getTaxSlabAdditional()
    {
        $annualTaxableBase = $this->getAnnualTaxableBase();
        $taxSlab = $this->getTaxSlab();
        if (!$taxSlab) return 0;
        $slabsData = $taxSlab->slabs_data;

        $applicableSlab = collect($slabsData)->first(function ($slab) use ($annualTaxableBase) {
            $min = (float) ($slab['min_annual_salary'] ?? 0);
            $max = isset($slab['max_annual_salary']) && $slab['max_annual_salary'] !== '' ? (float) $slab['max_annual_salary'] : PHP_INT_MAX;
            return $annualTaxableBase >= $min && $annualTaxableBase <= $max;
        });

        return $applicableSlab ? (float) ($applicableSlab['additional_tax'] ?? 0) : 0;
    }
}