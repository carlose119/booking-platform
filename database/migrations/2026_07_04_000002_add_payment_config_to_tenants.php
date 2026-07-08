<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payment_policy')->default('nopayment')->after('slug');
            $table->unsignedTinyInteger('deposit_percentage')->nullable()->after('payment_policy');
            $table->unsignedSmallInteger('refund_window_hours')->default(24)->after('deposit_percentage');
            $table->text('stripe_api_key')->nullable()->after('refund_window_hours');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'payment_policy',
                'deposit_percentage',
                'refund_window_hours',
                'stripe_api_key',
                'stripe_webhook_secret',
            ]);
        });
    }
};
