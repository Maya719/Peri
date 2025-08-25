<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\HolidayResource\Pages;
use App\Models\Holiday;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class HolidayResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Holiday::class;

    protected static ?string $navigationLabel = 'Plan Holidays';
    protected static ?string $navigationGroup = 'Attendance Management';
    protected static ?int $navigationSort = 7;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') ||  Auth::user()->can('holiday.manage') || $user->hasRole('CEO') || $user->hasRole('AMS Manager'));
    }

    public static function getDocumentation(): array
    {
        return [
            'holidays.introduction',
            KnowledgeBase::model()::find('holidays.working'),
        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(12)
                    ->schema([
                        Section::make('Details')
                            ->columnSpan(4)
                            ->columns([
                                'sm' => 12,
                                'xl' => 12,
                                '2xl' => 12,
                            ])
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        1 => 'Weekend',
                                        2 => 'Religious Day',
                                        3 => 'National Day',
                                        4 => 'Unplanned',
                                        5 => 'Other',
                                    ])
                                    ->required()
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 12,
                                        '2xl' => 12,
                                    ]),
                                Forms\Components\DatePicker::make('starting_date')
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 6,
                                        '2xl' => 6,
                                    ]),
                                Forms\Components\DatePicker::make('ending_date')
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->afterOrEqual('starting_date')
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 6,
                                        '2xl' => 6,
                                    ]),

                                Select::make('apply')
                                    ->label('Apply To')
                                    ->options([
                                        'all' => 'All',
                                        'shift' => 'Shift',
                                        'department' => 'Department',
                                        'user' => 'Employee',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 12,
                                        '2xl' => 12,
                                    ]),
                                Select::make('departments')
                                    ->label('Select Department(s)')
                                    ->multiple()
                                    ->searchable()
                                    ->required()
                                    ->options(fn() => Filament::getTenant()->departments()->pluck('name', 'id'))

                                    ->hidden(fn($get) => $get('apply') !== 'department')
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 12,
                                        '2xl' => 12,
                                    ]),
                                Select::make('users')
                                    ->label('Select Employee(s)')
                                    ->multiple()
                                    ->searchable()
                                    ->required()
                                    ->options(fn() => Filament::getTenant()->users()->pluck('name', 'id'))
                                    ->hidden(fn($get) => $get('apply') !== 'user')
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 12,
                                        '2xl' => 12,
                                    ]),
                                Select::make('shifts')
                                    ->label('Select Shift(s)')
                                    ->multiple()
                                    ->searchable()
                                    ->required()
                                    ->options(fn() => Filament::getTenant()->shifts()->pluck('name', 'id'))
                                    ->hidden(fn($get) => $get('apply') !== 'shift')
                                    ->columnSpan([
                                        'sm' => 12,
                                        'xl' => 12,
                                        '2xl' => 12,
                                    ]),
                                Textarea::make('remarks')->maxLength(500)->columnSpan([
                                    'sm' => 12,
                                    'xl' => 12,
                                    '2xl' => 12,
                                ]),
                            ])
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('starting_date')->sortable(),
                TextColumn::make('ending_date')->sortable(),
                BadgeColumn::make('type')
                    ->colors([
                        'primary' => 1,
                        'success' => 2,
                        'warning' => 3,
                        'info' => 4,
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        1 => 'Weekend',
                        2 => 'Religious Day',
                        3 => 'National Day',
                        4 => 'Unplanned',
                        5 => 'Other',
                    }),

                TextColumn::make('apply')
                    ->label('Applies To')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'all' => 'All',
                        'shift' => 'Shift(s)',
                        'department' => 'Department(s)',
                        'user' => 'Employee(s)',
                    }),

                TextColumn::make('remarks')->limit(50)->label('Remarks'),

            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        1 => 'Weekend',
                        2 => 'Religious Day',
                        3 => 'National Day',
                        4 => 'Unplanned',
                        5 => 'Other',
                    ]),
            ])
            ->defaultSort('starting_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => Auth::user()->can('holiday.manage') || Auth::user()->hasRole('Admin')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => Auth::user()->can('holiday.manage') || Auth::user()->hasRole('Admin')),
            ])
            ->actionsColumnLabel('Actions')
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => Auth::user()->can('holiday.manage') || Auth::user()->hasRole('Admin')),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit' => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('holiday.view') || Auth::user()->can('holiday.manage')) && Helper::has_feature('attendance');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('holiday.manage'));
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('holiday.manage'));
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('holiday.manage'));
    }
}
