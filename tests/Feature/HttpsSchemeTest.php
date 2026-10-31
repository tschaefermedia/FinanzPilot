<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class HttpsSchemeTest extends TestCase
{
    public function test_urls_use_https_when_app_url_is_https(): void
    {
        config(['app.url' => 'https://finanzen.example.com']);

        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_urls_keep_http_when_app_url_is_http_in_production(): void
    {
        config(['app.url' => 'http://localhost:8080']);
        $this->app['env'] = 'production';

        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->assertStringStartsWith('http://', url('/'));
    }
}
