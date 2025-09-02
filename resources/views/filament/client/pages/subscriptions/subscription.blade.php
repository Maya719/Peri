<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    Current Plan
                </x-slot>
                <x-slot name="headerEnd">
                    <span
                        class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                        @if ($this->currentSubscription->active())
                            Active
                        @else
                            Inactive
                        @endif
                    </span>
                </x-slot>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Plan</p>
                        <p class="text-xl font-medium text-gray-800 dark:text-white">{{ $this->currentPlan->name }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Billing Cycle</p>
                        <p class="text-base font-normal text-gray-800 dark:text-white">
                            {{ $this->currentPlan->invoice_interval }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Price</p>
                        <p class="text-xl font-medium text-gray-800 dark:text-white">
                            {{ $this->currentPlan->currency === 'USD' ? '$' : $this->currentPlan->currency }}{{ $this->currentPlan->price }}/{{ $this->currentPlan->invoice_interval }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Expire at</p>
                        <p class="text-base font-normal text-gray-800 dark:text-white">
                            {{ date('M d, Y', strtotime($this->currentSubscription->ends_at)) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Auto Renewal</p>
                        <p class="text-base font-medium text-gray-800 dark:text-white" id="renewal-status">
                            {{ $this->currentSubscription->auto_renew ? 'On' : 'Off' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        {{ $this->autoRenew }}
                        <x-filament::button color="primary" icon="heroicon-s-arrow-path"
                            wire:click="renewPlan({{ $this->currentPlan->id }})">
                            Renew
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
            @if ($this->recommendedPlan)
                <div class="grid grid-cols-2 md:grid-cols-2 sm:grid-cols-1 gap-6 mb-6">
                    <x-filament::section>
                        <x-slot name="heading">
                            Upgrade Plan
                        </x-slot>
                        <x-slot name="headerEnd">
                            <span
                                class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                                Recommended
                            </span>
                        </x-slot>
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2">
                                {{ $this->recommendedPlan->name }}
                            </h3>

                            <p class="text-gray-500 dark:text-gray-400 text-base mb-4">
                                {{ $this->recommendedPlan->description }}
                            </p>

                            <div class="mb-4">
                                <span class="text-4xl font-bold text-gray-800 dark:text-white">
                                    {{ $this->currentPlan->currency === 'USD' ? '$' : $this->currentPlan->currency }}{{ $this->recommendedPlan->price }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    /{{ $this->recommendedPlan->invoice_interval }}
                                </span>
                            </div>

                            <ul class="text-base text-gray-700 dark:text-gray-300 space-y-3">
                                {{-- Improved text color --}}
                                @foreach ($this->recommendedPlan->features as $feature)
                                    @if ($feature->value)
                                        <li class="flex items-start">
                                            <x-heroicon-s-check-circle
                                                class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" />
                                            <span>
                                                {{ $feature->name }}
                                                @if ($feature->value === true)
                                                    <span
                                                        class="ml-1 text-sm text-gray-500 dark:text-gray-400">(Unlimited)</span>
                                                @elseif (is_numeric($feature->value))
                                                    <span
                                                        class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $feature->value }})</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                        <x-filament::button
                            color="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($this->recommendedPlan) && $this->tenant->planSubscriptions()->first()?->active() ? 'success' : 'primary' }}"
                            icon="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($this->recommendedPlan) && $this->tenant->planSubscriptions()->first()?->active() ? 'heroicon-s-check-circle' : 'heroicon-s-arrows-right-left' }}"
                            wire:click="subscribe({{ $this->recommendedPlan->id }})">
                            {{ $this->textByPlan($this->recommendedPlan) }}
                        </x-filament::button>
                    </x-filament::section>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
