<?php

namespace App\Controller;

use App\Entity\IngestionQueueItem;
use App\Form\IngestionQueueItemType;
use App\Repository\DocumentNodeRepository;
use App\Repository\IngestionQueueItemRepository;
use App\Service\IngestionQueueManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/ingestions', name: 'ingestion_queue_')]
#[IsGranted('ROLE_EDITOR')]
class IngestionQueueController extends AbstractController
{
    public function __construct(
        private readonly IngestionQueueItemRepository $queueRepository,
        private readonly EntityManagerInterface $em,
        private readonly DocumentNodeRepository $documentNodeRepository,
        private readonly IngestionQueueManager $queueManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $items = $this->queueRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('ingestion_queue/index.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, IngestionQueueItem $item): Response
    {
        $form = $this->createForm(IngestionQueueItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('ingestion.queue.flash.updated'));

            return $this->redirectToRoute('ingestion_queue_index');
        }

        return $this->render('ingestion_queue/edit.html.twig', [
            'item' => $item,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/enqueue/{id}', name: 'enqueue', methods: ['POST'])]
    public function enqueue(Request $request, int $id): Response
    {
        $documentNode = $this->documentNodeRepository->find($id);
        if (!$documentNode) {
            $this->addFlash('error', $this->translator->trans('ingestion.queue.flash.not_found'));
            return $this->redirectToRoute('repository_tree');
        }

        if (!$this->isCsrfTokenValid('enqueue' . $documentNode->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid'));
            return $this->redirectToRoute('repository_tree');
        }

        try {
            $this->queueManager->enqueue($documentNode, $this->getUser());
            $this->addFlash('success', $this->translator->trans('ingestion.queue.flash.enqueued'));
        } catch (\Throwable $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('repository_tree');
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, IngestionQueueItem $item): Response
    {
        if ($this->isCsrfTokenValid('delete_ingestion_' . $item->getId(), $request->request->get('_token'))) {
            $this->em->remove($item);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('ingestion.queue.flash.deleted'));
        } else {
            $this->addFlash('error', $this->translator->trans('csrf.invalid'));
        }

        return $this->redirectToRoute('ingestion_queue_index');
    }
}
