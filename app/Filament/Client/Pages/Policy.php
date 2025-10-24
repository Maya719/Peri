<?php

namespace App\Filament\Client\Pages;

use App\Facades\Helper;
use App\Models\AttendancePolicy; // Replace with your new model
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class Policy extends Page implements HasKnowledgeBase
{
    use Forms\Concerns\InteractsWithForms;

    public array $data = [];
    protected static ?string $navigationLabel = 'Attendance Policies';
    protected static string $view = 'filament.client.pages.policy';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 6;
    public $tenant;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasRole('Admin') && Helper::has_feature('attendance-policies');
    }

    public static function getDocumentation(): array
    {
        return [
            'policy.introduction',
            KnowledgeBase::model()::find('policy.working'),
        ];
    }

    protected function getFormSchema(): array
    {
        $user = Auth::user();
        $canManage = $user->hasRole('Admin') || $user->can('attendancePolicies.manage');

        return [
            Section::make("Late Minutes Policy")
                ->schema([
                    Toggle::make('late_policy_enabled')
                        ->label('Enable')
                        ->reactive()
                        ->statePath('data.late_policy_enabled')
                        ->disabled(!$canManage)
                        ->hintAction(
                            Action::make('info')
                                ->icon('heroicon-m-question-mark-circle')
                                ->tooltip('Handle late arrivals and early departures.')
                        ),
                    Toggle::make('enable_late_come')
                        ->label('Count Late Minutes')
                        ->visible(fn($get) => $get('data.late_policy_enabled'))
                        ->statePath('data.enable_late_come')
                        ->disabled(!$canManage),
                    Toggle::make('enable_early_leave')
                        ->label('Count Early Leave Minutes')
                        ->visible(fn($get) => $get('data.late_policy_enabled'))
                        ->statePath('data.enable_early_leave')
                        ->disabled(!$canManage),
                    Toggle::make('time_offset_allowance')
                        ->label('Allow Minutes Offset')
                        ->hintAction(
                            Action::make('info')
                                ->icon('heroicon-m-question-mark-circle')
                                ->tooltip('Allows employees to compensate late minutes by staying late')
                        )
                        ->visible(fn($get) => $get('data.late_policy_enabled'))
                        ->statePath('data.time_offset_allowance')
                        ->disabled(!$canManage),
                ])
                ->extraAttributes(['class' => 'h-full']),

            Section::make("Single Biometric Policy")
                ->schema([
                    Toggle::make('single_biometric_policy_enabled')
                        ->label('Enable')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('data.single_biometric_behavior', 'half_day');
                            }
                        })
                        ->statePath('data.single_biometric_policy_enabled')
                        ->disabled(!$canManage)
                        ->hintAction(
                            Action::make('info')
                                ->icon('heroicon-m-question-mark-circle')
                                ->tooltip('If only one biometric punch is recorded for the day, the system will handle it based on your selected option')
                        ),
                    Radio::make('single_biometric_behavior')
                        ->label('')
                        ->options([
                            'half_day' => 'Mark as Half Day',
                            'biometric_missing' => 'Mark as Biometric Missing',
                        ])
                        ->visible(fn($get) => $get('data.single_biometric_policy_enabled'))
                        ->statePath('data.single_biometric_behavior')
                        ->disabled(!$canManage)
                        ->columnSpan('full'),
                ])
                ->extraAttributes(['class' => 'h-full']),

            Section::make("Grace Minutes Policy")
                ->schema([
                    Toggle::make('grace_policy_enabled')
                        ->label('Enable')
                        ->reactive()
                        ->statePath('data.grace_policy_enabled')
                        ->disabled(!$canManage)
                        ->hintAction(
                            Action::make('info')
                                ->icon('heroicon-m-question-mark-circle')
                                ->tooltip('Employees are granted grace minutes for arrival, applicable up to a certain number of days per month or week without being considered late.')
                        ),
                    Forms\Components\Grid::make(3)
                        ->schema([
                            TextInput::make('late_penalty')
                                ->label('Grant Minutes')
                                ->required()
                                ->numeric()
                                ->visible(fn($get) => $get('data.grace_policy_enabled'))
                                ->statePath('data.late_penalty')
                                ->disabled(!$canManage)
                                ->reactive(),

                            TextInput::make('days_counter')
                                ->label('For Number of Days')
                                ->numeric()
                                ->required()
                                ->visible(
                                    fn($get) =>
                                    $get('data.grace_policy_enabled') &&
                                        $get('data.grace_duration') !== 'day'
                                )
                                ->statePath('data.days_counter')
                                ->disabled(!$canManage)
                                ->reactive()
                                ->helperText(fn($get) => $get('data.grace_duration') === 'month'
                                    ? 'Max 31 days in a month'
                                    : '')
                                ->afterStateUpdated(function (callable $get, callable $set, $state) {
                                    if ($get('data.grace_duration') === 'month' && $state > 31) {
                                        $set('data.days_counter', 31);
                                    }
                                }),


                            Select::make('grace_duration')
                                ->label('Duration')
                                ->options([
                                    'day'  => 'Per Day',
                                    'month' => 'Per Month',
                                ])
                                ->required()
                                ->statePath('data.grace_duration')
                                ->disabled(!$canManage)
                                ->visible(fn($get) => $get('data.grace_policy_enabled'))
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $daysCounter = (int) $get('data.days_counter');
                                    if ($state === 'month' && $daysCounter > 31) {
                                        $set('data.days_counter', 31);
                                    }
                                }),
                        ])
                ])
                ->extraAttributes(['class' => 'h-full']),


            Section::make("Overtime Policy")
                ->schema([
                    Toggle::make('overtime_policy_enabled')
                        ->label('Enable')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('data.overtime_duration', 'per_day');
                            }
                        })
                        ->statePath('data.overtime_policy_enabled')
                        ->disabled(!$canManage)
                        ->hintAction(
                            Action::make('info')
                                ->icon('heroicon-m-question-mark-circle')
                                ->tooltip('Set the delay minutes after which overtime begins when shift ends, limit the total overtime minutes, and choose how often the limit resets.')
                        ),
                    Forms\Components\Grid::make(3)
                        ->schema([
                            TextInput::make('overtime_start_delay')
                                ->label('Overtime Start Delay Minutes')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->visible(fn($get) => $get('data.overtime_policy_enabled'))
                                ->statePath('data.overtime_start_delay')
                                ->disabled(!$canManage),
                            TextInput::make('overtime_max_minutes')
                                ->label('Maximum Overtime Minutes')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->visible(fn($get) => $get('data.overtime_policy_enabled'))
                                ->statePath('data.overtime_max_minutes')
                                ->disabled(!$canManage),
                            Select::make('overtime_duration')
                                ->label('Duration')
                                ->options([
                                    'per_day' => 'Per Day',
                                    'per_month' => 'Per Month',
                                ])
                                ->visible(fn($get) => $get('data.overtime_policy_enabled'))
                                ->statePath('data.overtime_duration')
                                ->disabled(!$canManage)
                                ->required(),
                        ])
                ])
                ->extraAttributes(['class' => 'h-full']),

            Section::make("Sandwich Leave Policy")
                ->schema([
                    Toggle::make('sandwich_rule_policy_enabled')
                        ->label('Enable')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('data.leaves_policy_option', 'before');
                            }
                        })
                        ->statePath('data.sandwich_rule_policy_enabled')
                        ->disabled(!$canManage),
                    Radio::make('leaves_policy_option')
                        ->label('')
                        ->options([
                            'before' => 'Apply only when leave is taken before an off day',
                            'after' => 'Apply only when leave is taken after an off day',
                            'after_and_before' => 'Apply when leave is taken both before and after an off day',
                            'after_or_before' => 'Apply when leave is taken either before or after an off day',
                        ])
                        ->visible(fn($get) => $get('data.sandwich_rule_policy_enabled'))
                        ->statePath('data.leaves_policy_option')
                        ->disabled(!$canManage)
                        ->columnSpan('full'),
                ])
                ->extraAttributes(['class' => 'h-full']),
        ];
    }

    public function mount(): void
    {
        $this->tenant = filament()->getTenant();

        $policy = $this->tenant->policies()->first();

        if (!$policy) {
            $policy = $this->tenant->policies()->create([
                'team_id' => $this->tenant->id,
                'full_day_counter' => 1,
                'half_day_counter' => 0.5,
                'late_policy_enabled' => 1,
                'single_biometric_policy_enabled' => 1,
                'enable_late_come' => 1,
                'enable_early_leave' => 1,
                'show_half_day' => 1,
                'time_offset_allowance' => 0,
                'grace_policy_enabled' => 0,
                'leave_balance_policy_enabled' => 1,
                'sandwich_rule_policy_enabled' => 0,
                'overtime_policy_enabled' => 0,
                'leaves_policy_option' => null,
                'single_biometric_behavior' => 'half_day',
            ]);
        }

        $this->data = $policy->toArray();
        $this->form->fill($this->data);
    }

    public function submit(): void
    {
        $user = Auth::user();
        if (!($user->hasRole('Admin') || $user->can('attendancePolicies.manage'))) {
            abort(403, 'You do not have permission to update policies.');
        }

        $this->tenant = filament()->getTenant();

        $policy = $this->tenant->policies()->first();
        if (!$policy) {
            abort(404, 'Attendance policy not found.');
        }

        $policy->update($this->data);

        Notification::make()
            ->title('Policies updated successfully.')
            ->success()
            ->send();
    }
    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Admin') && Helper::has_feature('attendance-policies');
    }
}
