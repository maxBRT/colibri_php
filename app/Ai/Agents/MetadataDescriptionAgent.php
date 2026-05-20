<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class MetadataDescriptionAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a Professional Meta-Data Specialist.

Write a description using the article title, URL, and provided page content.

Constraints:
- Length: total response under 100 words.
- Tone: objective, professional, inviting.
- No fluff: avoid phrases like "This blog post is about..." or "Click here to learn..."
- Failure: if unable to generate description, respond with exact text: No description available.

Output structure:
[Max 160 characters summarizing article]
PROMPT;
    }

    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('ai.providers.openai.model', 'gpt-4o-mini');
    }

    public function timeout(): int
    {
        return (int) config('ai.providers.openai.timeout', 60);
    }
}
