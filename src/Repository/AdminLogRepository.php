<?php

// src/Repository/AdminLogRepository.php
namespace App\Repository;

use App\Entity\AdminLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminLog>
 */
class AdminLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminLog::class);
    }

    /**
 * @return AdminLog[]
 */
    public function findByDateRange(?\DateTime $from, ?\DateTime $to): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if ($from) {
            $qb->andWhere('l.createdAt >= :from')
            ->setParameter('from', $from->setTime(0, 0, 0));
        }

        if ($to) {
            $qb->andWhere('l.createdAt <= :to')
            ->setParameter('to', $to->setTime(23, 59, 59));
        }

        return $qb->getQuery()->getResult();
    }
}