<?php

namespace Kibatic\CmsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kibatic\CmsBundle\Entity\Snapshot;

/**
 * @extends ServiceEntityRepository<Snapshot>
 */
class SnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Snapshot::class);
    }

    /**
     * @return list<Snapshot> the most recent first
     */
    public function findAllByDate(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
