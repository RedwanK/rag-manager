<?php

namespace App\Controller;

use App\Repository\ConversationRepository;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat', name: 'chat_ui_')]
#[IsGranted('ROLE_USER')]
class ChatUiController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ChatService $chatService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $conversations = $this->conversationRepository->listForUser($this->getUser());

        return $this->render('chat/list.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('chat_ui_new', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $conversation = $this->chatService->createConversation($this->getUser());

        return $this->redirectToRoute('chat_ui_show', ['id' => $conversation->getId()]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $initialConversations = [[
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'createdAt' => $conversation->getCreatedAt()?->format(DATE_ATOM),
            'lastActivityAt' => $conversation->getLastActivityAt()->format(DATE_ATOM),
        ]];

        return $this->render('chat/show.html.twig', [
            'conversation' => $conversation,
            'initialConversations' => $initialConversations,
        ]);
    }
}
