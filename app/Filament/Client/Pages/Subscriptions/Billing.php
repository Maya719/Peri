<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Http\Middleware\VerifyBillableIsSubscribed;
use Filament\Pages\Page;

class Billing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.client.pages.subscriptions.billing';
    protected static string|array $withoutRouteMiddleware = VerifyBillableIsSubscribed::class;
    public ?string $trx = null;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public function mount(?string $trx = null): void
    {
        $this->trx = $trx;
    }
}
