<?php

namespace Tests\Feature;

use App\Livewire\BookingCalendar;
use App\Models\Booking;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DTOs\PaymentIntentResult;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class BookingWithPaymentTest extends TestCase
{
    use RefreshDatabase;

    // ─── Booking with nopayment policy confirms immediately ───────────────

    public function test_booking_with_nopayment_policy_confirms_immediately(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create([
            'name' => 'NoPay Salon',
            'slug' => 'nopay-salon',
            'payment_policy' => 'nopayment',
        ]);

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
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestName', 'John Doe')
            ->set('guestEmail', 'john@example.com')
            ->set('guestPhone', '+1 555 123 456')
            ->call('submitGuestForm')
            ->assertSet('currentStep', 4) // Confirmation step (nopayment goes to step 4)
            ->assertSee('Booking Confirmed!');

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'client_name' => 'John Doe',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);
    }

    // ─── Booking with 100upfront shows payment step ──────────────────────

    public function test_booking_with_100upfront_shows_payment_step(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create([
            'name' => 'Upfront Salon',
            'slug' => 'upfront-salon',
            'default_currency' => 'eur',
            'payment_policy' => '100upfront',
            'stripe_api_key' => 'sk_test_fake_key',
        ]);

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
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        // Use Mockery::mock to create a mock that will be returned when resolving StripeService
        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createPaymentIntent')
            ->once()
            ->with(5000, 'eur', Mockery::on(fn (array $metadata): bool => $metadata['tenant_id'] === $tenant->id
                && $metadata['guest_email'] === 'john@example.com'
            ))
            ->andReturn(new PaymentIntentResult(
                id: 'pi_test_123',
                clientSecret: 'cs_test_456_secret',
                amount: 5000,
                status: 'requires_payment_method',
            ));

        // Bind the mock to the container so when app(StripeService::class, [...]) is called,
        // it returns our mock regardless of constructor arguments
        $this->app->bind(StripeService::class, fn () => $stripeMock);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestName', 'John Doe')
            ->set('guestEmail', 'john@example.com')
            ->set('guestPhone', '+1 555 123 456')
            ->call('submitGuestForm')
            ->assertSet('currentStep', 3) // Payment step
            ->assertSee('Complete Payment')
            ->assertSee('Your appointment is held while you finish secure payment.')
            ->assertSee('You can retry here if the payment does not go through.')
            ->assertSee('€50.00');

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'client_name' => 'John Doe',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_amount_cents' => 5000,
            'payment_currency' => 'eur',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);
    }

    // ─── Booking with fraction shows deposit amount ──────────────────────

    public function test_booking_with_fraction_shows_deposit_amount(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create([
            'name' => 'Deposit Salon',
            'slug' => 'deposit-salon',
            'default_currency' => 'gbp',
            'payment_policy' => 'fraction',
            'deposit_percentage' => 20,
            'stripe_api_key' => 'sk_test_fake_key',
        ]);

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
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        // Use Mockery::mock to create a mock that will be returned when resolving StripeService
        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createPaymentIntent')
            ->once()
            ->with(1000, 'gbp', Mockery::on(fn (array $metadata): bool => $metadata['tenant_id'] === $tenant->id
                && $metadata['guest_email'] === 'john@example.com'
            ))
            ->andReturn(new PaymentIntentResult(
                id: 'pi_test_deposit_123',
                clientSecret: 'cs_test_deposit_456_secret',
                amount: 1000, // 20% of 5000
                status: 'requires_payment_method',
            ));

        // Bind the mock to the container
        $this->app->bind(StripeService::class, fn () => $stripeMock);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestName', 'John Doe')
            ->set('guestEmail', 'john@example.com')
            ->set('guestPhone', '+1 555 123 456')
            ->call('submitGuestForm')
            ->assertSet('currentStep', 3) // Payment step
            ->assertSee('Deposit required')
            ->assertSee('Pay deposit')
            ->assertSee('£10.00');

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'client_name' => 'John Doe',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_amount_cents' => 1000,
            'payment_currency' => 'gbp',
            'stripe_payment_intent_id' => 'pi_test_deposit_123',
        ]);
    }

    public function test_public_booking_service_list_uses_tenant_currency_display(): void
    {
        $tenant = Tenant::create([
            'name' => 'Euro Salon',
            'slug' => 'euro-salon',
            'default_currency' => 'eur',
            'payment_policy' => 'nopayment',
        ]);

        Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->assertSee('Haircut (60 min, €50.00)')
            ->assertDontSee('$50.00');
    }

    public function test_payment_error_copy_guides_retry_without_changing_payment_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Retry Salon',
            'slug' => 'retry-salon',
            'payment_policy' => '100upfront',
            'stripe_api_key' => 'sk_test_fake_key',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $booking = Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('currentStep', 3)
            ->set('bookingId', $booking->id)
            ->set('paymentAmountFormatted', '50.00')
            ->call('handlePaymentError', 'Your card was declined.')
            ->assertSee('Payment could not be completed')
            ->assertSee('Your card was declined.')
            ->assertSee('Please check your payment details and try again.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => 'unpaid',
        ]);
    }

    // ─── Hold TTL extended for payment-required tenants ───────────────────

    public function test_hold_ttl_extended_for_payment_required_tenants(): void
    {
        $tenant = Tenant::create([
            'name' => 'Extended TTL Salon',
            'slug' => 'extended-ttl-salon',
            'payment_policy' => '100upfront',
        ]);

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
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        $bookingService = new BookingService;

        $hold = $bookingService->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        // Hold should have 15 minute TTL for payment-required tenants
        $expectedExpiry = Carbon::now()->addMinutes(15);
        $this->assertTrue(
            $hold->expires_at->lte($expectedExpiry->addSeconds(2)),
            'expires_at should be at most now + 15 minutes'
        );
    }

    // ─── Hold TTL standard for nopayment tenants ──────────────────────────

    public function test_hold_ttl_standard_for_nopayment_tenants(): void
    {
        $tenant = Tenant::create([
            'name' => 'Standard TTL Salon',
            'slug' => 'standard-ttl-salon',
            'payment_policy' => 'nopayment',
        ]);

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
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        $bookingService = new BookingService;

        $hold = $bookingService->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        // Hold should have 10 minute TTL (default) for nopayment tenants
        $expectedExpiry = Carbon::now()->addMinutes(10);
        $this->assertTrue(
            $hold->expires_at->lte($expectedExpiry->addSeconds(2)),
            'expires_at should be at most now + 10 minutes'
        );
    }

    // ─── Payment amount calculation ───────────────────────────────────────

    public function test_calculate_payment_amount_returns_correct_values(): void
    {
        $bookingService = new BookingService;

        // nopayment
        $nopayment = Tenant::create(['name' => 'NoPay', 'slug' => 'nopay', 'payment_policy' => 'nopayment']);
        $this->assertNull($bookingService->calculatePaymentAmount($nopayment, 5000));

        // 100upfront
        $upfront = Tenant::create(['name' => 'Upfront', 'slug' => 'upfront', 'payment_policy' => '100upfront']);
        $this->assertEquals(5000, $bookingService->calculatePaymentAmount($upfront, 5000));

        // fraction (20%)
        $fraction = Tenant::create([
            'name' => 'Fraction',
            'slug' => 'fraction',
            'payment_policy' => 'fraction',
            'deposit_percentage' => 20,
        ]);
        $this->assertEquals(1000, $bookingService->calculatePaymentAmount($fraction, 5000));
    }
}
