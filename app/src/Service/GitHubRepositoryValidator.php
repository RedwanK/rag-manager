<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubRepositoryValidator
{
    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    public function assertRepositoryIsReachable(string $owner, string $name, string $token): array
    {
        $response = $this->client->request('GET', sprintf('https://api.github.com/repos/%s/%s', $owner, $name), [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'rag-manager',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('GitHub returned %s while validating repository.', $response->getStatusCode()));
        }

        return $response->toArray();
    }
}
