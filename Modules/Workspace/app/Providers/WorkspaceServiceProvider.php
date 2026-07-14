<?php

namespace Modules\Workspace\Providers;

use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Infrastructure\LiveKitMediaRoomTokenProvider;
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
