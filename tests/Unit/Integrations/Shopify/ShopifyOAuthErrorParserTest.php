<?php

namespace Tests\Unit\Integrations\Shopify;

use App\Integrations\Shopify\ShopifyOAuthErrorParser;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class ShopifyOAuthErrorParserTest extends TestCase
{
    public function test_it_parses_json_oauth_errors(): void
    {
        $response = new Response(new \GuzzleHttp\Psr7\Response(400, [], json_encode([
            'error' => 'invalid_client',
            'error_description' => 'Invalid client credentials.',
        ])));

        $this->assertSame(
            'Failed to obtain Shopify access token: invalid_client — Invalid client credentials.',
            ShopifyOAuthErrorParser::messageFromResponse($response, 'Failed to obtain Shopify access token'),
        );
    }

    public function test_it_parses_html_oauth_errors(): void
    {
        $response = new Response(new \GuzzleHttp\Psr7\Response(
            400,
            [],
            '<title>400 - Oauth error app_not_installed</title>',
        ));

        $this->assertSame(
            'app_not_installed',
            ShopifyOAuthErrorParser::extractOAuthError($response),
        );
    }
}
