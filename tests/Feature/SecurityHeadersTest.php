<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_pages_send_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(), microphone=()');
    }

    public function test_https_pages_send_strict_transport_security_header(): void
    {
        $response = $this->withServerVariables(['HTTPS' => 'on', 'SERVER_PORT' => 443])->get('https://localhost/privacidade');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_forwarded_https_scheme_is_used_for_asset_urls(): void
    {
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '10.0.0.10',
        ])->get('/login');

        $response->assertOk();
        $response->assertSee('https://localhost:8000/build/assets/', false);
    }
}
