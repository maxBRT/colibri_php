<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebFetch;
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

Analyze text content from provided URL.

Constraints:
- Length: total response under 100 words.
- Tone: objective, professional, inviting.
- No fluff: avoid phrases like "This blog post is about..." or "Click here to learn..."
- Failure: if unable to generate description, respond with exact text: No description available.

Output structure:
[Max 160 characters summarizing article]
PROMPT;
    }

    public function provider(): Lab|string|array
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('ai.providers.gemini.model', 'gemini-2.5-flash');
    }

    public function timeout(): int
    {
        return (int) config('ai.providers.gemini.timeout', 30);
    }

    public function tools(): iterable
    {
        return [
            new WebFetch,
        ];
    }
}
