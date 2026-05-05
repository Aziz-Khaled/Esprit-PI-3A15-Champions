<?php

namespace App\Repository;

use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wallet>
 */
class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    // Tu peux ajouter tes méthodes personnalisées ici (comme findByRib)
    public function findByRib(string $rib): ?Wallet
    {
        return $this->findOneBy(['rib' => $rib]);
    }
}