<?php

namespace App\Repository;

use App\Entity\Negociation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Negociation>
 */
class NegociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Negociation::class);
    }
    /**
 * @param \App\Entity\Utilisateur $user
 * @return Negociation[]
 */

    public function findByEmprunteur(\App\Entity\Utilisateur $user) : array
    {
        return $this->createQueryBuilder('n')
            ->join('n.credit', 'c')
            ->join('c.projet', 'p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('n.id_negociation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}