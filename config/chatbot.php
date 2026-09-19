<?php

return [
    'rate_limit_per_minute' => env('CHATBOT_RATE_LIMIT_PER_MINUTE', 20),
    'rag_cache_minutes' => env('CHATBOT_RAG_CACHE_MINUTES', 15),
    'tool_calling_enabled' => env('CHATBOT_TOOL_CALLING_ENABLED', true),

    /*
    | Provider LLM chính của SmashBot: 'groq' hoặc 'openai'.
    | Khi provider chọn không có API key, hệ thống tự rơi về provider còn lại
    | và cuối cùng là engine luật (knowledge-v3) nếu không có key nào.
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    | true: SmashBot dùng agent tool calling cho mọi câu hỏi người dùng nhập,
    | kể cả câu hỏi ngoài nghiệp vụ, để trả lời được tất cả các câu hỏi.
    | false: chỉ gọi agent khi câu hỏi khớp từ khoá dữ liệu (tiết kiệm token).
    */
    'tool_calling_always' => env('CHATBOT_TOOL_CALLING_ALWAYS', true),

    /*
    | true: khi câu hỏi KHÔNG khớp từ khoá dữ liệu (kiến thức chung, luật chơi),
    | chỉ gửi nhóm tool tra cứu cơ bản (tìm sân, lịch trống, khuyến mãi) cho LLM
    | để tiết kiệm token/phút của Groq. false: luôn gửi đầy đủ danh sách tool.
    */
    'tiered_tools' => env('CHATBOT_TIERED_TOOLS', true),

    // Số lượt hỏi/đáp gần nhất gửi kèm để hiểu câu hỏi nối tiếp.
    'history_turns' => env('CHATBOT_HISTORY_TURNS', 6),

    // Số vòng tối đa của vòng lặp gọi tool.
    'max_tool_rounds' => env('CHATBOT_MAX_TOOL_ROUNDS', 4),

    'max_message_length' => env('CHATBOT_MAX_MESSAGE_LENGTH', 500),
];
