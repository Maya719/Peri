<?php

namespace App\Filament\Client\Resources\EmployeeResource\Pages;

use App\Facades\Email;
use App\Facades\Helper;
use App\Filament\Client\Concerns\HasModuleAuthorization;
use App\Filament\Client\Resources\EmployeeResource;
use App\Models\Invitation;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    
    use HasModuleAuthorization;

    protected string $moduleName = 'employees';

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make(
                [
                    Actions\CreateAction::make(),
                    Actions\Action::make('inviteUser')
                        ->label('Invite User')
                        ->icon('heroicon-o-envelope')
                        ->form([
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required(),

                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options(fn() => Filament::getTenant()->roles()->where('is_default', false)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            if (Filament::getTenant()->users()->where('email', $data['email'])->exists()) {
                                return Notification::make()
                                    ->title('This email is already registered.')
                                    ->body($data['email'] . ' is already register.')
                                    ->danger()
                                    ->send();
                            }

                            $token = Str::random(60);

                            $invitation = Invitation::updateOrCreate(
                                [
                                    'team_id' => Filament::getTenant()->id,
                                    'email' => $data['email'],
                                ],
                                [
                                    'role' => $data['role'],
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
                                ->body('An invitation has been sent to ' . $data['email'] . '.')
                                ->success()
                                ->send();
                            Email::mail('user.invite',  $data['email'], [
                                'cta_url' => $inviteUrl
                            ]);
                        })
                        ->modalHeading('Invite User')
                        ->modalButton('Send Invite')
                        ->modalWidth('md'),
                ]
            )   
                ->label('Add')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->button(),
        ];
    }
}
