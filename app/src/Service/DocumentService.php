<?php

namespace App\Service;

use App\Repository\DocumentNodeRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DocumentService
{
    public function __construct(
        protected DocumentNodeRepository $documentNodeRepository,
        protected UrlGeneratorInterface $urlGenerator
    )
    {
    }

    public function mapDocumentNodes(?array $sourceDocuments): array
    {
        if (empty($sourceDocuments)) return [];

        $docNodes = [];
        foreach ($sourceDocuments as $sourceDoc) {
            $docNode = $this->documentNodeRepository->findOneByName($sourceDoc['file_path']);
            if(empty($docNode)) {
                continue;
            }
            $docNode = $docNode[0];
            $docNode['url'] = $this->urlGenerator->generate('document_node_show', ['id' => $docNode['id']]);
            $docNode['title'] = pathinfo($docNode['path'], PATHINFO_BASENAME);
            
            if (!empty($docNode)) {
                $docNodes[] = $docNode;
            }
        }

        return $docNodes;
    }
}