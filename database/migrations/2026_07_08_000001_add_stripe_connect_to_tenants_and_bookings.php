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
            $table->string('payment_account_mode')->default('direct')->after('stripe_webhook_secret');
            $table->string('stripe_connected_account_id')->nullable()->after('payment_account_mode');
            $table->unique('stripe_connected_account_id', 'tenants_stripe_connected_account_id_unique');
            $table->boolean('stripe_connect_charges_enabled')->default(false)->after('stripe_connected_account_id');
            $table->boolean('stripe_connect_payouts_enabled')->default(false)->after('stripe_connect_charges_enabled');
            $table->string('stripe_connect_onboarding_status')->nullable()->after('stripe_connect_payouts_enabled');
            $table->timestamp('stripe_connect_onboarded_at')->nullable()->after('stripe_connect_onboarding_status');
        });

        DB::table('tenants')
            ->whereNull('payment_account_mode')
            ->update(['payment_account_mode' => 'direct']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_account_mode')->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_connected_account_id')->nullable()->after('payment_account_mode');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_account_mode', 'stripe_connected_account_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('tenants_stripe_connected_account_id_unique');
            $table->dropColumn([
                'payment_account_mode',
                'stripe_connected_account_id',
                'stripe_connect_charges_enabled',
                'stripe_connect_payouts_enabled',
                'stripe_connect_onboarding_status',
                'stripe_connect_onboarded_at',
            ]);
        });
    }
};
