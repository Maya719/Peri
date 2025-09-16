<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Subscription';
    protected static ?string $title = 'Payment History';
    protected static string $view = 'filament.client.pages.subscriptions.payment-history';

    public function table(Table $table): Table
    {
        return $table
            ->query(Filament::getTenant()->payments()->getQuery())
            ->columns([
                TextColumn::make('trx')
                    ->label('Transaction ID')
                    ->searchable(),
                TextColumn::make('method_name')
                    ->label('Payment Method')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('usd', true)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
