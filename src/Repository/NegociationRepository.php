<?php

namespace App\Repository;

use App\Entity\Negociation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NegociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Negociation::class);
    }

    public function findByEmprunteur($user)
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