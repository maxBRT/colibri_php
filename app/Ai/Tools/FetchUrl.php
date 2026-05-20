<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class FetchUrl implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch and return the readable text content of a public web page URL.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return $this->fetch($request->string('url'));
    }

    public function fetch(string $url): string
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Unable to fetch URL: invalid URL provided.';
        }

        try {
            $response = Http::connectTimeout((int) config('ai.providers.openai.connect_timeout', 10))
                ->timeout((int) config('ai.providers.openai.timeout', 60))
                ->withHeaders([
                    'User-Agent' => 'ColibriBot/1.0',
                ])
                ->get($url)
                ->throw();

            $content = html_entity_decode(strip_tags((string) $response->body()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = trim(preg_replace('/\s+/u', ' ', $content) ?? '');

            if ($content === '') {
                return 'Unable to fetch URL: page returned no readable text content.';
            }

            return Str::limit($content, 8000, '...');
        } catch (Throwable $exception) {
            return 'Unable to fetch URL: '.$exception->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema
                ->string()
                ->description('The public HTTP or HTTPS URL to fetch.')
                ->required(),
        ];
    }
}
