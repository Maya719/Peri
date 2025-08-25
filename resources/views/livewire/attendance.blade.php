@php
    use App\Filament\Client\Pages\UserAttendanceReport;
    use Carbon\Carbon;
    use Carbon\CarbonPeriod;
@endphp
<div>
    <div class="mb-6">
        <x-filament::card>
            <div>
                {{ $this->form }}
            </div>
        </x-filament::card>
    </div>

    <div class="relative">
        {{-- Loading Overlay --}}
        <div wire:loading class="absolute inset-0 z-50 bg-white bg-opacity-80 flex items-center justify-center">
            <div class="flex flex-col items-center space-y-3">
                <svg class="w-8 h-8 text-primary-600" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="4" fill="none"
                        class="opacity-20" />
                    <line x1="50" y1="50" x2="50" y2="20" stroke="currentColor"
                        stroke-width="3" stroke-linecap="round">
                        <animateTransform attributeName="transform" attributeType="XML" type="rotate" from="0 50 50"
                            to="360 50 50" dur="1s" repeatCount="indefinite" />
                    </line>
                    <circle cx="50" cy="50" r="2.5" fill="currentColor" />
                </svg>

                <div class="text-primary-600 text-sm font-medium">
                    Loading attendance data...
                </div>
            </div>
        </div>

        @php
            $datesGroupedByMonth = [];
            if (count($userAttendanceData)) {
                $firstUser = collect($userAttendanceData)->first();
                $dateKeys = array_filter(array_keys($firstUser), fn($key) => Carbon::canBeCreatedFromFormat($key, 'Y-m-d'));
                $period = CarbonPeriod::create(min($dateKeys), max($dateKeys));

                foreach ($period as $date) {
                    $monthLabel = $date->format('F-Y');
                    $datesGroupedByMonth[$monthLabel][] = $date->format('Y-m-d');
                }
            }
        @endphp

        {{-- Attendance Table --}}
        @if (count($userAttendanceData))
            <div class="mt-6">
                <div class="relative overflow-x-auto mt-4 mb-4">
                    <table
                        class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 border border-gray-300">
                        <thead class="text-xs text-gray-100 uppercase dark:text-gray-400">
                            <tr>
                                <th class="border border-gray-300 bg-primary-600 dark:bg-gray-600" colspan="4"></th>
                                @foreach ($datesGroupedByMonth as $month => $dates)
                                    <th colspan="{{ count($dates) }}"
                                        class="text-center border border-gray-300 py-1 bg-primary-600 dark:bg-gray-700">
                                        {{ $month }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                <th class="border border-gray-300 bg-primary-600 dark:bg-gray-600" colspan="4"></th>
                                @foreach ($datesGroupedByMonth as $month => $dates)
                                    @foreach ($dates as $index => $date)
                                        <th
                                            class="text-center border border-gray-300 py-1 bg-primary-600 dark:bg-gray-700">
                                            {{ \Carbon\Carbon::parse($date)->format('D')[0] }}
                                        </th>
                                    @endforeach
                                @endforeach
                            </tr>
                            <tr>
                                <th
                                    class="w-18 py-3 text-center dark:text-gray-400 border border-gray-300 bg-primary-600 dark:bg-gray-600">
                                    ID
                                </th>
                                <th
                                    class="py-3 text-center border dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                    Type
                                </th>
                                <th
                                    class="py-3 text-center border dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                    Name
                                </th>

                                @if ($policy->late_policy_enabled == 1 && $policy->overtime_policy_enabled == 0)
                                    <th
                                        class="text-center border dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                        A / L /
                                        LM</th>
                                @elseif ($policy->late_policy_enabled == 1 && $policy->overtime_policy_enabled == 1)
                                    <th
                                        class="text-center border text-gray-700 dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                        A / L /
                                        LM / OT</th>
                                @elseif ($policy->late_policy_enabled == 0 && $policy->overtime_policy_enabled == 1)
                                    <th
                                        class="text-center border text-gray-700 dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                        A / L /
                                        OT</th>
                                @else
                                    <th
                                        class="text-center border text-gray-700 dark:text-gray-400 border-gray-300 bg-primary-600 dark:bg-gray-600">
                                        A / L
                                    </th>
                                @endif

                                @foreach ($datesGroupedByMonth as $month => $dates)
                                    @foreach ($dates as $index => $date)
                                        <th class="text-center border border-gray-300 bg-primary-600 dark:bg-gray-700">
                                            {{ \Carbon\Carbon::parse($date)->format('d') }}
                                        </th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($userAttendanceData as $data)
                                <tr onclick="window.location='{{ UserAttendanceReport::getUrl(['record' => $data['user_id']]) }}'"
                                    class="cursor-pointer border-b text-gray-700 dark:border-gray-700">
                                    <td
                                        class="w-18 text-center font-medium text-gray-700 px-2 dark:text-white border border-gray-300 bg-gray-100 dark:bg-gray-700">
                                        {!! $data['employee_id'] !!}
                                    </td>
                                    <td
                                        class="w-18 text-center font-medium text-gray-700 px-2 dark:text-white border border-gray-300 bg-gray-100 dark:bg-gray-700">
                                        {!! $data['attendance_type'] !!}
                                    </td>
                                    <td
                                        class="custom-width font-medium text-gray-700 px-2 dark:text-white border border-gray-300 bg-gray-100 dark:bg-gray-700">
                                        {!! $data['name'] !!}
                                    </td>
                                    <td
                                        class="text-center border border-gray-300 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white font-medium">
                                        {!! $data['total_late_minutes'] !!}
                                    </td>
                                    @foreach ($datesGroupedByMonth as $month => $dates)
                                        @foreach ($dates as $date)
                                            @php
                                                $cell = $data[$date] ?? null;
                                                $cellClasses = 'border border-gray-300 text-center ';
                                                if (\Carbon\Carbon::parse($date)->isLastOfMonth()) {
                                                    $cellClasses .= 'border-r-4 border-orange-400 ';
                                                }
                                            @endphp
                                            <td class="{{ $cellClasses }}">
                                                @if (is_array($cell))
                                                    <div class="flex flex-col items-center justify-center h-full">
                                                        @if ($cell['status'])
                                                            <span class="text-xs font-medium text-{{ $cell['color'] }}-500">{{ $cell['status'] }}</span>
                                                        @endif
                                                        @if ($cell['punches'])
                                                            <span class="text-xs text-gray-500">{{ $cell['punches'] }}</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    {!! $cell !!}
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <x-filament::pagination :paginator="$attendanceUsers" :page-options="[5, 10, 20, 50, 100]"
                        current-page-option-property="perPage" />
                </div>
            </div>
        @else
            {{-- No Data Case --}}
            <div class="mt-6">
                <div class="flex justify-center items-center p-4">
                    <p class="text-gray-500">No attendance data available for the selected filters.</p>
                </div>
            </div>
        @endif
    </div>
</div>