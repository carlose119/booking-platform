<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('previous_date')->nullable()->after('end_time');
            $table->time('previous_start_time')->nullable()->after('previous_date');
            $table->time('previous_end_time')->nullable()->after('previous_start_time');
            $table->foreignId('rescheduled_by')
                ->nullable()
                ->after('previous_end_time')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reschedule_reason')->nullable()->after('rescheduled_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rescheduled_by');
            $table->dropColumn([
                'previous_date',
                'previous_start_time',
                'previous_end_time',
                'reschedule_reason',
            ]);
        });
    }
};
