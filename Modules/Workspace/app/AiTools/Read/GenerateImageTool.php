<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Services\AiChat\GeneratedImage;
use Modules\Workspace\Services\AiChat\ImageGenerator;

class GenerateImageTool extends AbstractAiTool
{
    public function __construct(
        private readonly ImageGenerator $imageService,
    ) {}

    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate an original image when the user explicitly asks to create, draw, render, or generate an image. '
            .'Write a detailed standalone visual prompt in the same language as the request.';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Detailed standalone description of the image to generate.',
                ],
                'aspect_ratio' => [
                    'type' => 'string',
                    'enum' => ['1:1', '3:4', '4:3', '9:16', '16:9'],
                    'description' => 'Output aspect ratio. Use 1:1 when the user gives no preference.',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
            'aspect_ratio' => ['sometimes', 'string', 'in:1:1,3:4,4:3,9:16,16:9'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return (bool) config('chat_ai.image_enabled', false);
    }

    public function execute(User $user, array $arguments): GeneratedImage|array
    {
        $limit = (int) config('chat_ai.image_rate_limit', 3);
        $key = 'ai-chat-image:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return ['error' => 'Image generation rate limit exceeded. Please wait a minute and try again.'];
        }

        RateLimiter::hit($key, 60);

        return $this->imageService->generate(
            $arguments['prompt'],
            $arguments['aspect_ratio'] ?? '1:1',
        );
    }

    public function summarize(array $arguments): string
    {
        return 'Generate an image';
    }
}
