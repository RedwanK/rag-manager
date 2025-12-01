<?php

namespace App\Service;

use App\Entity\IngestionQueueItem;
use App\Repository\IngestionLogRepository;

class IngestionLogService
{
    const PAGE_SIZE = 9;

    const ALLOWED_STATUSES = [
        IngestionQueueItem::STATUS_QUEUED,
        IngestionQueueItem::STATUS_PROCESSING,
        IngestionQueueItem::STATUS_INDEXED,
        IngestionQueueItem::STATUS_FAILED,
        IngestionQueueItem::STATUS_DOWNLOAD_FAILED,
    ];

    const ALLOWED_LEVEL = [
        'info',
        'warning',
        'error'
    ];

    public function __construct(protected IngestionLogRepository $ingestionLogRepository)
    {
    }

    public function filteredIndexLogs($query)
    {
        $page = max(1, $query->getInt('page', 1));

        $rawFilters = [
            'level' => $query->get('level'),
            'status' => $query->get('status'),
            'path' => $query->get('path'),
            'user' => $query->get('user'),
            'date_from' => $query->get('date_from'),
            'date_to' => $query->get('date_to'),
        ];

        $filters = [
            'level' => $this->normalizeLevel($rawFilters['level']),
            'status' => $this->normalizeStatus($rawFilters['status']),
            'path' => $this->trimNullable($rawFilters['path']),
            'user' => $this->trimNullable($rawFilters['user']),
            'date_from' => $this->parseDate($rawFilters['date_from']),
            'date_to' => $this->parseDate($rawFilters['date_to'], true),
        ];

        $result = $this->ingestionLogRepository->searchWithFilters($filters, $page, self::PAGE_SIZE);
        $pages = max(1, (int) ceil($result['total'] / self::PAGE_SIZE));

        return [
            'logs' => $result['items'],
            'page' => $page,
            'pages' => $pages,
            'total' => $result['total'],
            'filters' => $filters,
            'rawFilters' => $rawFilters,
            'levels' => self::ALLOWED_LEVEL,
            'statuses' => self::ALLOWED_STATUSES
        ];
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
        return in_array($level, self::ALLOWED_LEVEL, true) ? $level : null;
    }

    private function normalizeStatus(?string $status): ?string
    {
        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : null;
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