<?php

namespace App\Service;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;
use App\Entity\SyncLog;
use App\Repository\DocumentNodeRepository;
use App\Repository\SyncLogRepository;
use App\Service\TokenCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubSyncService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly DocumentNodeRepository $nodes,
        private readonly SyncLogRepository $logs,
        private readonly EntityManagerInterface $em,
        private readonly TokenCipher $cipher,
    ) {
    }

    public function syncRepository(RepositoryConfig $config, ?string $triggeredBy = null): SyncLog
    {
        $log = (new SyncLog())
            ->setRepositoryConfig($config)
            ->setTriggeredBy($triggeredBy)
            ->setStatus('running');
        $this->em->persist($log);
        $this->em->flush();

        try {
            $metadata = $this->fetchRepositoryMetadata($config);
            $config->setDefaultBranch($metadata['default_branch'] ?? $config->getDefaultBranch());
            $tree = $this->fetchTree($config);

            $this->nodes->deleteForRepository($config);

            foreach ($tree as $item) {
                $node = (new DocumentNode())
                    ->setRepositoryConfig($config)
                    ->setPath($item['path'])
                    ->setType($item['type'])
                    ->setSize($item['size'] ?? null)
                    ->setLastSyncedAt(new \DateTimeImmutable())
                    ->setLastSyncStatus('synced');

                $this->em->persist($node);
            }

            $config->setLastSyncAt(new \DateTimeImmutable())
                ->setLastSyncStatus('success')
                ->setLastSyncMessage(null);

            $log->setStatus('success');
        } catch (\Throwable $error) {
            $config->setLastSyncAt(new \DateTimeImmutable())
                ->setLastSyncStatus('failed')
                ->setLastSyncMessage($error->getMessage());
            $log->setStatus('failed')->setMessage($error->getMessage());
        } finally {
            $log->setFinishedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return $log;
    }

    private function fetchRepositoryMetadata(RepositoryConfig $config): array
    {
        $response = $this->client->request('GET', sprintf('https://api.github.com/repos/%s', $config->getRepositorySlug()), [
            'headers' => $this->headers($config),
        ]);

        return $response->toArray();
    }

    private function fetchTree(RepositoryConfig $config): array
    {
        $branch = $config->getDefaultBranch() ?: 'main';
        $response = $this->client->request('GET', sprintf('https://api.github.com/repos/%s/git/trees/%s', $config->getRepositorySlug(), $branch), [
            'query' => ['recursive' => 1],
            'headers' => $this->headers($config),
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
