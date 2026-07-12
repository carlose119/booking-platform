<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class BookingHoldMigrationTest extends TestCase
{
    public function test_expired_rows_backfill_to_null_and_active_rows_backfill_to_active_key(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable();

        $this->insertLegacyHold($tenant, $service, $employee, 'expired-slot', '09:00', now()->subMinute());
        $this->insertLegacyHold($tenant, $service, $employee, 'active-slot', '10:00', now()->addMinutes(10));

        $this->migration()->up();

        $this->assertNull($this->activeSlotKeyFor('expired-slot'));
        $this->assertSame('active', $this->activeSlotKeyFor('active-slot'));
        $this->assertTrue($this->hasIndex('booking_holds_unique_active_slot'));
        $this->assertFalse($this->hasIndex('booking_holds_unique_slot'));
    }

    public function test_nullable_active_slot_key_allows_expired_duplicates_but_rejects_active_duplicates(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable();

        $this->insertLegacyHold($tenant, $service, $employee, 'expired-slot', '09:00', now()->subMinute());
        $this->insertLegacyHold($tenant, $service, $employee, 'active-slot', '10:00', now()->addMinutes(10));

        $this->migration()->up();

        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'second-expired-slot',
            '09:00',
            now()->subMinutes(2),
            ['active_slot_key' => null]
        ));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'second-active-slot',
            '10:00',
            now()->addMinutes(10),
            ['active_slot_key' => 'active']
        ));
    }

    public function test_old_application_inserts_receive_active_default_after_migration(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable();

        $this->migration()->up();

        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'old-node-active-a',
            '10:00',
            now()->addMinutes(10),
        ));

        $this->assertSame('active', $this->activeSlotKeyFor('old-node-active-a'));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'old-node-active-b',
            '10:00',
            now()->addMinutes(10),
        ));
    }

    public function test_duplicate_active_rows_abort_before_replacing_the_slot_index(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable(withUniqueSlotIndex: false);

        $this->insertLegacyHold($tenant, $service, $employee, 'active-one', '10:00', now()->addMinutes(10));
        $this->insertLegacyHold($tenant, $service, $employee, 'active-two', '10:00', now()->addMinutes(15));

        try {
            $this->migration()->up();
            $this->fail('Expected duplicate active booking holds to abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate active booking holds', $exception->getMessage());
            $this->assertStringContainsString((string) $tenant->id, $exception->getMessage());
            $this->assertFalse($this->hasIndex('booking_holds_unique_active_slot'));
        }
    }

    public function test_rollback_duplicate_slot_rows_report_the_conflicting_slot_details(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable();

        $this->migration()->up();

        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'rollback-expired-a',
            '09:00',
            now()->subMinutes(2),
            ['active_slot_key' => null]
        ));
        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            'rollback-expired-b',
            '09:00',
            now()->subMinute(),
            ['active_slot_key' => null]
        ));

        try {
            $this->migration()->down();
            $this->fail('Expected duplicate slot rows to abort the rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back booking hold active-slot semantics', $exception->getMessage());
            $this->assertStringContainsString("tenant_id={$tenant->id}", $exception->getMessage());
            $this->assertStringContainsString("employee_id={$employee->id}", $exception->getMessage());
            $this->assertStringContainsString('start_time=09:00', $exception->getMessage());
        }
    }

    public function test_active_unique_index_is_created_before_legacy_unique_index_is_dropped(): void
    {
        $migrationSource = file_get_contents(database_path('migrations/2026_07_11_000001_add_active_slot_key_to_booking_holds.php'));

        $fkSupportIndexPosition = strpos($migrationSource, "booking_holds_tenant_id_fk_support');");
        $activeUniqueIndexPosition = strpos($migrationSource, "'booking_holds_unique_active_slot'");
        $legacyDropPosition = strpos($migrationSource, "dropUnique('booking_holds_unique_slot')");

        $this->assertNotFalse($fkSupportIndexPosition, 'Migration must add an FK-support index before the legacy unique slot index can be dropped.');
        $this->assertNotFalse($activeUniqueIndexPosition, 'Migration must create the active unique slot index.');
        $this->assertNotFalse($legacyDropPosition, 'Migration must drop the legacy unique slot index only after replacement protection exists.');

        $this->assertLessThan($legacyDropPosition, $fkSupportIndexPosition);
        $this->assertLessThan(
            $legacyDropPosition,
            $activeUniqueIndexPosition,
            'Create booking_holds_unique_active_slot before dropping booking_holds_unique_slot so a failed active-index DDL leaves legacy uniqueness in place.'
        );
    }

    public function test_rollback_recreates_legacy_unique_index_before_dropping_active_uniqueness(): void
    {
        $migrationSource = file_get_contents(database_path('migrations/2026_07_11_000001_add_active_slot_key_to_booking_holds.php'));
        $downMethodPosition = strpos($migrationSource, 'public function down(): void');

        $this->assertNotFalse($downMethodPosition, 'Migration must define a rollback method.');

        $legacyUniqueCreatePosition = strpos($migrationSource, "'booking_holds_unique_slot'", $downMethodPosition);
        $activeUniqueDropPosition = strpos($migrationSource, "dropUnique('booking_holds_unique_active_slot')", $downMethodPosition);
        $activeSlotColumnDropPosition = strpos($migrationSource, "dropColumn('active_slot_key')", $downMethodPosition);

        $this->assertNotFalse($legacyUniqueCreatePosition, 'Rollback must restore booking_holds_unique_slot before removing active uniqueness.');
        $this->assertNotFalse($activeUniqueDropPosition, 'Rollback must drop booking_holds_unique_active_slot only after legacy uniqueness exists.');
        $this->assertNotFalse($activeSlotColumnDropPosition, 'Rollback must drop active_slot_key only after legacy uniqueness exists.');

        $this->assertLessThan(
            $activeUniqueDropPosition,
            $legacyUniqueCreatePosition,
            'Create booking_holds_unique_slot before dropping booking_holds_unique_active_slot so rollback fails closed if legacy DDL fails.'
        );
        $this->assertLessThan(
            $activeSlotColumnDropPosition,
            $legacyUniqueCreatePosition,
            'Create booking_holds_unique_slot before dropping active_slot_key so rollback never opens a no-uniqueness window.'
        );
    }

    public function test_rollback_restores_legacy_uniqueness_without_leaving_active_artifacts(): void
    {
        Carbon::setTestNow('2026-07-11 10:00:00');

        [$tenant, $service, $employee] = $this->createSlotActors();
        $this->createLegacyBookingHoldsTable();

        $this->insertLegacyHold($tenant, $service, $employee, 'rollback-active-slot', '10:00', now()->addMinutes(10));

        $this->migration()->up();
        $this->migration()->down();

        $this->assertTrue($this->hasIndex('booking_holds_unique_slot'));
        $this->assertFalse($this->hasIndex('booking_holds_unique_active_slot'));
        $this->assertFalse($this->hasIndex('booking_holds_tenant_id_fk_support'));
        $this->assertFalse(Schema::hasColumn('booking_holds', 'active_slot_key'));
    }

    /** @return array{Tenant, Service, User} */
    private function createSlotActors(): array
    {
        $tenant = Tenant::create(['name' => 'Migration Salon', 'slug' => 'migration-salon']);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Stylist',
            'email' => 'stylist-migration@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        return [$tenant, $service, $employee];
    }

    private function createLegacyBookingHoldsTable(bool $withUniqueSlotIndex = true): void
    {
        Schema::dropIfExists('booking_holds');

        Schema::create('booking_holds', function (Blueprint $table) use ($withUniqueSlotIndex): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('session_id');
            $table->timestamp('expires_at');
            $table->timestamps();

            if ($withUniqueSlotIndex) {
                $table->unique(
                    ['tenant_id', 'employee_id', 'date', 'start_time', 'end_time'],
                    'booking_holds_unique_slot'
                );
            }
        });
    }

    private function insertLegacyHold(
        Tenant $tenant,
        Service $service,
        User $employee,
        string $sessionId,
        string $startTime,
        Carbon $expiresAt,
    ): void {
        DB::table('booking_holds')->insert($this->legacyHoldPayload(
            $tenant,
            $service,
            $employee,
            $sessionId,
            $startTime,
            $expiresAt
        ));
    }

    private function legacyHoldPayload(
        Tenant $tenant,
        Service $service,
        User $employee,
        string $sessionId,
        string $startTime,
        Carbon $expiresAt,
        array $overrides = [],
    ): array {
        return array_merge([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-13',
            'start_time' => $startTime,
            'end_time' => Carbon::parse($startTime)->addMinutes(30)->format('H:i'),
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function migration(): object
    {
        $path = database_path('migrations/2026_07_11_000001_add_active_slot_key_to_booking_holds.php');

        $this->assertFileExists($path);

        return require $path;
    }

    private function activeSlotKeyFor(string $sessionId): ?string
    {
        return DB::table('booking_holds')->where('session_id', $sessionId)->value('active_slot_key');
    }

    private function hasIndex(string $name): bool
    {
        return collect(DB::select("PRAGMA index_list('booking_holds')"))
            ->contains(fn (object $index): bool => $index->name === $name);
    }
}
