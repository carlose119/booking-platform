<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_today_metrics_returns_correct_counts_and_revenue(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 3000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Create today's bookings
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client 1',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client 2',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        // Create a booking with different payment status (should not be counted in revenue)
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client 3',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '12:00',
            'end_time' => '12:30',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        $serviceInstance = new DashboardMetricsService($tenant->id);
        $metrics = $serviceInstance->getTodayMetrics();

        $this->assertEquals(3, $metrics['bookings_today']);
        // Revenue: two paid bookings each 3000 cents = 6000 cents
        $this->assertEquals(6000, $metrics['revenue_today_cents']);
        $this->assertEquals(['usd' => 6000], $metrics['revenue_today_by_currency']);
        // Active bookings: confirmed or pending (3 bookings)
        $this->assertEquals(3, $metrics['active_bookings']);
    }

    public function test_get_today_metrics_returns_zeros_when_no_bookings(): void
    {
        $tenant = Tenant::create(['name' => 'Empty Tenant', 'slug' => 'empty-tenant']);
        $serviceInstance = new DashboardMetricsService($tenant->id);
        $metrics = $serviceInstance->getTodayMetrics();

        $this->assertEquals(0, $metrics['bookings_today']);
        $this->assertEquals(0, $metrics['revenue_today_cents']);
        $this->assertEquals([], $metrics['revenue_today_by_currency']);
        $this->assertEquals(0, $metrics['active_bookings']);
    }

    public function test_get_revenue_trend_returns_labels_and_data(): void
    {
        $tenant = Tenant::create(['name' => 'Trend Tenant', 'slug' => 'trend-tenant']);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service',
            'price_cents' => 1000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Create a paid booking yesterday
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client',
            'date' => Carbon::yesterday()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $serviceInstance = new DashboardMetricsService($tenant->id);
        $trend = $serviceInstance->getRevenueTrend(7);

        $this->assertArrayHasKey('labels', $trend);
        $this->assertArrayHasKey('data', $trend);
        $this->assertArrayHasKey('series', $trend);
        $this->assertCount(7, $trend['labels']);
        $this->assertCount(7, $trend['data']);
        $this->assertCount(7, $trend['series']['usd']);
        // Yesterday's data should be 1000 cents
        $yesterdayLabel = Carbon::yesterday()->format('Y-m-d');
        $yesterdayIndex = array_search($yesterdayLabel, $trend['labels']);
        $this->assertNotFalse($yesterdayIndex, 'Yesterday should be in labels array');
        $this->assertEquals(1000, $trend['data'][$yesterdayIndex]);
        $this->assertEquals(1000, $trend['series']['usd'][$yesterdayIndex]);
    }

    public function test_get_upcoming_bookings_returns_future_bookings_ordered(): void
    {
        $tenant = Tenant::create(['name' => 'Upcoming Tenant', 'slug' => 'upcoming-tenant']);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service',
            'price_cents' => 1000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Create bookings for tomorrow and day after tomorrow
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client Tomorrow',
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'status' => 'confirmed',
        ]);
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client Day After',
            'date' => Carbon::tomorrow()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'pending',
        ]);

        $serviceInstance = new DashboardMetricsService($tenant->id);
        $bookings = $serviceInstance->getUpcomingBookings(7);

        $this->assertCount(2, $bookings);
        // Should be ordered by date ASC, then start_time ASC
        $this->assertEquals('Client Tomorrow', $bookings->first()->client_name);
        $this->assertEquals('Client Day After', $bookings->last()->client_name);
        // Should eager-load service
        $this->assertNotNull($bookings->first()->service);
    }

    public function test_get_revenue_trend_returns_zeros_when_no_paid_bookings(): void
    {
        $tenant = Tenant::create(['name' => 'No Revenue Tenant', 'slug' => 'no-revenue-tenant']);
        $serviceInstance = new DashboardMetricsService($tenant->id);
        $trend = $serviceInstance->getRevenueTrend(7);

        $this->assertCount(7, $trend['labels']);
        $this->assertCount(7, $trend['data']);
        $this->assertSame([], $trend['series']);
        // All values should be 0
        foreach ($trend['data'] as $value) {
            $this->assertEquals(0, $value);
        }
    }

    public function test_get_upcoming_bookings_returns_empty_when_no_bookings(): void
    {
        $tenant = Tenant::create(['name' => 'No Upcoming Tenant', 'slug' => 'no-upcoming-tenant']);
        $serviceInstance = new DashboardMetricsService($tenant->id);
        $bookings = $serviceInstance->getUpcomingBookings(7);

        $this->assertCount(0, $bookings);
    }

    public function test_tenant_isolation_metrics_only_return_current_tenant_data(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $serviceA = Service::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Service A',
            'price_cents' => 5000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $serviceB = Service::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Service B',
            'price_cents' => 8000,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $employeeA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Employee A',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $employeeB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Employee B',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        // Create bookings for both tenants today
        Booking::create([
            'tenant_id' => $tenantA->id,
            'service_id' => $serviceA->id,
            'employee_id' => $employeeA->id,
            'client_name' => 'Client A1',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        Booking::create([
            'tenant_id' => $tenantA->id,
            'service_id' => $serviceA->id,
            'employee_id' => $employeeA->id,
            'client_name' => 'Client A2',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        Booking::create([
            'tenant_id' => $tenantB->id,
            'service_id' => $serviceB->id,
            'employee_id' => $employeeB->id,
            'client_name' => 'Client B1',
            'date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Tenant A metrics
        $serviceAInstance = new DashboardMetricsService($tenantA->id);
        $metricsA = $serviceAInstance->getTodayMetrics();

        // Tenant B metrics
        $serviceBInstance = new DashboardMetricsService($tenantB->id);
        $metricsB = $serviceBInstance->getTodayMetrics();

        // Tenant A should see 2 bookings, 5000 cents revenue, 2 active
        $this->assertEquals(2, $metricsA['bookings_today']);
        $this->assertEquals(5000, $metricsA['revenue_today_cents']);
        $this->assertEquals(['usd' => 5000], $metricsA['revenue_today_by_currency']);
        $this->assertEquals(2, $metricsA['active_bookings']);

        // Tenant B should see 1 booking, 8000 cents revenue, 1 active
        $this->assertEquals(1, $metricsB['bookings_today']);
        $this->assertEquals(8000, $metricsB['revenue_today_cents']);
        $this->assertEquals(['usd' => 8000], $metricsB['revenue_today_by_currency']);
        $this->assertEquals(1, $metricsB['active_bookings']);
    }

    public function test_today_metrics_group_revenue_by_snapshot_currency_without_mixed_total(): void
    {
        $tenant = Tenant::create(['name' => 'Mixed Tenant', 'slug' => 'mixed-tenant', 'default_currency' => 'eur']);
        $service = $this->createService($tenant, 3000);
        $employee = $this->createEmployee($tenant);

        $this->createPaidBooking($tenant, $service, $employee, Carbon::today()->toDateString(), [
            'payment_amount_cents' => 5000,
            'payment_currency' => 'eur',
        ]);
        $this->createPaidBooking($tenant, $service, $employee, Carbon::today()->toDateString(), [
            'payment_amount_cents' => 7000,
            'payment_currency' => 'usd',
        ]);

        $metrics = (new DashboardMetricsService($tenant->id))->getTodayMetrics();

        $this->assertSame(['eur' => 5000, 'usd' => 7000], $metrics['revenue_today_by_currency']);
        $this->assertNull($metrics['revenue_today_cents']);
    }

    public function test_today_metrics_uses_legacy_tenant_currency_fallback_when_snapshot_missing(): void
    {
        $tenant = Tenant::create(['name' => 'Euro Tenant', 'slug' => 'euro-tenant', 'default_currency' => 'eur']);
        $service = $this->createService($tenant, 3000);
        $employee = $this->createEmployee($tenant);

        $this->createPaidBooking($tenant, $service, $employee, Carbon::today()->toDateString());

        $metrics = (new DashboardMetricsService($tenant->id))->getTodayMetrics();

        $this->assertSame(['eur' => 3000], $metrics['revenue_today_by_currency']);
        $this->assertSame(3000, $metrics['revenue_today_cents']);
    }

    public function test_revenue_trend_returns_currency_keyed_series_without_conversion(): void
    {
        $tenant = Tenant::create(['name' => 'Trend Tenant', 'slug' => 'currency-trend', 'default_currency' => 'eur']);
        $service = $this->createService($tenant, 3000);
        $employee = $this->createEmployee($tenant);

        $this->createPaidBooking($tenant, $service, $employee, Carbon::yesterday()->toDateString(), [
            'payment_amount_cents' => 5000,
            'payment_currency' => 'eur',
        ]);
        $this->createPaidBooking($tenant, $service, $employee, Carbon::yesterday()->toDateString(), [
            'payment_amount_cents' => 7000,
            'payment_currency' => 'usd',
        ]);

        $trend = (new DashboardMetricsService($tenant->id))->getRevenueTrend(7);
        $yesterdayIndex = array_search(Carbon::yesterday()->format('Y-m-d'), $trend['labels']);

        $this->assertNotFalse($yesterdayIndex, 'Yesterday should be in labels array');
        $this->assertSame(5000, $trend['series']['eur'][$yesterdayIndex]);
        $this->assertSame(7000, $trend['series']['usd'][$yesterdayIndex]);
        $this->assertNull($trend['data']);
    }

    private function createService(Tenant $tenant, int $priceCents): Service
    {
        return Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service '.$priceCents,
            'price_cents' => $priceCents,
            'duration_minutes' => 30,
            'active' => true,
        ]);
    }

    private function createEmployee(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
    }

    private function createPaidBooking(Tenant $tenant, Service $service, User $employee, string $date, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client '.fake()->unique()->numberBetween(1, 9999),
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ], $overrides));
    }
}
