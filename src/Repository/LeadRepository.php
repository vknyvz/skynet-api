<?php

namespace App\Repository;

use App\DTO\LeadFilterDto;
use App\Entity\Lead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @extends ServiceEntityRepository<Lead>
 */
class LeadRepository extends ServiceEntityRepository
{
  private CacheInterface $cache;

  public function __construct(
    ManagerRegistry $registry,
    CacheInterface  $apiResponsesCache
  )
  {
    parent::__construct($registry, Lead::class);
    $this->cache = $apiResponsesCache;
  }

  public function save(Lead $entity, bool $flush = false): void
  {
    $this->getEntityManager()->persist($entity);

    if ($flush) {
      $this->getEntityManager()->flush();
      $this->clearLeadCaches();
    }
  }

  public function show(int $id)
  {
    $cacheKey = sprintf('leads_by_id_%d', $id);

    return $this->cache->get($cacheKey, function () use ($id) {
      return $this->find($id);
    });
  }

  /**
   * Find leads with pagination and filtering (cached)
   */
  public function findWithFilters(
    LeadFilterDto $filter
  ): array
  {
    $cacheKey = sprintf(
      'leads_filtered_%d_%d_%s_%s_%s',
      $filter->page,
      $filter->limit,
      $filter->status ?? 'null',
      $filter->email ?? 'null',
      $filter->search ?? 'null'
    );

    return $this->cache->get($cacheKey, function () use ($filter) {
      $qb = $this->createQueryBuilder('l')
        ->select('DISTINCT l.id')
        ->orderBy('l.createdAt', 'DESC')
        ->setFirstResult(($filter->page - 1) * $filter->limit)
        ->setMaxResults($filter->limit);

      if ($filter->status) {
        $qb->andWhere('l.status = :status')
          ->setParameter('status', $filter->status);
      }

      if ($filter->email) {
        $qb->andWhere('l.email LIKE :email')
          ->setParameter('email', '%' . $filter->email . '%');
      }

      if ($filter->search) {
        $qb->andWhere(
          $qb->expr()->orX(
            $qb->expr()->like('l.firstName', ':search'),
            $qb->expr()->like('l.lastName', ':search'),
            $qb->expr()->like('l.email', ':search'),
            $qb->expr()->like('l.phone', ':search')
          )
        )->setParameter('search', '%' . $filter->search . '%');
      }

      $leadIds = array_column($qb->getQuery()->getScalarResult(), 'id');

      $qb = $this->createQueryBuilder('l')
        ->leftJoin('l.dynamicData', 'd')
        ->addSelect('d')
        ->where('l.id IN (:ids)')
        ->setParameter('ids', $leadIds)
        ->orderBy('l.createdAt', 'DESC');

      return $qb->getQuery()->getResult();
    });
  }

  /**
   * Count leads with filters
   */
  public function countWithFilters(
    LeadFilterDto $filter
  ): int
  {
    $cacheKey = sprintf(
      'leads_count_filtered_%s_%s_%s',
      $filter->status ?? 'null',
      $filter->email ?? 'null',
      $filter->search ?? 'null'
    );

    return $this->cache->get($cacheKey, function () use ($filter) {
      $qb = $this->createQueryBuilder('l')
        ->select('COUNT(l.id)');

      if ($filter->status) {
        $qb->andWhere('l.status = :status')
          ->setParameter('status', $filter->status);
      }

      if ($filter->email) {
        $qb->andWhere('l.email LIKE :email')
          ->setParameter('email', '%' . $filter->email . '%');
      }

      if ($filter->search) {
        $qb->andWhere(
          $qb->expr()->orX(
            $qb->expr()->like('l.firstName', ':search'),
            $qb->expr()->like('l.lastName', ':search'),
            $qb->expr()->like('l.email', ':search'),
            $qb->expr()->like('l.phone', ':search')
          )
        )->setParameter('search', '%' . $filter->search . '%');
      }

      return (int)$qb->getQuery()->getSingleScalarResult();
    });
  }

  public function getStatistics(): array
  {
    $cacheKey = 'leads_statistics_' . (new \DateTime('today'))->format('Y-m-d');

    return $this->cache->get($cacheKey, function (ItemInterface $item) {
      $qb = $this->createQueryBuilder('l')
        ->select([
          'COUNT(l.id) as total',
          'SUM(CASE WHEN l.status = :active THEN 1 ELSE 0 END) as active',
          'SUM(CASE WHEN l.status = :converted THEN 1 ELSE 0 END) as converted',
          'SUM(CASE WHEN l.status = :inactive THEN 1 ELSE 0 END) as inactive',
          'SUM(CASE WHEN l.createdAt >= :today THEN 1 ELSE 0 END) as today'
        ])
        ->setParameter('active', 'active')
        ->setParameter('converted', 'converted')
        ->setParameter('inactive', 'inactive')
        ->setParameter('today', new \DateTime('today'));

      $query = $qb->getQuery();
      return $query->getSingleResult();
    });
  }

  /**
   * Clear all lead-related caches when data changes
   */
  private function clearLeadCaches(): void
  {
    $patterns = [
      'leads_filtered_*',
      'leads_dynamic_*'
    ];

    foreach ($patterns as $pattern) {
      $this->cache->delete($pattern);
    }
  }
}