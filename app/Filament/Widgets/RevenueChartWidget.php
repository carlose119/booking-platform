<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\Currency;
use Filament\Widgets\LineChartWidget;

class RevenueChartWidget extends LineChartWidget
{
    protected ?string $heading = 'Revenue Trend (30 days)';

    protected function getData(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $service = new DashboardMetricsService($tenantId);
        $trend = $service->getRevenueTrend(30);

        $series = $trend['series'] ?? [];
        $datasets = collect($series)
            ->map(fn (array $data, string $currency): array => [
                'label' => 'Revenue ('.strtoupper($currency).')',
                'data' => $data,
            ])
            ->values()
            ->all();

        return [
            'datasets' => $datasets !== [] ? $datasets : [[
                'label' => 'Revenue ('.strtoupper(Currency::default()).')',
                'data' => $trend['data'],
            ]],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
