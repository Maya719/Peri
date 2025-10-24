<x-filament-widgets::widget>
    <style>
        .badge-birthday {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.70rem;
            font-weight: 600;
        }

        .badge-anniversary {
            font-size: 0.70rem;
            font-weight: 600;
        }

        .badge-probation {
            font-size: 0.70rem;
            font-weight: 600;
        }

        .custom-input {
            padding: 0.5rem 2.4rem;
            width: 14rem;
        }

        .custom-input,
        .custom-select {
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            background-color: #fff;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .dark .custom-input,
        .dark .custom-select {
            background-color: #374151;
            border-color: #4b5563;
            color: #f3f4f6;
        }

        .custom-select {
            appearance: none;
            /* Remove default arrow on some browsers */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            /* Tailwind's default select arrow */
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            /* Make space for the arrow */
        }

        .dark .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            /* Dark mode arrow */
        }
    </style>
    <x-filament::card class="p-0 overflow-hidden border rounded-xl shadow-sm dark:!border-red-500">
        <div class="w-full">
            {{-- Header Table: Contains Main Title --}}
            <div class="w-full overflow-x-auto">
                <table class="w-full rounded-md table-fixed" style="background-color: #17345c;">
                    <thead>
                        <tr class="text-left border-b text-lg border-gray-700">
                            <th class="px-4 py-4 font-semibold text-gray-100" colspan="3">Reminders</th>
                        </tr>
                    </thead>
                </table>
            </div>

            {{-- Filter Controls Section --}}
            <div
                class="flex items-center justify-between gap-x-4 px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="flex w-full items-center justify-between gap-x-4">
                    <div class="relative flex-grow">
                        {{-- Search Icon --}}
                        <div class="absolute inset-y-0 left-0 flex items-center p-3 pointer-events-none">
                            <svg wire:loading.remove.delay.default="1" wire:target="tableSearch"
                                class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>


                        {{-- Input Field --}}
                        <input type="text" id="searchNameWidget" wire:model.live.debounce.300ms="searchName"
                            placeholder="Search Employee" class="custom-input w-full" />
                    </div>

                </div>
            </div>

            {{-- Column Headers for the Table --}}
            <div class="w-full overflow-x-auto">
                <table class="w-full table-fixed"> {{-- Table for headers --}}
                    <thead class="border-b bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-sm">
                            <th class="px-4 py-3.5 font-semibold text-black dark:text-gray-200" style="width: 50%;">
                                Employee</th>
                            <th class="px-4 py-3.5 font-semibold text-black dark:text-gray-200" style="width: 30%;">Type
                            </th>
                            <th class="px-4 py-3.5 font-semibold text-black dark:text-gray-200" style="width: 20%;">Date
                            </th>
                        </tr>
                    </thead>
                </table>
            </div>


            {{-- Scrollable Body Table --}}
            <div class="w-full overflow-y-auto" style="max-height: 26.5rem; overflow-x: hidden;">
                <table class="w-full table-fixed"> {{-- Table for body content --}}
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($events as $event)
                            <tr class="text-sm even:bg-gray-50 dark:even:bg-gray-800/50">
                                <td class="px-4 py-4 lg:py-3 text-left text-gray-700 dark:text-gray-200"
                                    style="width: 50%;">
                                    <div class="flex items-center">
                                        <img class="h-10 w-10 rounded-full object-cover mr-4"
                                            src="{{ $event['avatar_url'] }}" alt="{{ $event['name'] }}">
                                        <span style="padding-left: 10px;">{{ $event['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 lg:py-3 text-gray-700 dark:text-gray-200">
                                    @php
                                        $badgeColor = 'gray'; // Default color
                                        $badgeClass = '';
                                        if ($event['type'] === 'Birthday') {
                                            $badgeColor = 'warning';
                                            $badgeClass = 'badge-birthday';
                                        } elseif ($event['type'] === 'Work Anniversary') {
                                            $badgeColor = 'success';
                                            $badgeClass = 'badge-anniversary';
                                        } elseif ($event['type'] === 'Probation End') {
                                            $badgeColor = 'info';
                                            $badgeClass = 'badge-probation';
                                        }
                                    @endphp
                                    <x-filament::badge :color="$badgeColor">
                                        <span class="{{ $badgeClass }}">
                                            {{ $event['type'] }}
                                        </span>
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 lg:py-3 text-left text-gray-700 dark:text-gray-200">
                                    {{ $event['date'] }}
                                    @if ($event['days_until'] < 0 && $event['type'] === 'Probation End')
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            ({{ abs($event['days_until']) }} days ago)
                                        </span>
                                    @elseif($event['days_until'] == 0)
                                        <span class="text-xs text-primary-500"> (Today)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    No upcoming events.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::card>
</x-filament-widgets::widget>
