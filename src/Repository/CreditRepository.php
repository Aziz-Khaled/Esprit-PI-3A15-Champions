<?php

namespace App\Repository;

use App\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Credit>
 */
class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }
   public function findByAdvancedFilters(?string $term, ?string $status, ?float $minAmount, string $sortBy = 'date_desc')
{
    $qb = $this->createQueryBuilder('c')->leftJoin('c.projet', 'p');
    
    if ($term) {
        $qb->andWhere('p.title LIKE :t OR c.devise LIKE :t')->setParameter('t', '%'.$term.'%');
    }
    if ($status) {
        $qb->andWhere('c.status = :s')->setParameter('s', $status);
    }
    if ($minAmount) {
        $qb->andWhere('c.montant >= :m')->setParameter('m', $minAmount);
    }

    // Gestion dynamique du tri
    switch ($sortBy) {
        case 'amount_asc':
            $qb->orderBy('c.montant', 'ASC');
            break;
        case 'amount_desc':
            $qb->orderBy('c.montant', 'DESC');
            break;
        case 'date_asc':
            $qb->orderBy('c.id_credit', 'ASC');
            break;
        case 'date_desc':
        default:
            $qb->orderBy('c.id_credit', 'DESC');
            break;
    }

    return $qb->getQuery()->getResult();
}
//    /**
//     * @return Credit[] Returns an array of Credit objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Credit
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
