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
    protected static ?string $navigationLabel = 'Notepad';
    protected static ?string $modelLabel = 'Notepad';
    protected static ?string $title = 'Notepad';
    protected ?string $heading = 'Notepad';
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
                        TextInput::make('task')
                            ->required(),
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
               
            ])
            ->defaultSort('is_completed', 'asc')
            ->actions([
               
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
