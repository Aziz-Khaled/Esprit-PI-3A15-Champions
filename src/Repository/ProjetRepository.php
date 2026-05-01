<?php

namespace App\Repository;

use App\Entity\Projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Projet>
 */
class ProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Projet::class);
    }

    /**
 * @return Projet[]
 */
   public function findProjetsAdvanced(?string $term, ?string $status, ?string $secteur, string $sortBy = 'date_desc') : array
{
    $qb = $this->createQueryBuilder('p');

    // 1. Recherche textuelle (Titre ou Description)
    if ($term) {
        $qb->andWhere('p.title LIKE :t OR p.description LIKE :t')
           ->setParameter('t', '%'.$term.'%');
    }

    // 2. Filtre par Statut (DRAFT, ACTIVE, etc.)
    if ($status) {
        $qb->andWhere('p.status = :s')
           ->setParameter('s', $status);
    }

    // 3. Filtre par Secteur
    if ($secteur) {
        $qb->andWhere('p.secteur = :sec')
           ->setParameter('sec', $secteur);
    }

    // 4. Logique de Tri dynamique
    switch ($sortBy) {
        case 'amount_asc':
            $qb->orderBy('p.targetAmount', 'ASC');
            break;
        case 'amount_desc':
            $qb->orderBy('p.targetAmount', 'DESC');
            break;
        case 'title_asc':
            $qb->orderBy('p.title', 'ASC');
            break;
        case 'date_asc':
            $qb->orderBy('p.idProjet', 'ASC');
            break;
        case 'date_desc':
        default:
            $qb->orderBy('p.idProjet', 'DESC');
            break;
    }

    return $qb->getQuery()->getResult();
}

//    /**
//     * @return Projet[] Returns an array of Projet objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Projet
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}