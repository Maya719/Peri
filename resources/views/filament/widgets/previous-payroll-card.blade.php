<x-filament::widget>
    <div class="filament-widgets-card p-4 bg-white rounded-xl shadow-sm dark:bg-gray-900 border border-gray-200 ">
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-baseline gap-2 sm:gap-4">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Payroll Details
                </h2>
            </div>
            <div
                class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-white rounded-md px-3 py-1.5 shadow-sm transition focus-within:ring-2 focus-within:ring-primary-500">
                <x-filament::input.select wire:model.live="selectedMonth">
                    @foreach ($this->availableMonths as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </div>
        </div>

        @if ($this->getViewData()['hasData'])
            {{-- Main Stats Section --}}
            <div class="p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 gap-6 mb-6">
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Net Pay</h3>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($this->getViewData()['currencySymbol'], $this->getViewData()['totalNetPay']) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Tax</h3>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($this->getViewData()['currencySymbol'], $this->getViewData()['totalTax']) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Employees Processed</h3>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($this->getViewData()['employeeCount']) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">One-Time Payments</h3>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($this->getViewData()['offCyclePayrolls']) }}
                    </p>
                </div>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Status</h3>
                    @php
                        $status = $this->getViewData()['status'];
                        $statusColor = match (strtolower($status)) {
                            'finalized' => ' text-info-600',
                            'paid' => ' text-success-600',
                            'pending_approval' => ' text-warning-600',
                            'draft' => ' text-warning-600',
                            'rejected' => ' text-danger-600',
                            default => ' text-gray-800 ',
                        };
                        $formattedStatus = match (strtolower($status)) {
                            'finalized' => 'Approved',
                            default => ucwords(str_replace('_', ' ', strtolower($status))),
                        };
                    @endphp
                    <span class="text-2xl font-semibold {{ $statusColor }}">
                        <span class="w-2 h-2 rounded-full"></span>
                        {{ $formattedStatus }}
                    </span>
                </div>
            </div>

            {{-- Funds Breakdown Section --}}
            @if (!empty($this->getViewData()['fundsBreakdown']))
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3" style="padding-left: 1rem">Funds
                    Breakdown</h3>
                <div class="flex flex-wrap gap-4" style="padding-left: 1rem">
                    @foreach ($this->getViewData()['fundsBreakdown'] as $fundName => $fundAmount)
                        <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg flex-initial">
                            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $fundName }}
                            </h4>
                            <p class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ $this->formatCurrency($this->getViewData()['currencySymbol'], $fundAmount) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                No previous payroll data available for this month.
            </div>
        @endif

    </div>
</x-filament::widget>
