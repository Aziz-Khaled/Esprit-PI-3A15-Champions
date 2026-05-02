<?php

namespace App\Repository;

use App\Entity\Certificat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Certificat>
 */
class CertificatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificat::class);
    }

    /**
 * @return Certificat[]
 */

    public function findForAdminList(?string $search, ?string $sort): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.participation', 'p')
            ->addSelect('p')
            ->leftJoin('p.formation', 'f')
            ->addSelect('f')
            ->leftJoin('p.utilisateur', 'u')
            ->addSelect('u');

        if ($search) {
            $qb->andWhere('c.mention LIKE :search OR f.titre LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        switch ($sort) {
            case 'date_asc':
                $qb->orderBy('c.dateEmission', 'ASC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('c.dateEmission', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
