<?php

return [
    'rate_limit_per_minute' => env('CHATBOT_RATE_LIMIT_PER_MINUTE', 20),
    'rag_cache_minutes' => env('CHATBOT_RAG_CACHE_MINUTES', 15),
    'tool_calling_enabled' => env('CHATBOT_TOOL_CALLING_ENABLED', true),
];
