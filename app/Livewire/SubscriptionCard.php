<?php

namespace App\Livewire;

use App\Filament\Client\Pages\Billing;
use Filament\Facades\Filament;
use Livewire\Component;

class SubscriptionCard extends Component
{
    public string $redirectUrl;
    public string $text;
    public string $icon;
    public bool $primary = false;

    public function mount()
    {
        $team = Filament::getTenant();
        $subscription = $team->activePlanSubscriptions()->first();

        if (
            $subscription &&
            $subscription->ends_at &&
            $subscription->ends_at->isBetween(now(), now()->addDays(7))
        ) {
            $this->icon = $this->get_subscription_icon();
            $this->text = 'Subscription ending soon';
        }
        if (
            $subscription &&
            $subscription->plan->isFree()
        ) {
            $this->icon = $this->get_subscription_icon();
            $this->text = 'Free Trial';
        }
        if (
            $subscription &&
            $subscription->plan->isFree() &&
            $subscription->trial_ends_at &&
            $subscription->trial_ends_at->isBetween(now(), now()->addDays(7))
        ) {
            $this->icon = $this->get_free_trial_icon();
            $this->text = 'Free trial ending soon';
        }
        if (
            $subscription &&
            !$subscription->plan->isFree() &&
            !$subscription->trial_ends_at->isBetween(now(), now()->addDays(7) &&
            !$subscription->ends_at->isBetween(now(), now()->addDays(7)))
        ) {
            $this->text = $subscription->plan->name;
        }
        $this->redirectUrl = Billing::getUrl();
    }
    private function get_free_trial_icon(): string
    {
        return '<svg class="w-5 h-5 text-gray-500 dark:text-gray-300 group-hover:scale-110 transition-transform duration-300"
            viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd"
                d="M15 8H11.9451L13.9191 3.39392C14.2019 2.73405 13.7179 2 13 2H8C7.59997 2 7.23843 2.2384 7.08086 2.60608L4.08086 9.60608C3.79805 10.2659 4.28208 11 5 11H6.73423L4.07207 17.6273C3.67234 18.6223 4.90667 19.4633 5.68646 18.7272L10.7099 13.9849L15.6501 9.75985C16.3559 9.156 15.9289 8 15 8ZM9.50943 8.60608C9.22663 9.26595 9.71066 10 10.4286 10H12.2929L9.37334 12.4979L7.62514 14.1477L9.14152 10.3727C9.40546 9.71569 8.92168 9 8.21359 9H6.51654L8.6594 4H11.4835L9.50943 8.60608Z" />
        </svg>';
    }
    private function get_subscription_icon(): string
    {
        return '<svg fill="currentColor" class="w-5 h-5 text-gray-500 dark:text-gray-300 group-hover:scale-110 transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
	                viewBox="0 0 70 70" xml:space="preserve">
                <g>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M68.193,19.713L60.171,8.027c-1.539-2.262-4.937-3.967-7.903-3.967H19.721c-2.966,0-6.363,1.708-7.893,3.96L3.784,19.652
                        c-1.711,2.52-1.62,6.4,0.207,8.836l28.002,37.329c1.014,1.352,2.476,2.125,4.01,2.125c1.528,0,2.983-0.771,3.99-2.113l28.004-37.33
                        C69.842,26.035,69.93,22.262,68.193,19.713z M52.268,8.06c0.088,0,0.181,0.014,0.271,0.02l-0.782,0.715
                        c-0.408,0.372-0.436,1.005-0.064,1.412c0.197,0.217,0.469,0.326,0.74,0.326c0.239,0,0.48-0.086,0.674-0.262l1.718-1.569
                        c0.867,0.41,1.633,0.975,2.046,1.583l8.023,11.687c0.212,0.311,0.354,0.688,0.441,1.089h-26.24l8.34-7.612
                        c0.406-0.371,0.436-1.004,0.063-1.412c-0.371-0.407-1.005-0.438-1.413-0.064l-9.826,8.969c-0.038,0.035-0.056,0.081-0.087,0.119h-1
                        c-0.031-0.039-0.049-0.084-0.086-0.118L18.878,8.149c0.289-0.052,0.573-0.089,0.843-0.089H52.268z M15.127,10.282
                        c0.344-0.506,0.939-0.979,1.63-1.362L32.248,23.06H20.23c-0.001,0-0.001,0-0.002,0H6.743c-0.038,0-0.07,0.018-0.107,0.021
                        c0.083-0.435,0.226-0.842,0.447-1.167L15.127,10.282z M7.19,26.088c-0.217-0.289-0.375-0.647-0.481-1.035
                        c0.012,0,0.022,0.007,0.034,0.007h12.781l0.949,2.375c0.155,0.395,0.532,0.635,0.932,0.635c0.121,0,0.244-0.022,0.364-0.069
                        c0.513-0.201,0.767-0.781,0.566-1.295l-0.657-1.646h28.471l-14.16,36.531L25.008,33.534c-0.201-0.513-0.782-0.769-1.296-0.566
                        c-0.514,0.201-0.767,0.781-0.566,1.295l10.712,27.375L7.19,26.088z M38.093,61.697L52.294,25.06h12.988
                        c-0.106,0.386-0.266,0.744-0.485,1.038L38.093,61.697z"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M49.329,13.365c0.241,0,0.483-0.087,0.674-0.262l0.696-0.636c0.406-0.373,0.436-1.005,0.063-1.413
                        c-0.371-0.406-1.004-0.434-1.412-0.063l-0.695,0.636c-0.407,0.372-0.437,1.005-0.063,1.413
                        C48.788,13.256,49.059,13.365,49.329,13.365z"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M23.659,30.087l-0.351-0.895c-0.201-0.511-0.78-0.767-1.296-0.564c-0.513,0.201-0.767,0.781-0.566,1.295l0.351,0.895
                        c0.156,0.395,0.533,0.635,0.932,0.635c0.121,0,0.245-0.022,0.364-0.069C23.607,31.183,23.861,30.603,23.659,30.087z"/>
                </g>
            </svg>';
    }
    public function render()
    {
        return view('livewire.subscription-card');
    }
}
