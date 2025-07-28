<?php

namespace App\Service;

use App\Repository\LeadRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class CacheService
{
  public function __construct(
    private CacheInterface $cache,
    private LeadRepository $leadRepository
  ) {}

  /**
   * Warm up frequently accessed cache entries
   */
  public function warmUpCache(): void
  {
    // Warm up lead statistics (most frequently accessed)
    $this->leadRepository->getStatistics();

    // Warm up first page of leads (most common request)
    $this->leadRepository->findWithFilters(1, 20);

    // Warm up active leads count
    $this->leadRepository->countWithFilters('active');
  }

  /**
   * Clear all lead-related caches
   */
  public function clearLeadCaches(): void
  {
    $patterns = [
      'leads_*',
      'lead_*'
    ];

    foreach ($patterns as $pattern) {
      if ($this->cache instanceof TagAwareCacheInterface) {
        $this->cache->invalidateTags(['leads']);
      } else {
        // Fallback for non-tag-aware cache
        $this->cache->delete($pattern);
      }
    }
  }

  /**
   * Preload common lead queries into cache
   */
  public function preloadCommonQueries(): void
  {
    $commonStatuses = ['active', 'converted', 'inactive'];

    foreach ($commonStatuses as $status) {
      // Preload first page for each status
      $this->leadRepository->findWithFilters(1, 20, $status);

      // Preload count for each status
      $this->leadRepository->countWithFilters($status);
    }

    // Preload today's statistics
    $this->leadRepository->getStatistics();
  }
}