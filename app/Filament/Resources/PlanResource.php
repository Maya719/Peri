<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Laravelcm\Subscriptions\Interval;
use App\Models\Plan;
use App\Filament\Resources\PlanResource\RelationManagers;


class PlanResource extends Resource
{
    protected static ?int $navigationSort = 1;

    public static function getModel(): string
    {
        return config('laravel-subscriptions.models.plan', Plan::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Subscriptions';
    }

    public static function getNavigationLabel(): string
    {
        return 'Plans';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Plans';
    }

    public static function getLabel(): ?string
    {
        return 'Plans';
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->columnSpanFull()
                            ->label('Name')
                            ->required(),

                        TextInput::make('description')
                            ->columnSpanFull()
                            ->label('Description'),

                        Forms\Components\Hidden::make('currency')
                            ->default('USD'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active'),
                        Forms\Components\Toggle::make('free_trial')
                            ->label('Free Trial')
                            ->live(),
                        Forms\Components\Group::make()
                            ->schema(components: [
                                TextInput::make('price')
                                    ->default(0)
                                    ->label('Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('signup_fee')
                                    ->label('Signup Fee')
                                    ->default(0)
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        TextInput::make('invoice_period')
                                            ->label('Period')
                                            ->default(1)
                                            ->minValue(1)
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\Select::make('invoice_interval')
                                            ->default(Interval::MONTH->value)
                                            ->label(label: 'Interval')
                                            ->options([
                                                Interval::DAY->value => 'Day',
                                                Interval::MONTH->value => 'Month',
                                                Interval::YEAR->value => 'Year',
                                            ])->required(),
                                    ])
                            ])->hidden(fn (Forms\Get $get) => $get('free_trial')),
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    TextInput::make('trial_period')
                                        ->label('Period')
                                        ->default(1)
                                        ->minValue(1)
                                        ->numeric(),
                                    Forms\Components\Select::make('trial_interval')
                                        ->default(Interval::MONTH->value)
                                        ->label('Interval')
                                        ->options([
                                            Interval::DAY->value => 'Day',
                                            Interval::MONTH->value => 'Month',
                                            Interval::YEAR->value => 'Year',
                                        ]),
                                ])
                            ])->visible(fn (Forms\Get $get) => $get('free_trial')),

                    ])->columns(2)
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->sortable()
                    ->searchable()
                    ->money(locale: 'en', currency: fn($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            RelationManagers\FeatureManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
