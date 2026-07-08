<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('notification_channel')->default('email');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
