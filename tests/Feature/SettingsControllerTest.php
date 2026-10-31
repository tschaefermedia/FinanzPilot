<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AI\AiConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
    }

    public function test_api_key_encrypted_with_another_app_key_is_treated_as_missing(): void
    {
        Setting::set('ai_api_key', 'not-a-valid-ciphertext');

        $this->assertNull(AiConfigService::apiKey());
        $this->get('/settings')->assertInertia(fn ($page) => $page->where('settings.ai_api_key_set', false));
    }

    public function test_api_key_round_trips_through_encryption(): void
    {
        $this->put('/settings/ai', ['ai_provider' => 'claude', 'ai_api_key' => 'sk-ant-test']);
        Setting::flushCache();

        $this->assertSame('sk-ant-test', AiConfigService::apiKey());
        $this->get('/settings')->assertInertia(fn ($page) => $page->where('settings.ai_api_key_set', true));
    }
}
