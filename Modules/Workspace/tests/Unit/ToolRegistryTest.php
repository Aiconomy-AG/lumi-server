<?php

namespace Modules\Workspace\Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workspace\AiTools\ToolRegistry;
use Modules\Workspace\AiTools\Write\CreateTaskTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_gets_all_tools_including_stock(): void
    {
        $registry = app(ToolRegistry::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $names = collect($registry->declarationsFor($admin))->pluck('name')->all();

        $this->assertContains('update_stock', $names);
        $this->assertContains('create_task', $names);
    }

    #[Test]
    public function create_task_summarize_uses_title(): void
    {
        $tool = app(CreateTaskTool::class);

        $summary = $tool->summarize(['title' => 'Restock candles']);

        $this->assertSame('Create task: Restock candles', $summary);
    }
}
