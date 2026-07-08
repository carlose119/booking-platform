<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class CleanExpiredHolds extends Command
{
    protected $signature = 'booking:clean-holds';

    protected $description = 'Delete all expired booking holds from the database';

    public function handle(BookingService $bookingService): int
    {
        $deleted = $bookingService->expireHolds();

        $this->info("Cleaned {$deleted} expired hold(s).");

        return Command::SUCCESS;
    }
}
