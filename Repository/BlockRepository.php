<?php

namespace Kibatic\CmsBundle\Repository;

use App\Entity\AudioRecording;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kibatic\CmsBundle\Entity\Block;

/**
 * @extends ServiceEntityRepository<Block>
 */
class BlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Block::class);
    }
}
