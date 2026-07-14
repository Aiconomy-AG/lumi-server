<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Modules\Workspace\Enums\AiActionStatus;
use Modules\Workspace\Models\AiAction;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\GeminiChatService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiToolLoopTest extends TestCase
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

        Config::set('chat_ai.gemini_api_key', 'test-key');
        Config::set('chat_ai.gemini_model', 'gemini-test');
        Config::set('chat_ai.max_tool_iterations', 5);
    }

    private function createBotUser(): User
    {
        Config::set('chat_ai.user_email', 'ai@lumi.internal');

        return User::factory()->create([
            'email' => 'ai@lumi.internal',
            'role' => UserRole::Employee,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function text_only_response_returns_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Here is your answer.']]],
                ]],
            ]),
        ]);

        $this->createBotUser();
        $user = User::factory()->create(['role' => UserRole::Employee]);
        $messages = collect();

        $result = app(GeminiChatService::class)->generateReply(
            $messages,
            'What is up?',
            0,
            $user,
        );

        $this->assertNotNull($result);
        $this->assertFalse($result->hasError());
        $this->assertSame('Here is your answer.', $result->text);
        $this->assertFalse($result->hasProposedAction());
    }

    #[Test]
    public function read_tool_executes_and_continues_loop(): void
    {
        $project = Project::query()->create([
            'name' => 'Ops',
            'deadline' => '2026-08-01',
            'description' => 'Desc',
            'status' => 'in_progress',
        ]);

        Task::query()->create([
            'title' => 'Restock',
            'description' => 'Do it',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $project->id,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'functionCall' => ['name' => 'list_tasks', 'args' => []],
                        ]]],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'You have 1 task.']]],
                    ]],
                ]),
        ]);

        $this->createBotUser();
        $user = User::factory()->create(['role' => UserRole::Employee]);

        $result = app(GeminiChatService::class)->generateReply(
            collect(),
            'List my tasks',
            0,
            $user,
        );

        $this->assertNotNull($result);
        $this->assertFalse($result->hasError());
        $this->assertSame('You have 1 task.', $result->text);
        Http::assertSentCount(2);

        $secondRequest = Http::recorded()[1][0];
        $args = data_get($secondRequest->data(), 'contents.1.parts.0.functionCall.args');
        $this->assertTrue(is_object($args) || (is_array($args) && ! array_is_list($args)));
    }

    #[Test]
    public function write_tool_returns_proposed_action_without_mutating(): void
    {
        $project = Project::query()->create([
            'name' => 'Ops',
            'deadline' => '2026-08-01',
            'description' => 'Desc',
            'status' => 'in_progress',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'functionCall' => [
                            'name' => 'create_task',
                            'args' => [
                                'title' => 'New task',
                                'description' => 'From AI',
                                'status' => 'to_do',
                                'due_date' => '2026-07-25',
                                'project_id' => $project->id,
                            ],
                        ],
                    ]]],
                ]],
            ]),
        ]);

        $this->createBotUser();
        $user = User::factory()->create(['role' => UserRole::Employee]);

        $result = app(GeminiChatService::class)->generateReply(
            collect(),
            'Create a task',
            0,
            $user,
        );

        $this->assertNotNull($result);
        $this->assertFalse($result->hasError());
        $this->assertTrue($result->hasProposedAction());
        $this->assertSame('create_task', $result->proposedAction->toolName);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('ai_actions', 0);
    }

    #[Test]
    public function employee_tool_declarations_exclude_stock_tool(): void
    {
        $registry = app(\Modules\Workspace\AiTools\ToolRegistry::class);
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        $names = collect($registry->declarationsFor($employee))->pluck('name')->all();

        $this->assertContains('list_tasks', $names);
        $this->assertNotContains('update_stock', $names);
    }
}
