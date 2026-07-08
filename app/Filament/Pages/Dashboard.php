<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\UpcomingAppointmentsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            RevenueChartWidget::class,
            UpcomingAppointmentsWidget::class,
            QuickActionsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'xl' => 2,
            'lg' => 1,
        ];
    }
}