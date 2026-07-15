<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Workspace\Domain\Messages\MessageType;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if ($modelName === 'App\\Models\\User') {
                return 'Database\\Factories\\UserFactory';
            }

            return 'Modules\\Workspace\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Config::set('chat_ai.enabled', true);
        Config::set('chat_ai.gemini_api_key', 'test-gemini-key');
        Config::set('chat_ai.gemini_model', 'gemini-2.0-flash');
        Config::set('chat_ai.image_enabled', true);
        Config::set('chat_ai.gemini_image_model', 'gemini-image-test');
        Config::set('chat_ai.user_email', 'ai@lumi.internal');
    }

    private function conversationWith(User ...$users): Conversation
    {
        $conversation = Conversation::factory()->create([
            'created_by' => $users[0]->id,
        ]);
        $conversation->participants()->attach(collect($users)->pluck('id'));

        return $conversation;
    }

    private function createBotUser(): User
    {
        return User::factory()->create([
            'name' => 'Lumi AI',
            'email' => 'ai@lumi.internal',
            'role' => UserRole::Employee,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function mentioning_lumi_creates_a_bot_reply(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'I can help with that.'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $bot = $this->createBotUser();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '@lumi summarize our tasks',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $bot->id,
            'message' => 'I can help with that.',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    #[Test]
    public function mentioning_lumi_can_generate_and_store_an_image(): void
    {
        Storage::fake('wasabi');
        Config::set('media.disk', 'wasabi');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'functionCall' => [
                                'name' => 'generate_image',
                                'args' => [
                                    'prompt' => 'A bright red bicycle on a white background',
                                    'aspect_ratio' => '4:3',
                                ],
                            ],
                        ]]],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [
                            ['text' => 'Here is your bicycle.'],
                            ['inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode($png),
                            ]],
                        ]],
                    ]],
                ]),
        ]);

        $bot = $this->createBotUser();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '@lumi draw a red bicycle',
            ])
            ->assertStatus(201);

        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $bot->id)
            ->firstOrFail();

        $this->assertSame(MessageType::Image, $message->message_type);
        $this->assertSame('Here is your bicycle.', $message->message);
        $this->assertTrue($message->meta['generated_by_ai']);
        $this->assertSame('image/png', $message->meta['image']['mime']);
        Storage::disk('wasabi')->assertExists($message->meta['image']['path']);

        Http::assertSentCount(2);
        $imageRequest = Http::recorded()[1][0];
        $this->assertStringContainsString('gemini-image-test:generateContent', $imageRequest->url());
        $this->assertSame(
            ['TEXT', 'IMAGE'],
            data_get($imageRequest->data(), 'generationConfig.responseModalities'),
        );
        $this->assertSame(
            '4:3',
            data_get($imageRequest->data(), 'generationConfig.imageConfig.aspectRatio'),
        );
    }

    #[Test]
    public function messages_without_a_mention_do_not_create_bot_replies(): void
    {
        Http::fake();

        $this->createBotUser();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => 'hello team',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent();
    }

    #[Test]
    public function bot_messages_do_not_trigger_another_bot_reply(): void
    {
        Http::fake();

        $bot = $this->createBotUser();
        $user = User::factory()->create();
        $conversation = $this->conversationWith($user, $bot);

        $this->actingAs($bot, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '@lumi echo',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent();
    }

    #[Test]
    public function bot_is_auto_joined_when_mentioned_in_a_conversation(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Joined and ready.'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $bot = $this->createBotUser();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '@ai hello',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $bot->id,
        ]);
    }

    #[Test]
    public function gemini_failure_posts_error_message_to_chat(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid JSON payload received.',
                ],
            ], 400),
        ]);

        $bot = $this->createBotUser();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '@lumi hello',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $bot->id,
            'message' => '[Lumi AI error] Invalid JSON payload received.',
        ]);
    }
}
