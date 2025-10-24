<?php

namespace App\Models;

use App\Facades\Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
        'pay_run_id',
        'date_range_start',
        'date_range_end',
        'month_indicator',
        'base_salary',
        'earnings_data',
        'deductions_data',
        'fund_data',
        'loan_data',
        'loan_amount',
        'applied_one_time_deductions',
        'attendance_data',
        'deduct_late_penalties',
        'deduct_absent_penalties',
        'apply_overtime_earnings',
        'tax_data',
        'net_payable_salary',
        'applied_increment_amount',
        'status',
        'payment_mode',
        'other_payment_mode',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_range_start' => 'date',
        'date_range_end' => 'date',
        'month_indicator' => 'integer',
        'base_salary' => 'decimal:2', // Cast to decimal with 2 precision
        'earnings_data' => 'array', // Cast JSON columns to array
        'deductions_data' => 'array', // Cast JSON columns to array
        'applied_one_time_deductions' => 'array', // Cast JSON columns to array
        'attendance_data' => 'array', // Cast JSON columns to array
        'deduct_late_penalties' => 'boolean',
        'deduct_absent_penalties' => 'boolean',
        'apply_overtime_earnings' => 'boolean',
        'tax_data' => 'array', // Cast JSON columns to array
        'net_payable_salary' => 'decimal:2',
        'applied_increment_amount' => 'decimal:2',
        'other_payment_mode' => 'array',
        'fund_data' => 'array',
        'loan_data' => 'array',
    ];

    /**
     * Get the user that owns the payroll.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function scopeVisibleToCurrentUser($query)
    {
        if (Auth::user()->hasRole('Admin')) {
            return $query;
        } elseif (Helper::isAssignUsers()) {
            return $query->whereIn('user_id', Helper::getAssignUsersIds());
        } else {
            return $query->where('user_id', Auth::id());
        }
    }
    /**
     * Get the pay run that the payroll belongs to.
     */
    public function payRun()
    {
        return $this->belongsTo(PayRun::class);
    }

    /**
     * Get the total earnings for the payroll.
     *
     * @return float
     */
    public function getTotalEarningsAttribute(): float
    {
        $total = 0;

        // Ensure earnings_data is an array (due to JSON casting)
        $earningsData = $this->earnings_data ?? [];

        // Sum from custom_earnings_applied
        foreach (($earningsData['custom_earnings_applied'] ?? []) as $earning) {
            $total += (float)($earning['calculated_amount'] ?? 0);
        }

        // Sum from ad_hoc_earnings
        foreach (($earningsData['ad_hoc_earnings'] ?? []) as $earning) {
            $total += (float)($earning['calculated_amount'] ?? 0);
        }

        // Add overtime from attendance data ONLY IF 'apply_overtime_earnings' toggle is true
        $attendanceData = $this->attendance_data ?? [];
        if ($this->apply_overtime_earnings && !empty($attendanceData['overtime_earning_amount'])) {
            $total += (float)($attendanceData['overtime_earning_amount']);
        }

        return round($total, 2);
    }

    /**
     * Get the total deductions for the payroll.
     *
     * @return float
     */
    public function getTotalDeductionsAttribute(): float
    {
        $total = 0;

        // Ensure deductions_data is an array (due to JSON casting)
        $deductionsData = $this->deductions_data ?? [];
        $fundDeductions = $this->fund_data ?? [];

        // Sum from custom_deductions_applied
        foreach (($deductionsData['custom_deductions_applied'] ?? []) as $deduction) {
            $total += (float)($deduction['calculated_amount'] ?? 0);
        }

        // Sum from ad_hoc_deductions
        foreach (($deductionsData['ad_hoc_deductions'] ?? []) as $deduction) {
            $total += (float)($deduction['calculated_amount'] ?? 0);
        }

        // Add late penalties from attendance data ONLY IF 'deduct_late_penalties' toggle is true
        $attendanceData = $this->attendance_data ?? [];
        if ($this->deduct_late_penalties && !empty($attendanceData['late_minutes_deduction_amount'])) {
            $total += (float)($attendanceData['late_minutes_deduction_amount']);
        }

        // Add absent penalties from attendance data ONLY IF 'deduct_absent_penalties' toggle is true
        if ($this->deduct_absent_penalties && !empty($attendanceData['absent_deduction_amount'])) {
            $total += (float)($attendanceData['absent_deduction_amount']);
        }
        foreach ($fundDeductions as $fundDeduction) {
            $total += (float)($fundDeduction['calculated_amount']);
        }

        // Add loan amount
        $total += (float)($this->loan_amount ?? 0);

        return round($total, 2);
    }
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
