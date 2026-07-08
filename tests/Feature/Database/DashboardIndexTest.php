<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_index_exists_on_bookings_table(): void
    {
        // The migration should create a composite index on (tenant_id, date, payment_status, status)
        $this->assertTrue(
            Schema::hasIndex('bookings', ['tenant_id', 'date', 'payment_status', 'status']),
            'Dashboard composite index (tenant_id, date, payment_status, status) should exist on bookings table'
        );
    }
}