<?php

namespace App\Service;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;
use App\Entity\SyncLog;
use App\Repository\DocumentNodeRepository;
use App\Service\API\GitHubApiRouteResolver;
use App\Service\TokenCipher;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
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

        $errors = [];
        $syncedAt = new DateTimeImmutable();

        try {
            $metadata = $this->fetchRepositoryMetadata($config);
            $config->setDefaultBranch($metadata['default_branch'] ?? $config->getDefaultBranch());
            $tree = $this->indexTree($this->fetchTree($config));

            $existingNodes = $this->documentNodeRepository->findByRepositoryIncludingDeleted($config);
            $existingMap = $this->indexByPath($existingNodes);

            foreach ($tree as $path => $item) {
                try {
                    $node = $existingMap[$path] ?? (new DocumentNode())
                        ->setRepositoryConfig($config)
                        ->setPath($path);

                    $this->hydrateDocumentNode($node, $item, $syncedAt);

                    $this->em->persist($node);
                } catch (\Throwable $error) {
                    $errors[] = sprintf('Failed to sync %s: %s', $path, $error->getMessage());
                }
            }

            foreach ($existingMap as $path => $node) {
                if (!isset($tree[$path]) && $node->getDeletedAt() === null) {
                    $node->setDeletedAt(\DateTime::createFromImmutable($syncedAt));
                }
            }

            $status = empty($errors) ? self::SYNC_STATUS_SUCCESS : self::SYNC_STATUS_FAILED;
            $message = empty($errors) ? null : implode("\n", $errors);

            $config->setLastSyncAt($syncedAt)
                ->setLastSyncStatus($status)
                ->setLastSyncMessage($message);

            $log->setStatus($status)->setMessage($message);

            $this->em->flush();
        } catch (\Throwable $error) {
            $config->setLastSyncAt(new DateTimeImmutable())
                ->setLastSyncStatus(self::SYNC_STATUS_FAILED)
                ->setLastSyncMessage($error->getMessage());
            $log->setStatus(self::SYNC_STATUS_FAILED)->setMessage($error->getMessage());
        } finally {
            $log->setFinishedAt(new DateTimeImmutable());
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

    /**
     * @param array<string, mixed> $item
     */
    private function hydrateDocumentNode(DocumentNode $node, array $item, DateTimeImmutable $syncedAt): void
    {
        $node
            ->setType($item['type'])
            ->setSize($item['size'] ?? null)
            ->setLastSyncedAt($syncedAt)
            ->setLastSyncStatus(self::SYNC_STATUS_SYNCED)
            ->setDeletedAt(null);
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexTree(array $tree): array
    {
        $indexed = [];

        foreach ($tree as $item) {
            if (!isset($item['path'])) {
                continue;
            }

            $indexed[$item['path']] = $item;
        }

        return $indexed;
    }

    /**
     * @param DocumentNode[] $nodes
     *
     * @return array<string, DocumentNode>
     */
    private function indexByPath(array $nodes): array
    {
        $map = [];

        foreach ($nodes as $node) {
            $map[$node->getPath()] = $node;
        }

        return $map;
    }
}
