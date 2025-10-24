<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Current Subscription Section --}}
            @if($this->currentSubscription)
                <x-filament::section>
                    <x-slot name="heading">
                        Current Plan
                    </x-slot>

                    <x-slot name="headerEnd">
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                            @if ($this->currentSubscription && $this->currentSubscription->active())
                                Active
                            @else
                                Inactive
                            @endif
                        </span>
                    </x-slot>

                    {{-- Plan Details Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        {{-- Plan Name --}}
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Plan</p>
                            <p class="text-xl font-medium text-gray-800 dark:text-white">
                                {{ $this->getCurrentPlan()->name }}
                            </p>
                        </div>

                        {{-- Billing Cycle --}}
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Billing Cycle</p>
                            <p class="text-base font-normal text-gray-800 dark:text-white">
                                {{ $this->currentPlan->invoice_interval }}
                            </p>
                        </div>

                        {{-- Price --}}
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Price</p>
                            <p class="text-xl font-medium text-gray-800 dark:text-white">
                                {{ $this->currentPlan->currency === 'USD' ? '$' : $this->currentPlan->currency }}{{ $this->currentPlan->price }}/{{ $this->currentPlan->invoice_interval }}
                            </p>
                        </div>

                        {{-- Expiry Date --}}
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Expire at</p>
                            <p class="text-base font-normal text-gray-800 dark:text-white">
                                {{ date('M d, Y', strtotime($this->currentSubscription->ends_at)) }}
                            </p>
                        </div>
                    </div>

                    {{-- Auto Renewal Section --}}
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Auto Renewal</p>
                            <p class="text-base font-medium text-gray-800 dark:text-white" id="renewal-status">
                                {{ $this->currentSubscription->auto_renew ? 'On' : 'Off' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            {{ $this->autoRenew }}

                            @if(!$this->currentSubscription->active() && !$this->currentSubscription->auto_renew)
                                <x-filament::button
                                    color="primary"
                                    icon="heroicon-s-arrow-path"
                                    wire:click="renewPlan({{ $this->currentPlan->id }})">
                                    Renew
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endif

            {{-- Recommended Plan Section --}}
            @if ($this->recommendedPlan)
                <div class="grid grid-cols-2 md:grid-cols-2 sm:grid-cols-1 gap-6 mb-6">
                    <x-filament::section>
                        <x-slot name="heading">
                            Upgrade Plan
                        </x-slot>

                        <x-slot name="headerEnd">
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                                Recommended
                            </span>
                        </x-slot>

                        <div class="mb-6">
                            {{-- Plan Name --}}
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2">
                                {{ $this->recommendedPlan->name }}
                            </h3>

                            {{-- Plan Description --}}
                            <p class="text-gray-500 dark:text-gray-400 text-base mb-4">
                                {{ $this->recommendedPlan->description }}
                            </p>

                            {{-- Plan Price --}}
                            <div class="mb-4">
                                <span class="text-4xl font-bold text-gray-800 dark:text-white">
                                    {{ $this->currentPlan->currency === 'USD' ? '$' : $this->currentPlan->currency }}{{ $this->recommendedPlan->price }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    /{{ $this->recommendedPlan->invoice_interval }}
                                </span>
                            </div>

                            {{-- Plan Features List --}}
                            <ul class="text-base text-gray-700 dark:text-gray-300 space-y-3">
                                @foreach ($this->recommendedPlan->features as $feature)
                                    @if ($feature->value)
                                        <li class="flex items-start">
                                            <x-heroicon-s-check-circle class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" />
                                            <span>
                                                {{ $feature->name }}
                                                @if ($feature->value === true)
                                                    <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">(Unlimited)</span>
                                                @elseif (is_numeric($feature->value))
                                                    <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $feature->value }})</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>

                        {{-- Subscribe Button --}}
                        <x-filament::button
                            color="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($this->recommendedPlan) && $this->tenant->planSubscriptions()->first()?->active() ? 'success' : 'primary' }}"
                            icon="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($this->recommendedPlan) && $this->tenant->planSubscriptions()->first()?->active() ? 'heroicon-s-check-circle' : 'heroicon-s-arrows-right-left' }}"
                            wire:click="subscribe({{ $this->recommendedPlan->id }})">
                            {{ $this->textByPlan($this->recommendedPlan) }}
                        </x-filament::button>
                    </x-filament::section>
                </div>
            @endif

            {{-- No Subscription Yet--}}
            @if(!$this->currentSubscription)
                <div class="grid grid-cols-3 md:grid-cols-3 sm:grid-cols-1 gap-6 mb-6">
                    @foreach($this->plans as $plan)
                            <x-filament::section>
                                <div class="mb-6">
                                    {{-- Plan Name --}}
                                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2">
                                        {{ $plan->name }}
                                    </h3>

                                    {{-- Plan Description --}}
                                    <p class="text-gray-500 dark:text-gray-400 text-base mb-4">
                                        {{ $plan->description }}
                                    </p>

                                    {{-- Plan Price --}}
                                    <div class="mb-4">
                                    <span class="text-4xl font-bold text-gray-800 dark:text-white">
                                        {{ $plan->currency === 'USD' ? '$' : $plan->currency }}{{ $plan->price }}
                                    </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                        /{{ $plan->invoice_interval }}
                                    </span>
                                    </div>

                                    {{-- Plan Features List --}}
                                    <ul class="text-base text-gray-700 dark:text-gray-300 space-y-3">
                                        @foreach ($plan->features as $feature)
                                            @if ($feature->value)
                                                <li class="flex items-start">
                                                    <x-heroicon-s-check-circle class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" />
                                                    <span>
                                                    {{ $feature->name }}
                                                        @if ($feature->value === true)
                                                            <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">(Unlimited)</span>
                                                        @elseif (is_numeric($feature->value))
                                                            <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $feature->value }})</span>
                                                        @endif
                                                </span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>

                                {{-- Subscribe Button --}}
                                <x-filament::button
                                    color="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($plan) && $this->tenant->planSubscriptions()->first()?->active() ? 'success' : 'primary' }}"
                                    icon="{{ $this->tenant->planSubscriptions()->first()?->plan()?->is($plan) && $this->tenant->planSubscriptions()->first()?->active() ? 'heroicon-s-check-circle' : 'heroicon-s-arrows-right-left' }}"
                                    wire:click="subscribe({{ $plan->id }})">
                                    {{ $this->textByPlan($plan) }}
                                </x-filament::button>
                            </x-filament::section>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
