<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\StripeService;
use App\Support\Currency;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingCalendar extends Component
{
    public int $tenantId;

    public ?int $selectedService = null;

    public ?string $selectedDate = null;

    public ?int $selectedEmployee = null;

    // ─── Step flow (PR 2) ──────────────────────────────────────────────────

    public int $currentStep = 1;

    public ?int $holdId = null;

    public ?string $guestName = null;

    public ?string $guestEmail = null;

    public ?string $guestPhone = null;

    public ?string $guestNotificationChannel = 'email';

    public ?int $bookingId = null;

    public ?string $errorMessage = null;

    // ─── Payment (PR 2) ────────────────────────────────────────────────────

    public ?string $clientSecret = null;

    public ?string $paymentAmountFormatted = null;

    public ?int $paymentAmountCents = null;

    #[Computed]
    public function services(): array
    {
        $currency = Tenant::find($this->tenantId)?->currency() ?? Currency::default();

        return Service::where('tenant_id', $this->tenantId)
            ->where('active', true)
            ->get()
            ->map(fn (Service $service): array => array_merge($service->toArray(), [
                'formatted_price' => Currency::format($service->price_cents, $currency),
            ]))
            ->toArray();
    }

    #[Computed]
    public function employees(): array
    {
        if (! $this->selectedService) {
            return [];
        }

        return Service::find($this->selectedService)
            ?->employees()
            ->where('tenant_id', $this->tenantId)
            ->get()
            ->toArray() ?? [];
    }

    #[Computed]
    public function availableSlots(): array
    {
        if (! $this->selectedService || ! $this->selectedDate) {
            return [];
        }

        $service = app(AvailabilityService::class);

        return $service->getAvailableSlots(
            serviceId: $this->selectedService,
            date: $this->selectedDate,
            tenantId: $this->tenantId,
        );
    }

    #[Computed]
    public function confirmationSummary(): array
    {
        if (! $this->bookingId) {
            return [];
        }

        $booking = Booking::with('service')->find($this->bookingId);

        if (! $booking) {
            return [];
        }

        return [
            'service' => $booking->service?->name,
            'date' => $booking->date?->format('M j, Y'),
            'time' => sprintf('%s – %s', $booking->start_time?->format('H:i'), $booking->end_time?->format('H:i')),
            'name' => $booking->client_name,
            'email' => $booking->client_email,
            'phone' => $booking->client_phone,
            'notification' => match ($booking->notification_channel) {
                'sms' => 'SMS',
                'both' => 'email and SMS',
                default => 'email',
            },
        ];
    }

    public string $tenantPaymentPolicy = 'nopayment';

    public bool $requiresPayment = false;

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->selectedDate = now()->toDateString();

        $tenant = Tenant::find($tenantId);
        $this->tenantPaymentPolicy = $tenant?->payment_policy ?? 'nopayment';
        $this->requiresPayment = in_array($this->tenantPaymentPolicy, ['100upfront', 'fraction']);
    }

    public function updatedSelectedService(): void
    {
        $this->selectedEmployee = null;
    }

    public function updatedSelectedDate(): void
    {
        //
    }

    public function updatedSelectedEmployee(): void
    {
        //
    }

    // ─── Slot selection (4.2) ──────────────────────────────────────────────

    public function selectSlot(int $employeeId, string $start, string $end): void
    {
        $this->errorMessage = null;

        try {
            $hold = app(BookingService::class)->createHold(
                tenantId: $this->tenantId,
                employeeId: $employeeId,
                serviceId: $this->selectedService,
                date: $this->selectedDate,
                startTime: $start,
                endTime: $end,
            );

            $this->holdId = $hold->id;
            $this->selectedEmployee = $employeeId;
            $this->currentStep = 2;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                $this->errorMessage = 'This slot is already being booked by someone else. Please choose another time.';

                return;
            }
            throw $e;
        }
    }

    // ─── Guest form submission (4.3) ──────────────────────────────────────

    public function submitGuestForm(): void
    {
        $this->errorMessage = null;

        $this->validate([
            'guestName' => 'required',
            'guestEmail' => 'required|email',
            'guestPhone' => 'required',
            'guestNotificationChannel' => 'required|in:email,sms,both',
        ], [
            'guestName.required' => 'Please enter your full name so the business knows who is coming.',
            'guestEmail.required' => 'Please enter an email address for booking updates.',
            'guestEmail.email' => 'Please enter a valid email address for booking updates.',
            'guestPhone.required' => 'Please enter a phone number in case the business needs to reach you.',
            'guestNotificationChannel.required' => 'Please choose how you want to receive booking updates.',
        ]);

        try {
            $booking = app(BookingService::class)->confirmBooking(
                holdId: $this->holdId,
                tenantId: $this->tenantId,
                clientName: $this->guestName,
                clientEmail: $this->guestEmail,
                clientPhone: $this->guestPhone,
                notificationChannel: $this->guestNotificationChannel,
            );

            $this->bookingId = $booking->id;

            // If payment is required, create PaymentIntent and show payment step
            if ($this->requiresPayment) {
                $this->createPaymentIntent($booking);
                $this->currentStep = 3; // Payment step
            } else {
                $this->currentStep = 4; // Confirmation step (no payment)
            }
        } catch (HttpException $e) {
            $this->errorMessage = 'Your slot expired before we could confirm it. Please choose a new available time to continue.';
            $this->currentStep = 1;
            $this->reset(['holdId', 'guestName', 'guestEmail', 'guestPhone']);
        }
    }

    // ─── Payment (PR 2) ────────────────────────────────────────────────────

    /**
     * Create a Stripe PaymentIntent for the booking.
     */
    private function createPaymentIntent(Booking $booking): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $service = Service::where('tenant_id', $this->tenantId)->findOrFail($booking->service_id);

        $bookingService = app(BookingService::class);
        $snapshot = $bookingService->snapshotPaymentForStripe($booking, $tenant, $service);

        if ($snapshot === null) {
            // Shouldn't happen, but fallback to confirmation
            $this->currentStep = 4;

            return;
        }

        $stripeService = app(StripeService::class, ['apiKeyOrClient' => $tenant->stripe_api_key]);

        $result = $stripeService->createPaymentIntent(
            amountCents: $snapshot['amount_cents'],
            currency: $snapshot['currency'],
            metadata: [
                'booking_id' => $booking->id,
                'tenant_id' => $tenant->id,
                'guest_email' => $this->guestEmail,
            ],
        );

        // Store PaymentIntent ID on the booking
        $booking->update(['stripe_payment_intent_id' => $result->id]);

        $this->clientSecret = $result->clientSecret;
        $this->paymentAmountCents = $snapshot['amount_cents'];
        $this->paymentAmountFormatted = Currency::format($snapshot['amount_cents'], $snapshot['currency']);
    }

    /**
     * Handle payment success confirmation from Stripe Elements.
     */
    public function confirmPaymentSuccess(): void
    {
        // Payment confirmed via webhook — show pending confirmation
        $this->currentStep = 4;
    }

    /**
     * Handle payment failure from Stripe Elements.
     */
    public function handlePaymentError(string $message): void
    {
        $this->errorMessage = "Payment could not be completed: {$message}";
    }

    // ─── Cancel (4.4) ─────────────────────────────────────────────────────

    public function cancelBooking(): void
    {
        if ($this->holdId) {
            BookingHold::where('id', $this->holdId)
                ->where('tenant_id', $this->tenantId)
                ->update(['expires_at' => now()]);
        }

        $this->reset([
            'currentStep',
            'holdId',
            'guestName',
            'guestEmail',
            'guestPhone',
            'bookingId',
            'errorMessage',
            'clientSecret',
            'paymentAmountFormatted',
            'paymentAmountCents',
        ]);

        $this->currentStep = 1;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '23000')
            || str_contains($e->getMessage(), 'Integrity constraint violation');
    }

    public function render()
    {
        return view('livewire.booking-calendar', [
            'tenant' => Tenant::find($this->tenantId),
        ]);
    }
}
