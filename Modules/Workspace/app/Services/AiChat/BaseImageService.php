<?php

namespace Modules\Workspace\Services\AiChat;

use RuntimeException;

abstract class BaseImageService implements ImageGenerator
{
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    protected function makeGeneratedImage(string $bytes, ?string $mimeType, ?string $caption, string $model): GeneratedImage
    {
        if ($bytes === '') {
            throw new RuntimeException('The image provider returned an empty image.');
        }

        $maxBytes = (int) config('chat_ai.image_max_bytes', 15 * 1024 * 1024);
        if (strlen($bytes) > $maxBytes) {
            throw new RuntimeException('Generated image exceeds the configured size limit.');
        }

        $dimensions = @getimagesizefromstring($bytes);
        $mimeType ??= is_array($dimensions) ? ($dimensions['mime'] ?? null) : null;

        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException(
                is_string($mimeType) && $mimeType !== ''
                    ? "The image provider returned an unsupported image type: {$mimeType}."
                    : 'The image provider returned an unsupported image type.',
            );
        }

        return new GeneratedImage(
            bytes: $bytes,
            mimeType: $mimeType,
            width: is_array($dimensions) ? $dimensions[0] : null,
            height: is_array($dimensions) ? $dimensions[1] : null,
            caption: $caption,
            model: $model,
        );
    }
}
