<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function __construct()
    {
        $settings = Setting::getByType('social_login');

        config([
            'services.google.client_id' => $settings["google_client_id"],
            'services.google.client_secret' => $settings["google_client_secret"],
            'services.google.redirect' => 'https://peri.airnet-technologies.com/auth/google/callback',
        ]);
    }
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Google Authentication Error')
                ->body('There was an error authenticating with Google. Please try again.')
                ->danger()
                ->send();
            return redirect('/client/login');
        }

        if (!$googleUser->getEmail()) {
            Notification::make()
                ->title('Google Authentication Error')
                ->body('Google did not return a valid email address. Please try again.')
                ->danger()
                ->send();
            return redirect('/client/login');
        }

        $user = User::where('email', $googleUser->getEmail())->first();
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Google User',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(Str::random(32)),
            ]);
            Auth::login($user, true);
            return redirect('/client');
        }
        $user->update([
            'google_id' => $googleUser->getId(),
        ]);
        Auth::login($user, true);

        return redirect('/client');
    }
}
