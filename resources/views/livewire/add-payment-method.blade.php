<div class="w-full max-w-md mx-auto">
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Add Payment Method
        </h3>

        <form wire:submit.prevent="handlePaymentSubmission" class="space-y-4">
            <!-- Stripe Payment Element Container -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Payment Information
                </label>
                <div id="payment-element"
                    class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700">
                    <!-- Stripe Elements will mount here -->
                </div>
                <div id="payment-errors" class="text-red-600 text-sm mt-2 hidden"></div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <x-filament::button color="primary" icon="heroicon-m-credit-card" class="w-full justify-center"
                    id="submit" icon-position="before" wire:loading.attr="disabled" wire:target="savePaymentMethod">
                    <span wire:loading.remove wire:target="savePaymentMethod">
                        Save Payment Method
                    </span>
                    <span wire:loading wire:target="savePaymentMethod" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Processing...
                    </span>
                </x-filament::button>
            </div>
        </form>

        <!-- Additional Information -->
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd"></path>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Your payment information is securely processed by Stripe. We don't store your card details.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    // Store references globally so we can recreate elements
    let stripe, elements, paymentElement;

    function getStripeAppearance(isDark = false) {
        return {
            theme: isDark ? 'night' : 'stripe',
            variables: {
                colorPrimary: isDark ? '#60a5fa' : '#3b82f6',
                colorBackground: isDark ? '#374151' : '#ffffff',
                colorText: isDark ? '#f9fafb' : '#1f2937',
                colorDanger: '#ef4444',
                fontFamily: 'system-ui, sans-serif',
                spacingUnit: '4px',
                borderRadius: '6px',
            },
            rules: {
                '.Input': {
                    border: isDark ? '1px solid #4b5563' : '1px solid #d1d5db',
                    padding: '12px',
                    fontSize: '14px',
                    backgroundColor: isDark ? '#4b5563' : '#ffffff',
                    color: isDark ? '#f9fafb' : '#1f2937',
                },
                '.Input:focus': {
                    border: isDark ? '1px solid #60a5fa' : '1px solid #3b82f6',
                    boxShadow: isDark ?
                        '0 0 0 3px rgba(96, 165, 250, 0.1)' : '0 0 0 3px rgba(59, 130, 246, 0.1)',
                    outline: 'none',
                },
                '.Label': {
                    fontSize: '14px',
                    fontWeight: '500',
                    color: isDark ? '#d1d5db' : '#374151',
                    marginBottom: '6px',
                }
            }
        };
    }

    function initializeStripeElements(clientSecret, publishableKey) {
        // Check if we're in dark mode
        const isDarkMode = document.documentElement.classList.contains('dark');

        // Initialize Stripe
        stripe = Stripe(publishableKey);

        // Create elements with appropriate theme
        elements = stripe.elements({
            clientSecret: clientSecret,
            appearance: getStripeAppearance(isDarkMode)
        });

        // Create and mount payment element
        paymentElement = elements.create("payment", {
            theme: 'flat',
            layout: {
                type: 'accordion',
                defaultCollapsed: false,
                radios: false,
                spacedAccordionItems: true
            },
        });

        paymentElement.mount("#payment-element");

        // Handle events
        setupPaymentElementEvents();
    }

    function recreateStripeElements(clientSecret) {
        // Check current theme
        const isDarkMode = document.documentElement.classList.contains('dark');

        // Unmount existing element
        if (paymentElement) {
            paymentElement.unmount();
        }

        // Create new elements with updated theme
        elements = stripe.elements({
            clientSecret: clientSecret,
            appearance: getStripeAppearance(isDarkMode)
        });

        paymentElement = elements.create("payment", {
            layout: {
                type: 'tabs',
                defaultCollapsed: false,
            },
        });

        paymentElement.mount("#payment-element");

        // Re-setup events
        setupPaymentElementEvents();

        console.log(`Stripe elements recreated for ${isDarkMode ? 'dark' : 'light'} mode`);
    }

    function setupPaymentElementEvents() {
        // Handle payment element ready
        paymentElement.on('ready', () => {
            console.log('Payment element is ready');
        });

        // Handle real-time validation errors
        paymentElement.on('change', (event) => {
            const displayError = document.getElementById('payment-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
                displayError.classList.remove('hidden');
            } else {
                displayError.textContent = '';
                displayError.classList.add('hidden');
            }
        });
    }

    // Main initialization
    document.addEventListener("livewire:init", () => {
        const clientSecret = @js($clientSecret);
        const publishableKey = "{{ $this->clientPublish }}";

        // Initialize Stripe elements
        initializeStripeElements(clientSecret, publishableKey);

        // Watch for dark mode changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const target = mutation.target;

                    // Check if dark class was added or removed
                    const hadDark = mutation.oldValue?.includes('dark') ?? false;
                    const hasDark = target.classList.contains('dark');

                    // Only recreate if dark mode actually changed
                    if (hadDark !== hasDark) {
                        console.log(`Theme changed to: ${hasDark ? 'dark' : 'light'} mode`);
                        recreateStripeElements(clientSecret);
                    }
                }
            });
        });

        // Start observing the document element for class changes
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
            attributeOldValue: true // This gives us the old class value
        });

        // Handle form submission (same as before)
        const formButton = document.getElementById("submit");
        if (formButton) {
            formButton.addEventListener("click", async (e) => {
                e.preventDefault();
                formButton.disabled = true;

                try {
                    const {
                        setupIntent,
                        error
                    } = await stripe.confirmSetup({
                        elements,
                        redirect: "if_required",
                        confirmParams: {
                            return_url: window.location.href,
                        }
                    });

                    if (error) {
                        @this.dispatch('notify', {
                            type: 'error',
                            message: error.message
                        });

                        const displayError = document.getElementById('payment-errors');
                        displayError.textContent = error.message;
                        displayError.classList.remove('hidden');
                    } else if (setupIntent?.payment_method) {
                        @this.call('savePaymentMethod', setupIntent.payment_method);
                        const displayError = document.getElementById('payment-errors');
                        displayError.textContent = '';
                        displayError.classList.add('hidden');
                    }
                } catch (err) {
                    console.error('Payment processing error:', err);
                    @this.dispatch('notify', {
                        type: 'error',
                        message: 'An unexpected error occurred. Please try again.'
                    });
                } finally {
                    formButton.disabled = false;
                }
            });
        }

        // Cleanup on page navigation
        window.addEventListener('beforeunload', () => {
            observer.disconnect();
        });
    });

    // Clean up on Livewire navigation
    document.addEventListener('livewire:navigating', () => {
        if (paymentElement) {
            paymentElement.unmount();
        }
    });
</script>
