<?php

namespace App\Providers\Filament;

use App\Facades\Helper;
use App\Filament\Client\Pages\Tenancy\EditTeamProfile;
use App\Filament\Client\Pages\Tenancy\RegisterTeam;
use App\Filament\Client\Pages\Dashboard;
use App\Filament\Client\Pages\Auth\Login;
use App\Filament\Client\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Client\Pages\Auth\Invitation;
use App\Http\Middleware\DefaultTeamVerify;
use App\Http\Middleware\ExtendedAuthenticate;
use App\Http\Middleware\VerifyBillableIsSubscribed;
use App\Models\Team;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Client\Widgets\AttendanceTableWidget;
use App\Filament\Client\Widgets\DaysWidget;
use App\Filament\Client\Widgets\LeaveBalanceChart;
use App\Filament\Client\Widgets\MonthWidget;
use App\Filament\Client\Widgets\UserEventsList;
use App\Filament\Client\Widgets\UserMonthlyAttendanceWidget;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\View;
use App\Filament\Client\Pages\Auth\Profile;
use App\Filament\Client\Pages\Auth\Registration;
use App\Filament\Client\Pages\Auth\RequestPasswordReset;
use App\Filament\Client\Pages\Auth\ResetPassword;
use Guava\FilamentKnowledgeBase\KnowledgeBasePlugin;
use \Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Route;
use App\Filament\Client\Pages\Settings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $settings = Helper::getGenralSettings();
        return $panel
            ->default()
            ->id('client')
            ->path('client')
            ->colors([
                'primary' => $settings['primary_color'] ?? '#193a66',
            ])
            ->favicon(asset('storage/' . $settings['favicon']))
            ->font('Poppins')
            ->brandLogo(asset('storage/' . $settings['main_logo']))
            ->darkModeBrandLogo(asset('storage/' . $settings['dark_logo']))
            ->brandLogoHeight('3rem')
            ->sidebarCollapsibleOnDesktop()
            ->breadcrumbs(false)
            ->login(Login::class)
            ->registration(Registration::class)
            ->profile(Profile::class)
            ->passwordReset(
                requestAction: RequestPasswordReset::class,
                resetAction: ResetPassword::class
            )
            ->emailVerification(EmailVerificationPrompt::class)
            ->userMenuItems([
                MenuItem::make()
                    ->label('Super Admin')
                    ->icon('heroicon-o-briefcase')
                    ->url('/admin')
                    ->visible(fn(): bool => Auth::user()->is_super_admin),
                MenuItem::make()
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn() => Settings\Index::getUrl())
                    ->visible(fn(): bool => Auth::user()->hasRole('Admin')),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                AuthUIEnhancerPlugin::make()
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelPosition('right')
                    ->formPanelWidth('40%')
                    ->emptyPanelBackgroundImageOpacity('90%')
                    ->emptyPanelBackgroundImageUrl(Helper::getImageUrl()),
                (function () {
                    $plugin = KnowledgeBasePlugin::make();
                    $plugin->disableKnowledgeBasePanelButton();
                    $plugin->modalPreviews();
                    $plugin->helpMenuRenderHook(PanelsRenderHook::TOPBAR_END);
                    return $plugin;
                })(),
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\\Filament\\Client\\Widgets')
            ->widgets([
                DaysWidget::class,
                MonthWidget::class,
                UserMonthlyAttendanceWidget::class,
                AttendanceTableWidget::class,
                LeaveBalanceChart::class,
                UserEventsList::class,
                \App\Filament\Client\PayRunWidgets\PayRunHistoryTableWidget::class,
                \App\Filament\Client\CustomWidgets\UserAMSreport::class,
                \App\Filament\Client\Widgets\CurrentPayroll::class,
                \App\Filament\Client\Widgets\PreviousPayroll::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Attendance Management')
                    ->icon('heroicon-o-finger-print')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Reports')
                    ->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make()
                    ->label('Payroll Management')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make()
                    ->label('Subscription')
                    ->icon('heroicon-o-credit-card'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                ExtendedAuthenticate::class,
            ])
            ->tenantMiddleware([
                DefaultTeamVerify::class,
                VerifyBillableIsSubscribed::class,
            ], isPersistent: true)
            ->databaseNotificationsPolling('5s')
            ->routes(function () {
                Route::get('/auth/invitations/{token}', Invitation::class)
                    ->name('client.auth.invitation');
            })
            ->databaseNotifications()
            ->tenant(Team::class, ownershipRelationship: 'team', slugAttribute: 'slug')
            ->tenantRegistration(RegisterTeam::class)
            ->maxContentWidth(MaxWidth::Full)
            ->tenantProfile(EditTeamProfile::class)
            ->tenantMenuItems([
                'profile' => MenuItem::make()->label('Edit team profile'),
                'register' => MenuItem::make()->label('Register New Company')->visible(false),
            ])
            ->renderHook('panels::footer', fn() => view('filament.components.footer'))
            ->renderHook('panels::topbar.before', function () {
                return View::make('filament.client.pages.notices')->render();
            })
            ->renderHook('panels::footer.before', function () {
                return View::make('filament.client.components.stripe')->render();
            })
            ->renderHook('panels::global-search.before', function () {
                $team = Filament::getTenant();
                $subscription = $team->activePlanSubscriptions()->first();
                if ($subscription) {
                    $plan = $subscription->plan;
                    $endingSoon = $subscription->ends_at && $subscription->ends_at->isBetween(now(), now()->addDays(7));
                    $trialEndingSoon = $subscription->trial_ends_at && $subscription->trial_ends_at->isBetween(now(), now()->addDays(7));
                    if ($endingSoon || $plan->isFree() || $trialEndingSoon) {
                        return Blade::render('@livewire(\'subscription-card\')');
                    }
                }
                return '';
            })
            ->renderHook('panels::global-search.before', function () {
                $user = Auth::user();
                $role = $user?->getRoleNames()->first() ?? 'User';
                $roleBadgeView = View::make('filament.components.role-badge', compact('role'))->render();
                return $roleBadgeView;
            });
    }
}
