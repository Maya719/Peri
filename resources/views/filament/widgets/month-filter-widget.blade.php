@php
    $user = auth()->user();
    $showDashboard2 =
        ($user && $user->attendance_config == 0 && $user->can('payroll.manage')) || $user->hasRole('Payroll Manager');
@endphp

<x-filament::widget>
    <div class="flex justify-between items-center p-4">
        @if ($showDashboard2)
            <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                Payroll Dashboard
            </h2>

            <div class>
                <x-filament::button tag="a"
                    href="{{ \App\Filament\Client\Resources\PayRunResource::getUrl('index') }}"> Go to Payroll
                </x-filament::button>
            </div>
        @else
            <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl block sm:hidden">
                Dashboard
            </h2>
            <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl hidden sm:block">
                Dashboard Overview
            </h2>

            <div
                class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-3 py-1.5 shadow-sm transition focus-within:ring-2 focus-within:ring-primary-500">
                <x-filament::input type="month" wire:model.live="selectedMonth" :max="now()->format('Y-m')"
                    class="border-0 focus:ring-0 text-sm text-gray-700 dark:text-white bg-transparent px-0 py-0" />
            </div>
        @endif
    </div>
</x-filament::widget>
