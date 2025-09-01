<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Status card --}}
        <div class="flex items-center justify-between bg-white border rounded-lg p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="text-green-500 text-xl">✔</span>
                <p class="text-gray-700">
                    You have <span class="font-semibold">{{ count($this->payment_methods) }} active</span> payment
                    methods
                </p>
            </div>
        </div>
        {{-- Payment methods list --}}
        <x-filament::section>
            <x-slot name="heading">
                Payment method list
            </x-slot>
            <div class="divide-y divide-gray-200">
                @foreach ($this->payment_methods as $paymentMethod)
                    <a href="javascript:void(0);"
                        class="flex items-center justify-between px-4 py-4 hover:bg-gray-50 transition group">
                        <div class="flex items-center gap-3">
                            <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 text-primary-600" />
                            <div>
                                <p class="text-gray-800 font-semibold">**** **** ****
                                    {{ $paymentMethod['last4'] }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ strtoupper($paymentMethod['brand']) }} |
                                    | Expires
                                    {{ $paymentMethod['exp_year'] }}-{{ $paymentMethod['exp_month'] }}
                                    @if ($paymentMethod['is_default'])
                                        <span
                                            class="ml-2 bg-gray-200 text-gray-700 text-xs font-semibold px-2 py-0.5 rounded">
                                            DEFAULT METHOD
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (!$paymentMethod['is_default'])
                                <x-filament::button
                                    wire:click="setDefault('{{ $paymentMethod['id'] }}')"
                                    size="xs"
                                >
                                    Set as Default
                                </x-filament::button>
                            @endif

                            {{ ($this->openDeleteModal)(['record' => $paymentMethod['id']]) }}
                        </div>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
