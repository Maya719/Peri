<?php

namespace App\Filament\Pages;

use App\Facades\Email;
use App\Models\Setting;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

class SmtpConfig extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel = 'SMTP Settings';
    protected static string $view = 'filament.pages.smtp-config';

    public $smtp = [];
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public function mount(): void
    {
        $this->smtp = Setting::getByType('smtp_config');
    }
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('SMTP Settings')->schema([
                Forms\Components\TextInput::make('smtp.host')->label('SMTP Host')->required(),
                Forms\Components\TextInput::make('smtp.port')->label('SMTP Port')->required(),
                Forms\Components\TextInput::make('smtp.username')->label('SMTP Username')->required(),
                Forms\Components\TextInput::make('smtp.password')->label('SMTP Password')->password()->required(),
                Forms\Components\Select::make('smtp.encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                        'null' => 'None'
                    ])->required(),
                Forms\Components\TextInput::make('smtp.from_address')->label('From Email Address')->required(),
                Forms\Components\TextInput::make('smtp.from_name')->label('From Name')->required(),
            ])->columns(2),
        ];
    }

    public function save(): void
    {
        Setting::setByType('smtp_config', $this->smtp);
        Notification::make()
            ->title('SMTP settings updated successfully.')
            ->success()
            ->send();
    }
    protected function getActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Test Email')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('testEmail')
                        ->label('Test Email Address')
                        ->email()
                        ->required()
                        ->placeholder('Enter email address to test')
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail($data['testEmail']);
                })
        ];
    }

    public function sendTestEmail(string $email): void
    {
        Email::mail('test_email', $email, [
            'user_name' => $email,
            'company_name' => 'Peri'
        ]);
        Notification::make()
            ->title('Test email sent successfully!')
            ->body("Test email has been sent to {$email}")
            ->success()
            ->send();
    }
}
