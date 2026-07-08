<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('default_currency', 3)->nullable()->default('usd')->after('slug');
        });

        DB::table('tenants')
            ->whereNull('default_currency')
            ->update(['default_currency' => 'usd']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('payment_amount_cents')->nullable()->after('payment_status');
            $table->string('payment_currency', 3)->nullable()->after('payment_amount_cents');
        });

        DB::table('bookings')
            ->whereNotNull('stripe_payment_intent_id')
            ->whereNull('payment_currency')
            ->update(['payment_currency' => 'usd']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_amount_cents', 'payment_currency']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('default_currency');
        });
    }
};
