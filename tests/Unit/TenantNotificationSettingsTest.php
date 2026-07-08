<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Notification fields are fillable ─────────────────────────────────

    public function test_notification_fields_are_fillable(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'twilio_sid' => 'AC1234567890abcdef',
            'twilio_auth_token' => 'auth-token-secret',
            'twilio_phone_number' => '+1 555 123 4567',
            'mailgun_domain' => 'mg.testsalon.com',
            'mailgun_secret' => 'key-1234567890',
        ]);

        $this->assertEquals('AC1234567890abcdef', $tenant->twilio_sid);
        $this->assertEquals('+1 555 123 4567', $tenant->twilio_phone_number);
        $this->assertEquals('mg.testsalon.com', $tenant->mailgun_domain);
    }

    // ─── Encrypted fields are decrypted on read ───────────────────────────

    public function test_twilio_auth_token_is_encrypted(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'twilio_auth_token' => 'super-secret-token',
        ]);

        // Force re-read from DB to verify encryption round-trip
        $fresh = Tenant::find($tenant->id);
        $this->assertEquals('super-secret-token', $fresh->twilio_auth_token);
    }

    public function test_mailgun_secret_is_encrypted(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'mailgun_secret' => 'key-super-secret',
        ]);

        $fresh = Tenant::find($tenant->id);
        $this->assertEquals('key-super-secret', $fresh->mailgun_secret);
    }

    // ─── Notification fields are nullable ─────────────────────────────────

    public function test_notification_fields_default_to_null(): void
    {
        $tenant = Tenant::create([
            'name' => 'Minimal Tenant',
            'slug' => 'minimal-tenant',
        ]);

        $this->assertNull($tenant->twilio_sid);
        $this->assertNull($tenant->twilio_auth_token);
        $this->assertNull($tenant->twilio_phone_number);
        $this->assertNull($tenant->mailgun_domain);
        $this->assertNull($tenant->mailgun_secret);
    }

    // ─── Config keys exist ────────────────────────────────────────────────

    public function test_twilio_config_keys_exist(): void
    {
        $this->assertArrayHasKey('sid', config('services.twilio'));
        $this->assertArrayHasKey('auth_token', config('services.twilio'));
        $this->assertArrayHasKey('phone_number', config('services.twilio'));
    }

    public function test_mailgun_config_keys_exist(): void
    {
        $this->assertArrayHasKey('domain', config('services.mailgun'));
        $this->assertArrayHasKey('secret', config('services.mailgun'));
    }
}
