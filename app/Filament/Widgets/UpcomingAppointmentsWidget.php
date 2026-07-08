<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

class UpcomingAppointmentsWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-appointments-widget';

    public $bookings = [];

    public function mount(): void
    {
        $tenantId = auth()->user()->tenant_id;
        $service = new DashboardMetricsService($tenantId);
        $this->bookings = $service->getUpcomingBookings(7);
    }
}