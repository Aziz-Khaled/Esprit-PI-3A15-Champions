<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[]
     */
    public function findByFilters(?string $userName, ?string $type): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.walletSource', 'ws')
            ->leftJoin('ws.utilisateur', 'u')
            ->leftJoin('t.creditCard', 'cc')
            ->leftJoin('cc.utilisateur', 'u2');

        if ($userName) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'CONCAT(u.prenom, \' \', u.nom) LIKE :name',
                    'CONCAT(u.nom, \' \', u.prenom) LIKE :name',
                    'CONCAT(u2.prenom, \' \', u2.nom) LIKE :name',
                    'CONCAT(u2.nom, \' \', u2.prenom) LIKE :name'
                )
            )->setParameter('name', '%' . $userName . '%');
        }

        if ($type) {
            $qb->andWhere('t.type = :type')
            ->setParameter('type', $type);
        }

        return $qb->orderBy('t.dateTransaction', 'DESC')
                ->getQuery()
                ->getResult();
    }

    public function calculateIsSameCard(int $idTransaction): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT CASE WHEN COUNT(DISTINCT id_card) = 1 THEN 1 ELSE 0 END as res 
                FROM (SELECT t.id_card FROM transaction t ...) as last_moves";

        $result = $conn->executeQuery($sql, ['id' => $idTransaction])->fetchOne();

        return (int) $result;
    }
}