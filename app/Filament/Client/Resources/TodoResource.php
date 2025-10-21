<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\TodoResource\Pages;
use App\Models\Todo;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Card;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

class TodoResource extends Resource
{
    protected static ?string $model = Todo::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Notpad';

    protected static ?string $modelLabel = 'To-Do List';

    protected static ?string $navigationBadgeTooltip = 'Pending Tasks';

    protected static ?int $navigationSort = 4;

    public static function getActiveNavigationIcon(): string|Htmlable|null
    {
        return str(self::getNavigationIcon())->replace('heroicon-o', 'heroicon-s')->toString();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('is_completed', 0)
            ->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(3)->schema([
                Card::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('task')
                                ->required(),
                            DatePicker::make('deadline')
                                ->native(false)
                                ->prefixIcon('heroicon-m-calendar')
                                ->required(),
                        ]),
                        Textarea::make('description')
                            ->autosize(),
                    ])
                    ->columnSpan(2),
            ]),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Split::make([
                    TextColumn::make('task'),
                    TextColumn::make('deadline')
                        ->date(),
                    TextColumn::make('is_completed')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn(string $state): string => $state ? 'Completed' : 'Pending')
                        ->color(fn(string $state): string => $state ? 'success' : 'warning'),
                ]),
                Panel::make([
                    Stack::make([
                        TextColumn::make('description')
                            ->html()
                            ->wrap()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                            ->formatStateUsing(fn(string $state): string => nl2br($state)),
                    ]),
                ])->collapsible(),
            ])
            ->defaultSort('is_completed', 'asc')
            ->actions([
                ActionGroup::make([
                    Action::make('Complete')
                        ->action(fn(Todo $record) => $record->update(['is_completed' => true]))
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn(Todo $record) => ! $record->is_completed),
                    Tables\Actions\EditAction::make()->color('info'),
                    Tables\Actions\DeleteAction::make(),
                ])->iconButton()
            ])
            ->actionsColumnLabel('Actions');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTodos::route('/'),
            'create' => Pages\CreateTodo::route('/create'),
            'edit' => Pages\EditTodo::route('/{record}/edit'),
        ];
    }
}
