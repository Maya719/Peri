<?php

namespace App\Providers;

use App\Facades\FilamentSubscriptions;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\View\View;
use Illuminate\Support\Facades\Blade;
use App\Services\EmailService;
use Guava\FilamentKnowledgeBase\Filament\Panels\KnowledgeBasePanel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('filament-subscriptions', function () {
            return new \App\Services\FilamentSubscriptionServices();
        });
        $this->app->singleton('helper', function () {
            return new \App\Services\HelperService();
        });
        $this->app->singleton('email-service', function () {
            return new EmailService();
        });
        KnowledgeBasePanel::configureUsing(
            fn(KnowledgeBasePanel $panel) => $panel
                ->viteTheme('resources/css/filament/admin/themeKB.css')
                ->disableAnchors()
                ->disableBreadcrumbs()
                ->disableTableOfContents()
        );
    }

    public function boot(): void
    {
        FilamentSubscriptions::register(
            \App\Services\Contracts\Subscriber::make('Team')->model(\App\Models\Team::class)
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn(): View => view('filament.components.date'),
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn(): string => Blade::render('<livewire:remote-attendance />'),
        );
        Blade::component('google-auth-button', 'components.google-auth-button');
    }
}
