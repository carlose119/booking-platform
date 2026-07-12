<?php

namespace Tests\Unit;

use App\Models\BookingHold;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class BookingHoldActiveSlotKeyTest extends TestCase
{
    public function test_active_slot_key_is_the_only_non_null_active_token(): void
    {
        $this->assertSame('active', BookingHold::ACTIVE_SLOT_KEY);
    }

    public function test_active_slot_key_is_mass_assignable_and_persisted(): void
    {
        [$tenant, $service, $employee] = $this->createSlotActors();

        $hold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-13',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'session_id' => 'active-model-token',
            'expires_at' => Carbon::parse('2026-07-13 09:55:00'),
            'active_slot_key' => BookingHold::ACTIVE_SLOT_KEY,
        ]);

        $this->assertSame(BookingHold::ACTIVE_SLOT_KEY, $hold->refresh()->active_slot_key);
    }

    public function test_scope_participating_in_active_slot_uniqueness_excludes_null_keys(): void
    {
        [$tenant, $service, $employee] = $this->createSlotActors();

        BookingHold::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-13',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'session_id' => 'active-participant',
            'expires_at' => now()->addMinutes(10),
            'active_slot_key' => BookingHold::ACTIVE_SLOT_KEY,
        ]);
        BookingHold::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-13',
            'start_time' => '11:00',
            'end_time' => '11:30',
            'session_id' => 'expired-non-participant',
            'expires_at' => now()->subMinute(),
            'active_slot_key' => null,
        ]);

        $this->assertSame(
            ['active-participant'],
            BookingHold::query()->participatingInActiveSlotUniqueness()->pluck('session_id')->all()
        );
    }

    /** @return array{Tenant, Service, User} */
    private function createSlotActors(): array
    {
        $tenant = Tenant::create(['name' => 'Model Salon', 'slug' => uniqid('model-salon-')]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Stylist',
            'email' => uniqid('stylist-model-').'@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        return [$tenant, $service, $employee];
    }
}
