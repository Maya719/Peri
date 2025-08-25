<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\NoticeResource\Pages;
use App\Filament\Client\Resources\NoticeResource\RelationManagers;
use App\Models\Notice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\{TextInput, RichEditor, Select, Checkbox, ColorPicker, Toggle, Fieldset, Grid, Hidden};
use Filament\Forms\Get;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Auth;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

class NoticeResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Notice::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Notice Board';
    protected static ?string $navigationBadgeTooltip = 'Active Notices';
    protected static ?int $navigationSort = 3;
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function getActiveNavigationIcon(): string|Htmlable|null
    {
        return str(self::getNavigationIcon())->replace('heroicon-o', 'heroicon-s')->toString();
    }

    public static function getDocumentation(): array
    {
        return [
            'notice board.introduction',
            KnowledgeBase::model()::find('notice board.working'),
        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\Card::make()
                        ->columnSpan(1)
                        ->schema([
                            Hidden::make('id')
                                ->default(fn() => uniqid()),
                            TextInput::make('name')
                                ->label('Notice Title')
                                ->required()
                                ->maxLength(255),
                            Fieldset::make('Content Type')
                                ->schema([
                                    Forms\Components\Group::make([
                                        Select::make('content.type')
                                            ->label('')
                                            ->options([
                                                'file' => 'Attach File',
                                                'link' => 'Attach URL',
                                                'text' => 'Add Text',
                                            ])
                                            ->reactive()
                                            ->native(false),

                                        RichEditor::make('content.body')
                                            ->label('Content Text')
                                            ->required()
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'strike',
                                                'underline',
                                                'undo',
                                            ])
                                            ->columnSpanFull()
                                            ->visible(fn(Get $get) => $get('content.type') === 'text'),

                                        FileUpload::make('content.document')
                                            ->label('Upload File')
                                            ->hint('Accepted File Types: PDF, Images')
                                            ->directory('uploads/notices')
                                            ->preserveFilenames()
                                            ->imagePreviewHeight('250')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->removeUploadedFileButtonPosition('right')
                                            ->visibility('public')
                                            ->required()
                                            ->visible(fn(Get $get) => $get('content.type') === 'file'),

                                        TextInput::make('content.link_url')
                                            ->label('Link URL')
                                            ->url()
                                            ->required()
                                            ->columnSpanFull()
                                            ->suffixIcon('heroicon-m-link')
                                            ->visible(fn(Get $get) => $get('content.type') === 'link'),
                                    ])->columnSpanFull(),
                                ]),

                            // Colors
                            Grid::make(3)->schema([
                                ColorPicker::make('content.BackgroundColor')
                                    ->label('Background Color')
                                    ->default('#D97706'),

                                ColorPicker::make('content.IconColor')
                                    ->label('Icon Color')
                                    ->default('#FFFFFF'),

                                ColorPicker::make('content.TextColor')
                                    ->label('Text Color')
                                    ->default('#FFFFFF'),
                            ]),

                            // Options
                            Fieldset::make('Options')->schema([
                                Checkbox::make('content.can_be_closed_by_user')
                                    ->label('Can Be Closed By User')
                                    ->columnSpanFull(),
                            ]),

                            // Status toggle
                            Toggle::make('is_active')
                                ->label('Is Active'),
                        ]),
                ])
        ]);
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('name', '!=', 'Subscription Expiring Soon');
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name')
                ->label('Title'),

            TextEntry::make('content.link_url')
                ->label('URL')
                ->visible(fn($record) => !empty($record->content['link_url'] ?? null)),

            TextEntry::make('content.body')
                ->label('Content')
                ->html()
                ->getStateUsing(fn($record) => $record->content['body'] ?? null)
                ->visible(fn($record) => !empty($record->content['body'] ?? null)),

            TextEntry::make('content.document')
                ->label('Document')
                ->url(fn($record) => !empty($record->content['document'])
                    ? Storage::url($record->content['document'])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn($record) => !empty($record->content['document'] ?? null)),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Title'),
                Tables\Columns\TextColumn::make('content.display')
                    ->label('Content')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $content = $record->content ?? [];

                        if (!empty($content['body'])) {
                            return $content['body']; // Show as HTML
                        }

                        if (!empty($content['link_url'])) {
                            return '<span class="text-gray-700">URL Attached</span>';
                        }

                        if (!empty($content['document'])) {
                            return '<span class="text-gray-700">File Uploaded</span>';
                        }

                        return '<span class="text-gray-400 italic">No content provided.</span>';
                    })
                    ->limit(50),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created At'),
            ])
            ->searchPlaceholder('Search Notice Title')
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (Notice $record) {
                        $filePath = $record->content['document'] ?? null;
                        if ($filePath) {
                            Storage::disk('public')->delete($filePath);
                        }
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotices::route('/'),
            'create' => Pages\CreateNotice::route('/create'),
            'view' => Pages\ViewNotice::route('/{record}'),
            'edit' => Pages\EditNotice::route('/{record}/edit'),
        ];
    }

    // Authorization Methods
    public static function canViewAny(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('noticeBoard.view') || Auth::user()->can('noticeBoard.manage'));
    }

    public static function canCreate(): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('noticeBoard.manage'));
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('noticeBoard.manage'));
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && (Auth::user()->hasRole('Admin') || Auth::user()->can('noticeBoard.manage'));
    }
}
