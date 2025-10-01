<?php

namespace App\Filament\Client\Pages\Subscriptions;

use App\Models\Payment;
use Carbon\Carbon;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class PaymentHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Subscription';
    protected static ?string $title = 'Payment History';
    protected static string $view = 'filament.client.pages.subscriptions.payment-history';
    public static function shouldRegisterNavigation(): bool
    {
        return (Auth::check() && Auth::user()->hasRole('Admin'));
    }
    public function table(Table $table): Table
    {
        return $table
            ->query(Filament::getTenant()->payments()->getQuery())
            ->columns([
                TextColumn::make('trx')
                    ->label('Transaction ID')
                    ->searchable(),
                TextColumn::make('method_name')
                    ->label('Method Name')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function (Payment $record) {
                        return Number::currency($record->amount, in: $record->method_currency) . " + " . Number::currency($record->charge, in: $record->method_currency) . '<br>' . Number::currency(($record->amount + $record->charge), in: $record->method_currency);
                    })->html(),

                TextColumn::make('rate')
                    ->label('Conversion')
                    ->formatStateUsing(function (Payment $record) {
                        return Number::currency(1, in: 'USD') . " = " . Number::currency($record->rate, in: $record->method_currency) . '<br>' . Number::currency($record->final_amount, in: 'USD');
                    })->html(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn($record) => match ($record->status) {
                        0 => 'Processing',
                        1 => 'Completed',
                        2 => 'Cancelled',
                        default => 'Initiated',
                    })
                    ->icon(fn($record) => match ($record->status) {
                        0 => 'heroicon-o-clock',
                        1 => 'heroicon-s-check-circle',
                        2 => 'heroicon-s-x-circle',
                        default => 'heroicon-s-x-circle',
                    })
                    ->color(fn($record) => match ($record->status) {
                        0 => 'info',
                        1 => 'success',
                        2 => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y h:iA')
                    ->description(fn($record): string => Carbon::parse($record->created_at)->diffForHumans()),
            ])
            ->searchPlaceholder('Search Transaction ID')
            ->defaultSort('created_at', 'desc');
    }
}
