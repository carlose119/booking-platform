<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_services');
    }
};
