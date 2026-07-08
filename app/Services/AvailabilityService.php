<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Get available time slots for a service on a given date.
     *
     * Returns an array keyed by employee_id, each value containing
     * employee info and their slots with availability status.
     */
    public function getAvailableSlots(
        int $serviceId,
        string $date,
        ?int $tenantId = null,
        ?int $excludeBookingId = null,
    ): array {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $service = Service::where('tenant_id', $tenantId)
            ->findOrFail($serviceId);

        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeekIso; // 1=Monday, 7=Sunday

        // Get employees that provide this service
        $employeeIds = $service->employees()
            ->where('tenant_id', $tenantId)
            ->pluck('users.id');

        // Get schedules matching the day of week for these employees
        $schedules = EmployeeSchedule::where('day_of_week', $dayOfWeek)
            ->whereIn('employee_id', $employeeIds)
            ->with('employee')
            ->get()
            ->keyBy('employee_id');

        // Get existing bookings for these employees on this date (non-cancelled)
        $existingBookings = Booking::where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($excludeBookingId, fn ($query) => $query->whereKeyNot($excludeBookingId))
            ->get()
            ->groupBy('employee_id');

        // Get active holds for these employees on this date
        $activeHolds = BookingHold::where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', $date)
            ->active()
            ->get()
            ->groupBy('employee_id');

        $result = [];

        foreach ($employeeIds as $employeeId) {
            if (! $schedules->has($employeeId)) {
                continue;
            }

            $schedule = $schedules[$employeeId];
            $employeeBookings = $existingBookings->get($employeeId, collect());
            $employeeHolds = $activeHolds->get($employeeId, collect());

            $slots = $this->generateSlotsFromSchedule($schedule, $service->duration_minutes);

            // Filter booking conflicts
            $slots = $this->filterConflicts($slots, $employeeBookings);

            // Filter active hold conflicts
            $slots = $this->filterHoldConflicts($slots, $employeeHolds);

            // Filter past times if date is today
            if ($carbonDate->isToday()) {
                $slots = $this->filterPastSlots($slots);
            }

            $result[$employeeId] = [
                'employee_id' => $employeeId,
                'employee_name' => $schedule->employee->name,
                'slots' => $slots,
            ];
        }

        return $result;
    }

    /**
     * Generate time slots from an employee schedule.
     *
     * Creates slots from schedule start_time to end_time
     * using the service duration as the step interval.
     */
    protected function generateSlotsFromSchedule(
        EmployeeSchedule $schedule,
        int $durationMinutes,
    ): array {
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        $slots = [];

        while ($start->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($durationMinutes);

            $slots[] = [
                'start' => $start->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'available' => true,
            ];

            $start = $slotEnd;
        }

        return $slots;
    }

    /**
     * Filter out slots that overlap with existing bookings.
     *
     * A slot is marked unavailable if ANY non-cancelled booking overlaps:
     * booking.start_time < slot.end AND booking.end_time > slot.start
     */
    protected function filterConflicts(array $slots, Collection $bookings): array
    {
        return array_map(function (array $slot) use ($bookings) {
            $slotStart = Carbon::parse($slot['start']);
            $slotEnd = Carbon::parse($slot['end']);

            $hasConflict = $bookings->contains(function (Booking $booking) use ($slotStart, $slotEnd) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);

                return $bookingStart->lt($slotEnd) && $bookingEnd->gt($slotStart);
            });

            $slot['available'] = ! $hasConflict;

            if ($hasConflict) {
                $slot['unavailable_reason'] = 'booked';
            }

            return $slot;
        }, $slots);
    }

    /**
     * Filter out slots whose end time has already passed (for today).
     */
    protected function filterPastSlots(array $slots): array
    {
        $now = Carbon::now();

        return array_values(array_filter($slots, function (array $slot) use ($now) {
            $parts = explode(':', $slot['end']);
            $endHour = (int) $parts[0];
            $endMinute = (int) ($parts[1] ?? 0);

            // Compare time-only: slot is past if end hour/minute <= current hour/minute
            return ($endHour * 60 + $endMinute) > ($now->hour * 60 + $now->minute);
        }));
    }

    /**
     * Filter out slots that overlap with active holds.
     *
     * A slot is marked unavailable if ANY active hold overlaps:
     * hold.start_time < slot.end AND hold.end_time > slot.start
     */
    protected function filterHoldConflicts(array $slots, Collection $holds): array
    {
        return array_map(function (array $slot) use ($holds) {
            $slotStart = Carbon::parse($slot['start']);
            $slotEnd = Carbon::parse($slot['end']);

            $hasConflict = $holds->contains(function (BookingHold $hold) use ($slotStart, $slotEnd) {
                $holdStart = Carbon::parse($hold->start_time);
                $holdEnd = Carbon::parse($hold->end_time);

                return $holdStart->lt($slotEnd) && $holdEnd->gt($slotStart);
            });

            if ($hasConflict) {
                $slot['available'] = false;
                $slot['unavailable_reason'] = 'held';
            }

            return $slot;
        }, $slots);
    }
}
