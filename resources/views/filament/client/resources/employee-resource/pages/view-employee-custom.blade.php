<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profile Section -->
        <div class="lg:col-span-1">
            <x-filament::section class="overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                    <!-- Profile Info -->
                    <div class="flex flex-col items-center justify-center space-y-4">
                        <!-- Profile Picture -->
                        <div class="relative">
                            @if ($record->avatar_url)
                                <img src="{{ asset('storage/' . $record->avatar_url) }}" alt="Profile Picture"
                                    class="rounded-full object-cover shadow-lg border-4 border-white ring-2 ring-primary-100"
                                    style="width: 10rem; height: 10rem;">
                            @else
                                <div class="rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white text-2xl font-semibold shadow-lg ring-2 ring-primary-100"
                                    style="width: 10rem; height: 10rem;">
                                    {{ strtoupper(substr($record->name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <!-- Employee Info -->
                        <div class="text-center space-y-2">
                            <!-- Status Badge and Name -->
                            <div class="space-y-2">
                                <div
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $record->active ? 'bg-success-600 text-success-200' : 'bg-danger-100 text-danger-800' }}">
                                    @if ($record->active)
                                        <div
                                            class="h-4 w-4 bg-success-500 rounded-full border-3 border-white shadow-lg flex items-center justify-center">
                                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div
                                            class="h-4 w-4 bg-danger-500 rounded-full border-3 border-white shadow-lg flex items-center justify-center">
                                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    @endif
                                    <h2 class="text-xl ml-1 font-semibold text-gray-900 dark:text-white">
                                        {{ $record->name }}</h2>
                                </div>
                            </div>

                            @if ($record->designation)
                                <p class="text-primary-600 font-semibold dark:text-gray-200">
                                    {{ $record->designation }}
                                </p>
                            @endif
                            <p class="text-gray-500 text-sm dark:text-gray-400">{{ $record->email }}</p>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <h3
                            class="text-base font-semibold text-gray-700 uppercase tracking-wide hidden sm:block dark:text-white">
                            Contact
                            Information</h3>
                        <!-- Phone -->
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                        </path>
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-xs text-gray-500 font-medium uppercase tracking-wide dark:text-gray-200">
                                        Phone Number
                                    </p>
                                    <p class="text-sm text-gray-900 font-semibold dark:text-white">
                                        {{ $record->phone_number ?? 'Not provided' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 bg-red-100 rounded-lg flex items-center justify-center">
                                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z">
                                        </path>
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-xs text-gray-500 font-medium uppercase tracking-wide dark:text-gray-200">
                                        Emergency
                                        Contact</p>
                                    <p class="text-sm text-gray-900 font-semibold dark:text-white">
                                        {{ $record->emergency_person ?? 'Not provided' }}
                                    </p>
                                    @if ($record->emergency_contact)
                                        <p class="text-xs text-gray-600 mt-1 dark:text-white">
                                            {{ $record->emergency_contact }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <div class="flex items-start space-x-3">
                                <div
                                    class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-xs text-gray-500 font-medium uppercase tracking-wide dark:text-gray-200">
                                        Address</p>
                                    <p class="text-sm text-gray-900 font-semibold leading-relaxed dark:text-white">
                                        {{ $record->address ?? 'Not provided' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Basic Information -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div>
                            <span>Basic Information</span>
                        </div>
                    </x-slot>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                        Employee ID</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->id }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                Gender
                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ ucfirst($record->gender) }}
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">Date
                                of
                                Birth</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $record->date_of_birth ? \Carbon\Carbon::parse($record->date_of_birth)->format('M d, Y') : 'Not provided' }}
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                Blood
                                Group</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $record->blood_group ?? 'Not provided' }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">NIC
                                Number</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $record->cnic ?? 'Not provided' }}
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                Marital
                                Status</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ ucfirst($record->martial_status) ?? 'Not provided' }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <!-- Employment Details -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div>
                            <span>Employment Details</span>
                        </div>
                    </x-slot>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Joining Date</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->joining_date ? \Carbon\Carbon::parse($record->joining_date)->format('M d, Y') : 'Not provided' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Department</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->assignedDepartment->department->name ?? 'Not assigned' }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Role</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->getRoleNames()->join(', ') ?? 'No role assigned' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Probation End Date
                                </p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->probation ? \Carbon\Carbon::parse($record->probation)->format('M d, Y') : 'N/A' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Shift</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->assignedShift->shift->name ?? 'Not assigned' }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                <p
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                    Employment Status</p>
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="w-3 h-3 bg-{{ $record->active ? 'success' : 'danger' }}-500 rounded-full">
                                    </div>
                                    <p class="text-sm ml-1 font-semibold text-gray-900 dark:text-white">
                                        {{ $record->active ? 'Active' : 'Inactive' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <!-- Salary Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-filament::section>
                    <x-slot name="heading">
                        <div>
                            <span>Salary Information</span>
                        </div>
                    </x-slot>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                Base
                                Salary</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $record->bankDetails->first()->salary_currency ?? '' }}
                                {{ number_format($record->bankDetails->first()->base_salary ?? 0, 2) }}
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                Payment
                                Method</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ ucfirst(str_replace('_', ' ', $record->bankDetails->first()->payment_method ?? 'N/A')) }}
                            </p>
                        </div>
                    </div>

                    @if ($record->bankDetails->first() && $record->bankDetails->first()->payment_method === 'bank_transfer')
                        <div class="mt-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-white mb-3">Bank Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                    <p
                                        class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                        Account Holder Name</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $record->bankDetails->first()->account_holder_name ?? 'N/A' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                    <p
                                        class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                        Bank Name</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $record->bankDetails->first()->bank_name ?? 'N/A' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                    <p
                                        class="text-xs font-medium text-gray-500 uppercase tracking-wide dark:text-gray-200">
                                        Account Number</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $record->bankDetails->first()->account_number ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($record->fundsForCurrentTeam->isNotEmpty())
                        <div class="mt-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-white mb-3">Assigned Funds</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($record->fundsForCurrentTeam as $fund)
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $fund->name }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-filament::section>

                <!-- Leave Approval Hierarchy -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div>
                            <span>Leave Approval Hierarchy</span>
                        </div>
                    </x-slot>
                    @if ($record->approvalSteps->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($record->approvalSteps->sortBy('level') as $step)
                                <div
                                    class="flex items-center space-x-4 bg-gray-50 rounded-lg p-4 border border-gray-200 dark:bg-gray-800">
                                    <div
                                        class="h-10 w-10 bg-primary-500 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-lg">
                                        {{ $step->level }}
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm ml-2 font-semibold text-gray-900 dark:text-white">
                                            {{ $step->role->name ?? 'Role not found' }}</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:text-gray-200">
                                                {{ match (strtolower($step->permission)) {
                                                    'recommend' => 'Recommender',
                                                    'approve' => 'Approver',
                                                    default => ucfirst($step->permission),
                                                } }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No approval hierarchy</h3>
                            <p class="mt-1 text-sm text-gray-500">No approval hierarchy has been set for this employee.
                            </p>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
