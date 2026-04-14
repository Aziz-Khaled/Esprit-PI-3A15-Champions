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
        ->select('n', 'c', 'p', 'u')
        ->join('n.credit', 'c')
        ->leftJoin('c.projet', 'p')
        ->join('n.utilisateur', 'u')
        ->where('c.borrower = :user') // Utilise 'borrower' défini dans Credit.php
        ->setParameter('user', $user)
        ->orderBy('n.id_negociation', 'DESC') // Utilise l'ID de ta table
        ->getQuery()
        ->getResult();
}}