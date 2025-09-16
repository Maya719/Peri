@extends('layout_home.app')
@section('content')
    <!-- Hero Section -->
    <header class=" text-white position-relative overflow-hidden">
        <!-- Background Effect -->
        <div class="background">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="container-fluid px-4 px-md-5 position-relative" style="z-index: 2;">
            <div class="row align-items-center">
                <!-- Left Content (Text Section) -->
                <div class="col-md-6 text-start d-flex flex-column justify-content-center h-100 px-5">
                    <h1 class="fw-semibold text-justify text-uppercase custom-heading">
                        <span>P</span>lan <span>E</span>xecute <span>R</span>eview <span>I</span>mprove
                    </h1>
                    <h1 class="display-4
                        fw-bold">
                        Agile Your Work
                    </h1>
                    <p class="lead fw-semibold text-justify">
                        Boost your productivity with our all-in-one solution. Designed for simplicity, seamless
                        collaboration, and agile workflows, our platform helps you stay organized and work
                        smarter—effortlessly.
                    </p>
                    <div class="d-inline-block mt-4 ">
                        <a href="{{ url('/client/register') }}"
                            class="hero-btn px-5 py-3 font-semibold rounded
                                  transition-all duration-300 hover:bg-white">
                            @if($freeplan)
                                {{ $freeplan->trial_period}} {{ ucfirst($freeplan->trial_interval) }} free trial →
                            @else
                                Get Started →
                            @endif
                        </a>
                    </div>
                </div>

                <!-- Right Image (Centered & Scaled to 120%) -->
                <div class="col-md-6 d-flex justify-content-center mt-5">
                    <img src="{{ asset('assets/front/four/img/landing_page_images/hero.png') }}" class="img-fluid w-100"
                        style="max-width: 120%; transform: scale(1.2);" alt="hero">
                </div>
            </div>
        </div>
    </header>

    <!-- Partner Companies Section -->
    <section class="py-5 mt-5 mb-5 text-center bg-[#f8f9fa]">
        <div class="container">
            <!-- Main Heading with Gradient Background -->
            <div class="text-center mb-4">
                <div class="inline-block px-3 py-1 text-sm font-bold relative tab">
                    <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                    PARTNERS
                </div>
                <h2 class="text-4xl font-bold mt-4 heading-text">Trusted By These Brands</h2>
            </div>

            <!-- Logos Row -->
            <div class="row justify-content-center align-items-center mt-5">
                <div class="col-6 col-sm-4 col-md-2 d-flex justify-content-center p-1">
                    <img src="{{ asset('assets/front/four/img/partners/1.png') }}" class="img-fluid" alt="Partner 1"
                        style="max-width: 140px;">
                </div>
                <div class="col-6 col-sm-4 col-md-2 d-flex justify-content-center p-1">
                    <img src="{{ asset('assets/front/four/img/partners/2.png') }}" class="img-fluid" alt="Partner 2"
                        style="max-width: 140px;">
                </div>
                <div class="col-6 col-sm-4 col-md-2 d-flex justify-content-center p-1">
                    <img src="{{ asset('assets/front/four/img/partners/3.png') }}" class="img-fluid" alt="Partner 3"
                        style="max-width: 140px;">
                </div>
                <div class="col-6 col-sm-4 col-md-2 d-flex justify-content-center p-1">
                    <img src="{{ asset('assets/front/four/img/partners/4.png') }}" class="img-fluid" alt="Partner 4"
                        style="max-width: 180px;">
                </div>
            </div>

        </div>
    </section>

    <!-- Features Section -->
    <section class="fluid-container py-5 bg-[#f8f9fa]" id="features">
        <div class="text-center mb-5">
            <div class="inline-block px-3 py-1 text-sm font-bold relative tab">
                <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                FEATURES
            </div>
            <h2 class="text-4xl font-bold mt-4 heading-text">What We Are Offering</h2>
        </div>
        <div class="slider" style="--width: 380px;--height: 380px;--quantity: {{ $featureCount }};">
            <div class="list">
                @foreach ($feature as $index => $feature)
                    <div class="item" style="--position: {{ $index + 1 }}">
                        <div class="card">
                            <div class="icon-box">
                                <i class="bi {{ $feature->icon ?? 'bi-check-circle-fill' }}"></i>
                            </div>
                            <p class="title">{{ $feature->title ?? 'No Title' }}</p>
                            <p>{{ $feature->description ?? 'No Description Available' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </section>

    <!-- Dashboards Section -->
    <section class="container mx-auto text-center py-12" x-data="{ selectedTab: 'tab1' }">
        <!-- Heading and Subheading -->
        <div class="text-center mb-4">
            <div class="inline-block tab px-3 py-1 text-sm font-bold relative">
                <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                DASHBOARDS
            </div>
            <h2 class="text-4xl font-bold mt-4 heading-text">Combine Info with Multiple Dashboards</h2>
        </div>

        <!-- Tabs -->
        <div class="flex justify-center space-x-4">
            <button class="tab-btn px-6 py-2 text-lg transition"
                :class="{ 'bg-black text-white': selectedTab === 'tab1' }" @click="selectedTab = 'tab1'">
                HR Dashboard
            </button>
            <button class="tab-btn px-6 py-2 text-lg transition"
                :class="{ 'bg-black text-white': selectedTab === 'tab2' }" @click="selectedTab = 'tab2'">
                AMS Dashboard
            </button>
            <button class="tab-btn px-6 py-2 text-lg transition"
                :class="{ 'bg-black text-white': selectedTab === 'tab3' }" @click="selectedTab = 'tab3'">
                PMS Dashboard
            </button>
        </div>

        <!-- Image Display -->
        <div class="mt-8 flex justify-center">
            <template x-if="selectedTab === 'tab1'">
                <img src="{{ asset('assets/front/four/img/landing_page_images/hr_dashboard.png') }}" alt="Feature 1"
                    class="rounded-lg shadow-lg">
            </template>
            <template x-if="selectedTab === 'tab2'">
                <img src="{{ asset('assets/front/four/img/landing_page_images/ams_dashboard.png') }}" alt="Feature 2"
                    class="rounded-lg shadow-lg">
            </template>
            <template x-if="selectedTab === 'tab3'">
                <img src="{{ asset('assets/front/four/img/landing_page_images/pms_dashboard.png') }}" alt="Feature 3"
                    class="rounded-lg shadow-lg">
            </template>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing-table" class="container mx-auto text-center py-12">
        <div class="text-center mb-4">
            <div class="inline-block tab px-3 py-1 text-sm font-bold relative">
                <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                PRICING
            </div>
            <h2 class="text-4xl font-bold mt-4 heading-text">Choose Your Perfect Plan</h2>
        </div>

        <div class="overflow-x-auto mt-5">
            <table class="w-full border-collapse border border-gray-300 text-center table-fixed">
                <thead class="bg-gray-200 text-lg font-semibold">
                    <tr>
                        <th class="p-4 border border-gray-300"></th>
                        @foreach ($plans as $plan)
                            <th class="p-4 border border-gray-300">{{ $plan->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $settings = \App\Models\Setting::getByType('general');
                        $currency_symbol = $settings['currency_symbol'] ?? '$';
                    @endphp
                    <tr class="bg-white">
                        <td class="p-4 border border-gray-300 text-lg font-bold">Price</td>
                        @foreach ($plans as $plan)
                            <td class="p-4 border border-gray-300 text-base">
                                {{ $currency_symbol }}{{ $plan->price ?? 'N/A' }} /
                                {{ $plan->invoice_interval ?? 'N/A' }}</td>
                        @endforeach
                    </tr>
                    @php
                        $plan_features = $plans->pluck('features')->flatten()->pluck('name')->unique()->values();
                    @endphp
                    @foreach ($plan_features as $plan_feature)
                        <tr class="{{ $loop->odd ? 'bg-gray-100' : 'bg-white' }}">
                            <td class="p-4 border border-gray-300 text-lg font-bold">{{ ucfirst($plan_feature) }}</td>
                            @foreach ($plans as $plan)
                                <td class="p-4 border border-gray-300 text-base">
                                    @php
                                        $value = $plan->features()->where('name', $plan_feature)->first()->value;
                                    @endphp
                                    @if ($value == 'true' || $value == 999)
                                        <i class="bi bi-check-circle-fill text-green-500 text-2xl"></i>
                                    @elseif ($value == 'false' || $value == 0)
                                        <i class="bi bi-x-circle-fill text-red-500 text-2xl"></i>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="bg-gray-100">
                        <td class="p-4 border border-gray-300 font-semibold"></td>
                        @foreach ($plans as $plan)
                            <td class="p-4 border border-gray-300 btn-started">
                                <div class="flex flex-col md:flex-row md:space-x-2 space-y-2 md:space-y-0 justify-center">
                                    <a href="{{ url('/client/register') }}"
                                        class="btn-get-started hover:bg-[var(--primary)] hover:text-white">
                                        Get Started →
                                    </a>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <!-- Strategy Sections -->
    <section class="overflow-hidden bg-white py-16 sm:py-17">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-2xl grid-cols-1 gap-x-8 gap-y-16 sm:gap-y-20 lg:mx-0 lg:max-w-none lg:grid-cols-2">
                <div class="lg:pr-8 lg:pt-4">
                    <div class="lg:max-w-lg">
                        <div class="inline-block tab px-3 py-1 text-sm font-bold relative">
                            <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                            STRATEGY
                        </div>
                        <p class="mt-2 text-pretty text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
                            Centralize Operations</p>

                        <dl class="mt-10 max-w-xl space-y-8 text-base/7 text-gray-600 lg:max-w-none">
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z"
                                            clip-rule="evenodd" />
                                        <path
                                            d="M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.486l-.115.04c-.567.2-1.156.349-1.764.441Z" />
                                    </svg>
                                    Unified Access & Collaboration.
                                </dt>
                                <dd class="inline">Bring together all project details in one place. Enable streamlined
                                    workflows and break down silos to foster team collaboration.</dd>
                            </div>
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M12 6.75a5.25 5.25 0 0 1 6.775-5.025.75.75 0 0 1 .313 1.248l-3.32 3.319c.063.475.276.934.641 1.299.365.365.824.578 1.3.64l3.318-3.319a.75.75 0 0 1 1.248.313 5.25 5.25 0 0 1-5.472 6.756c-1.018-.086-1.87.1-2.309.634L7.344 21.3A3.298 3.298 0 1 1 2.7 16.657l8.684-7.151c.533-.44.72-1.291.634-2.309A5.342 5.342 0 0 1 12 6.75ZM4.117 19.125a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-.008Z"
                                            clip-rule="evenodd" />
                                        <path
                                            d="m10.076 8.64-2.201-2.2V4.874a.75.75 0 0 0-.364-.643l-3.75-2.25a.75.75 0 0 0-.916.113l-.75.75a.75.75 0 0 0-.113.916l2.25 3.75a.75.75 0 0 0 .643.364h1.564l2.062 2.062 1.575-1.297Z" />
                                        <path fill-rule="evenodd"
                                            d="m12.556 17.329 4.183 4.182a3.375 3.375 0 0 0 4.773-4.773l-3.306-3.305a6.803 6.803 0 0 1-1.53.043c-.394-.034-.682-.006-.867.042a.589.589 0 0 0-.167.063l-3.086 3.748Zm3.414-1.36a.75.75 0 0 1 1.06 0l1.875 1.876a.75.75 0 1 1-1.06 1.06L15.97 17.03a.75.75 0 0 1 0-1.06Z"
                                            clip-rule="evenodd" />
                                    </svg>

                                    Customizable Workspace.
                                </dt>
                                <dd class="inline">Tailor PERI to precisely match your team's processes, roles, and
                                    terminology. Enjoy flexible features that adapt to your organization’s unique
                                    requirements.</dd>
                            </div>
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z"
                                            clip-rule="evenodd" />
                                        <path fill-rule="evenodd"
                                            d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375Zm9.586 4.594a.75.75 0 0 0-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.116-.062l3-3.75Z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Automated Operations.
                                </dt>
                                <dd class="inline">Automate repetitive tasks to save time and reduce manual workload.
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <!-- Mobile Image (visible on small screens only) -->
                <img src="{{ asset('assets/front/four/img/landing_page_images/light_mobile.png') }}"
                    alt="Product screenshot" class="block md:hidden w-full rounded-xl shadow-xl ring-1 ring-gray-400/10">

                <!-- Desktop Image (visible on medium and larger screens) -->
                <img src="{{ asset('assets/front/four/img/landing_page_images/light_page.png') }}"
                    alt="Product screenshot"
                    class="hidden md:block w-[48rem] max-w-none rounded-xl shadow-xl ring-1 ring-gray-400/10 sm:w-[57rem] md:-ml-4 lg:-ml-0"
                    width="2432" height="1442">
            </div>
        </div>
    </section>

    <section class="overflow-hidden bg-white py-16 sm:py-17">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div
                class="mx-auto grid max-w-2xl grid-cols-1 gap-x-8 gap-y-16 sm:gap-y-20 lg:mx-0 lg:max-w-none lg:grid-cols-2">
                <!-- Mobile Image (visible on small screens only) -->
                <img src="{{ asset('assets/front/four/img/landing_page_images/dark_mobile.png') }}"
                    alt="Product screenshot" class="block md:hidden w-full rounded-xl shadow-xl ring-1 ring-gray-400/10">
                <!-- Image on the Left -->
                <img src="{{ asset('assets/front/four/img/landing_page_images/dark_page.png') }}"
                    alt="Product screenshot"
                    class="hidden md:block w-[48rem] max-w-none rounded-xl shadow-xl ring-1 ring-gray-400/10 sm:w-[57rem] md:-mr-4 lg:-ml-[320px]"
                    width="2432" height="1442">
                <!-- Text on the Right -->
                <div class="lg:pl-16 lg:pt-4">
                    <div class="lg:max-w-lg">
                        <div class="inline-block tab px-3 py-1 text-sm font-bold relative">
                            <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                            STRATEGY
                        </div>
                        <p class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
                            Seamless Efficiency
                        </p>

                        <dl class="mt-10 max-w-xl space-y-8 text-base/7 text-gray-600 lg:max-w-none">
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M2.25 2.25a.75.75 0 0 0 0 1.5H3v10.5a3 3 0 0 0 3 3h1.21l-1.172 3.513a.75.75 0 0 0 1.424.474l.329-.987h8.418l.33.987a.75.75 0 0 0 1.422-.474l-1.17-3.513H18a3 3 0 0 0 3-3V3.75h.75a.75.75 0 0 0 0-1.5H2.25Zm6.54 15h6.42l.5 1.5H8.29l.5-1.5Zm8.085-8.995a.75.75 0 1 0-.75-1.299 12.81 12.81 0 0 0-3.558 3.05L11.03 8.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l2.47-2.47 1.617 1.618a.75.75 0 0 0 1.146-.102 11.312 11.312 0 0 1 3.612-3.321Z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Enhanced Productivity.
                                </dt>
                                <dd class="inline">
                                    Maximize productivity by reducing delays and enabling faster decision-making. Leverage
                                    real-time insights for quicker problem-solving.
                                </dd>
                            </div>
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M12 6.75a5.25 5.25 0 0 1 6.775-5.025.75.75 0 0 1 .313 1.248l-3.32 3.319c.063.475.276.934.641 1.299.365.365.824.578 1.3.64l3.318-3.319a.75.75 0 0 1 1.248.313 5.25 5.25 0 0 1-5.472 6.756c-1.018-.086-1.87.1-2.309.634L7.344 21.3A3.298 3.298 0 1 1 2.7 16.657l8.684-7.151c.533-.44.72-1.291.634-2.309A5.342 5.342 0 0 1 12 6.75ZM4.117 19.125a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-.008Z"
                                            clip-rule="evenodd" />
                                        <path
                                            d="m10.076 8.64-2.201-2.2V4.874a.75.75 0 0 0-.364-.643l-3.75-2.25a.75.75 0 0 0-.916.113l-.75.75a.75.75 0 0 0-.113.916l2.25 3.75a.75.75 0 0 0 .643.364h1.564l2.062 2.062 1.575-1.297Z" />
                                        <path fill-rule="evenodd"
                                            d="m12.556 17.329 4.183 4.182a3.375 3.375 0 0 0 4.773-4.773l-3.306-3.305a6.803 6.803 0 0 1-1.53.043c-.394-.034-.682-.006-.867.042a.589.589 0 0 0-.167.063l-3.086 3.748Zm3.414-1.36a.75.75 0 0 1 1.06 0l1.875 1.876a.75.75 0 1 1-1.06 1.06L15.97 17.03a.75.75 0 0 1 0-1.06Z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Integrated HR & Analytics Tools.
                                </dt>
                                <dd class="inline">
                                    Benefit from a complete suite of HR management features, including performance tracking
                                    and attendance.
                                </dd>
                            </div>
                            <div class="relative pl-9">
                                <dt class="inline font-semibold text-gray-900">
                                    <svg class="absolute left-1 top-1 size-5 text-[var(--secondary)]"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="size-6">
                                        <path fill-rule="evenodd"
                                            d="M12.516 2.17a.75.75 0 0 0-1.032 0 11.209 11.209 0 0 1-7.877 3.08.75.75 0 0 0-.722.515A12.74 12.74 0 0 0 2.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 0 0 .374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 0 0-.722-.516l-.143.001c-2.996 0-5.717-1.17-7.734-3.08Zm3.094 8.016a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                                            clip-rule="evenodd" />
                                    </svg>

                                    Strengthened Security.
                                </dt>
                                <dd class="inline">
                                    Ensure data protection with robust security measures and compliance standards.
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section class="bg-[#f8f9fa]">
        <div class="bg-gray-50 py-16">
            <div class="text-center mb-4">
                <div class="inline-block tab px-3 py-1 text-sm font-bold relative">
                    <span class="absolute left-0 top-0 h-full w-1 tab-span"></span>
                    ELEVATE
                </div>
                <h2 class="text-4xl font-bold mt-4 heading-text">Simplify Your Workflow, Enhance Your Growth</h2>
            </div>
            <div class="mx-auto max-w-2xl px-6 lg:max-w-7xl lg:px-8">
                <div class="mt-10 grid gap-4 sm:mt-16 lg:grid-cols-3 lg:grid-rows-2">
                    <div class="relative lg:row-span-2">
                        <div class="absolute inset-px rounded-lg shadow-lg bg-white lg:rounded-l-[2rem]"></div>
                        <div
                            class="relative flex h-full flex-col overflow-hidden rounded-[calc(var(--radius-lg)+1px)] lg:rounded-l-[calc(2rem+1px)]">
                            <div class="@container relative min-h-[30rem] w-full grow max-lg:mx-auto max-lg:max-w-sm">
                                <div>
                                    <img class="size-full object-cover object-top"
                                        src="{{ asset('assets/front/four/img/landing_page_images/img_02.png') }}"
                                        alt="">
                                </div>
                            </div>
                        </div>
                        <div
                            class="pointer-events-none absolute inset-px rounded-lg ring-1 shadow-sm ring-black/5 lg:rounded-l-[2rem]">
                        </div>
                    </div>
                    <div class="relative max-lg:row-start-1">
                        <div class="absolute inset-px rounded-lg shadow-lg bg-white max-lg:rounded-t-[2rem]"></div>
                        <div
                            class="relative flex h-full flex-col overflow-hidden rounded-[calc(var(--radius-lg)+1px)] max-lg:rounded-t-[calc(2rem+1px)]">
                            <div
                                class="flex flex-1 items-center justify-center px-8 max-lg:pt-10 max-lg:pb-12 sm:px-10 lg:pb-2">
                                <img class="w-full max-lg:max-w-xs"
                                    src="{{ asset('assets/front/four/img/landing_page_images/img_1.png') }}"
                                    alt="">
                            </div>
                        </div>
                        <div
                            class="pointer-events-none absolute inset-px rounded-lg ring-1 shadow-sm ring-black/5 max-lg:rounded-t-[2rem]">
                        </div>
                    </div>
                    <div class="relative max-lg:row-start-3 lg:col-start-2 lg:row-start-2">
                        <div class="absolute inset-px rounded-lg shadow-lg bg-white"></div>
                        <div
                            class="relative flex h-full flex-col overflow-hidden text-center justify-center rounded-[calc(var(--radius-lg)+1px)]">
                            <h4 class="fw-bold mb-3" style="font-size: 1.75rem;">Market Fit</h4>
                            <div class="progress-circle d-flex justify-content-center">
                                <svg width="150" height="150">
                                    <!-- Background Circle -->
                                    <circle cx="75" cy="75" r="60" stroke="#e0e0e0" stroke-width="8"
                                        fill="none"></circle>
                                    <!-- Progress Circle -->
                                    <circle cx="75" cy="75" r="60" stroke="var(--primary)"
                                        stroke-width="8" fill="none" stroke-dasharray="376.8"
                                        stroke-dashoffset="37.68"></circle>
                                    <!-- Text -->
                                    <text x="75" y="85" text-anchor="middle" font-size="28" fill="black"
                                        font-weight="bold">90%</text>
                                </svg>
                            </div>
                        </div>
                        <div class="pointer-events-none absolute inset-px rounded-lg ring-1 shadow-sm ring-black/5"></div>
                    </div>
                    <div class="relative lg:row-span-2">
                        <div
                            class="absolute inset-px rounded-lg shadow-lg bg-white max-lg:rounded-b-[2rem] lg:rounded-r-[2rem]">
                        </div>
                        <div
                            class="relative p-5 h-full rounded-[calc(var(--radius-lg)+1px)] max-lg:rounded-b-[calc(2rem+1px)] lg:rounded-r-[calc(2rem+1px)]">
                            <!-- Icon above heading -->
                            <div class="mb-3">
                                <i class="bi bi-boxes benefits-text" style="font-size: 2.5rem;"></i>
                            </div>

                            <!-- Heading -->
                            <h2 class="fw-bold benefits-text">
                                Everything your team is looking for
                            </h2>

                            <!-- Subheading -->
                            <p class="text-muted">
                                PERI's remarkable adaptability makes it capable of managing various types of tasks
                                efficiently.
                                Moreover, our commitment to continuous innovation ensures that we're always enhancing our
                                platform
                                to meet evolving needs and challenges.
                            </p>

                            <!-- Highlighted Feature -->
                            <div class="d-flex align-items-center mb-5">
                                <div class="me-3 p-3 bg-light rounded-circle shadow">
                                    <i class="bi bi-list-task benefits-text" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1">PERI's Excellence</h5>
                                    <p class="fst-italic mb-0">
                                        "PERI excels, setting industry standards. We continually refine our approach
                                        to meet your team's evolving needs."
                                    </p>
                                </div>
                            </div>

                            <!-- Call to Action -->
                            <a href="{{ url('client/register') }}"
                                class="px-4 py-3 text-[var(--primary)] bg-[var(--secondary)] font-semibold rounded
                                  transition-all duration-300 border-0 hover:bg-[var(--primary)] hover:text-white">
                                Get Free Demo →
                            </a>
                        </div>
                        <div
                            class="pointer-events-none absolute inset-px rounded-lg ring-1 shadow-sm ring-black/5 max-lg:rounded-b-[2rem] lg:rounded-r-[2rem]">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    @if ($about)
        <section class="py-16 flex justify-center about" id="about"
            style="background-image: url('{{ asset('storage/' . $about->section_image) }}'); background-size: cover; background-position: center;"
            x-data="carouselData({{ json_encode($about->value) }})" x-init="initCarousel()">
            <div
                class="backdrop-blur-lg bg-white/10 border border-white/30 shadow-lg rounded-lg p-10 max-w-6xl w-full relative">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">

                    <!-- Left Side: Transparent Image -->
                    <div class="flex justify-center">
                        <img src="{{ asset('storage/' . $about->product_image) }}" alt="Dashboard Preview"
                            class="w-full max-w-lg object-contain">
                    </div>

                    <!-- Right Side: Alpine.js Carousel -->
                    <div class="relative">
                        <div class="min-h-[300px]">
                            <!-- Slides -->
                            <template x-for="(section, index) in sections" :key="index">
                                <div x-show="index === activeSlide"
                                    x-transition:enter="transition ease-out duration-700 transform"
                                    x-transition:enter-start="opacity-0 translate-y-12"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-500 transform"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 -translate-y-12"
                                    class="absolute top-0 left-0 w-full">
                                    <div
                                        class="bg-white/30 backdrop-blur-md p-6 rounded-lg shadow-sm border border-white/20">
                                        <div class="flex items-center gap-4 mb-4">
                                            <i :class="'bi ' + section.icon + ' text-4xl text-[var(--primary)]'"></i>
                                            <h3 class="text-2xl font-bold text-white" x-text="section.title"></h3>
                                        </div>
                                        <p class="text-gray-200 leading-relaxed" x-text="section.description"></p>
                                        <a href="{{ url('/register') }}"
                                            class="inline-block mt-4 bg-[var(--primary)] text-white px-4 py-2 rounded-lg font-bold
                                        hover:bg-[var(--secondary)] transition duration-300">
                                            View →
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Responsive Navigation Dots -->
                        <div class="flex justify-center md:justify-start mt-6 space-x-2">
                            <template x-for="(section, index) in sections" :key="index">
                                <button @click="activeSlide = index"
                                    class="w-3 h-3 md:w-4 md:h-4 rounded-full transition duration-300 ease-in-out transform hover:scale-125"
                                    :class="activeSlide === index ?
                                        'bg-[var(--primary)] scale-125' :
                                        'bg-gray-300 hover:bg-gray-400'"
                                    aria-label="Navigate to slide"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <script>
        function carouselData(initialSections) {
            // Convert object to array
            const sectionsArray = Object.keys(initialSections).map(key => ({
                ...initialSections[key]
            }));

            return {
                sections: sectionsArray,
                activeSlide: 0,

                initCarousel() {
                    // Ensure the first slide is active
                    this.$nextTick(() => {
                        this.activeSlide = 0;
                    });

                    // Optional: Auto-rotate slides
                    setInterval(() => {
                        this.activeSlide = (this.activeSlide + 1) % this.sections.length;
                    }, 8000);
                }
            }
        }
    </script>
    <livewire:contact-form />
@endsection
