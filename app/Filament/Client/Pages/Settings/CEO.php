<?php

namespace App\Filament\Client\Pages\Settings;

use App\Facades\Email;
use App\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class CEO extends Page implements HasTable
{
    use InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static string $view = 'filament.client.pages.settings.c-e-o';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'CEO';
    protected static ?string $slug = 'settings/ceo';
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->form([
                    Forms\Components\TagsInput::make('emails')
                        ->label('Emails')
                        ->placeholder('Add emails...')
                        ->required()
                        ->separator(','),
                ])
                ->action(function (array $data) {
                    $emails = is_array($data['emails']) ? $data['emails'] : explode(',', $data['emails']);

                    foreach ($emails as $email) {
                        $email = trim($email);
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }

                        $token = Str::random(60);

                        $role = Filament::getTenant()->roles()->where('name', 'CEO')->first();

                        $invitation = Invitation::updateOrCreate(
                            [
                                'team_id' => Filament::getTenant()->id,
                                'email' => $email,
                            ],
                            [
                                'role' => $role->id,
                                'token' => $token,
                                'expires_at' => now()->addDays(3),
                            ]
                        );

                        $expires = $invitation->expires_at->timestamp;
                        $params = [
                            'expires' => $expires,
                        ];

                        $baseUrl = URL::to('client/auth/invitations/' . $token) . '?' . http_build_query($params);
                        $signature = hash_hmac('sha256', rtrim($baseUrl, '?'), config('app.key'));
                        $inviteUrl = $baseUrl . '&signature=' . $signature;

                        Notification::make()
                            ->title('Invitation Sent')
                            ->body('An invitation has been sent to ' . $email . '.')
                            ->success()
                            ->send();

                        Email::mail('user.invite', $email, [
                            'cta_url' => $inviteUrl,
                        ]);
                    }
                })
                ->label('Add')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->button()
                ->modalWidth('md')
                ->disableCreateAnother(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Filament::getTenant()
                    ->users()
                    ->getQuery()
                    ->whereHas('roles', fn($q) => $q->where('name', 'CEO'))
            )
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                ToggleColumn::make('active')->label('Active')->sortable(),
            ])

            ->actions([
                Action::make('removeAdmin')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $team = Filament::getTenant();
                        $record->admins()->detach($team->id);
                        $team->members()->detach($record);
                    }),
            ]);
    }
}
