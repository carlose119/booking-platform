<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_SLOT_KEY = 'active';

    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_holds', 'active_slot_key')) {
                $table->string('active_slot_key')->nullable()->default(self::ACTIVE_SLOT_KEY)->after('expires_at');
            }
        });

        DB::table('booking_holds')
            ->where('expires_at', '>', now())
            ->update(['active_slot_key' => self::ACTIVE_SLOT_KEY]);

        DB::table('booking_holds')
            ->where('expires_at', '<=', now())
            ->update(['active_slot_key' => null]);

        $duplicates = $this->duplicateSlotRows(
            DB::table('booking_holds')->where('active_slot_key', self::ACTIVE_SLOT_KEY)
        );

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Duplicate active booking holds detected before active-slot index migration: '
                .$this->formatDuplicateSlotRows($duplicates)
            );
        }

        Schema::table('booking_holds', function (Blueprint $table): void {
            if (! $this->indexExists('booking_holds_tenant_id_fk_support')) {
                $table->index('tenant_id', 'booking_holds_tenant_id_fk_support');
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            if (! $this->indexExists('booking_holds_unique_active_slot')) {
                $table->unique(
                    ['tenant_id', 'employee_id', 'date', 'start_time', 'end_time', 'active_slot_key'],
                    'booking_holds_unique_active_slot'
                );
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            if ($this->indexExists('booking_holds_unique_slot')) {
                $table->dropUnique('booking_holds_unique_slot');
            }
        });
    }

    public function down(): void
    {
        $duplicates = $this->duplicateSlotRows(DB::table('booking_holds'));

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot roll back booking hold active-slot semantics while duplicate slot rows exist. '
                .'Delete expired/non-active duplicates first: '
                .$this->formatDuplicateSlotRows($duplicates)
            );
        }

        Schema::table('booking_holds', function (Blueprint $table): void {
            if (! $this->indexExists('booking_holds_unique_slot')) {
                $table->unique(
                    ['tenant_id', 'employee_id', 'date', 'start_time', 'end_time'],
                    'booking_holds_unique_slot'
                );
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            if ($this->indexExists('booking_holds_unique_active_slot')) {
                $table->dropUnique('booking_holds_unique_active_slot');
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_holds', 'active_slot_key')) {
                $table->dropColumn('active_slot_key');
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            if ($this->indexExists('booking_holds_tenant_id_fk_support')) {
                $table->dropIndex('booking_holds_tenant_id_fk_support');
            }
        });
    }

    private function duplicateSlotRows($query)
    {
        return $query
            ->select([
                'tenant_id',
                'employee_id',
                'date',
                'start_time',
                'end_time',
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('GROUP_CONCAT(id) as ids'),
            ])
            ->groupBy('tenant_id', 'employee_id', 'date', 'start_time', 'end_time')
            ->having('duplicate_count', '>', 1)
            ->get();
    }

    private function formatDuplicateSlotRows($duplicates): string
    {
        return $duplicates->map(fn (object $row): string => sprintf(
            'tenant_id=%s employee_id=%s date=%s start_time=%s end_time=%s ids=%s',
            $row->tenant_id,
            $row->employee_id,
            $row->date,
            $row->start_time,
            $row->end_time,
            $row->ids
        ))->implode('; ');
    }

    private function indexExists(string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('booking_holds')"))
                ->contains(fn (object $index): bool => $index->name === $indexName);
        }

        return (int) DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'booking_holds')
            ->where('index_name', $indexName)
            ->count() > 0;
    }
};
