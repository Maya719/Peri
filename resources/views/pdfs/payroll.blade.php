<!DOCTYPE html>
<html lang="en">

<head>
    @if (!function_exists('numberToWords'))
        <?php
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
            $roundedNumber = (int) round($number);
        
            return $currencyName . ' ' . ucfirst($formatter->format($roundedNumber)) . ' only';
        }
        ?>
    @endif
    @php
        use Illuminate\Support\Facades\Auth;
        use App\Models\Team;
        use App\Models\TaxSlabs;

        // Determine admin context
        $contextAdminId = Auth::user()->latest_team_id;
        // Fetch company details
        $company = Team::where('id', $contextAdminId)->first();

        $companyName = $company?->name ?? 'Company';

        // Decode attendance data
        $attendance = is_array($payroll->attendance_data)
            ? $payroll->attendance_data
            : json_decode($payroll->attendance_data, true);

        // Decode tax data
        $tax = is_array($payroll->tax_data) ? $payroll->tax_data : json_decode($payroll->tax_data, true);

        // Currency symbols mapping
        $currencySymbols = [
            'USD' => '$ ',
            'PKR' => 'Rs ',
            'INR' => '₹ ',
            'EUR' => '€ ',
            'GBP' => '£ ',
            'AED' => 'د.إ ',
        ];

        // Get currency code from TaxSlabs
        $currencyCode = null;
        if ($company?->country_id) {
            $taxSlab = TaxSlabs::where('country_id', $company->country_id)->first();
            if (!empty($taxSlab?->salary_currency)) {
                $currencyCode = $taxSlab->salary_currency;
            }
        }

        // Determine currency symbol
        $symbol = $currencySymbols[$currencyCode] ?? '¤';

        // Calculate increment percentage
        $incrementPercentage = null;
        $appliedIncrement = (float) ($payroll->applied_increment_amount ?? 0);
        if ($appliedIncrement > 0) {
            $salaryAfterIncrement = (float) ($payroll->base_salary ?? 0);
            $salaryBeforeIncrement = $salaryAfterIncrement - $appliedIncrement;

            if ($salaryBeforeIncrement > 0) {
                $incrementPercentage = ($appliedIncrement / $salaryBeforeIncrement) * 100;
            }
        }
    @endphp
    @php
        $attendance = $payroll->attendance_data ?? [];

        $showAbsent = $payroll->deduct_absent_penalties ?? false;
        $showLate = $payroll->deduct_late_penalties ?? false;
        $showOvertime = $payroll->apply_overtime_earnings ?? false;

        $shouldShowTable = $showAbsent || $showLate || $showOvertime;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: white;
            padding: 0;
            margin: 0;
        }

        td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .salary-slip {
            width: 180mm;
            margin: 0 auto;
            background: white;
            padding: 15mm;
            font-size: 12px;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            width: 100px;
            height: auto;
            margin: 0 auto 8px auto;
            display: block;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #000;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #000;
        }

        .basic-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .basic-info td {
            padding: 4px 8px;
            border: 1px solid #000;
            font-size: 12px;
        }

        .basic-info .label {
            font-weight: bold;
            background-color: #f0f0f0;
            width: 25%;
        }

        .earnings-deductions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .earnings-deductions-table td,
        .earnings-deductions-table th {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 12px;
            text-align: left;
        }

        .earnings-deductions-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .earnings-deductions-table .amount {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        .net-salary-section {
            margin: 20px 0;
        }

        .net-salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .net-salary-table td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .net-salary-table .label {
            background-color: #f0f0f0;
            width: 30%;
        }

        .payment-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .payment-info td {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 12px;
        }

        .payment-info .label {
            font-weight: bold;
            background-color: #f0f0f0;
            width: 25%;
        }

        .attendance-section {
            margin-bottom: 20px;
        }

        .attendance-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table td,
        .attendance-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 11px;
            text-align: center;
        }

        .attendance-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .footer {
            position: absolute;
            bottom: 10;
            left: 0;
            right: 0;
            font-size: 10px;
            text-align: center;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="salary-slip">
        <div class="header">
            @if ($company && $company->logo)
                <img src="{{ asset('storage/' . $company->logo) }}" class="logo">
            @endif
            <div class="company-name">{{ $companyName }}</div>
            <div class="document-title">Salary Slip</div>
        </div>

        <!-- Basic Information Table -->
        <table class="basic-info" style="width: 100%; table-layout: fixed;">
            <tr>
                <td class="label" style="width: 25%;">Month</td>
                <td style="width: 75%;" colspan="3">{{ $payroll->date_range_start->format('F Y') }}</td>
            </tr>
            <tr>
                <td class="label" style="width: 25%;">Employee ID</td>
                <td style="width: 25%;">{{ $payroll->user->id ?? 'N/A' }}</td>
                <td class="label" style="width: 25%;">Name</td>
                <td style="width: 25%;">{{ $payroll->user->name }}</td>
            </tr>
            <tr>
                <td class="label" style="width: 25%;">Designation</td>
                <td style="width: 25%;">{{ $payroll->user->designation ?? 'N/A' }}</td>
                <td class="label" style="width: 25%;">Email</td>
                <td style="width: 25%;">{{ $payroll->user->email }}</td>
            </tr>
            <tr>
                <td class="label" style="width: 25%;">Month Days</td>
                <td style="width: 25%;">{{ $payroll->attendance_data['total_days'] ?? 'N/A' }}</td>
                <td class="label" style="width: 25%;">Working Days</td>
                <td style="width: 25%;">{{ $payroll->attendance_data['actual_working_days'] ?? 'N/A' }}</td>
            </tr>
        </table>


        <!-- Earnings and Deductions Table -->
        <table class="earnings-deductions-table" style="width: 100%; table-layout: fixed;">
            <tr>
                <th style="width: 25%;">Earnings</th>
                <th style="width: 25%;">Amount</th>
                <th style="width: 25%;">Deductions</th>
                <th style="width: 25%;">Amount</th>
            </tr>
            @php
                $earnings = collect();
                $deductions = collect();

                $earnings->push([
                    'title' => 'Gross Salary',
                    'calculated_amount' => $payroll->base_salary,
                ]);

                $earningsData = $payroll->earnings_data ?? [];
                if (!empty($earningsData['ad_hoc_earnings'])) {
                    foreach ($earningsData['ad_hoc_earnings'] as $item) {
                        $earnings->push([
                            'title' => $item['title'] ?? $item['name'],
                            'calculated_amount' => $item['calculated_amount'] ?? 0,
                        ]);
                    }
                }
                if (!empty($earningsData['custom_earnings_applied'])) {
                    foreach ($earningsData['custom_earnings_applied'] as $item) {
                        $earnings->push([
                            'title' => $item['title'] ?? $item['name'],
                            'calculated_amount' => $item['calculated_amount'] ?? 0,
                        ]);
                    }
                }

                $deductionsData = $payroll->deductions_data ?? [];
                $fundDeductions = $payroll->fund_data ?? [];
                if (!empty($deductionsData['ad_hoc_deductions'])) {
                    foreach ($deductionsData['ad_hoc_deductions'] as $item) {
                        $deductions->push([
                            'title' => $item['title'] ?? $item['name'],
                            'calculated_amount' => $item['calculated_amount'] ?? 0,
                        ]);
                    }
                }
                if (!empty($deductionsData['custom_deductions_applied'])) {
                    foreach ($deductionsData['custom_deductions_applied'] as $item) {
                        $deductions->push([
                            'title' => $item['title'] ?? $item['name'],
                            'calculated_amount' => $item['calculated_amount'] ?? 0,
                        ]);
                    }
                }

                $attendance = $payroll->attendance_data ?? [];

                if (
                    $showOvertime &&
                    !empty($attendance['overtime_earning_amount']) &&
                    $attendance['overtime_earning_amount'] > 0
                ) {
                    $earnings->push([
                        'title' => 'Overtime Earning',
                        'calculated_amount' => $attendance['overtime_earning_amount'],
                    ]);
                }

                if (
                    $showLate &&
                    !empty($attendance['late_minutes_deduction_amount']) &&
                    $attendance['late_minutes_deduction_amount'] > 0
                ) {
                    $deductions->push([
                        'title' => 'Late Minutes Deduction',
                        'calculated_amount' => $attendance['late_minutes_deduction_amount'],
                    ]);
                }

                if (
                    $showAbsent &&
                    !empty($attendance['absent_deduction_amount']) &&
                    $attendance['absent_deduction_amount'] > 0
                ) {
                    $deductions->push([
                        'title' => 'Absent Deduction',
                        'calculated_amount' => $attendance['absent_deduction_amount'],
                    ]);
                }
                if (!empty($fundDeductions)) {
                    foreach ($fundDeductions as $item) {
                        $deductions->push([
                            'title' => $item['title'],
                            'calculated_amount' => $item['calculated_amount'],
                        ]);
                    }
                }

                $deductions->push([
                    'title' => 'Govt. Tax',
                    'calculated_amount' => $payroll->tax_data['monthly_tax_calculated'] ?? 0,
                ]);

                $earningCount = $earnings->count();
                $deductionCount = $deductions->count();
                $max = max($earningCount, $deductionCount);

                $totalEarnings = 0;
                $totalDeductions = 0;
            @endphp

            @for ($i = 0; $i < $max; $i++)
                @php
                    $earning = $earnings[$i] ?? null;
                    $deduction = $deductions[$i] ?? null;

                    $earningTitle = $earning['title'] ?? null;
                    $deductionTitle = $deduction['title'] ?? null;

                    $earningAmount = $earning['calculated_amount'] ?? 0;
                    $deductionAmount = $deduction['calculated_amount'] ?? 0;

                    if ($earningTitle) {
                        $totalEarnings += $earningAmount;
                    }

                    if ($deductionTitle) {
                        $totalDeductions += $deductionAmount;
                    }
                @endphp
                <tr>
                    <td>{{ $earningTitle ?? '' }}</td>
                    <td class="amount">{{ $earningTitle ? number_format($earningAmount) : '' }}</td>
                    <td>{{ $deductionTitle ?? '' }}</td>
                    <td class="amount">{{ $deductionTitle ? number_format($deductionAmount) : '' }}</td>
                </tr>
            @endfor

            <tr>
                <td><strong>Total Earnings</strong></td>
                <td class="amount">{{ $symbol }} {{ number_format($totalEarnings) }}</td>
                <td><strong>Total Deductions</strong></td>
                <td class="amount">{{ $symbol }} {{ number_format($totalDeductions) }}</td>
            </tr>

            @if (!empty($payroll->applied_increment_amount) && $payroll->applied_increment_amount > 0)
                <tr>
                    <td><strong>Increment @if ($incrementPercentage !== null)
                                ({{ number_format($incrementPercentage) }}% incl.)
                            @endif
                        </strong></td>
                    <td class="amount">{{ $symbol }} {{ number_format($payroll->applied_increment_amount) }}
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
        </table>

        <!-- Net Salary Section -->
        <div class="net-salary-section">
            <table class="net-salary-table" style="width: 100%; table-layout: fixed;">
                <tr>
                    <td class="label" style="width: 25%;">Net Salary</td>
                    <td style="width: 75%;">
                        <strong>{{ $symbol }}{{ number_format($payroll->net_payable_salary) }}</strong>
                    </td>
                </tr>
                <tr>
                    <td class="label" style="width: 25%;">Salary in Words</td>
                    <td style="width: 75%; font-weight: normal;">
                        {{ ucfirst(numberToWords($payroll->net_payable_salary, $symbol)) }}</td>
                </tr>
            </table>
        </div>

        <!-- Payment Information -->
        @php
            $bankDetails = $payroll->user->bankDetails()->where('team_id', $company->id)->first();
        @endphp
        <table class="payment-info" style="width: 100%; table-layout: fixed;">
            <tr>
                <td class="label" style="width: 25%;">Payment Mode</td>
                <td style="width: 25%;">
                    @if ($bankDetails)
                        @switch($bankDetails->payment_method)
                            @case('cheque')
                                Cheque
                            @break

                            @case('cash')
                                Cash
                            @break

                            @case('bank_transfer')
                                Bank Transfer
                            @break

                            @case('other')
                                {{ $bankDetails->other_payment_method ?? 'N/A' }}
                            @break

                            @default
                                {{ ucfirst($bankDetails->payment_method ?? 'N/A') }}
                        @endswitch
                    @else
                        N/A
                    @endif
                </td>
                @if ($bankDetails && $bankDetails->payment_method === 'bank_transfer')
                    <td class="label" style="width: 25%;">A/C Number</td>
                    <td style="width: 25%;">
                        {{ $bankDetails->account_number ?? 'N/A' }}
                    </td>
                @endif
            </tr>
        </table>


        <!-- Monthly Attendance Rate -->
        @if ($shouldShowTable)
            <div class="attendance-section">
                <table class="attendance-table">
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
                            <th>Per Day Rate</th>
                            <th>Per Minute Rate</th>
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
                            <td>{{ $symbol }}{{ number_format($attendance['per_day_rate_used'] ?? 0) }}
                            </td>
                            <td>{{ $symbol }}{{ number_format($attendance['per_minute_rate_used'] ?? 0) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
        <div class="footer">
            Note: This is a computer generated salary slip and does not require a stamp or signature.
        </div>
    </div>
</body>

</html>
