<?php

namespace App\Services;

enum AIChoice
{
    case Sonnet;
    case GPT4MINI;
    case QWEN_FLASH;

    public function model(): array
    {
        return match ($this) {
            AIChoice::Sonnet => [
                'model' => 'claude-sonnet-4',
                'temperature' => 0.7,
                'top_p' => 0.95,
                'frequency_penalty' => 0.4,
                'presence_penalty' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ],
            AIChoice::GPT4MINI => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.0,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'response_format' => ['type' => 'json_object'],
            ],
            AIChoice::QWEN_FLASH => [
                'model' => 'qwen3.7-flash',
                'temperature' => 0.5,
                'top_p' => 0.9,
                'frequency_penalty' => 0.3,
                'presence_penalty' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]
        };
    }
}
