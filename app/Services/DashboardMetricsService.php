<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{
    private const CACHE_TTL = 60; // seconds

    public function __construct(
        protected int $tenantId,
    ) {}

    /**
     * Get today's metrics: bookings count, revenue, active bookings.
     */
    public function getTodayMetrics(): array
    {
        $cacheKey = "metrics:{$this->tenantId}:today";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $today = Carbon::today();

            $bookingsToday = Booking::where('bookings.tenant_id', $this->tenantId)
                ->whereDate('bookings.date', $today)
                ->count();

            $revenueTodayByCurrency = $this->paidBookingsBetween($today, $today)
                ->groupBy(fn (Booking $booking): string => $booking->resolvedPaymentCurrency())
                ->map(fn ($bookings): int => (int) $bookings->sum(
                    fn (Booking $booking): int => $booking->resolvedPaymentAmountCents() ?? 0
                ))
                ->sortKeys()
                ->all();

            $revenueTodayCents = count($revenueTodayByCurrency) <= 1
                ? (int) array_sum($revenueTodayByCurrency)
                : null;

            $activeBookings = Booking::where('bookings.tenant_id', $this->tenantId)
                ->whereDate('bookings.date', $today)
                ->whereIn('bookings.status', ['confirmed', 'pending'])
                ->count();

            return [
                'bookings_today' => $bookingsToday,
                'revenue_today_cents' => $revenueTodayCents,
                'revenue_today_by_currency' => $revenueTodayByCurrency,
                'active_bookings' => $activeBookings,
            ];
        });
    }

    /**
     * Get revenue trend for the last N days.
     */
    public function getRevenueTrend(int $days = 30): array
    {
        $cacheKey = "metrics:{$this->tenantId}:revenue-trend";

        return Cache::remember($cacheKey, 60, function () use ($days) {
            $startDate = Carbon::today()->subDays($days - 1);
            $endDate = Carbon::today();

            // Generate all dates in range
            $labels = [];
            $data = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $labels[] = $date->format('Y-m-d');
                $data[] = 0;
            }

            $series = [];
            foreach ($this->paidBookingsBetween($startDate, $endDate) as $booking) {
                $currency = $booking->resolvedPaymentCurrency();
                $series[$currency] ??= array_fill(0, count($labels), 0);
                $dateOnly = is_string($booking->date) ? substr($booking->date, 0, 10) : $booking->date->format('Y-m-d');
                $index = array_search($dateOnly, $labels);

                if ($index !== false) {
                    $series[$currency][$index] += $booking->resolvedPaymentAmountCents() ?? 0;
                }
            }

            ksort($series);
            $data = count($series) <= 1
                ? (array_values($series)[0] ?? $data)
                : null;

            return [
                'labels' => $labels,
                'data' => $data,
                'series' => $series,
            ];
        });
    }

    /**
     * Get upcoming bookings for the next N days.
     */
    public function getUpcomingBookings(int $days = 7): Collection
    {
        $cacheKey = "metrics:{$this->tenantId}:upcoming";

        return Cache::remember($cacheKey, 60, function () use ($days) {
            $startDate = Carbon::today();
            $endDate = Carbon::today()->addDays($days);

            return Booking::where('bookings.tenant_id', $this->tenantId)
                ->whereDate('bookings.date', '>=', $startDate)
                ->whereDate('bookings.date', '<=', $endDate)
                ->orderBy('bookings.date', 'asc')
                ->orderBy('bookings.start_time', 'asc')
                ->with('service')
                ->get();
        });
    }

    private function paidBookingsBetween(Carbon $startDate, Carbon $endDate): Collection
    {
        return Booking::where('bookings.tenant_id', $this->tenantId)
            ->where('bookings.payment_status', 'paid')
            ->whereDate('bookings.date', '>=', $startDate)
            ->whereDate('bookings.date', '<=', $endDate)
            ->with(['service', 'tenant'])
            ->get();
    }
}
