<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FetchUrl;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

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

Use the fetch_url tool to read the article at the provided URL before writing the description.

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
        return 'moonshot';
    }

    public function model(): string
    {
        return (string) config('ai.providers.moonshot.model', 'kimi-k2-turbo-preview');
    }

    public function timeout(): int
    {
        return (int) config('ai.providers.moonshot.timeout', 30);
    }

    public function tools(): iterable
    {
        return [
            new FetchUrl,
        ];
    }
}
