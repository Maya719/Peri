<?php

namespace App\Filament\Client\Resources;

use App\Facades\Helper;
use App\Filament\Client\Resources\SalaryComponentResource\Pages;
use App\Models\SalaryComponent;
use App\Models\TaxSlabs;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class SalaryComponentResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = SalaryComponent::class;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 7;
    protected static ?string $tenantOwnershipRelationshipName = 'team';
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->count();
    }
    public static function getDocumentation(): array
    {
        return [
            'salary components.introduction',
            KnowledgeBase::model()::find('salary components.working'),
        ];
    }

    public static function form(Form $form): Form
    {

        $currencySymbol = '';
        $companyDetail = Filament::getTenant();;
        if ($companyDetail && $companyDetail->country_id) {
            $taxSlab = TaxSlabs::where('country_id', $companyDetail->country_id)->first();
            if ($taxSlab && !empty($taxSlab->salary_currency)) {
                $currencySymbol = $taxSlab->salary_currency;
            }
        }
        $team_id = Filament::getTenant()->id;
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Group::make()
                                            ->schema([
                                                Forms\Components\Select::make('component_type')
                                                    ->label('Component Type')
                                                    ->options([
                                                        'earning' => 'Earning',
                                                        'deduction' => 'Deduction',
                                                    ])
                                                    ->required()
                                                    ->live(),
                                                Select::make('salary_component_category_id')
                                                    ->label('Category')
                                                    ->relationship('category', 'name', function ($query) use ($team_id) {
                                                        return $query->where(function ($q) use ($team_id) {
                                                            $q->where('team_id', $team_id)
                                                                ->orWhere('team_id', null);
                                                        });
                                                    })
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('name')
                                                            ->label('Category Name')
                                                            ->required()
                                                            ->unique(table: 'salary_component_categories', column: 'name'),
                                                        Forms\Components\Hidden::make('team_id')->default($team_id),
                                                        Forms\Components\Hidden::make('is_default')->default(false),
                                                    ])
                                                    ->preload()
                                                    ->required(),
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Title')
                                                    ->required()
                                                    ->maxLength(40),
                                                Forms\Components\Radio::make('value_type')
                                                    ->label('Calculation Type')
                                                    ->options([
                                                        'number' => 'Fixed Amount',
                                                        'percentage' => 'Percentage of Gross Salary',
                                                    ])
                                                    ->required()
                                                    ->live()
                                                    ->default('number'),
                                                Forms\Components\TextInput::make('amount')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->live()
                                                    ->prefix(fn(Get $get) => $get('value_type') === 'percentage' ? '%' : $currencySymbol),
                                                Forms\Components\Checkbox::make('is_one_time_deduction')
                                                    ->label('Mark as one-time deduction')
                                                    ->default(false)
                                                    ->visible(fn(Get $get) => $get('component_type') === 'deduction')
                                                    ->columnSpan('full'),
                                                Forms\Components\Checkbox::make('tax_status')
                                                    ->label('Mark as taxable')
                                                    ->formatStateUsing(fn(?string $state): bool => $state === 'taxable')
                                                    ->dehydrateStateUsing(fn(bool $state): string => $state ? 'taxable' : 'non-taxable')
                                                    ->default(true),
                                            ])->columnSpan(1),

                                    ])->columnSpanFull(),

                            ])
                            ->columnSpan(2),

                    ]),
                Forms\Components\Hidden::make('team_id')->default($team_id),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Currency code for the money column - Keep the currency symbol for non-percentage amounts
        $currencySymbol = ''; // Default
        $team_id = Filament::getTenant()->id;
        if ($team_id) {
            $companyDetail = Filament::getTenant();
            if ($companyDetail && $companyDetail->country_id) {
                $taxSlab = TaxSlabs::where('country_id', $companyDetail->country_id)->first();
                if ($taxSlab && !empty($taxSlab->salary_currency)) {
                    $currencySymbol = $taxSlab->salary_currency;
                }
            }
        }

        $hasUnfinalizedPayRun = Filament::getTenant()->payRuns()
            ->whereIn('status', ['draft', 'pending_approval', 'rejected'])
            ->exists();

        // Define the message to display when actions are restricted
        $restrictedMessage = 'Cannot modify salary components while a payroll is in process.';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('component_type')
                    ->label('Type')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'earning' => 'success',
                        'deduction' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tax_status')
                    ->label('Tax Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'taxable' => 'warning',
                        'non-taxable' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(function (string $state, SalaryComponent $record) use ($currencySymbol): string {
                        $formattedNumber = number_format((float) $state);

                        if ($record->value_type === 'percentage') {
                            return $formattedNumber . ' %';
                        }

                        return $currencySymbol . ' ' . $formattedNumber;
                    }),
                Tables\Columns\ToggleColumn::make('is_active')->onIcon('heroicon-s-check')
                    ->offIcon('heroicon-s-x-mark')->label('Active'),
            ])
            ->searchPlaceholder('Search Component')
            ->filters([
                Tables\Filters\SelectFilter::make('component_type')
                    ->label('Component Type')
                    ->options([
                        'earning' => 'Earning',
                        'deduction' => 'Deduction',
                    ]),
                Tables\Filters\SelectFilter::make('salary_component_category_id')
                    ->label('Category')
                    ->relationship('category', 'name', function ($query) use ($team_id) {
                        return $query->where(function ($q) use ($team_id) {
                            $q->where('team_id', $team_id)
                                ->orWhere('team_id', null);
                        });
                    })
                    ->options([]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_component')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->action(function (SalaryComponent $record) use ($hasUnfinalizedPayRun, $restrictedMessage) {
                        if ($hasUnfinalizedPayRun) {
                            Notification::make()
                                ->warning()
                                ->title('Action Restricted')
                                ->body($restrictedMessage)
                                ->send();
                            return;
                        }
                        return redirect()->to(SalaryComponentResource::getUrl('edit', ['record' => $record]));
                    }),

                Tables\Actions\Action::make('delete_component')
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SalaryComponent $record) use ($hasUnfinalizedPayRun, $restrictedMessage) {
                        if ($hasUnfinalizedPayRun) {
                            Notification::make()
                                ->warning()
                                ->title('Action Restricted')
                                ->body($restrictedMessage)
                                ->send();
                            return;
                        }
                        $record->delete();
                        Notification::make()
                            ->success()
                            ->title('Salary component deleted successfully.')
                            ->send();
                    }),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn() => $hasUnfinalizedPayRun)
                        ->tooltip(fn() => $hasUnfinalizedPayRun ? $restrictedMessage : null),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryComponents::route('/'),
            'create' => Pages\CreateSalaryComponent::route('/create'),
            'edit' => Pages\EditSalaryComponent::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Payroll Manager') ||
            Auth::user()->can('payroll.manage')) && Helper::has_feature('payroll');
    }

    public static function canCreate(): bool
    {
        return !Filament::getTenant()->payruns()->whereIn('status', ['draft', 'pending_approval', 'rejected'])->exists();
    }
}
