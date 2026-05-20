<?php

namespace App\Services;

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrichmentService
{
    public function generateSummary(Post $post): ?string
    {
        $attempts = max(1, (int) config('ai.providers.moonshot.retries', 3));
        $retrySleepMilliseconds = max(0, (int) config('ai.providers.moonshot.retry_sleep_ms', 200));
        $context = $this->logContext($post);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = MetadataDescriptionAgent::make()->prompt(
                    $this->buildPrompt($post)
                );

                $summary = trim((string) $response);

                if ($summary === '' || $summary === 'No description available.') {
                    Log::warning('LLM enrichment returned no usable description.', [
                        ...$context,
                        'attempt' => $attempt,
                        'response' => $summary === '' ? '(empty)' : $summary,
                    ]);

                    return null;
                }

                Log::info('LLM enrichment succeeded.', [
                    ...$context,
                    'attempt' => $attempt,
                    'summary_length' => mb_strlen($summary),
                ]);

                return $summary;
            } catch (Throwable $exception) {
                if ($attempt === $attempts) {
                    report($exception);

                    Log::warning('LLM enrichment failed after all retries.', [
                        ...$context,
                        'attempts' => $attempts,
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);

                    return null;
                }

                Log::warning('LLM enrichment attempt failed, retrying.', [
                    ...$context,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                usleep($retrySleepMilliseconds * 1000);
            }
        }

        return null;
    }

    /**
     * @return array{post_id?: string, post_title: string, post_link: string}
     */
    private function logContext(Post $post): array
    {
        return array_filter([
            'post_id' => $post->id,
            'post_title' => $post->title,
            'post_link' => $post->link,
        ], fn ($value) => $value !== null && $value !== '');
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
