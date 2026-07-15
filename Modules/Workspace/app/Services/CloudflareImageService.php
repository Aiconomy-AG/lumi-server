<?php

namespace Modules\Workspace\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Services\AiChat\BaseImageService;
use Modules\Workspace\Services\AiChat\GeneratedImage;
use RuntimeException;

class CloudflareImageService extends BaseImageService
{
    public function generate(string $prompt, string $aspectRatio = '1:1'): GeneratedImage
    {
        $accountId = config('chat_ai.cloudflare_account_id');
        $token = config('chat_ai.cloudflare_api_token');
        $model = config('chat_ai.cloudflare_image_model');

        if (! filled($accountId) || ! filled($token)) {
            throw new RuntimeException('Cloudflare Workers AI credentials are not configured.');
        }

        if (! filled($model)) {
            throw new RuntimeException('Cloudflare image model is not configured.');
        }

        $response = Http::timeout((int) config('chat_ai.image_timeout_seconds', 120))
            ->withToken($token)
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                'prompt' => $prompt,
                'steps' => 4,
            ]);

        if (! $response->successful() || $response->json('success') === false) {
            $message = data_get($response->json(), 'errors.0.message');

            Log::warning('Cloudflare image request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                is_string($message) && $message !== ''
                    ? $message
                    : "Cloudflare image request failed (HTTP {$response->status()}).",
            );
        }

        $encoded = $response->json('result.image');

        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Cloudflare returned no generated image.');
        }

        $bytes = base64_decode($encoded, true);

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Cloudflare returned invalid base64 image data.');
        }

        return $this->makeGeneratedImage($bytes, null, null, (string) $model);
    }
}
