<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('twilio_sid')->nullable()->after('stripe_webhook_secret');
            $table->text('twilio_auth_token')->nullable()->after('twilio_sid');
            $table->string('twilio_phone_number')->nullable()->after('twilio_auth_token');
            $table->string('mailgun_domain')->nullable()->after('twilio_phone_number');
            $table->text('mailgun_secret')->nullable()->after('mailgun_domain');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'twilio_sid',
                'twilio_auth_token',
                'twilio_phone_number',
                'mailgun_domain',
                'mailgun_secret',
            ]);
        });
    }
};
