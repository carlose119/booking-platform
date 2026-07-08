<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table) {
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

            $table->unique(
                ['tenant_id', 'employee_id', 'date', 'start_time', 'end_time'],
                'booking_holds_unique_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
