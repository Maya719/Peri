<?php

namespace App\Filament\Client\Pages\Auth;

use App\Facades\Email;
use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
use Filament\Notifications\Auth\VerifyEmail;
use Filament\Facades\Filament;


class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    use HasCustomLayout;
    protected static string $view = 'filament.client.pages.auth.email-verification-prompt';
    public function mount(): void
    {
        if ($this->getVerifiable()->hasVerifiedEmail()) {
            redirect()->intended(Filament::getUrl());
        }
        $this->sendEmailVerificationNotification($this->getVerifiable());
    }
    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if (! method_exists($user, 'notify')) {
            $userClass = $user::class;

            throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
        }

        $notification = app(VerifyEmail::class);
        $notification->url = Filament::getVerifyEmailUrl($user);
        Email::mail('email.verify', $user->email, [
            'name' => $user->name,
            'cta_url' => $notification->url
        ]);
    }
}
