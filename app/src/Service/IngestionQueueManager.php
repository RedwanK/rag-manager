<?php

namespace App\Service;

use App\Entity\DocumentNode;
use App\Entity\IngestionQueueItem;
use App\Entity\RepositoryConfig;
use App\Entity\User;
use App\Service\API\GitHubApiRouteResolver;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Manage ingestion queue creation and artifact download lifecycle.
 */
class IngestionQueueManager
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly GitHubApiRouteResolver $routeResolver,
        private readonly EntityManagerInterface $em,
        private readonly TokenCipher $cipher,
        private readonly Filesystem $filesystem,
        private readonly string $githubDefaultBranch,
        private readonly string $ragSharedDir,
        private readonly int $ingestionMaxFileSize,
        private readonly array $ingestionAllowedExtensions,
    ) {
    }

    /**
     * Enqueue a document for ingestion, downloading it to the shared directory.
     */
    public function enqueue(DocumentNode $documentNode, User $user, string $source = IngestionQueueItem::SOURCE_USER): IngestionQueueItem
    {
        $this->assertCanEnqueue($documentNode);

        $queueItem = $documentNode->getIngestionQueueItem() ?? new IngestionQueueItem();
        $documentNode->setIngestionQueueItem($queueItem);

        $queueItem
            ->setDocumentNode($documentNode)
            ->setAddedBy($user)
            ->setSource($source)
            ->setStartedAt(null)
            ->setEndedAt(null);

        try {
            $content = $this->downloadFile($documentNode);
            [$absolutePath, $relativePath] = $this->buildStoragePaths($documentNode);

            $this->filesystem->dumpFile($absolutePath, $content);

            $queueItem
                ->setStoragePath($relativePath)
                ->setStatus(IngestionQueueItem::STATUS_QUEUED)
                ->setRagMessage(null);
        } catch (\Throwable $error) {
            $queueItem
                ->setStatus(IngestionQueueItem::STATUS_DOWNLOAD_FAILED)
                ->setRagMessage($error->getMessage());
        }

        $this->em->persist($queueItem);
        $this->em->flush();

        return $queueItem;
    }

    public function assertCanEnqueue(DocumentNode $documentNode): void
    {
        if ($documentNode->getType() !== 'blob') {
            throw new RuntimeException('Seuls les fichiers peuvent être ajoutés à la file d\'ingestion.');
        }

        $existingItem = $documentNode->getIngestionQueueItem();
        if ($existingItem && in_array($existingItem->getStatus(), [IngestionQueueItem::STATUS_QUEUED, IngestionQueueItem::STATUS_PROCESSING], true)) {
            throw new RuntimeException('Ce document est déjà dans la file d\'attente ou en cours de traitement.');
        }

        $size = $documentNode->getSize();
        if ($size !== null && $size > $this->ingestionMaxFileSize) {
            throw new RuntimeException(sprintf('Le fichier dépasse la taille maximale autorisée de %s.', $this->formatBytes($this->ingestionMaxFileSize)));
        }

        $this->validateExtension($documentNode);
    }

    private function validateExtension(DocumentNode $documentNode): void
    {
        $extension = strtolower(pathinfo($documentNode->getPath(), PATHINFO_EXTENSION));

        if ($extension === '') {
            throw new RuntimeException('Le fichier ne possède pas d\'extension reconnue pour l\'ingestion.');
        }

        if (!in_array($extension, $this->ingestionAllowedExtensions, true)) {
            throw new RuntimeException(sprintf(
                'Ce type de fichier n\'est pas pris en charge (extensions autorisées : %s).',
                implode(', ', $this->ingestionAllowedExtensions)
            ));
        }
    }

    private function downloadFile(DocumentNode $documentNode): string
    {
        $config = $documentNode->getRepositoryConfig();
        $branch = $config->getDefaultBranch() ?: $this->githubDefaultBranch;
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $documentNode->getPath())));
        $route = $this->routeResolver->resolve('fileContents', [$config->getRepositorySlug(), $encodedPath]);

        $response = $this->client->request($route['method'], $route['route'], [
            'query' => ['ref' => $branch],
            'headers' => $this->headers($config),
        ]);

        $data = $response->toArray();
        $content = base64_decode($data['content'] ?? '', true);

        if ($content === false) {
            throw new RuntimeException('Impossible de récupérer le contenu du fichier sur GitHub.');
        }

        $remoteSize = $data['size'] ?? strlen($content);
        if ($remoteSize > $this->ingestionMaxFileSize || strlen($content) > $this->ingestionMaxFileSize) {
            throw new RuntimeException(sprintf('Le fichier dépasse la taille maximale autorisée de %s.', $this->formatBytes($this->ingestionMaxFileSize)));
        }

        return $content;
    }

    /**
     * @return array{string, string} Absolute path and relative path for storage.
     */
    private function buildStoragePaths(DocumentNode $documentNode): array
    {
        $relativePath = ltrim($documentNode->getPath(), '/');

        if ($relativePath === '') {
            throw new RuntimeException('Le chemin du document est invalide.');
        }

        if (str_contains($relativePath, '..')) {
            throw new RuntimeException('Le chemin du document contient des segments non autorisés.');
        }

        $normalizedRelativePath = Path::normalize($relativePath);
        $basePath = Path::canonicalize($this->ragSharedDir);
        $absolutePath = Path::canonicalize(Path::join($basePath, $normalizedRelativePath));

        if (!str_starts_with($absolutePath, $basePath)) {
            throw new RuntimeException('Le chemin cible de stockage est invalide.');
        }

        return [$absolutePath, $normalizedRelativePath];
    }

    private function headers(RepositoryConfig $config): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->cipher->decrypt($config->getEncryptedToken()),
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'rag-manager',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        return sprintf('%.1f Mo', $bytes / 1024 / 1024);
    }
}
