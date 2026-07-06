<?php

namespace Tests\Unit\Integrations\Shopify;

use App\Integrations\Shopify\ShopifyGraphQlErrorParser;
use PHPUnit\Framework\TestCase;

class ShopifyGraphQlErrorParserTest extends TestCase
{
    public function test_it_formats_graphql_errors_with_code_path_and_location(): void
    {
        $message = ShopifyGraphQlErrorParser::messageFromErrors([
            [
                'message' => 'Access denied for shop field.',
                'path' => ['shop'],
                'locations' => [['line' => 2, 'column' => 5]],
                'extensions' => ['code' => 'ACCESS_DENIED'],
            ],
        ]);

        $this->assertStringContainsString('[ACCESS_DENIED] Access denied for shop field.', $message);
        $this->assertStringContainsString('(path: shop)', $message);
        $this->assertStringContainsString('(line 2, column 5)', $message);
    }

    public function test_it_redacts_tokens_from_error_messages(): void
    {
        $formatted = ShopifyGraphQlErrorParser::formatErrors([
            ['message' => 'Invalid token shpat_abc123 in request'],
        ]);

        $this->assertStringContainsString('[redacted]', $formatted);
        $this->assertStringNotContainsString('shpat_abc123', $formatted);
    }

    public function test_it_formats_errors_as_json(): void
    {
        $json = ShopifyGraphQlErrorParser::formatErrorsAsJson([
            [
                'message' => 'Access denied',
                'extensions' => ['code' => 'ACCESS_DENIED'],
            ],
        ]);

        $this->assertStringContainsString('"code": "ACCESS_DENIED"', $json);
        $this->assertStringContainsString('"message": "Access denied"', $json);
    }
}
