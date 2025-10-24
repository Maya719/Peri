<?php

namespace App\Filament\Resources\PlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravelcm\Subscriptions\Interval;

class FeatureManager extends RelationManager
{
    protected static string $relationship = 'features';

    public static function getLabel(): ?string
    {
        return 'Features';
    }

    public static function getModelLabel(): ?string
    {
        return 'Feature';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Features';
    }

    public function form(Form $form): Form
    {
        $options = [
            'Attendance' => 'Attendance',
            'PayRoll' => 'PayRoll',
            'Funds' => 'Funds',
            'Employees' => 'Employees',
            'Shifts' => 'Shifts',
            'Departments' => 'Departments',
            'Roles' => 'Roles',
            'Biometric Devices' => 'Biometric Devices',
            'Attendance Policies' => 'Attendance Policies',
        ];

        return $form
            ->schema([
                Forms\Components\Select::make('name')
                    ->label('Name')
                    ->options($options)
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('description')
                    ->label('Description')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->default(0)
                    ->helperText('Enter -1 for unlimited, 0 for not available, or a specific count.')
                    ->dehydrateStateUsing(function ($state) {
                        if ($state == 0) {
                            return 'false';
                        } elseif ($state == -1) {
                            return 'true';
                        }
                        return (int) $state;
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state === 'false') {
                            return 0;
                        } elseif ($state === 'true') {
                            return -1;
                        }
                        return $state;
                    })
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('feature')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'true') {
                            return '✅';
                        } elseif ($state == 'false') {
                            return '❌';
                        }
                        return $state; // keep number as it is
                    })
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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

}
