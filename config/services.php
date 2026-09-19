<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'openai' => [
        'enabled' => env('OPENAI_ENABLED', true),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'rag_threshold' => env('OPENAI_RAG_THRESHOLD', 0.68),
        'rag_direct_threshold' => env('OPENAI_RAG_DIRECT_THRESHOLD', 0.84),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => env('OPENAI_TIMEOUT', 15),
        'attempts' => env('OPENAI_ATTEMPTS', 3),
    ],

    /*
    | Groq Cloud chạy trên endpoint OpenAI-compatible /chat/completions nên
    | SmashBot dùng chung tool calling + structured outputs với hạ tầng OpenAI.
    | Model mặc định gpt-oss-120b hỗ trợ cả function calling lẫn json_schema strict.
    */
    'groq' => [
        'enabled' => env('GROQ_ENABLED', true),
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'timeout' => env('GROQ_TIMEOUT', 20),
        'attempts' => env('GROQ_ATTEMPTS', 3),
        'max_completion_tokens' => env('GROQ_MAX_COMPLETION_TOKENS', 700),
        'temperature' => env('GROQ_TEMPERATURE', 0.3),
        // gpt-oss mặc định suy luận rất dài; 'low' giúp tiết kiệm token/phút
        // của Groq. Đặt rỗng để tắt hoàn toàn tham số này.
        'reasoning_effort' => env('GROQ_REASONING_EFFORT', 'low'),
    ],

];
