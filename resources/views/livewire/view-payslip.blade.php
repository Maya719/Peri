<div>
    <style>
        body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            color: #333;
        }

        .payslip-container {
            font-family: Poppins, sans-serif;
            /* width: 100%; */
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            background-color: white;
            color: #333;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }

        .details-grid p {
            margin: 5px 0;
            font-size: 14px;
        }

        .section-title {
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
            color: #0056b3;
        }

        .payslip-container table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background-color: #fff;
            table-layout: fixed;
        }

        .payslip-container table th,
        .payslip-container table td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: left;
            font-size: 14px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .payslip-container table thead th {
            background-color: #e9e9e9;
            font-weight: bold;
            color: #333;
        }

        .payslip-container table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals-table td {
            font-size: 16px;
            font-weight: normal;
            text-align: left;
        }

        .totals-table tbody tr:first-child td {
            font-weight: bold;
        }

        .totals-table th {
            font-size: 16px;
            font-weight: bold;
        }
    </style>

    @php
        use App\Models\CompanyDetail;
        use App\Models\Setting;
        use App\Models\TaxSlabs;
        use App\Models\Team;

        $settings = Setting::getByType('general');

        function numberToWords($number, $symbol)
        {
            $currencyNames = [
                '$' => 'Dollars',
                'Rs' => 'Rupees',
                '₹' => 'Rupees',
                '€' => 'Euros',
                '£' => 'Pounds',
            ];
            $cleanSymbol = trim($symbol);
            $currencyName = $currencyNames[$cleanSymbol] ?? 'Currency';
            $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            return $currencyName . ' ' . ucfirst($formatter->format((int) round($number))) . ' only';
        }

        $contextAdminId = auth()->user()->latest_team_id;
        $company = Team::where('id', $contextAdminId)->first();
        $companyName = $company?->company_name ?? 'Company';

        $attendance = is_array($payroll->attendance_data)
            ? $payroll->attendance_data
            : json_decode($payroll->attendance_data ?? '{}', true);

        $showAbsent = $payroll->deduct_absent_penalties ?? false;
        $showLate = $payroll->deduct_late_penalties ?? false;
        $showOvertime = $payroll->apply_overtime_earnings ?? false;

        $currencySymbols = [
            'USD' => '$ ',
            'PKR' => 'Rs ',
            'INR' => '₹ ',
            'EUR' => '€ ',
            'GBP' => '£ ',
            'AED' => 'د.إ ',
        ];

        $currencyCode = optional(TaxSlabs::where('country_id', $company?->country_id)->first())->salary_currency;
        $symbol = $currencySymbols[$currencyCode] ?? '¤';

        $taxData = is_array($payroll->tax_data) ? $payroll->tax_data : json_decode($payroll->tax_data ?? '{}', true);
        $earningsData = $payroll->earnings_data ?? [];
        $deductionsData = $payroll->deductions_data ?? [];

        $incrementPercentage = null;
        $appliedIncrement = (float) ($payroll->applied_increment_amount ?? 0);
        if ($appliedIncrement > 0) {
            $salaryAfterIncrement = (float) ($payroll->base_salary ?? 0);
            $salaryBeforeIncrement = $salaryAfterIncrement - $appliedIncrement;
            if ($salaryBeforeIncrement > 0) {
                $incrementPercentage = ($appliedIncrement / $salaryBeforeIncrement) * 100;
            }
        }

        $baseSalary = $payroll->base_salary;
        $taxableEarnings = [];
        $nonTaxableEarnings = [];

        // Collect all earnings from custom_earnings_applied and ad_hoc_earnings
        $allEarnings = array_merge(
            $earningsData['custom_earnings_applied'] ?? [],
            $earningsData['ad_hoc_earnings'] ?? [],
        );

        foreach ($allEarnings as $earning) {
            if (isset($earning['tax_status']) && $earning['tax_status'] === 'taxable') {
                $taxableEarnings[] = $earning;
            } else {
                // Default to non-taxable if status is missing or not taxable
                $nonTaxableEarnings[] = $earning;
            }
        }

        // Add overtime earnings if applicable
        if ($showOvertime && !empty($attendance['overtime_earning_amount'])) {
            // Assuming overtime is non-taxable for display purposes here,
            // as its actual tax status is handled in PayrollCalculationService
            $nonTaxableEarnings[] = [
                'title' => 'Overtime Earnings',
                'calculated_amount' => $attendance['overtime_earning_amount'],
            ];
        }

        // Calculate total earnings for display
        $totalEarnings = $baseSalary;
        foreach ($taxableEarnings as $earning) {
            $totalEarnings += $earning['calculated_amount'];
        }
        foreach ($nonTaxableEarnings as $earning) {
            $totalEarnings += $earning['calculated_amount'];
        }

        $totalDeductions = 0;
        foreach ($deductionsData['custom_deductions_applied'] ?? [] as $deduction) {
            $totalDeductions += $deduction['calculated_amount'];
        }
        foreach ($deductionsData['ad_hoc_deductions'] ?? [] as $deduction) {
            $totalDeductions += $deduction['calculated_amount'];
        }
        foreach ($payroll->fund_data ?? [] as $fund_data) {
            $totalDeductions += $fund_data['calculated_amount'];
        }
        foreach ($payroll->loan_data ?? [] as $loan_data) {
            $totalDeductions += $loan_data['deducted_amount'];
        }
        if ($showLate && !empty($attendance['late_minutes_deduction_amount'])) {
            $totalDeductions += $attendance['late_minutes_deduction_amount'];
        }
        if ($showAbsent && !empty($attendance['absent_deduction_amount'])) {
            $totalDeductions += $attendance['absent_deduction_amount'];
        }
        $totalDeductions += $taxData['monthly_tax_calculated'] ?? 0;
    @endphp

    <div class="payslip-container">
        <div class="details-grid">
            <div>
                <p><strong>Employee Name:</strong> {{ $payroll->user->name }}</p>
            </div>
            <div>
                <p><strong>Period:</strong> {{ $payroll->date_range_start->format('M Y') }}</p>
            </div>
            <div>
                <p><strong>Month Days:</strong> {{ $attendance['total_days'] ?? 'N/A' }}</p>
            </div>
            <div>
                <p><strong>Working Days:</strong> {{ $attendance['actual_working_days'] ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="payroll-components">
            <div class="section-title">Earnings</div>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Gross Salary</td>
                        <td>{{ $symbol }}{{ number_format($baseSalary) }}</td>
                    </tr>
                    @if (!empty($taxableEarnings))
                        @foreach ($taxableEarnings as $earning)
                            @if (($earning['calculated_amount'] ?? 0) > 0)
                                <tr>
                                    <td>{{ $earning['title'] }}</td>
                                    <td>{{ $symbol }}{{ number_format($earning['calculated_amount']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @endif
                    @if (!empty($nonTaxableEarnings))
                        @foreach ($nonTaxableEarnings as $earning)
                            @if (($earning['calculated_amount'] ?? 0) > 0)
                                <tr>
                                    <td>{{ $earning['title'] }}</td>
                                    <td>{{ $symbol }}{{ number_format($earning['calculated_amount']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @endif
                    <tr>
                        <td><strong>Total Earnings</strong></td>
                        <td><strong>{{ $symbol }}{{ number_format($totalEarnings) }}</strong></td>
                    </tr>
                    @if ($appliedIncrement > 0)
                        <tr>
                            <td><strong>Increment @if ($incrementPercentage !== null)
                                        ({{ number_format($incrementPercentage) }}%)
                                    @endif
                                </strong></td>
                            <td>{{ $symbol }}{{ number_format($appliedIncrement) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="section-title">Deductions</div>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deductionsData['custom_deductions_applied'] ?? [] as $deduction)
                        @if (($deduction['calculated_amount'] ?? 0) > 0)
                            <tr>
                                <td>{{ $deduction['title'] }}</td>
                                <td>{{ $symbol }}{{ number_format($deduction['calculated_amount']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @foreach ($deductionsData['ad_hoc_deductions'] ?? [] as $deduction)
                        @if (($deduction['calculated_amount'] ?? 0) > 0)
                            <tr>
                                <td>{{ $deduction['title'] }}</td>
                                <td>{{ $symbol }}{{ number_format($deduction['calculated_amount']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @foreach ($payroll->fund_data ?? [] as $fund_data)
                        @if (($fund_data['calculated_amount'] ?? 0) > 0)
                            <tr>
                                <td>{{ $fund_data['title'] }}</td>
                                <td>{{ $symbol }}{{ number_format($fund_data['calculated_amount']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @foreach ($payroll->loan_data ?? [] as $loan_data)
                        @if (($loan_data['deducted_amount'] ?? 0) > 0)
                            <tr>
                                <td>{{ $loan_data['loan_name'] }}</td>
                                <td>{{ $symbol }}{{ number_format($loan_data['deducted_amount']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @if ($showLate && !empty($attendance['late_minutes_deduction_amount']))
                        <tr>
                            <td>Late Penalties</td>
                            <td>{{ $symbol }}{{ number_format($attendance['late_minutes_deduction_amount']) }}
                            </td>
                        </tr>
                    @endif
                    @if ($showAbsent && !empty($attendance['absent_deduction_amount']))
                        <tr>
                            <td>Absent Penalties</td>
                            <td>{{ $symbol }}{{ number_format($attendance['absent_deduction_amount']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Govt. Tax</td>
                        <td>{{ $symbol }}{{ number_format($taxData['monthly_tax_calculated'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Deductions</strong></td>
                        <td><strong>{{ $symbol }}{{ number_format($totalDeductions) }}</strong></td>
                    </tr>
                </tbody>
            </table>
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td>Net Pay</td>
                        <td>{{ $symbol }}{{ number_format($payroll->net_payable_salary) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">{{ numberToWords($payroll->net_payable_salary, $symbol) }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($showAbsent || $showLate || $showOvertime)
                <div class="section-title">Attendance Summary</div>
                <table>
                    <thead>
                        <tr>
                            <th>Paid Leaves</th>
                            <th>Unpaid Leaves</th>
                            <th>Present Days</th>
                            @if ($showAbsent)
                                <th>Absent Days</th>
                            @endif
                            @if ($showLate)
                                <th>Late Minutes</th>
                            @endif
                            @if ($showOvertime)
                                <th>Overtime Minutes</th>
                            @endif
                            <th>Per Day Rate ({{ $symbol }})</th>
                            <th>Per Minute Rate ({{ $symbol }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $attendance['paid_leave_days_count'] ?? 0 }}</td>
                            <td>{{ $attendance['unpaid_leave_days_count'] ?? 0 }}</td>
                            <td>{{ $attendance['present_days_count'] ?? 0 }}</td>
                            @if ($showAbsent)
                                <td>{{ $attendance['absent_days_count'] ?? 0 }}</td>
                            @endif
                            @if ($showLate)
                                <td>{{ $attendance['total_late_minutes'] ?? 0 }}</td>
                            @endif
                            @if ($showOvertime)
                                <td>{{ $attendance['total_overtime_minutes'] ?? 0 }}</td>
                            @endif
                            <td>{{ number_format($attendance['per_day_rate_used'] ?? 0, 2) }}</td>
                            <td>{{ number_format($attendance['per_minute_rate_used'] ?? 0, 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
