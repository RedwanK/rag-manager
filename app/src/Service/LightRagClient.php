<?php

namespace App\Service;

use App\Exception\ChatStreamCancelledException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LightRagClient
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $lightRagBaseUrl,
        private readonly int $lightRagTimeout,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @param list<array{role: string, content: string|null}> $history
     * @param callable(string, mixed): void $onEvent
     * @param callable(): bool|null $shouldCancel
     */
    public function streamQuery(string $prompt, array $history, callable $onEvent, ?callable $shouldCancel = null): void
    {
        set_time_limit(360);

        $response = $this->client->request('POST', rtrim($this->lightRagBaseUrl, '/') . '/query/stream', [
            'headers' => [
                'Accept' => 'text/event-stream',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $prompt,
                'conversation_history' => $history,
                'mode' => 'mix',
                'enable_rerank' => false
            ],
            'timeout' => $this->lightRagTimeout,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new \RuntimeException(sprintf('LightRag responded with HTTP %d', $status));
        }

        $buffer = '';
        foreach ($this->client->stream($response) as $chunk) {
            if ($shouldCancel && $shouldCancel()) {
                $response->cancel();
                throw new ChatStreamCancelledException('cancelled_by_user');
            }

            if ($chunk->isTimeout()) {
                continue;
            }

            $content = $chunk->getContent(false);
            $this->logger->debug('LightRag chunk', ['chunk' => $content]);

            $buffer .= $content;
            foreach ($this->parseEvents($buffer) as $parsedEvent) {
                [$event, $payload] = $parsedEvent;
                $onEvent($event, $payload);
            }
        }

        // flush remaining buffered data if the stream ended without a trailing separator
        foreach ($this->parseEvents($buffer, true) as $parsedEvent) {
            [$event, $payload] = $parsedEvent;
            $onEvent($event, $payload);
        }
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function parseEvents(string &$buffer, bool $forceFlush = false): array
    {
        $events = [];
        
        // NDJSON / line-based JSON (LightRag emits {"response": "..."} or {"references": [...]})
        $parts = preg_split("/\r?\n/", $buffer);
        if ($parts === false) {
            return $events;
        }

        $buffer = array_pop($parts) ?? ($forceFlush ? '' : $buffer);
        if ($forceFlush && $buffer !== '') {
            $parts[] = $buffer;
            $buffer = '';
        }

        foreach ($parts as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                if (array_key_exists('references', $decoded)) {
                    $events[] = ['sources', $decoded['references']];
                }

                if (array_key_exists('response', $decoded)) {
                    $events[] = ['token', ['text' => (string) $decoded['response']]];
                }

                if (!array_key_exists('references', $decoded) && !array_key_exists('response', $decoded)) {
                    $events[] = ['token', $decoded];
                }
            } else {
                $events[] = ['token', ['text' => $line]];
            }
        }
    

        return $events;
    }
}
