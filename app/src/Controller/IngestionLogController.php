<?php

namespace App\Controller;

use App\Entity\IngestionQueueItem;
use App\Repository\IngestionLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ingestions/logs', name: 'ingestion_logs_')]
#[IsGranted('ROLE_EDITOR')]
class IngestionLogController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly IngestionLogRepository $logRepository)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $rawFilters = [
            'level' => $request->query->get('level'),
            'status' => $request->query->get('status'),
            'path' => $request->query->get('path'),
            'user' => $request->query->get('user'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
        ];

        $filters = [
            'level' => $this->normalizeLevel($rawFilters['level']),
            'status' => $this->normalizeStatus($rawFilters['status']),
            'path' => $this->trimNullable($rawFilters['path']),
            'user' => $this->trimNullable($rawFilters['user']),
            'date_from' => $this->parseDate($rawFilters['date_from']),
            'date_to' => $this->parseDate($rawFilters['date_to'], true),
        ];

        $result = $this->logRepository->searchWithFilters($filters, $page, self::PAGE_SIZE);
        $pages = max(1, (int) ceil($result['total'] / self::PAGE_SIZE));

        return $this->render('ingestion_log/index.html.twig', [
            'logs' => $result['items'],
            'page' => $page,
            'pages' => $pages,
            'total' => $result['total'],
            'filters' => $filters,
            'rawFilters' => $rawFilters,
            'levels' => ['info', 'warning', 'error'],
            'statuses' => [
                IngestionQueueItem::STATUS_QUEUED,
                IngestionQueueItem::STATUS_PROCESSING,
                IngestionQueueItem::STATUS_INDEXED,
                IngestionQueueItem::STATUS_FAILED,
                IngestionQueueItem::STATUS_DOWNLOAD_FAILED,
            ],
        ]);
    }

    private function parseDate(?string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
            return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeLevel(?string $level): ?string
    {
        $allowed = ['info', 'warning', 'error'];
        return in_array($level, $allowed, true) ? $level : null;
    }

    private function normalizeStatus(?string $status): ?string
    {
        $allowed = [
            IngestionQueueItem::STATUS_QUEUED,
            IngestionQueueItem::STATUS_PROCESSING,
            IngestionQueueItem::STATUS_INDEXED,
            IngestionQueueItem::STATUS_FAILED,
            IngestionQueueItem::STATUS_DOWNLOAD_FAILED,
        ];

        return in_array($status, $allowed, true) ? $status : null;
    }

    private function trimNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
