<?php

namespace App\Service;

use App\Exception\ChatStreamCancelledException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LightRagClient
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $lightRagBaseUrl,
        private readonly int $lightRagTimeout
    ) {
    }

    /**
     * @param list<array{role: string, content: string|null}> $history
     * @param callable(string, mixed): void $onEvent
     * @param callable(): bool|null $shouldCancel
     */
    public function streamQuery(string $prompt, array $history, callable $onEvent, ?callable $shouldCancel = null): void
    {
        $response = $this->client->request('POST', rtrim($this->lightRagBaseUrl, '/') . '/query/stream', [
            'headers' => [
                'Accept' => 'text/event-stream',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $prompt,
                'history' => $history,
                'mode' => 'mix'
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

            $buffer .= $chunk->getContent(false);
            foreach ($this->parseEvents($buffer) as $parsedEvent) {
                [$event, $payload] = $parsedEvent;
                $onEvent($event, $payload);
            }
        }
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function parseEvents(string &$buffer): array
    {
        $events = [];
        $parts = preg_split("/\n\n/", $buffer);
        if ($parts === false) {
            return $events;
        }

        // keep last part in buffer if it is incomplete
        $buffer = array_pop($parts) ?? '';

        foreach ($parts as $part) {
            $event = 'token';
            $dataLines = [];

            foreach (explode("\n", trim($part)) as $line) {
                if (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = trim(substr($line, 5));
                }
            }

            $payloadRaw = implode("\n", $dataLines);
            $decoded = json_decode($payloadRaw, true);
            $events[] = [$event, $decoded ?? $payloadRaw];
        }

        return $events;
    }
}
