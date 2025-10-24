<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\Payment;
use App\Models\Subscription;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget as BaseWidget;
use EightyNine\FilamentAdvancedWidget\AdvancedStatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'md' => 12,
    ];

    public static function canView(): bool
    {
        return true;
    }
    
    protected function getStats(): array
    {
        $earnings = Payment::where('status', '1')
            ->sum('final_amount');
        $order = Subscription::count();
        $message = Contact::count();
        $activeCount = Subscription::where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere('ends_at', '>', now());
        })->count();

        return [
            Stat::make('', '$' . number_format($earnings, 2))
                ->description("Earnings")
                ->backgroundColor('success')
                ->extraAttributes(['class' => 'stat-total']),
            Stat::make('', $order)
                ->description("Orders")
                ->backgroundColor('info')
                ->extraAttributes(['class' => 'stat-total']),
            Stat::make('', $activeCount)
                ->description("Active Subscribers")
                ->backgroundColor('warning')
                ->extraAttributes(['class' => 'stat-late']),
            Stat::make('', $message)
                ->description("Messages")
                ->backgroundColor('danger')
                ->extraAttributes(['class' => 'stat-absent']),
        ];
    }
}
