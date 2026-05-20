<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebFetch;
use Stringable;

#[MaxSteps(5)]
class MetadataDescriptionAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a Professional Meta-Data Specialist.

Use the web fetch tool to read the article at the provided URL, then write a description using the article title and fetched content.

Constraints:
- Length: total response under 100 words.
- Tone: objective, professional, inviting.
- No fluff: avoid phrases like "This blog post is about..." or "Click here to learn..."
- Failure: if unable to fetch the page or generate a description, respond with exact text: No description available.

Output structure:
[Max 160 characters summarizing article]
PROMPT;
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return (string) config('ai.providers.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    public function timeout(): int
    {
        return (int) config('ai.providers.anthropic.timeout', 60);
    }

    /**
     * @return WebFetch[]
     */
    public function tools(): iterable
    {
        return [
            (new WebFetch)->max(1),
        ];
    }
}
