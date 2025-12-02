<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\RateLimitExceededException;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;

class PromptRateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $maxRequests,
        private readonly int $intervalSeconds
    ) {
    }

    public function assertWithinLimit(User $user): void
    {
        $cacheKey = $this->buildKey($user);
        $item = $this->cache->getItem($cacheKey);
        /** @var array<int, int> $timestamps */
        $timestamps = $item->isHit() ? (array) $item->get() : [];

        $now = (new DateTimeImmutable())->getTimestamp();
        $windowStart = $now - $this->intervalSeconds;
        $recent = array_values(array_filter($timestamps, static fn (int $ts) => $ts >= $windowStart));

        if (count($recent) >= $this->maxRequests) {
            $retryAfter = ($recent[0] + $this->intervalSeconds) - $now;
            throw new RateLimitExceededException(max($retryAfter, 1));
        }

        $recent[] = $now;
        $item->set($recent);
        $item->expiresAfter($this->intervalSeconds);
        $this->cache->save($item);
    }

    private function buildKey(User $user): string
    {
        return 'prompt_rate_limit_' . $user->getId();
    }
}
