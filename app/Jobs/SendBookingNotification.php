<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBookingNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Booking $booking,
        public string $event,
        public ?string $reason = null,
        public ?string $originalDate = null,
        public ?string $originalTime = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $service): void
    {
        match ($this->event) {
            'confirmed' => $service->sendBookingConfirmed($this->booking),
            'reminder' => $service->sendBookingReminder($this->booking),
            'cancelled' => $service->sendBookingCancelled($this->booking, $this->reason),
            'rescheduled' => $service->sendBookingRescheduled(
                $this->booking,
                $this->originalDate,
                $this->originalTime,
            ),
            default => null,
        };
    }
}
