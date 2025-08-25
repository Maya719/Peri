<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;
use Guava\FilamentKnowledgeBase\Actions\Forms\Components\HelpAction;

class RoleResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Roles & Permissions';
    protected static ?string $modelLabel = 'Roles & Permissions';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getDocumentation(): array
    {
        return [
            'roles.introduction',
            'roles.working',
            'roles.permissions',
            KnowledgeBase::model()::find('roles.recommended'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(12)
                ->schema([
                    Forms\Components\Section::make('Role Details')
                        ->columnSpan(6)
                        ->schema([
                            Section::make('')->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->hintAction(
                                        HelpAction::forDocumentable('roles.permissions')
                                            ->label('More About Permissions')
                                            ->slideOver(false)
                                    )
                                    ->rules([
                                        'string',
                                        'max:255',
                                        Rule::notIn(['Admin', 'admin']),
                                    ])
                                    ->label('Role Name'),
                            ])->columns(1),

                            ...self::getPermissionSections(),

                            Forms\Components\Section::make('Assign Employees')
                                ->description('Choose if employees should be assigned to this role')
                                ->schema([
                                    Forms\Components\Select::make('assignment')
                                        ->label('')
                                        ->options([
                                            'all'    => 'All Employees',
                                            'select' => 'Select Employee(s)',
                                        ])
                                        ->reactive()
                                        ->afterStateUpdated(function (callable $set, $state) {
                                            $team = Filament::getTenant();
                                            if ($state === 'all') {
                                                $userIds = $team->users()->pluck('users.id')->map(fn($id) => (int) $id)->toArray();
                                                $set('assigned_users', $userIds);
                                            } else {
                                                $set('assigned_users', []);
                                            }
                                        }),

                                    Forms\Components\Hidden::make('assigned_users'),

                                    Forms\Components\MultiSelect::make('manual_assigned_users')
                                        ->label('Selected Employees')
                                        ->options(fn() => Filament::getTenant()
                                            ? Filament::getTenant()->users()->where('active', 1)->pluck('name', 'users.id')
                                            : [])
                                        ->visible(fn(callable $get) => $get('assignment') === 'select')
                                        ->reactive()
                                        ->afterStateHydrated(function (callable $set, $state, $get) {
                                            if ($get('assignment') === 'select') {
                                                $set('manual_assigned_users', $get('assigned_users') ?? []);
                                            }
                                        })
                                        ->afterStateUpdated(function (callable $set, $state) {
                                            $set('assigned_users', $state);
                                        }),
                                ]),
                        ]),
                ]),
        ]);
    }

    protected static function getPermissionSections(): array
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        })->sortKeys();

        return [
            Forms\Components\Section::make('Permissions')
                ->schema(
                    $permissions->map(function ($group, $groupName) {
                        return Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->options(
                                $group->pluck('name', 'id')->mapWithKeys(function ($value, $key) use ($groupName) {
                                    $label = trim(Str::after($value, $groupName . '.'));
                                    return [$key => Str::headline($label)];
                                })
                            )
                            ->columns(5)
                            ->bulkToggleable()
                            ->label(Str::headline($groupName));
                    })->values()->all()
                )
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_default', false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->wrap()
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn($record) => $record->name !== 'Admin'),
                Tables\Actions\DeleteAction::make()->visible(fn($record) => $record->name !== 'Admin')
                    ->after(function () {
                        $tenant = Filament::getTenant();
                        $subscription = $tenant->activePlanSubscriptions()->first();
                        if ($subscription) {
                            $subscription->reduceFeatureUsage('roles');
                        }
                    }),
            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::has_feature('roles');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::is_module_allowed('roles');
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
