<?php

use Modules\Workspace\AiTools\Read\GenerateImageTool;
use Modules\Workspace\AiTools\Read\GetCurrentTimeTool;
use Modules\Workspace\AiTools\Read\GetTaskTool;
use Modules\Workspace\AiTools\Read\ListProjectsTool;
use Modules\Workspace\AiTools\Read\ListTasksTool;
use Modules\Workspace\AiTools\Read\ListUsersTool;
use Modules\Workspace\AiTools\Read\SearchProductsTool;
use Modules\Workspace\AiTools\Write\AssignTaskEmployeesTool;
use Modules\Workspace\AiTools\Write\CreateGroupConversationTool;
use Modules\Workspace\AiTools\Write\CreateTaskTool;
use Modules\Workspace\AiTools\Write\DeleteTaskTool;
use Modules\Workspace\AiTools\Write\UpdateConversationParticipantsTool;
use Modules\Workspace\AiTools\Write\UpdateStockTool;
use Modules\Workspace\AiTools\Write\UpdateTaskTool;

return [
    'enabled' => env('CHAT_AI_ENABLED', false),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),

    'image_enabled' => env('CHAT_AI_IMAGE_ENABLED', false),

    'image_provider' => env('CHAT_AI_IMAGE_PROVIDER', 'gemini'),

    'gemini_image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),

    'cloudflare_account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

    'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),

    'cloudflare_image_model' => env('CLOUDFLARE_IMAGE_MODEL', '@cf/black-forest-labs/flux-1-schnell'),

    'image_rate_limit' => (int) env('CHAT_AI_IMAGE_RATE_LIMIT', 3),

    'image_timeout_seconds' => (int) env('CHAT_AI_IMAGE_TIMEOUT_SECONDS', 120),

    'image_max_bytes' => (int) env('CHAT_AI_IMAGE_MAX_BYTES', 15 * 1024 * 1024),

    'user_email' => env('CHAT_AI_USER_EMAIL', 'ai@lumi.internal'),

    'user_name' => env('CHAT_AI_USER_NAME', 'Lumi AI'),

    'history_limit' => (int) env('CHAT_AI_HISTORY_LIMIT', 20),

    'mention_patterns' => ['@lumi', '@ai'],

    'max_tool_iterations' => (int) env('CHAT_AI_MAX_TOOL_ITERATIONS', 5),

    'action_ttl_minutes' => (int) env('CHAT_AI_ACTION_TTL_MINUTES', 15),

    'workspace_timezone' => env('APP_WORKSPACE_TIMEZONE', 'Europe/Bucharest'),

    'tools' => [
        'generate_image' => GenerateImageTool::class,
        'get_current_time' => GetCurrentTimeTool::class,
        'list_tasks' => ListTasksTool::class,
        'get_task' => GetTaskTool::class,
        'list_projects' => ListProjectsTool::class,
        'list_users' => ListUsersTool::class,
        'search_products' => SearchProductsTool::class,
        'create_task' => CreateTaskTool::class,
        'update_task' => UpdateTaskTool::class,
        'delete_task' => DeleteTaskTool::class,
        'assign_task_employees' => AssignTaskEmployeesTool::class,
        'update_stock' => UpdateStockTool::class,
        'create_group_conversation' => CreateGroupConversationTool::class,
        'update_conversation_participants' => UpdateConversationParticipantsTool::class,
    ],

    'tool_roles' => [
        'admin' => [
            'generate_image',
            'get_current_time',
            'list_tasks',
            'get_task',
            'list_projects',
            'list_users',
            'search_products',
            'create_task',
            'update_task',
            'delete_task',
            'assign_task_employees',
            'update_stock',
            'create_group_conversation',
            'update_conversation_participants',
        ],
        'employee' => [
            'generate_image',
            'get_current_time',
            'list_tasks',
            'get_task',
            'list_projects',
            'list_users',
            'search_products',
            'create_task',
            'update_task',
            'delete_task',
            'assign_task_employees',
            'create_group_conversation',
            'update_conversation_participants',
        ],
        'client' => [
            'generate_image',
            'get_current_time',
            'list_users',
            'create_group_conversation',
            'update_conversation_participants',
        ],
    ],
];
