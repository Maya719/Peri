<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\BiometricResource\Pages;
use App\Filament\Client\Resources\BiometricResource\RelationManagers;
use App\Models\Biometric;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class BiometricResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Biometric::class;
    protected static ?string $navigationLabel = 'Biometric Requests';
    protected static ?string $modelLabel = 'Biometric Request';
    protected static ?string $navigationGroup = 'Attendance Management';
    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return ($user->hasRole('Admin') || $user->attendance_config == 1 || $user->hasRole('CEO') || $user->hasRole('AMS Manager'));
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        if (Auth::user()->hasRole('Admin')) {
            $query;
        } elseif (Helper::isAssignUsers()) {
            $query->whereIn('user_id', Helper::getAssignUsersIds());
        } else {
            $query->where('user_id', Auth::user()->id);
        }
        return $query;
    }

    public static function getDocumentation(): array
    {
        return [
            'biometric.introduction',
            KnowledgeBase::model()::find('biometric.working'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(12)
                ->schema([
                    Forms\Components\Section::make('Details')
                        ->columnSpan(4)
                        ->schema([
                            TextInput::make('user_id')
                                ->default(fn() => Auth::id())
                                ->hidden()
                                ->required(),

                            Select::make('period')
                                ->label('Period')
                                ->searchable()
                                ->options([
                                    'morning' => 'Morning',
                                    'break_start' => 'Break Start',
                                    'break_end' => 'Break End',
                                    'evening' => 'Evening',
                                ])
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    if ($state) {
                                        $date = Carbon::today();
                                        switch ($state) {
                                            case 'morning':
                                                $date->setTime(9, 0);
                                                break;
                                            case 'break_start':
                                                $date->setTime(12, 30);
                                                break;
                                            case 'break_end':
                                                $date->setTime(13, 30);
                                                break;
                                            case 'evening':
                                                $date->setTime(18, 0);
                                                break;
                                        }
                                        $set('timedate', $date->format('Y-m-d H:i:s'));
                                    }
                                })
                                ->columns(1)
                                ->required()
                                ->disabled(fn($record) => ($record && in_array($record->status, ['approved', 'rejected']))),

                            DateTimePicker::make('timedate')
                                ->label('Date & Time')
                                ->time(true)
                                ->maxDate(fn() => today())
                                ->required()
                                ->disabled(fn($record) => ($record && in_array($record->status, ['approved', 'rejected']))),

                            TextInput::make('reason')
                                ->required()
                                ->datalist([
                                    'Forgot to check in',
                                    'Forgot to check out',
                                    'Technical issue',
                                    'Official business',
                                ])
                                ->placeholder('Select a reason or type your own')
                                ->disabled(
                                    fn($record) => ($record && ($record->status === 'approved' || $record->status === 'rejected'))

                                ),
                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'approved' => 'Approve',
                                    'rejected' => 'Reject',
                                ])
                                ->default(fn() => Gate::allows('biometric.approve') || Auth::user()->hasRole('Admin') ? 'approved' : 'pending')
                                ->reactive()
                                ->hidden(
                                    fn($record) =>
                                    !$record ||
                                    ($record->user_id === Auth::id()) ||
                                    !(Gate::allows('biometric.approve') || Auth::user()->hasRole('Admin'))
                                )
                                ->disabled(
                                    fn($record) => ($record && ($record->status === 'approved' || $record && $record->status === 'rejected'))

                                ),
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->rows(3)
                                ->columnSpanFull()
                                ->visible(fn(callable $get) => $get('status') === 'rejected')
                                ->disabled(
                                    fn($record) => $record && in_array($record->status, ['approved', 'rejected'])
                                ),
                        ]),
                ]),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.id')->label('Employee ID'),
                TextColumn::make('user.name')->searchable()->label('Name'),
                TextColumn::make('timedate')
                    ->label('Time & Date')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Period')
                    ->formatStateUsing(fn($state) => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('reason')->limit(50)->label('Reason'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->description(fn(Biometric $record): ?string => $record->status === 'rejected' ? $record->rejection_reason : null),

            ])
            ->defaultSort('timedate', 'desc')
            ->searchPlaceholder('Search Employee')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => Auth::user()->hasPermissionTo('biometric.createRequest') || Auth::user()->hasRole('Admin')),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => (Auth::user()->hasPermissionTo('biometric.createRequest') || Auth::user()->hasRole('Admin')) && $record->user_id === Auth::id())
                    ->disabled(fn($record) => $record && $record->status === 'approved'),
            ])
            ->actionsColumnLabel('Actions')
            ->filters([]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBiometrics::route('/'),
            'create' => Pages\CreateBiometric::route('/create'),
            'edit' => Pages\EditBiometric::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('biometric.view') || Auth::user()->can('biometric.createRequest')) && Helper::has_feature('attendance');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && Auth::user()->attendance_config === 1 && (
            Auth::user()->hasRole('Admin') ||
            Auth::user()->can('biometric.createRequest')
        );
    }

    public static function canEdit($record): bool
    {
        if (!$record) {
            return false;
        }
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('biometric.createRequest')) && ($record->status !== 'approved' && $record->status !== 'rejected');
    }

    public static function canDelete($record): bool
    {
        if (!$record) {
            return false;
        }
        return $record->status !== 'approved' &&
            (Auth::user()->hasRole('Admin') || Auth::user()->can('biometric.createRequest'));
    }
}
