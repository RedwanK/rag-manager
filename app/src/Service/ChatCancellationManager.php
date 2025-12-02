<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class ChatCancellationManager
{
    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function cancel(int $messageId): void
    {
        $item = $this->cache->getItem($this->key($messageId));
        $item->set(true);
        $item->expiresAfter(300);
        $this->cache->save($item);
    }

    public function isCancelled(int $messageId): bool
    {
        $item = $this->cache->getItem($this->key($messageId));

        return (bool) $item->get();
    }

    private function key(int $messageId): string
    {
        return 'chat_cancel_' . $messageId;
    }
}
