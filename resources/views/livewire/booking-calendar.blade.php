<div class="space-y-8">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Book an Appointment</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $tenant->name }} — Select a service, date, and available time slot.</p>
    </div>

    {{-- Error message --}}
    @if ($errorMessage)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p>
            @if (str_contains($errorMessage, 'slot'))
                <p class="mt-1 text-sm text-red-700">Choose another available time below. We refreshed availability without creating a booking or payment.</p>
            @elseif (str_contains($errorMessage, 'Payment'))
                <p class="mt-1 text-sm text-red-700">Please check your payment details and try again.</p>
            @endif
        </div>
    @endif

    {{-- Step indicator --}}
    @php
        $steps = $this->requiresPayment
            ? [1 => 'Select Slot', 2 => 'Your Details', 3 => 'Payment', 4 => 'Confirmation']
            : [1 => 'Select Slot', 2 => 'Your Details', 3 => 'Confirmation'];
        $visibleStep = $this->requiresPayment ? $currentStep : min($currentStep, 3);
    @endphp
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" aria-label="Booking progress">
        <p class="mb-3 text-sm font-medium text-gray-700">Step {{ $visibleStep }} of {{ count($steps) }}: {{ $steps[$visibleStep] }}</p>
        <ol class="grid grid-cols-1 gap-2 sm:grid-cols-{{ count($steps) }}">
            @foreach ($steps as $stepNumber => $stepLabel)
                @php
                    $isCurrent = $visibleStep === $stepNumber;
                    $isComplete = $visibleStep > $stepNumber;
                @endphp
                <li class="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm {{ $isCurrent ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : ($isComplete ? 'border-green-200 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-50 text-gray-500') }}">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold {{ $isCurrent ? 'bg-indigo-600 text-white' : ($isComplete ? 'bg-green-600 text-white' : 'bg-white text-gray-500') }}">
                        {{ $isComplete ? '✓' : $stepNumber }}
                    </span>
                    <span class="font-medium">{{ $stepLabel }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         STEP 1: Slot Selection (existing calendar UI)
         ══════════════════════════════════════════════════════════════════════ --}}
    @if ($currentStep === 1)
        {{-- Filters --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Service Select --}}
            <div>
                <label for="service" class="block text-sm font-medium text-gray-700">Service</label>
                <select
                    id="service"
                    wire:model.live="selectedService"
                    wire:loading.attr="disabled"
                    wire:target="selectedService,selectedDate,selectedEmployee,selectSlot"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">— Select a service —</option>
                    @foreach ($this->services as $service)
                        <option value="{{ $service['id'] }}">
                            {{ $service['name'] }} ({{ $service['duration_minutes'] }} min, {{ $service['formatted_price'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date Picker --}}
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                <input
                    type="date"
                    id="date"
                    wire:model.live="selectedDate"
                    wire:loading.attr="disabled"
                    wire:target="selectedService,selectedDate,selectedEmployee,selectSlot"
                    min="{{ now()->toDateString() }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
            </div>

            {{-- Employee Select (optional filter) --}}
            <div>
                <label for="employee" class="block text-sm font-medium text-gray-700">Employee (optional)</label>
                <select
                    id="employee"
                    wire:model.live="selectedEmployee"
                    wire:loading.attr="disabled"
                    wire:target="selectedService,selectedDate,selectedEmployee,selectSlot"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">All employees</option>
                    @foreach ($this->employees as $employee)
                        <option value="{{ $employee['id'] }}">{{ $employee['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="text-sm text-gray-500" wire:loading wire:target="selectedService,selectedDate,selectedEmployee,selectSlot">Loading times...</p>

        {{-- Slot Grid --}}
        @if ($selectedService && $selectedDate)
            @if (empty($this->availableSlots))
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">No time slots available</h2>
                    <p class="mt-2 text-sm text-gray-600">Try another date or service to find open appointment times.</p>
                    <p class="mt-1 text-xs text-gray-500">No booking has been created yet.</p>
                </div>
            @else
                @php
                    $filteredSlots = collect($this->availableSlots);
                    if ($selectedEmployee) {
                        $filteredSlots = $filteredSlots->only([$selectedEmployee]);
                    }
                @endphp

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($filteredSlots as $employeeId => $employeeData)
                        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="employee-slots-{{ $employeeId }}">
                            <div class="mb-4">
                                <h2 id="employee-slots-{{ $employeeId }}" class="text-base font-semibold text-gray-900">Available times with {{ $employeeData['employee_name'] }}</h2>
                                <p class="text-sm text-gray-500">Tap a time to hold it while you enter your details.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($employeeData['slots'] as $slot)
                                    <button
                                        type="button"
                                        @if ($slot['available'])
                                            wire:click="selectSlot({{ $employeeId }}, '{{ $slot['start'] }}', '{{ $slot['end'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="selectSlot({{ $employeeId }}, '{{ $slot['start'] }}', '{{ $slot['end'] }}')"
                                            class="min-h-12 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-left text-sm font-semibold text-green-800 transition-colors hover:bg-green-100 disabled:cursor-wait disabled:opacity-60"
                                        @else
                                            class="min-h-12 cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-4 py-3 text-left text-sm font-semibold text-gray-500"
                                            disabled
                                        @endif
                                    >
                                        @if ($slot['available'])
                                            <span wire:loading.remove wire:target="selectSlot({{ $employeeId }}, '{{ $slot['start'] }}', '{{ $slot['end'] }}')">Choose {{ $slot['start'] }} – {{ $slot['end'] }}</span>
                                            <span wire:loading wire:target="selectSlot({{ $employeeId }}, '{{ $slot['start'] }}', '{{ $slot['end'] }}')">Holding this time...</span>
                                        @elseif (($slot['unavailable_reason'] ?? null) === 'held')
                                            Temporarily held {{ $slot['start'] }} – {{ $slot['end'] }}
                                        @else
                                            Booked {{ $slot['start'] }} – {{ $slot['end'] }}
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        @else
            <div class="rounded-lg border border-dashed border-gray-300 p-12 text-center">
                <p class="text-sm text-gray-500">Select a service and date to view available time slots.</p>
            </div>
        @endif
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         STEP 2: Guest Form
         ══════════════════════════════════════════════════════════════════════ --}}
    @if ($currentStep === 2)
        <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Your Details</h2>
                <p class="mt-1 text-sm text-gray-600">We will use these details to confirm your appointment.</p>
            </div>

            <form wire:submit="submitGuestForm" class="space-y-5">
                {{-- Name --}}
                <div>
                    <label for="guestName" class="block text-sm font-medium text-gray-800">Full name</label>
                    <p class="mt-1 text-sm text-gray-500">Use the name you want on the booking.</p>
                    <input
                        type="text"
                        id="guestName"
                        wire:model="guestName"
                        wire:loading.attr="disabled"
                        wire:target="submitGuestForm,cancelBooking"
                        class="mt-2 block min-h-12 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-wait disabled:bg-gray-50 disabled:opacity-70 sm:text-sm"
                        placeholder="Jane Client"
                    />
                    @error('guestName')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="guestEmail" class="block text-sm font-medium text-gray-800">Email address</label>
                    <p class="mt-1 text-sm text-gray-500">Where should we send booking updates?</p>
                    <input
                        type="email"
                        id="guestEmail"
                        wire:model="guestEmail"
                        wire:loading.attr="disabled"
                        wire:target="submitGuestForm,cancelBooking"
                        class="mt-2 block min-h-12 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-wait disabled:bg-gray-50 disabled:opacity-70 sm:text-sm"
                        placeholder="jane@example.com"
                    />
                    @error('guestEmail')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="guestPhone" class="block text-sm font-medium text-gray-800">Phone number</label>
                    <p class="mt-1 text-sm text-gray-500">Used only if the business needs to reach you about this booking.</p>
                    <input
                        type="tel"
                        id="guestPhone"
                        wire:model="guestPhone"
                        wire:loading.attr="disabled"
                        wire:target="submitGuestForm,cancelBooking"
                        class="mt-2 block min-h-12 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-wait disabled:bg-gray-50 disabled:opacity-70 sm:text-sm"
                        placeholder="+1 555 123 456"
                    />
                    @error('guestPhone')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notification Channel --}}
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-800">Notification preference</legend>
                    <p class="mt-1 text-sm text-gray-500">Choose the best way to receive appointment updates.</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach (['email' => 'Email only', 'sms' => 'SMS only', 'both' => 'Both email and SMS'] as $value => $label)
                            <label class="flex min-h-12 items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-800">
                                <input type="radio" wire:model="guestNotificationChannel" wire:loading.attr="disabled" wire:target="submitGuestForm,cancelBooking" value="{{ $value }}" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('guestNotificationChannel')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- Actions --}}
                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submitGuestForm,cancelBooking"
                        class="inline-flex min-h-12 items-center justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        <span wire:loading.remove wire:target="submitGuestForm">Confirm Booking</span>
                        <span wire:loading wire:target="submitGuestForm">Confirming...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="cancelBooking"
                        wire:loading.attr="disabled"
                        wire:target="submitGuestForm,cancelBooking"
                        class="inline-flex min-h-12 items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-wait disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="cancelBooking">Cancel</span>
                        <span wire:loading wire:target="cancelBooking">Releasing hold...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         STEP 3: Payment (only when payment required)
         ══════════════════════════════════════════════════════════════════════ --}}
    @if ($currentStep === 3 && $this->requiresPayment)
        <div class="max-w-lg rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Complete Payment</h2>
            <p class="mb-4 text-sm text-gray-600">Your appointment is held while you finish secure payment. You can retry here if the payment does not go through.</p>
            <p class="text-sm text-gray-600 mb-4">
                @if ($tenantPaymentPolicy === 'fraction')
                    Deposit required: <strong>{{ $paymentAmountFormatted }}</strong>
                @else
                    Total amount: <strong>{{ $paymentAmountFormatted }}</strong>
                @endif
            </p>

            {{-- Stripe Elements Container --}}
            <div id="stripe-payment-element" class="rounded-md border border-gray-300 p-4 mb-4">
                {{-- Stripe.js will mount the payment element here --}}
                <div class="animate-pulse bg-gray-200 h-12 rounded"></div>
            </div>

            {{-- Payment Error --}}
            @if ($errorMessage)
                <div role="alert" class="rounded-md bg-red-50 p-3 mb-4">
                    <p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p>
                    <p class="mt-1 text-sm text-red-700">Please check your payment details and try again.</p>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    id="stripe-submit-button"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="confirmPaymentSuccess">{{ $tenantPaymentPolicy === 'fraction' ? 'Pay deposit' : 'Pay' }} {{ $paymentAmountFormatted }}</span>
                    <span wire:loading wire:target="confirmPaymentSuccess">Processing...</span>
                </button>
                <button
                    type="button"
                    wire:click="cancelBooking"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                >
                    Cancel
                </button>
            </div>

            {{-- Stripe.js initialization --}}
            @push('scripts')
            <script src="https://js.stripe.com/v3/"></script>
            <script>
                document.addEventListener('livewire:init', () => {
                    const stripe = Stripe('{{ config('services.stripe.publishable_key', '') }}');
                    const elements = stripe.elements({
                        clientSecret: @js($clientSecret),
                        appearance: { theme: 'stripe' }
                    });

                    const paymentElement = elements.create('payment');
                    paymentElement.mount('#stripe-payment-element');

                    const submitButton = document.getElementById('stripe-submit-button');
                    submitButton.addEventListener('click', async () => {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Processing...';

                        const { error } = await stripe.confirmPayment({
                            elements,
                            confirmParams: {
                                return_url: window.location.href,
                            },
                        });

                        if (error) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Pay {{ $paymentAmountFormatted }}';
                            @this.call('handlePaymentError', error.message);
                        } else {
                            @this.call('confirmPaymentSuccess');
                        }
                    });
                });
            </script>
            @endpush
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         STEP 3/4: Confirmation
         ══════════════════════════════════════════════════════════════════════ --}}
    @if (($currentStep === 3 && !$this->requiresPayment) || $currentStep === 4)
        @php($summary = $this->confirmationSummary)
        <div class="max-w-2xl rounded-lg border border-green-200 bg-green-50 p-6 shadow-sm">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="text-center text-lg font-semibold text-gray-900">Booking Confirmed!</h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Your appointment has been booked. We'll send a confirmation to <strong>{{ $guestEmail }}</strong>.
            </p>
            <p class="mt-1 text-center text-xs text-gray-500">Booking #{{ $bookingId }}</p>

            @if ($summary)
                <dl class="mt-6 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium text-gray-500">Service</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $summary['service'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Date and time</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $summary['date'] }} at {{ $summary['time'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Guest</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $summary['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Contact</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $summary['email'] }} · {{ $summary['phone'] }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-sm text-gray-700">You will receive booking updates by {{ $summary['notification'] }}.</p>
            @endif

            @if ($this->requiresPayment && $currentStep === 4)
                <p class="mt-2 text-sm text-amber-700">
                    Your payment is being processed. You'll receive an email once confirmed.
                </p>
            @endif

            <button
                type="button"
                wire:click="cancelBooking"
                class="mt-6 inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            >
                Book Another Appointment
            </button>
        </div>
    @endif
</div>
