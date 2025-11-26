<?php

namespace App\Service;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;

class RepositoryTreeService
{

    const NODE_TYPE_DIRECTORY = "tree";

    /**
     * @param DocumentNode[] $nodes
     */
    public function computeStats(array $nodes): array
    {
        $files = 0;
        $directories = 0;

        foreach ($nodes as $node) {
            if ($node->getType() === self::NODE_TYPE_DIRECTORY) {
                $directories++;
            } else {
                $files++;
            }
        }

        return [
            'total' => $files + $directories,
            'files' => $files,
            'directories' => $directories,
        ];
    }

    /**
     * @param DocumentNode[] $nodes
     * @param RepositoryConfig $config
     */
    public function buildTreeData(array $nodes, RepositoryConfig $config): array
    {
        // Root node of the tree (repository itself)
        $root = [
            'name' => $config->getName(),
            'path' => $config->getRepositorySlug(),
            'type' => self::NODE_TYPE_DIRECTORY,
            'children' => [],
            'lastSyncedAt' => $config->getLastSyncAt()?->format(\DATE_ATOM),
            'lastSyncStatus' => $config->getLastSyncStatus(),
        ];


        foreach ($nodes as $node) {
            // Split the stored "path" (like src/Controller/Foo.php) into hierarchical segments
            $segments = array_values(array_filter(explode('/', $node->getPath()), static fn ($segment) => $segment !== ''));

            $cursor = &$root;
            $currentPath = [];

            foreach ($segments as $index => $segment) {
                //dump($cursor);
                $currentPath[] = $segment;
                $isLeaf = $index === count($segments) - 1;

                // Leaf node that is not a directory: add it as a child and move to next DocumentNode
                if ($isLeaf && $node->getType() !== self::NODE_TYPE_DIRECTORY) {
                    $cursor['children'][] = [
                        'name' => $segment,
                        'path' => implode('/', $currentPath),
                        'type' => $node->getType(),
                        'size' => $node->getSize(),
                        'lastSyncedAt' => $node->getLastSyncedAt()?->format(\DATE_ATOM),
                        'lastSyncStatus' => $node->getLastSyncStatus(),
                    ];
                    continue;
                }

                $foundIndex = null;
                foreach ($cursor['children'] as $index => $child) {
                    if (($child['type'] ?? null) === self::NODE_TYPE_DIRECTORY && $child['name'] === $segment) {
                        $foundIndex = $index;
                        break;
                    }
                }

                // If the directory level does not exist yet, create it; otherwise update its metadata for directory leaves
                if ($foundIndex === null) {
                    $cursor['children'][] = [
                        'name' => $segment,
                        'path' => implode('/', $currentPath),
                        'type' => self::NODE_TYPE_DIRECTORY,
                        'children' => [],
                        'lastSyncedAt' => $node->getLastSyncedAt()?->format(\DATE_ATOM),
                        'lastSyncStatus' => $node->getLastSyncStatus(),
                    ];

                    $foundIndex = array_key_last($cursor["children"]);
                } elseif ($isLeaf && $node->getType() === self::NODE_TYPE_DIRECTORY) {
                    $cursor['children'][$foundIndex]['lastSyncedAt'] = $node->getLastSyncedAt()?->format(\DATE_ATOM);
                    $cursor['children'][$foundIndex]['lastSyncStatus'] = $node->getLastSyncStatus();
                }
                $cursor = &$cursor['children'][$foundIndex];
            }
            unset($cursor);
            
        }

        return $root;
    }
}
