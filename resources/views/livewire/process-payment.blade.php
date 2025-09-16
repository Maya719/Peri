    <div class="fi-page-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="space-y-6">
            <x-filament::section class="p-6">
                <form wire:submit="completePayment">
                    <style>
                        .fi-radio-list-wrapper .fi-fo-radio .fi-fo-radio-option-label-wrapper {
                            padding: 12px 16px;
                            border-radius: var(--radius);
                            border: 1px solid var(--gray-200);
                            background-color: var(--white);
                            box-shadow: var(--shadow-sm);
                            transition: all 0.2s ease-in-out;
                        }

                        .fi-radio-list-wrapper .fi-fo-radio .fi-fo-radio-option-label-wrapper:hover {
                            border-color: var(--primary-400);
                        }

                        .fi-radio-list-wrapper .fi-fo-radio input[type="radio"]:checked+.fi-fo-radio-option-label-wrapper {
                            border-color: var(--primary-500);
                            background-color: var(--primary-50);
                            box-shadow: var(--shadow-md);
                        }

                        .fi-radio-list-wrapper .fi-fo-radio input[type="radio"]:checked+.fi-fo-radio-option-label-wrapper .fi-fo-radio-option-label {
                            font-weight: 600;
                            color: var(--primary-700);
                        }

                        .fi-radio-list-wrapper .fi-fo-radio input[type="radio"]:checked+.fi-fo-radio-option-label-wrapper .fi-fo-radio-option-description {
                            color: var(--primary-600);
                            font-weight: 500;
                        }

                        .fi-radio-list-wrapper .fi-fo-radio .fi-fo-radio-option-description {
                            margin-top: 0;
                            text-align: right;
                            margin-left: auto;
                            display: flex;
                            align-items: center;
                        }

                        .fi-radio-list-wrapper .fi-fo-radio .fi-fo-radio-option-label-wrapper {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                    </style>

                    <x-filament::section class="mt-6 mb-3">
                        <x-slot name="heading">
                            Order Summary
                        </x-slot>

                        <div class="space-y-4 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-300">Plan</span>
                                <span class="text-gray-800 dark:text-white">{{  $this->planName }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-300">Taxes & Fees</span>
                                <span class="text-gray-800 dark:text-white">US$
                                    {{ number_format($taxesFees, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-300">Expiration Date</span>
                                <span class="text-gray-800 dark:text-white">{{ $this->expirationDate }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700 font-semibold text-lg">
                                <span class="text-gray-800 dark:text-white">Total</span>
                                <span class="text-primary-600 dark:text-primary-400">US$
                                    {{ number_format($this->total, 2) }}</span>
                            </div>

                            {{-- <div class="flex items-center justify-between mt-4">
                                <span class="text-gray-600 dark:text-gray-300">Coupon code</span>
                                <x-filament::link wire:click="$set('showCouponInput', true)">Add</x-filament::link>
                            </div> --}}

                            @if ($this->showCouponInput ?? false)
                                <div class="mt-2">
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model.live="couponCode"
                                            placeholder="Enter coupon code" />
                                    </x-filament::input.wrapper>
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                    {{ $this->form }}

                    <div
                        class="text-xs text-gray-500 dark:text-gray-400 mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        By checking out, you agree with our
                        <x-filament::link href="#" target="_blank"
                            class="text-primary-600 dark:text-primary-400">Terms of Service</x-filament::link>
                        and confirm that you have read our
                        <x-filament::link href="#" target="_blank"
                            class="text-primary-600 dark:text-primary-400">Privacy Policy</x-filament::link>.
                        You can cancel recurring payments at any time.
                    </div>

                    <div class="flex items-center justify-end gap-x-4 mt-6">
                        <x-filament::button color="gray" outlined>
                            Cancel
                        </x-filament::button>
                        <x-filament::button type="submit">
                            Complete payment
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>
    </div>
