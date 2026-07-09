<x-filament-widgets::widget>
    @php
        $canStartStripeConnect = auth()->user()?->role === \App\Enums\UserRole::BusinessAdmin
            && filled(config('services.stripe.secret'))
            && filled(config('services.stripe.client_id'))
            && filled(config('services.stripe.connect_webhook_secret'));
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <a href="{{ \App\Filament\Resources\BookingResource::getUrl('index') }}" 
               class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-calendar-days class="h-8 w-8 text-primary-500" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">View Bookings</p>
                        <p class="text-xs text-gray-500">Manage appointments</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/tenant/' . auth()->user()->tenant_id . '/services') }}" 
               class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-wrench class="h-8 w-8 text-primary-500" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">Manage Services</p>
                        <p class="text-xs text-gray-500">Service catalog</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/tenant/' . auth()->user()->tenant_id . '/employee-schedules') }}" 
               class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-users class="h-8 w-8 text-primary-500" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">Employee Schedules</p>
                        <p class="text-xs text-gray-500">Manage staff hours</p>
                    </div>
                </div>
            </a>

            @if ($canStartStripeConnect)
                <a href="{{ route('stripe.connect.start') }}"
                   class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-credit-card class="h-8 w-8 text-primary-500" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-900">Stripe Connect</p>
                            <p class="text-xs text-gray-500">Start or resume onboarding</p>
                        </div>
                    </div>
                </a>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
