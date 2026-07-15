<?php

namespace Modules\Workspace\Services\AiChat;

class GeneratedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $caption = null,
        public readonly ?string $model = null,
    ) {}
}
