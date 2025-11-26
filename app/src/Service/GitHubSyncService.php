<?php

namespace App\Service;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;
use App\Entity\SyncLog;
use App\Repository\DocumentNodeRepository;
use App\Service\API\GitHubApiRouteResolver;
use App\Service\TokenCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubSyncService
{
    const SYNC_STATUS_RUNNING = "running";
    const SYNC_STATUS_SYNCED = "synced";
    const SYNC_STATUS_SUCCESS = "success";
    const SYNC_STATUS_FAILED = "failed";

    public function __construct(
        protected readonly HttpClientInterface $client,
        protected readonly DocumentNodeRepository $documentNodeRepository,
        protected readonly GitHubApiRouteResolver $routeResolver,
        protected readonly EntityManagerInterface $em,
        protected readonly TokenCipher $cipher,
        protected readonly string $githubDefaultBranch
    ) {
    }

    public function syncRepository(RepositoryConfig $config, ?string $triggeredBy = null): SyncLog
    {
        $log = (new SyncLog())
            ->setRepositoryConfig($config)
            ->setTriggeredBy($triggeredBy)
            ->setStatus(self::SYNC_STATUS_RUNNING);
        $this->em->persist($log);
        $this->em->flush();

        try {
            $metadata = $this->fetchRepositoryMetadata($config);
            $config->setDefaultBranch($metadata['default_branch'] ?? $config->getDefaultBranch());
            $tree = $this->fetchTree($config);

            $this->documentNodeRepository->deleteForRepository($config);

            foreach ($tree as $item) {
                $node = (new DocumentNode())
                    ->setRepositoryConfig($config)
                    ->setPath($item['path'])
                    ->setType($item['type'])
                    ->setSize($item['size'] ?? null)
                    ->setLastSyncedAt(new \DateTimeImmutable())
                    ->setLastSyncStatus(self::SYNC_STATUS_SYNCED);

                $this->em->persist($node);
            }

            $config->setLastSyncAt(new \DateTimeImmutable())
                ->setLastSyncStatus(self::SYNC_STATUS_SUCCESS)
                ->setLastSyncMessage(null);

            $log->setStatus(self::SYNC_STATUS_SUCCESS);
        } catch (\Throwable $error) {
            $config->setLastSyncAt(new \DateTimeImmutable())
                ->setLastSyncStatus(self::SYNC_STATUS_FAILED)
                ->setLastSyncMessage($error->getMessage());
            $log->setStatus(self::SYNC_STATUS_FAILED)->setMessage($error->getMessage());
        } finally {
            $log->setFinishedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return $log;
    }

    private function fetchRepositoryMetadata(RepositoryConfig $config): array
    {
        $route = $this->routeResolver->resolve("repoMetadata", [$config->getRepositorySlug()]);

        $response = $this->client->request($route['method'], $route['route'], [
            'headers' => $this->headers($config),
        ]);

        return $response->toArray();
    }

    private function fetchTree(RepositoryConfig $config): array
    {
        $branch = $config->getDefaultBranch() ?: $this->githubDefaultBranch;

        $route = $this->routeResolver->resolve('repoTree', [$config->getRepositorySlug(), $branch]);

        $response = $this->client->request($route['method'], $route['route'], [
            'query' => ['recursive' => 1],
            'headers' => $this->headers($config)
        ]);

        $data = $response->toArray();

        return $data['tree'] ?? [];
    }

    private function headers(RepositoryConfig $config): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->cipher->decrypt($config->getEncryptedToken()),
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'rag-manager',
        ];
    }
}
