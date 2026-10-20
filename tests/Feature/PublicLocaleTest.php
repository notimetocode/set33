<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLocaleTest extends TestCase
{
    public function test_home_defaults_to_english(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('AI SEO analytics for your site', false);
        $response->assertSee('lang="en"', false);
    }

    public function test_home_russian_prefix(): void
    {
        $response = $this->get('/ru');

        $response->assertOk();
        $response->assertSee('ИИ-аналитика SEO сайта', false);
        $response->assertSee('lang="ru"', false);
    }

    public function test_privacy_localized_paths(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy', false);

        $this->get('/ru/privacy')
            ->assertOk()
            ->assertSee('Политика конфиденциальности', false);
    }

    public function test_russian_discover_docs_keep_locale_alternates(): void
    {
        $this->get('/ru/docs/discover')
            ->assertOk()
            ->assertSee('hreflang="en"', false)
            ->assertSee(url('/docs/discover'), false)
            ->assertSee(url('/ru/docs/discover.md'), false);

        $this->get('/ru/docs/discover.md')
            ->assertOk()
            ->assertSee('# Проверка URL для Google Discover', false);
    }

    public function test_unsupported_locale_prefix_returns_not_found(): void
    {
        $this->get('/de')->assertNotFound();
        $this->get('/en')->assertNotFound();
    }
}
