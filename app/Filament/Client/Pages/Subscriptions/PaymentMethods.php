<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Http\Middleware\VerifyBillableIsSubscribed;
use App\Models\PaymentMethod;
use App\Services\Drivers\StripeV3;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PaymentMethods extends Page implements HasActions
{
    use \Filament\Actions\Concerns\InteractsWithActions;
    protected static string $view = 'filament.client.pages.subscriptions.payment-methods';
    protected static string|array $withoutRouteMiddleware = VerifyBillableIsSubscribed::class;
    protected static ?string $navigationGroup = 'Subscription';
    public $customer_id;
    public $payment_methods = [];
    public static function shouldRegisterNavigation(): bool
    {
        /** @var User $user */
        $user = Auth::user();
        return $user->hasRole('Admin');
    }
    public function mount()
    {
        $this->loadPaymentMethods();
    }
    private function loadPaymentMethods(): void
    {
        $this->customer_id = Filament::getTenant()->payment_methods()->first()?->stripe_customer_id;
        if (!$this->customer_id) {
            $this->payment_methods = [];
            return;
        }

        $stripe = new \Stripe\StripeClient(app(StripeV3::class)->secretKey());
        $customer = $stripe->customers->retrieve($this->customer_id);
        $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;
        $methods = $stripe->paymentMethods->all([
            'customer' => $this->customer_id,
            'type' => 'card',
        ]);
        $this->payment_methods = collect($methods->data)->map(function ($method) use ($defaultPaymentMethodId) {
            return [
                'id' => $method->id,
                'brand' => strtoupper($method->card->brand),
                'last4' => $method->card->last4,
                'exp_month' => sprintf('%02d', $method->card->exp_month),
                'exp_year' => $method->card->exp_year,
                'is_default' => $method->id === $defaultPaymentMethodId,
            ];
        })->toArray();
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPaymentMethod')
                ->label('Add Payment Method')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(AddPaymentMethod::getUrl()),
        ];
    }
    public function delete(): Action
    {
        return Action::make('delete')
            ->label(fn() => 'Delete')
            ->requiresConfirmation()
            ->modalHeading('Delete Payment Method')
            ->modalDescription('Are you sure you want to delete this payment method? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->modalCancelActionLabel('Cancel')
            ->action(function (array $arguments) {
                $paymentMethodId = $arguments['payment_method_id'];
                $stripe = new \Stripe\StripeClient(app(StripeV3::class)->secretKey());
                try {
                    StripeV3::detachPaymentMethod($paymentMethodId);
                    $defaultPaymentMethodId = StripeV3::getDefaultPaymentMethodId($this->customer_id);
                    PaymentMethod::where('stripe_payment_method_id', $paymentMethodId)->delete();
                    $methods = StripeV3::getAllPaymentMethods($this->customer_id);
                    $this->payment_methods = collect($methods->data)->map(function ($method) use ($defaultPaymentMethodId) {
                        return [
                            'id' => $method->id,
                            'brand' => strtoupper($method->card->brand),
                            'last4' => $method->card->last4,
                            'exp_month' => sprintf('%02d', $method->card->exp_month),
                            'exp_year' => $method->card->exp_year,
                            'is_default' => $method->id === $defaultPaymentMethodId,
                        ];
                    })->toArray();
                    Notification::make()
                        ->title('Payment method deleted successfully')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to delete payment method')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->color(fn() => 'danger')
            ->icon(fn() => 'heroicon-o-trash')
            ->tooltip(fn() => 'Delete Payment Method');
    }
    public function setDefault($payment_method_id)
    {
        StripeV3::setDefaultPaymentMethod($this->customer_id, $payment_method_id);
        $this->loadPaymentMethods();
        Notification::make()
            ->title('Payment method changed successfully')
            ->success()
            ->send();
    }
}
