<?php

namespace App\Repository;

use App\Entity\WalletCurrency;
use App\Entity\Utilisateur;
use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletCurrency>
 */
class WalletCurrencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletCurrency::class);
    }

    /**
     * Calcule le total des soldes par devise pour un utilisateur spécifique.
     *
     * @param Utilisateur $user
     * @return array<int, array{name: string, total: string}>
     */
    public function sumBalancesByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('wc')
            ->select('wc.nomCurrency as name', 'SUM(wc.solde) as total')
            ->join('wc.wallet', 'w')
            ->where('w.utilisateur = :user')
            ->setParameter('user', $user)
            ->groupBy('wc.nomCurrency')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Utilisateur $user
     * @return WalletCurrency[]
     */
    public function findByWalletType(Utilisateur $user, string $type): array
    {
        return $this->createQueryBuilder('wc')
            ->join('wc.wallet', 'w')
            ->where('w.utilisateur = :user')
            ->andWhere('w.typeWallet = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function findTndByWallet(Wallet $wallet): ?WalletCurrency
    {
        return $this->createQueryBuilder('wc')
            ->join('wc.currency', 'c')
            ->where('wc.wallet = :wallet')
            ->andWhere(
                'UPPER(c.code) = :code OR LOWER(c.nom) LIKE :nom'
            )
            ->setParameter('wallet', $wallet)
            ->setParameter('code', 'TND')
            ->setParameter('nom', '%dinar%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}