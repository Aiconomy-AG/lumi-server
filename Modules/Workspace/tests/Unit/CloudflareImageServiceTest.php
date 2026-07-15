<?php

namespace Modules\Workspace\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Workspace\Services\AiChat\ImageGenerator;
use Modules\Workspace\Services\CloudflareImageService;
use Modules\Workspace\Services\GeminiChatService;
use Modules\Workspace\Services\GeminiImageService;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CloudflareImageServiceTest extends TestCase
{
    private const ONE_BY_ONE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chat_ai.cloudflare_account_id' => 'test-account',
            'chat_ai.cloudflare_api_token' => 'test-token',
            'chat_ai.cloudflare_image_model' => '@cf/black-forest-labs/flux-1-schnell',
        ]);
    }

    #[Test]
    public function it_generates_an_image_from_a_successful_response(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'result' => ['image' => self::ONE_BY_ONE_PNG],
            ]),
        ]);

        $image = app(CloudflareImageService::class)->generate('a red apple');

        $this->assertSame('image/png', $image->mimeType);
        $this->assertSame(1, $image->width);
        $this->assertSame(1, $image->height);
        $this->assertSame('@cf/black-forest-labs/flux-1-schnell', $image->model);
        $this->assertNotSame('', $image->bytes);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-token')
                && str_contains($request->url(), '/accounts/test-account/ai/run/@cf/black-forest-labs/flux-1-schnell')
                && $request['prompt'] === 'a red apple';
        });
    }

    #[Test]
    public function it_throws_the_provider_error_message_on_failure(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [['code' => 7009, 'message' => 'Account not authorized.']],
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account not authorized.');

        app(CloudflareImageService::class)->generate('a red apple');
    }

    #[Test]
    public function it_rejects_a_response_without_image_data(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => []]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare returned no generated image.');

        app(CloudflareImageService::class)->generate('a red apple');
    }

    #[Test]
    public function it_rejects_an_oversized_image(): void
    {
        config(['chat_ai.image_max_bytes' => 10]);

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'result' => ['image' => self::ONE_BY_ONE_PNG],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Generated image exceeds the configured size limit.');

        app(CloudflareImageService::class)->generate('a red apple');
    }

    #[Test]
    public function it_rejects_bytes_that_are_not_an_image(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'result' => ['image' => base64_encode('definitely not an image')],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported image type');

        app(CloudflareImageService::class)->generate('a red apple');
    }

    #[Test]
    public function it_throws_when_credentials_are_missing(): void
    {
        config(['chat_ai.cloudflare_api_token' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare Workers AI credentials are not configured.');

        app(CloudflareImageService::class)->generate('a red apple');
    }

    #[Test]
    public function image_intent_detection_matches_explicit_requests_only(): void
    {
        $service = GeminiChatService::class;

        $this->assertTrue($service::asksForImage('generate an image of a sunset'));
        $this->assertTrue($service::asksForImage('can you draw a picture of a cat?'));
        $this->assertTrue($service::asksForImage('make me a logo'));
        $this->assertTrue($service::asksForImage('genereaza o imagine cu un apus'));

        $this->assertFalse($service::asksForImage('what tasks do I have today?'));
        $this->assertFalse($service::asksForImage('the picture in the report looks fine'));
        $this->assertFalse($service::asksForImage('create a task for the deploy'));
    }

    #[Test]
    public function the_container_resolves_the_provider_from_config(): void
    {
        config(['chat_ai.image_provider' => 'cloudflare']);
        $this->assertInstanceOf(CloudflareImageService::class, app(ImageGenerator::class));

        config(['chat_ai.image_provider' => 'gemini']);
        $this->assertInstanceOf(GeminiImageService::class, app(ImageGenerator::class));

        config(['chat_ai.image_provider' => 'nonsense']);
        $this->assertInstanceOf(GeminiImageService::class, app(ImageGenerator::class));
    }
}
