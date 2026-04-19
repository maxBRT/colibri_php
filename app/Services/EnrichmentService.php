<?php

namespace App\Services;

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Models\Post;
use Throwable;

class EnrichmentService
{
    public function generateSummary(Post $post): ?string
    {
        $attempts = max(1, (int) config('ai.providers.gemini.retries', 3));
        $retrySleepMilliseconds = max(0, (int) config('ai.providers.gemini.retry_sleep_ms', 200));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = MetadataDescriptionAgent::make()->prompt(
                    $this->buildPrompt($post)
                );

                $summary = trim((string) $response);

                if ($summary === '' || $summary === 'No description available.') {
                    return null;
                }

                return $summary;
            } catch (Throwable) {
                if ($attempt === $attempts) {
                    return null;
                }

                usleep($retrySleepMilliseconds * 1000);
            }
        }

        return null;
    }

    private function buildPrompt(Post $post): string
    {
        return <<<PROMPT
Generate metadata description for article.

Title: {$post->title}
URL: {$post->link}
PROMPT;
    }
}
