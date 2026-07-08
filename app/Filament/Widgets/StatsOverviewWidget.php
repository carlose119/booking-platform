<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\Currency;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $service = new DashboardMetricsService($tenantId);
        $metrics = $service->getTodayMetrics();

        return [
            Stat::make('Bookings Today', $metrics['bookings_today']),
            Stat::make('Revenue Today', $this->formatRevenue($metrics['revenue_today_by_currency'] ?? [])),
            Stat::make('Active Bookings', $metrics['active_bookings']),
        ];
    }

    private function formatRevenue(array $revenueByCurrency): string
    {
        if ($revenueByCurrency === []) {
            return Currency::format(0, Currency::default());
        }

        return collect($revenueByCurrency)
            ->map(fn (int $amount, string $currency): string => Currency::format($amount, $currency))
            ->implode(' / ');
    }
}
