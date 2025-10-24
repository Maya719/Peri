<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\LeaveTypeResource\Pages;
use App\Models\LeaveType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class LeaveTypeResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $navigationLabel = 'Leave Types';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') || $user->attendance_config == 1 || $user->hasRole('CEO') || $user->hasRole('AMS Manager'));
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function getDocumentation(): array
    {
        return [
            KnowledgeBase::model()::find('leave types.introduction'),
        ];
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $canEdit = $user->hasRole('Admin') || $user->can('leaveType.manage');

        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->helperText('Enter the name of the leave type')
                            ->disabled(! $canEdit),

                        Forms\Components\Select::make('duration')
                            ->label('Duration')
                            ->options([
                                'annual' => 'Annual',
                                '1 month' => '1 Month',
                                '3 months' => '3 Months',
                                '4 months' => '4 months',
                                '6 months' => '6 months',
                            ])
                            ->required()
                            ->disabled(! $canEdit),

                        Forms\Components\Select::make('apply_on')
                            ->label('Apply On')
                            ->options([
                                'all' => 'All',
                                'male_unmarried' => 'Male - Unmarried',
                                'male_married' => 'Male - Married',
                                'female_unmarried' => 'Female - Unmarried',
                                'female_married' => 'Female - Married',
                            ])
                            ->required()
                            ->disabled(! $canEdit),

                        Forms\Components\TextInput::make('leaves_count')
                            ->label('Leave Count')
                            ->numeric()
                            ->required()
                            ->disabled(! $canEdit),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration'),
                Tables\Columns\TextColumn::make('apply_on')
                    ->label('Apply On')
                    ->formatStateUsing(fn(string $state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('leaves_count')
                    ->label('Leaves Count')
                    ->sortable(),
            ])
            ->searchPlaceholder('Search Leave')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn() => Auth::user()->hasRole('Admin') || Auth::user()->can('leaveType.manage')),
                DeleteAction::make()
                    ->visible(fn() => Auth::user()->hasRole('Admin') || Auth::user()->can('leaveType.manage')),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()->hasRole('Admin') || Auth::user()->can('leaveType.manage')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveTypes::route('/'),
            'create' => Pages\CreateLeaveType::route('/create'),
            'edit' => Pages\EditLeaveType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::has_feature('attendance');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole('Admin')
        );
    }
}
