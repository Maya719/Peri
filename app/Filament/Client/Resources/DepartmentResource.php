<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class DepartmentResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Department::class;

    protected static ?string $tenantOwnershipRelationshipName = 'team';
    protected static ?string $navigationLabel = 'Departments';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function form(Form $form): Form
    {
        $user = Auth::user();
        return $form->schema([
            Card::make()->schema([
                TextInput::make('name')
                    ->label('Department Name')
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255)
            ]),
        ]);
    }

    public static function getDocumentation(): array
    {
        return [
            KnowledgeBase::model()::find('departments.introduction'),
        ];
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])
            ->searchPlaceholder('Search Department')
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $tenant = Filament::getTenant();
                        $subscription = $tenant->activePlanSubscriptions()->first();
                        if ($subscription) {
                            $subscription->reduceFeatureUsage('departments');
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
    
    // Permissions for CRUD operations
    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::has_feature('departments');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin')) && Helper::is_module_allowed('departments');
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
