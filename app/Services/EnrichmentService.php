<?php

namespace App\Services;

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class EnrichmentService
{
    public function generateSummary(Post $post): ?string
    {
        $attempts = max(1, (int) config('ai.providers.anthropic.retries', 3));
        $retrySleepMilliseconds = max(0, (int) config('ai.providers.anthropic.retry_sleep_ms', 2000));
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
                $errorContext = [
                    ...$context,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    ...$this->providerErrorContext($exception),
                ];

                if ($attempt === $attempts) {
                    if (! $exception instanceof RateLimitedException) {
                        report($exception);
                    }

                    Log::warning('LLM enrichment failed after all retries.', [
                        ...$errorContext,
                        'attempts' => $attempts,
                    ]);

                    return null;
                }

                Log::warning('LLM enrichment attempt failed, retrying.', [
                    ...$errorContext,
                    'attempt' => $attempt,
                    'retry_in_ms' => $this->retryDelayMilliseconds($exception, $retrySleepMilliseconds),
                ]);

                Sleep::usleep($this->retryDelayMilliseconds($exception, $retrySleepMilliseconds) * 1000);
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

    /**
     * @return array{provider_status?: int, provider_body?: string}
     */
    private function providerErrorContext(Throwable $exception): array
    {
        $previous = $exception;

        while ($previous !== null) {
            if ($previous instanceof RequestException && $previous->response !== null) {
                return [
                    'provider_status' => $previous->response->status(),
                    'provider_body' => Str::limit((string) $previous->response->body(), 500),
                ];
            }

            $previous = $previous->getPrevious();
        }

        return [];
    }

    private function retryDelayMilliseconds(Throwable $exception, int $defaultDelayMilliseconds): int
    {
        if ($exception instanceof RateLimitedException) {
            $providerBody = $this->providerErrorContext($exception)['provider_body'] ?? '';

            if (preg_match('/after (\d+) seconds?/i', $providerBody, $matches) === 1) {
                return max($defaultDelayMilliseconds, ((int) $matches[1] + 1) * 1000);
            }

            if (str_contains(strtolower($providerBody), 'input tokens per minute')) {
                return max($defaultDelayMilliseconds, 60_000);
            }

            return max($defaultDelayMilliseconds, 5000);
        }

        if ($exception instanceof ConnectionException) {
            return max($defaultDelayMilliseconds, 3000);
        }

        return $defaultDelayMilliseconds;
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
