<?php

namespace App\Service;

use App\Service\API\GitHubApiRouteResolver;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubRepositoryValidator
{
    public function __construct(private readonly HttpClientInterface $client, protected GitHubApiRouteResolver $routeResolver)
    {
    }

    public function assertRepositoryIsReachable(string $owner, string $name, string $token): array
    {
        $route = $this->routeResolver->resolve('repo', [$owner, $name]);

        // TODO : Handle properly and dynamically headers and bodies
        $response = $this->client->request($route['method'], $route['route'], [
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
