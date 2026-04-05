<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function findForAdminList(?string $search, ?string $domaine, ?string $sort): array
    {
        $qb = $this->createQueryBuilder('f');

        if ($search) {
            $qb->andWhere('LOWER(f.titre) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($domaine) {
            $qb->andWhere('f.domaine = :domaine')
                ->setParameter('domaine', $domaine);
        }

        $direction = 'ASC';
        $field = 'f.dateDebut';
        if ($sort === 'prix_desc') {
            $field = 'f.prix';
            $direction = 'DESC';
        } elseif ($sort === 'prix_asc') {
            $field = 'f.prix';
        } elseif ($sort === 'date_desc') {
            $direction = 'DESC';
        }

        $qb->orderBy($field, $direction);

        return $qb->getQuery()->getResult();
    }

    public function findDistinctDomaines(): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('DISTINCT f.domaine')
            ->orderBy('f.domaine', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(fn(array $row) => $row['domaine'] ?? null, $rows)));
    }

//    /**
//     * @return Formation[] Returns an array of Formation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Formation
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
