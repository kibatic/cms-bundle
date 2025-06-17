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

    public function findAll()
    {
        return $this->findBy([], ['slug' => 'ASC', 'language' => 'ASC']);
    }

    public function getExistingLanguages(): array
    {
        $languages = array_filter(
            $this->createQueryBuilder('b')
                ->select('b.language')
                ->getQuery()
                ->getSingleColumnResult()
        );

        sort($languages);
        return $languages;
    }

    public function findBySlug(string $slug): array
    {
        return $this->findBy(['slug' => $slug], ['language' => 'ASC']);
    }
}
