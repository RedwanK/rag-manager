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
                'X-GitHub-Api-Version' => "2022-11-28"
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $payload = $response->toArray(false);
            $message = $payload['message'] ?? 'GitHub returned an unexpected response while validating repository.';

            throw new \RuntimeException(sprintf('[%s] %s', $response->getStatusCode(), $message));
        }

        return $response->toArray();
    }
}
