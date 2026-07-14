<?php

namespace Modules\Workspace\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Infrastructure\LiveKitMediaRoomTokenProvider;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Policies\ConversationPolicy;
use Modules\Workspace\Policies\ProjectPolicy;
use Modules\Workspace\Policies\TaskPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WorkspaceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Workspace';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'workspace';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(MediaRoomTokenProvider::class, LiveKitMediaRoomTokenProvider::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
