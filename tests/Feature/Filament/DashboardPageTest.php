<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\UpcomingAppointmentsWidget;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::create(['name' => 'Test Salon', 'slug' => 'test-salon']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'business_admin',
        ]);

        // Create service and employee for bookings
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Haircut',
            'price_cents' => 3000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $this->employee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Employee',
            'email' => 'employee@test.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Authenticate
        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    public function test_dashboard_page_loads(): void
    {
        Livewire::test(Dashboard::class)
            ->assertStatus(200);
    }

    public function test_dashboard_registers_all_widgets(): void
    {
        $dashboard = new Dashboard;
        $widgets = $dashboard->getWidgets();

        $this->assertContains(StatsOverviewWidget::class, $widgets);
        $this->assertContains(RevenueChartWidget::class, $widgets);
        $this->assertContains(UpcomingAppointmentsWidget::class, $widgets);
        $this->assertContains(QuickActionsWidget::class, $widgets);
    }

    public function test_stats_widget_renders_metrics(): void
    {
        // Create a booking today
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_name' => 'Client',
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Bookings Today')
            ->assertSee('Revenue Today')
            ->assertSee('Active Bookings');
    }

    public function test_stats_widget_displays_single_currency_revenue(): void
    {
        $this->tenant->update(['default_currency' => 'eur']);
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_name' => 'Currency Client',
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_amount_cents' => 5000,
            'payment_currency' => 'eur',
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Revenue Today')
            ->assertSee('€50.00')
            ->assertDontSee('$50.00');
    }

    public function test_stats_widget_displays_grouped_mixed_currency_revenue(): void
    {
        $this->tenant->update(['default_currency' => 'eur']);
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_name' => 'Euro Client',
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_amount_cents' => 5000,
            'payment_currency' => 'eur',
        ]);
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_name' => 'USD Client',
            'date' => now()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_amount_cents' => 7000,
            'payment_currency' => 'usd',
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('€50.00')
            ->assertSee('$70.00')
            ->assertDontSee('120.00');
    }

    public function test_revenue_chart_widget_renders(): void
    {
        Livewire::test(RevenueChartWidget::class)
            ->assertSee('Revenue Trend');
    }

    public function test_upcoming_appointments_widget_renders(): void
    {
        Livewire::test(UpcomingAppointmentsWidget::class)
            ->assertSee('Upcoming Appointments');
    }

    public function test_quick_actions_widget_renders(): void
    {
        Livewire::test(QuickActionsWidget::class)
            ->assertSee('Quick Actions')
            ->assertSee('View Bookings')
            ->assertSee('Manage Services')
            ->assertSee('Employee Schedules');
    }
}
