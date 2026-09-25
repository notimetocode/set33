<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    public function test_privacy_policy_page_is_publicly_available(): void
    {
        $response = $this->get(route('public.privacy'));

        $response->assertOk();
        $response->assertSee('Политика конфиденциальности', false);
        $response->assertSee('Privacy Policy', false);
    }

    public function test_terms_of_service_page_is_publicly_available(): void
    {
        $response = $this->get(route('public.terms'));

        $response->assertOk();
        $response->assertSee('Условия использования', false);
        $response->assertSee('Terms of Service', false);
    }

    public function test_home_page_links_to_legal_pages(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee(route('public.privacy'), false);
        $response->assertSee(route('public.terms'), false);
    }
}
