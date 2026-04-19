<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyRevenue(): array
    {
        // Simple monthly aggregation
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT MONTH(order_date) as month, SUM(total_amount) as total
            FROM orders
            GROUP BY month
            ORDER BY month ASC
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getShippingAddressStats(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.shippingAddress, COUNT(o.id) as count')
            ->groupBy('o.shippingAddress')
            ->getQuery()
            ->getResult();
    }
}