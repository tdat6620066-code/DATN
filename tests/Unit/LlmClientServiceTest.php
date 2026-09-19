<?php

namespace Tests\Unit;

use App\Services\GroqService;
use App\Services\LlmClientService;
use App\Services\OpenAiService;
use App\Services\SmashBotPromptService;
use Tests\TestCase;

class LlmClientServiceTest extends TestCase
{
    public function test_it_prefers_groq_when_both_providers_are_configured(): void
    {
        config(['chatbot.provider' => 'groq', 'services.groq.enabled' => true, 'services.groq.api_key' => 'gsk_test', 'services.openai.api_key' => 'sk-test']);

        $llm = new LlmClientService(new GroqService, new OpenAiService, new SmashBotPromptService);

        $this->assertSame('groq', $llm->provider());
        $this->assertSame('openai/gpt-oss-120b', $llm->model());
        $this->assertTrue($llm->configured());
    }

    public function test_it_falls_back_to_openai_when_groq_key_is_missing(): void
    {
        config(['chatbot.provider' => 'groq', 'services.groq.api_key' => null, 'services.openai.api_key' => 'sk-test', 'services.openai.enabled' => true]);

        $llm = new LlmClientService(new GroqService, new OpenAiService, new SmashBotPromptService);

        $this->assertSame('openai', $llm->provider());
    }

    public function test_it_reports_rules_provider_when_no_key_is_configured(): void
    {
        config(['services.groq.api_key' => null, 'services.openai.api_key' => null]);

        $llm = new LlmClientService(new GroqService, new OpenAiService, new SmashBotPromptService);

        $this->assertSame('rules', $llm->provider());
        $this->assertFalse($llm->configured());
        $this->assertSame('knowledge-v3', $llm->model());
    }

    public function test_chatbot_schema_always_requires_every_field(): void
    {
        $schema = SmashBotPromptService::chatbotSchema();

        $this->assertSame(['understood', 'answer', 'suggestions', 'intent'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }
}