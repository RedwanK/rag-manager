<?php

namespace App\Controller;

use App\Repository\ConversationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat', name: 'chat_ui_')]
#[IsGranted('ROLE_USER')]
class ChatUiController extends AbstractController
{
    public function __construct(private readonly ConversationRepository $conversationRepository)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $conversations = array_map(static function ($conversation) {
            return [
                'id' => $conversation->getId(),
                'title' => $conversation->getTitle(),
                'createdAt' => $conversation->getCreatedAt()?->format(DATE_ATOM),
                'lastActivityAt' => $conversation->getLastActivityAt()->format(DATE_ATOM),
            ];
        }, $this->conversationRepository->listForUser($this->getUser()));

        return $this->render('chat/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }
}
