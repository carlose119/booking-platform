<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for bookings happening tomorrow';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $bookings = Booking::whereDate('date', $tomorrow)
            ->whereNull('reminded_at')
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings to remind for tomorrow.');
            return Command::SUCCESS;
        }

        $this->info("Sending reminders for {$bookings->count()} bookings...");

        foreach ($bookings as $booking) {
            SendBookingNotification::dispatch($booking, 'reminder');
            $booking->update(['reminded_at' => now()]);
        }

        $this->info('Reminders sent successfully.');

        return Command::SUCCESS;
    }
}
