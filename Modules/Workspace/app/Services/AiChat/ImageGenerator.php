<?php

namespace Modules\Workspace\Services\AiChat;

interface ImageGenerator
{
    /**
     * @throws \RuntimeException on provider failure
     */
    public function generate(string $prompt, string $aspectRatio = '1:1'): GeneratedImage;
}
