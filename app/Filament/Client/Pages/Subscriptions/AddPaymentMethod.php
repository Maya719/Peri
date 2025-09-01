<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Http\Middleware\VerifyBillableIsSubscribed;
use Filament\Pages\Page;

class AddPaymentMethod extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string|array $withoutRouteMiddleware = VerifyBillableIsSubscribed::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static string $view = 'filament.client.pages.subscriptions.add-payment-method';
}
