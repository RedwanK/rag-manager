<?php

namespace App\Controller;

use App\Service\IngestionLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ingestions/logs', name: 'ingestion_logs_')]
#[IsGranted('ROLE_EDITOR')]
class IngestionLogController extends AbstractController
{
    public function __construct(private readonly IngestionLogService $ingestionLogService)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $data = $this->ingestionLogService->filteredIndexLogs($request->query);

        return $this->render('ingestion_log/index.html.twig', $data);
    }
}
