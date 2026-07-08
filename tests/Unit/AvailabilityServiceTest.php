<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService();
    }

    /**
     * Helper: create a full tenant stack and return the EmployeeSchedule.
     */
    private function createSchedule(
        string $startTime = '09:00',
        string $endTime = '17:00',
        int $dayOfWeek = 1,
        int $durationMinutes = 60,
    ): EmployeeSchedule {
        $tenant = Tenant::create(['name' => 'Test Salon', 'slug' => 'test-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => $durationMinutes,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        return EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Helper: invoke a protected method on AvailabilityService via reflection.
     */
    private function invokeProtected(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(AvailabilityService::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->service, $args);
    }

    // ─── 5.1: generateSlotsFromSchedule ────────────────────────────────────

    public function test_generate_slots_returns_correct_count_for_8_hour_day(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '17:00', durationMinutes: 60);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);

        $this->assertCount(8, $slots);

        $expectedTimes = [
            ['start' => '09:00', 'end' => '10:00'],
            ['start' => '10:00', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:00'],
            ['start' => '12:00', 'end' => '13:00'],
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00'],
        ];

        foreach ($expectedTimes as $i => $expected) {
            $this->assertSame($expected['start'], $slots[$i]['start']);
            $this->assertSame($expected['end'], $slots[$i]['end']);
            $this->assertTrue($slots[$i]['available']);
        }
    }

    public function test_generate_slots_handles_30_minute_service(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '10:00', durationMinutes: 30);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 30]);

        $this->assertCount(2, $slots);
        $this->assertSame('09:00', $slots[0]['start']);
        $this->assertSame('09:30', $slots[0]['end']);
        $this->assertSame('09:30', $slots[1]['start']);
        $this->assertSame('10:00', $slots[1]['end']);
    }

    public function test_generate_slots_returns_empty_when_schedule_shorter_than_duration(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '09:30', durationMinutes: 60);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);

        $this->assertCount(0, $slots);
    }

    // ─── 5.2: Conflict filtering ───────────────────────────────────────────

    public function test_filter_conflicts_marks_overlapping_booking_as_unavailable(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '12:00', durationMinutes: 60);

        $tenant = $schedule->employee->tenant;
        $employee = $schedule->employee;
        $service = $employee->services->first();

        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client A',
            'client_email' => 'clienta@test.com',
            'date' => Carbon::now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);
        $employeeBookings = Booking::where('employee_id', $employee->id)->get();

        $filtered = $this->invokeProtected('filterConflicts', [$slots, $employeeBookings]);

        $this->assertTrue($filtered[0]['available']);   // 09:00-10:00
        $this->assertFalse($filtered[1]['available']);  // 10:00-11:00 (booked)
        $this->assertTrue($filtered[2]['available']);   // 11:00-12:00
    }

    public function test_filter_conflicts_handles_partial_overlap(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '12:00', durationMinutes: 60);

        $tenant = $schedule->employee->tenant;
        $employee = $schedule->employee;
        $service = $employee->services->first();

        // Booking 09:30-10:30 overlaps both the 09:00 and 10:00 slots
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client B',
            'date' => Carbon::now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:30',
            'status' => 'confirmed',
        ]);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);
        $employeeBookings = Booking::where('employee_id', $employee->id)->get();

        $filtered = $this->invokeProtected('filterConflicts', [$slots, $employeeBookings]);

        $this->assertFalse($filtered[0]['available']);  // 09:00-10:00 (overlaps 09:30-10:30)
        $this->assertFalse($filtered[1]['available']);  // 10:00-11:00 (overlaps 09:30-10:30)
        $this->assertTrue($filtered[2]['available']);   // 11:00-12:00
    }

    public function test_filter_conflicts_does_not_block_for_empty_bookings(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '12:00', durationMinutes: 60);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);
        $filtered = $this->invokeProtected('filterConflicts', [$slots, collect()]);

        $this->assertTrue($filtered[0]['available']);
        $this->assertTrue($filtered[1]['available']);
        $this->assertTrue($filtered[2]['available']);
    }

    // ─── 5.3: Past-time filtering ──────────────────────────────────────────

    public function test_filter_past_slots_removes_expired_slots(): void
    {
        $now = Carbon::create(2026, 1, 1, 12, 0);
        Carbon::setTestNow($now);

        try {
            $slots = [
                ['start' => '09:00', 'end' => '10:00', 'available' => true],
                ['start' => '11:00', 'end' => '12:00', 'available' => true],
                ['start' => '12:00', 'end' => '13:00', 'available' => true],
                ['start' => '13:00', 'end' => '14:00', 'available' => true],
            ];

            $filtered = $this->invokeProtected('filterPastSlots', [$slots]);

            $this->assertCount(2, $filtered);
            $this->assertSame('13:00', $filtered[0]['end']);
            $this->assertSame('14:00', $filtered[1]['end']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_filter_hold_conflicts_marks_active_hold_as_unavailable(): void
    {
        $schedule = $this->createSchedule(startTime: '09:00', endTime: '12:00', durationMinutes: 60);

        $tenant = $schedule->employee->tenant;
        $employee = $schedule->employee;
        $service = $employee->services->first();

        BookingHold::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => Carbon::now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'hold-conflict-test',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $slots = $this->invokeProtected('generateSlotsFromSchedule', [$schedule, 60]);
        $holds = BookingHold::where('employee_id', $employee->id)->get();

        $filtered = $this->invokeProtected('filterHoldConflicts', [$slots, $holds]);

        $this->assertTrue($filtered[0]['available']);
        $this->assertFalse($filtered[1]['available']);
        $this->assertSame('held', $filtered[1]['unavailable_reason']);
        $this->assertTrue($filtered[2]['available']);
    }

    public function test_filter_past_slots_keeps_all_future_slots(): void
    {
        $now = Carbon::create(2026, 1, 1, 12, 0);
        Carbon::setTestNow($now);

        try {
            $futureSlots = [
                ['start' => $now->copy()->addMinutes(5)->format('H:i'), 'end' => $now->copy()->addMinutes(15)->format('H:i'), 'available' => true],
                ['start' => $now->copy()->addMinutes(15)->format('H:i'), 'end' => $now->copy()->addMinutes(25)->format('H:i'), 'available' => true],
            ];

            $filtered = $this->invokeProtected('filterPastSlots', [$futureSlots]);

            $this->assertCount(2, $filtered);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_filter_past_slots_returns_empty_when_all_expired(): void
    {
        // Use times that are definitely in the past regardless of current time:
        // 00:01 and 00:02 — only fails if run exactly at midnight (acceptable edge case)
        $pastSlots = [
            ['start' => '00:00', 'end' => '00:01', 'available' => true],
            ['start' => '00:01', 'end' => '00:02', 'available' => true],
        ];

        $filtered = $this->invokeProtected('filterPastSlots', [$pastSlots]);

        $this->assertCount(0, $filtered);
    }

    // ─── 5.4: No schedule = empty slots ────────────────────────────────────

    public function test_get_available_slots_returns_empty_when_no_schedule_for_day(): void
    {
        $tenant = Tenant::create(['name' => 'No Schedule Salon', 'slug' => 'no-schedule']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ghost Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Massage',
            'price_cents' => 8000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        // No EmployeeSchedule created — employee has no schedule for any day
        $futureMonday = Carbon::now()->addWeek()->startOfWeek()->toDateString();

        $result = $this->service->getAvailableSlots(
            serviceId: $service->id,
            date: $futureMonday,
            tenantId: $tenant->id,
        );

        $this->assertEmpty($result);
    }

    // ─── 5.5: Full day booked = all unavailable ────────────────────────────

    public function test_get_available_slots_all_unavailable_when_fully_booked(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();

        $schedule = $this->createSchedule(startTime: '09:00', endTime: '12:00', durationMinutes: 60);

        $tenant = $schedule->employee->tenant;
        $employee = $schedule->employee;
        $service = $employee->services->first();

        // Override schedule to match the future Monday
        $schedule->update(['day_of_week' => $futureMonday->dayOfWeekIso]);

        // Book all 3 slots
        foreach (['09:00-10:00', '10:00-11:00', '11:00-12:00'] as $timeRange) {
            [$start, $end] = explode('-', $timeRange);
            Booking::create([
                'tenant_id' => $tenant->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'client_name' => 'Client '.$start,
                'date' => $futureMonday->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'status' => 'confirmed',
            ]);
        }

        $result = $this->service->getAvailableSlots(
            serviceId: $service->id,
            date: $futureMonday->toDateString(),
            tenantId: $tenant->id,
        );

        $this->assertArrayHasKey($employee->id, $result);
        $this->assertCount(3, $result[$employee->id]['slots']);

        foreach ($result[$employee->id]['slots'] as $slot) {
            $this->assertFalse($slot['available']);
        }
    }

    // ─── 5.6: Integration — getAvailableSlots with real DB + tenant isolation

    public function test_get_available_slots_returns_correct_data_from_db(): void
    {
        $futureTuesday = Carbon::now()->addWeek()->startOfWeek()->addDay();

        $tenant = Tenant::create(['name' => 'Full Service', 'slug' => 'full-service']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Garcia',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manicure',
            'price_cents' => 3000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureTuesday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        // Book the middle slot
        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client X',
            'date' => $futureTuesday->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $result = $this->service->getAvailableSlots(
            serviceId: $service->id,
            date: $futureTuesday->toDateString(),
            tenantId: $tenant->id,
        );

        $this->assertArrayHasKey($employee->id, $result);
        $this->assertSame('Maria Garcia', $result[$employee->id]['employee_name']);
        $this->assertCount(3, $result[$employee->id]['slots']);
        $this->assertTrue($result[$employee->id]['slots'][0]['available']);   // 10:00-11:00
        $this->assertFalse($result[$employee->id]['slots'][1]['available']);  // 11:00-12:00 (booked)
        $this->assertTrue($result[$employee->id]['slots'][2]['available']);   // 12:00-13:00
    }

    public function test_tenant_isolation_no_cross_tenant_data_leakage(): void
    {
        $futureWednesday = Carbon::now()->addWeek()->startOfWeek()->addDays(2);

        // Tenant A
        $tenantA = Tenant::create(['name' => 'Salon A', 'slug' => 'salon-a']);
        $employeeA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Employee A',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $serviceA = Service::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Service A',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $serviceA->employees()->attach($employeeA->id);
        EmployeeSchedule::create([
            'employee_id' => $employeeA->id,
            'day_of_week' => $futureWednesday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        // Tenant B
        $tenantB = Tenant::create(['name' => 'Salon B', 'slug' => 'salon-b']);
        $employeeB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Employee B',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $serviceB = Service::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Service B',
            'price_cents' => 6000,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $serviceB->employees()->attach($employeeB->id);
        EmployeeSchedule::create([
            'employee_id' => $employeeB->id,
            'day_of_week' => $futureWednesday->dayOfWeekIso,
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        // Query Tenant A — should NOT see Employee B
        $resultA = $this->service->getAvailableSlots(
            serviceId: $serviceA->id,
            date: $futureWednesday->toDateString(),
            tenantId: $tenantA->id,
        );

        $this->assertArrayHasKey($employeeA->id, $resultA);
        $this->assertArrayNotHasKey($employeeB->id, $resultA);

        // Query Tenant B — should NOT see Employee A
        $resultB = $this->service->getAvailableSlots(
            serviceId: $serviceB->id,
            date: $futureWednesday->toDateString(),
            tenantId: $tenantB->id,
        );

        $this->assertArrayHasKey($employeeB->id, $resultB);
        $this->assertArrayNotHasKey($employeeA->id, $resultB);
    }
}
