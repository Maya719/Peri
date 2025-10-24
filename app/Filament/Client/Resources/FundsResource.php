<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\FundsResource\Pages;
use App\Models\Fund;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use App\Services\PayrollCalculationService;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;

class FundsResource extends Resource
{
    protected static ?string $model = Fund::class;
    protected static ?string $tenantOwnershipRelationshipName = 'team';
    protected static ?string $navigationLabel = 'Funds';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;
    protected static ?string $recordTitleAttribute = 'name';
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\Select::make('tax_status')
                                    ->options([
                                        'non-taxable' => 'Non-Taxable (Pre-Tax)',
                                        'taxable' => 'Taxable (Post-Tax)',
                                    ])
                                    ->required(),
                                TableRepeater::make('brackets')
                                    ->label('Brackets')
                                    ->schema([
                                        TextInput::make('min_annual_salary')
                                            ->label('Minimum Salary')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->columnSpan(1),

                                        TextInput::make('max_annual_salary')
                                            ->label('Maximum Salary')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->columnSpan(1),

                                        Select::make('type')
                                            ->default('percentage')
                                            ->label('Type')
                                            ->options([
                                                'percentage' => 'Percentage',
                                                'fixed_amount' => 'Fixed Amount',
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state === 'percentage') {
                                                    $set('fixed_amount', null);
                                                }

                                                if ($state === 'fixed_amount') {
                                                    $set('percentage', null);
                                                }
                                            }),

                                        TextInput::make('percentage')
                                            ->label('Value')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->nullable()
                                            ->columnSpan(1)
                                            ->visible(fn($get) => $get('type') === 'percentage'),

                                        TextInput::make('fixed_amount')
                                            ->label('Value')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('$')
                                            ->nullable()
                                            ->columnSpan(1)
                                            ->visible(fn($get) => $get('type') === 'fixed_amount'),
                                    ])
                                    ->mutateDehydratedStateUsing(function ($state) {
                                        return collect($state)->map(function ($item) {
                                            $item['percentage'] = array_key_exists('percentage', $item) ? $item['percentage'] : null;
                                            $item['fixed_amount'] = array_key_exists('fixed_amount', $item) ? $item['fixed_amount'] : null;
                                            return $item;
                                        })->values()->toArray();
                                    })
                                    ->reorderable(false)
                                    ->collapsible(false)
                                    ->columns(3)
                                    ->createItemButtonLabel('Add Bracket')
                                    ->defaultItems(1)
                            ])->columns(1)
                    ])
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Split::make([
                    TextColumn::make('name')->searchable(),
                    ToggleColumn::make('is_active')
                        ->label('Active')
                        ->onIcon('heroicon-s-check')
                        ->offIcon('heroicon-s-x-mark')
                        ->default(true)
                        ->columnSpan(1),
                    Tables\Columns\TextColumn::make('created_at')
                        ->formatStateUsing(fn($state) => 'Created: ' . \Carbon\Carbon::parse($state)->format('d M Y, h:i A')),
                ]),

                Panel::make([
                    Stack::make([
                        TextColumn::make('brackets')
                            ->label('Incentive Brackets')
                            ->formatStateUsing(function ($state, $record) {
                                $state = $record->brackets;
                                $payrollCalculationService = app(PayrollCalculationService::class);
                                $currencySymbol = $payrollCalculationService->getCurrencySymbolForAdmin($record->team->id);

                                if (is_string($state)) {
                                    $state = json_decode($state, true);
                                }

                                if (!is_array($state) || empty($state)) {
                                    return '-';
                                }

                                return collect($state)->map(function ($item) use ($currencySymbol) {
                                    if (!is_array($item) || !isset($item['type'])) {
                                        return '-';
                                    }

                                    $min = $currencySymbol . ' ' . number_format($item['min_annual_salary'] ?? 0);
                                    $max = $currencySymbol . ' ' . number_format($item['max_annual_salary'] ?? 0);

                                    $range = $max > 0
                                        ? "<strong>Min:</strong> {$min} | <strong>Max:</strong> {$max}"
                                        : "<strong>Above:</strong> {$min}";

                                    if ($item['type'] === 'percentage') {
                                        $value = $item['percentage'] ?? 0;
                                        return "
                                            <div style='margin-bottom:8px'>
                                                {$range}<br>
                                                <strong>Value:</strong> {$value}%
                                            </div>
                                        ";
                                    }

                                    if ($item['type'] === 'fixed_amount') {
                                        $value = $currencySymbol . ' ' . number_format($item['fixed_amount'] ?? 0);
                                        return "
                                            <div style='margin-bottom:8px'>
                                                {$range}<br>
                                                <strong>Type:</strong> Fixed Amount<br>
                                                <strong>Value:</strong> {$value}
                                            </div>
                                        ";
                                    }

                                    return '-';
                                })->implode('<hr style=\"margin:6px 0\">');
                            })
                            ->html()
                            ->wrap()
                    ]),
                ])->collapsible(),
            ])
            ->searchPlaceholder('Search Fund')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $tenant = Filament::getTenant();
                        $subscription = $tenant->activePlanSubscriptions()->first();
                        if ($subscription) {
                            $subscription->reduceFeatureUsage('funds');
                        }
                    }),
            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFunds::route('/'),
            'create' => Pages\CreateFunds::route('/create'),
            'edit' => Pages\EditFunds::route('/{record}/edit'),
        ];
    }
    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Payroll Manager') ||
            Auth::user()->can('payroll.manage')) && Helper::has_feature('payroll');
    }

    public static function canCreate(): bool
    {
        return !Filament::getTenant()->payruns()->whereIn('status', ['draft', 'pending_approval', 'rejected'])->exists()
            && Helper::is_module_allowed('funds');
    }
}
