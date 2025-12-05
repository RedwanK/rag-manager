<?php

namespace App\Controller;

use App\Entity\DocumentNode;
use App\Repository\DocumentNodeRepository;
use App\Service\IngestionQueueManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents', name: 'document_node_')]
#[IsGranted('ROLE_VIEWER')]
class DocumentNodeController extends AbstractController
{
    public function __construct(
        private readonly DocumentNodeRepository $documentNodeRepository,
        private readonly IngestionQueueManager $ingestionQueueManager,
    ) {
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $document = $this->documentNodeRepository->find($id);
        if (!$document) {
            throw $this->createNotFoundException();
        }

        return $this->render('document_node/show.html.twig', [
            'document' => $document,
            'queueItem' => $document->getIngestionQueueItem(),
        ]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $document = $this->documentNodeRepository->find($id);
        if (!$document) {
            throw $this->createNotFoundException();
        }

        if ($document->getType() !== 'blob') {
            $this->addFlash('error', 'document_node.flash.download_not_file');

            return $this->redirectToRoute('document_node_show', ['id' => $document->getId()]);
        }

        try {
            $fileContent = $this->ingestionQueueManager->fetchFile($document);
        } catch (\Throwable $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('document_node_show', ['id' => $document->getId()]);
        }

        return new Response($fileContent['content'], Response::HTTP_OK, [
            'Content-Type' => $fileContent['mimeType'],
            'Content-Disposition' => $fileContent['disposition'],
        ]);
    }
}
