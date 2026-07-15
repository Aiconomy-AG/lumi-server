<?php

namespace Modules\Workspace\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Services\AiChat\BaseImageService;
use Modules\Workspace\Services\AiChat\GeneratedImage;
use RuntimeException;

class GeminiImageService extends BaseImageService
{
    public function generate(string $prompt, string $aspectRatio = '1:1'): GeneratedImage
    {
        $apiKey = config('chat_ai.gemini_api_key');
        $model = config('chat_ai.gemini_image_model');

        if (! filled($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        if (! filled($model)) {
            throw new RuntimeException('Gemini image model is not configured.');
        }

        $response = Http::timeout((int) config('chat_ai.image_timeout_seconds', 120))
            ->withQueryParameters(['key' => $apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => $aspectRatio,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $message = data_get($response->json(), 'error.message');

            Log::warning('Gemini image request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                is_string($message) && $message !== ''
                    ? $message
                    : "Gemini image request failed (HTTP {$response->status()}).",
            );
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $imagePart = collect(is_array($parts) ? $parts : [])
            ->first(fn ($part) => is_array($part) && isset($part['inlineData']['data']));

        if (! is_array($imagePart)) {
            throw new RuntimeException('Gemini returned no generated image.');
        }

        $encoded = data_get($imagePart, 'inlineData.data');
        $mimeType = data_get($imagePart, 'inlineData.mimeType', 'image/png');

        if (! is_string($encoded) || ! is_string($mimeType)) {
            throw new RuntimeException('Gemini returned invalid image data.');
        }

        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Gemini returned invalid base64 image data.');
        }

        $caption = collect($parts)
            ->pluck('text')
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->map(fn (string $text) => trim($text))
            ->implode("\n");

        return $this->makeGeneratedImage(
            $bytes,
            $mimeType,
            $caption !== '' ? $caption : null,
            (string) $model,
        );
    }
}
