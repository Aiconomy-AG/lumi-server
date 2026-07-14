<?php

return [
    'enabled' => env('CHAT_AI_ENABLED', false),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),

    'user_email' => env('CHAT_AI_USER_EMAIL', 'ai@lumi.internal'),

    'user_name' => env('CHAT_AI_USER_NAME', 'Lumi AI'),

    'history_limit' => (int) env('CHAT_AI_HISTORY_LIMIT', 20),

    'mention_patterns' => ['@lumi', '@ai'],

    'max_tool_iterations' => (int) env('CHAT_AI_MAX_TOOL_ITERATIONS', 5),

    'action_ttl_minutes' => (int) env('CHAT_AI_ACTION_TTL_MINUTES', 15),

    'workspace_timezone' => env('APP_WORKSPACE_TIMEZONE', 'Europe/Bucharest'),

    'tools' => [
        'get_current_time' => \Modules\Workspace\AiTools\Read\GetCurrentTimeTool::class,
        'list_tasks' => \Modules\Workspace\AiTools\Read\ListTasksTool::class,
        'get_task' => \Modules\Workspace\AiTools\Read\GetTaskTool::class,
        'list_projects' => \Modules\Workspace\AiTools\Read\ListProjectsTool::class,
        'list_users' => \Modules\Workspace\AiTools\Read\ListUsersTool::class,
        'search_products' => \Modules\Workspace\AiTools\Read\SearchProductsTool::class,
        'create_task' => \Modules\Workspace\AiTools\Write\CreateTaskTool::class,
        'update_task' => \Modules\Workspace\AiTools\Write\UpdateTaskTool::class,
        'delete_task' => \Modules\Workspace\AiTools\Write\DeleteTaskTool::class,
        'assign_task_employees' => \Modules\Workspace\AiTools\Write\AssignTaskEmployeesTool::class,
        'update_stock' => \Modules\Workspace\AiTools\Write\UpdateStockTool::class,
        'create_group_conversation' => \Modules\Workspace\AiTools\Write\CreateGroupConversationTool::class,
        'update_conversation_participants' => \Modules\Workspace\AiTools\Write\UpdateConversationParticipantsTool::class,
    ],

    'tool_roles' => [
        'admin' => [
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
            'get_current_time',
            'list_users',
            'create_group_conversation',
            'update_conversation_participants',
        ],
    ],
];
