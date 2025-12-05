<?php

namespace App\Service;

use App\Repository\DocumentNodeRepository;

class DocumentService
{
    public function __construct(protected DocumentNodeRepository $documentNodeRepository)
    {
    }

    public function mapDocumentNodes(array $sourceDocuments): array
    {
        $docNodes = [];
        foreach ($sourceDocuments as $sourceDoc) {
            $docNode = $this->documentNodeRepository->findOneByName($sourceDoc['file_path']);
            
            if (!empty($docNode)) {
                $docNodes[] = $docNode;
            }
        }

        return $docNodes;
    }
}