<?php

namespace App\Service\API;

use Exception;

class GitHubApiRouteResolver
{
    const ROUTES = [
        'repo' => [
            "base" => "repos/%s/%s",
            "nbRouteParams" => 2,
            "method" => "GET"
        ],
        'repoMetadata' => [
            "base" => "repos/%s",
            "nbRouteParams" => 1,
            "method" => "GET"
        ],
        'repoTree' => [
            "base" => "repos/%s/git/trees/%s",
            "nbRouteParams" => 2,
            "method" => "GET"
        ]
    ];

    public function __construct(protected string $githubApiBaseUrl)
    {
    }

    /**
     * @param string $routeName
     * @param array<string> $params
     * @throws Exception
     * @return array
     */
    public function resolve($routeName, $params): array
    {
        if (!isset(self::ROUTES[$routeName]) || count($params) != self::ROUTES[$routeName]['nbRouteParams']) {
            throw new Exception("Unknown route");
        }

        $finalRoute = $this->githubApiBaseUrl . "/" . self::ROUTES[$routeName]['base'];

        return [
            "route" => sprintf($finalRoute, ...$params),
            "method" => self::ROUTES[$routeName]["method"]
        ];
    }
}