@props(['changelogs'])

@if ($changelogs->isNotEmpty())
    <div
        class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Change Log
        </h3>
        <div class="space-y-3">
            @foreach ($changelogs as $log)
                <div class="flex items-start text-sm text-gray-700 dark:text-gray-200">
                    <x-heroicon-s-pencil-square class="w-5 h-5 text-gray-400 mr-2 flex-shrink-0" /> {{-- Icon added back --}}
                    <div>
                        {{-- Extracting the updater's name from remarks --}}
                        @php
                            $updaterName = 'System'; // Default to System if name not found
                            if (Illuminate\Support\Str::startsWith($log->remarks, 'Updated by ')) {
                                $parts = explode(':', $log->remarks, 2);
                                if (isset($parts[0])) {
                                    $updaterName = str_replace('Updated by ', '', $parts[0]);
                                }
                            }
                        @endphp
                        <span
                            class="font-bold text-gray-800 dark:text-gray-200">{{ $log->role->name ?? $updaterName }}</span>
                        {{-- Display role name or extracted name --}}
                        @if (isset($log->user) && $log->user->id !== $log->role->id)
                            {{-- Only show user if it's different from role and actually set --}}
                            <span class="text-gray-600 dark:text-gray-400">({{ $log->user->name }})</span>
                        @elseif ($log->user === null && $updaterName !== 'System')
                            {{-- If no user object, but we extracted a name, show it --}}
                            <span class="text-gray-600 dark:text-gray-400">({{ $updaterName }})</span>
                        @endif
                        <span class="text-xs">made changes on {{ $log->created_at->format('M d, Y') }}</span>
                        <p class="mt-1 ml-4">{{ Illuminate\Support\Str::after($log->remarks, ': ') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
