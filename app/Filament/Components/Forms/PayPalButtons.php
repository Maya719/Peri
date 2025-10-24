<?php

namespace App\Filament\Components\Forms;

use Filament\Forms\Components\Field;

class PayPalButtons extends Field
{
    protected string $view = 'filament.components.forms.pay-pal-buttons';
    protected string $client;
    protected string $plan;
    protected string $trx;

    public $paypal_subscription_id;

    public function client(string $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function plan(string $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function payTrx(string $trx): static
    {
        $this->trx = $trx;
        return $this;
    }

    public function getClient(): string
    {
        return $this->client;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function getPayTrx(): string
    {
        return $this->trx;
    }
}
