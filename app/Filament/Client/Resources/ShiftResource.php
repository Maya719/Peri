<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class ShiftResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationLabel = 'Shifts';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function getDocumentation(): array
    {
        return [
            KnowledgeBase::model()::find('shifts.introduction'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') || $user->hasRole('CEO') || $user->hasRole('AMS Manager'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('Shift Name'),

                    Forms\Components\TextInput::make('short_leave')
                        ->label('Short Leave Limit')
                        ->numeric()
                        ->suffix('minutes')
                        ->required(),

                    Forms\Components\TimePicker::make('starting_time')
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->label('Starting Time')
                        ->withoutSeconds(),

                    Forms\Components\TimePicker::make('ending_time')
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->label('Ending Time')
                        ->withoutSeconds(),

                    Forms\Components\TimePicker::make('break_start')
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->label('Break Start')
                        ->withoutSeconds(),

                    Forms\Components\TimePicker::make('break_end')
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->label('Break End')
                        ->withoutSeconds(),

                    Forms\Components\TimePicker::make('half_day_check_in')
                        ->label('Half Day Check In')
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->withoutSeconds(),

                    Forms\Components\TimePicker::make('half_day_check_out')
                        ->label('Half Day Check Out')
                        ->native(false)
                        ->prefixIcon('heroicon-m-clock')
                        ->withoutSeconds(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('starting_time'),
            Tables\Columns\TextColumn::make('ending_time'),
            Tables\Columns\TextColumn::make('break_start'),
            Tables\Columns\TextColumn::make('break_end'),
            Tables\Columns\TextColumn::make('half_day_check_in'),
            Tables\Columns\TextColumn::make('half_day_check_out'),
            Tables\Columns\TextColumn::make('short_leave')->label('Short leave Limit')->suffix(' mins'),
        ])
            ->poll(1)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => Auth::user()->can('shifts.manage') || Auth::user()->hasRole('Admin')),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => Auth::user()->can('shifts.manage') || Auth::user()->hasRole('Admin'))
                    ->after(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $subscription = $tenant->activePlanSubscriptions()->first();
                        if ($subscription) {
                            $subscription->reduceFeatureUsage('shifts');
                        }
                    }),
            ])
            ->actionsColumnLabel('Actions')

            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => Auth::user()->can('shifts.manage') || Auth::user()->hasRole('Admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit'   => Pages\EditShift::route('/{record}/edit'),
        ];
    }

    // Permissions for CRUD operations
    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::has_feature('shifts');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::is_module_allowed('shifts');
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin'));
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin'));
    }
}
