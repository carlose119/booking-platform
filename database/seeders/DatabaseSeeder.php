<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'name' => 'Demo Salon',
            'slug' => 'demo-salon',
        ]);

        // Create Super Admin (no tenant)
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@booking-platform.com',
            'role' => UserRole::SuperAdmin,
            'password' => 'password',
        ]);

        // Create Business Admin
        $businessAdmin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Salon Owner',
            'email' => 'owner@demo-salon.com',
            'role' => UserRole::BusinessAdmin,
            'password' => 'password',
        ]);

        // Create Employee
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Stylist',
            'email' => 'jane@demo-salon.com',
            'role' => UserRole::Employee,
            'password' => 'password',
        ]);

        // Create Client
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'John Client',
            'email' => 'john@example.com',
            'role' => UserRole::Client,
            'password' => 'password',
        ]);

        // Create sample service
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'description' => 'Standard haircut and styling',
            'price_cents' => 3500,
            'duration_minutes' => 45,
            'active' => true,
        ]);

        // Assign service to employee
        $employee->services()->attach($service->id);

        // Create employee schedule (0=Monday, 1=Tuesday, etc.)
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => 0, // Monday
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => 1, // Tuesday
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }
}
