<?php

return [
    'enabled' => env('CHAT_AI_ENABLED', false),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'user_email' => env('CHAT_AI_USER_EMAIL', 'ai@lumi.internal'),

    'user_name' => env('CHAT_AI_USER_NAME', 'Lumi AI'),

    'history_limit' => (int) env('CHAT_AI_HISTORY_LIMIT', 20),

    'mention_patterns' => ['@lumi', '@ai'],
];
