<?php

namespace Kibatic\CmsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kibatic\CmsBundle\Entity\Version;

/**
 * @extends ServiceEntityRepository<Version>
 */
class VersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Version::class);
    }

    public function findPublished(): ?Version
    {
        return $this->createQueryBuilder('s')
            ->where('s.publishedAt IS NOT NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Version> the most recent first
     */
    public function findAllByDate(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
