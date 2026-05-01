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
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT shipping_address AS shippingAddress, COUNT(id) AS count
            FROM orders
            WHERE shipping_address IS NOT NULL AND shipping_address != \'\'
            GROUP BY shipping_address
            ORDER BY count DESC
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getOrderStatusDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT status, COUNT(id) AS count FROM orders GROUP BY status ORDER BY count DESC';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getPaymentMethodDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT payment_method AS paymentMethod, COUNT(id) AS count FROM orders GROUP BY payment_method ORDER BY count DESC';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getAverageOrderValue(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery('SELECT AVG(total_amount) AS avg FROM orders')->fetchOne();
        return round((float)$result, 4);
    }

    public function getWeeklyOrderTrend(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT DATE(order_date) AS day, COUNT(id) AS count, SUM(total_amount) AS revenue
            FROM orders
            WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(order_date)
            ORDER BY day ASC
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getTopOrderedProducts(int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT p.name, SUM(oi.quantity) AS total_qty, SUM(oi.sub_total) AS total_revenue
            FROM order_item oi
            JOIN product p ON oi.product_id = p.id
            GROUP BY p.id, p.name
            ORDER BY total_qty DESC
            LIMIT ' . (int)$limit . '
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function getRevenueThisMonth(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery('
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE MONTH(order_date) = MONTH(NOW()) AND YEAR(order_date) = YEAR(NOW())
        ')->fetchOne();
        return round((float)$result, 4);
    }

    public function getRevenueLastMonth(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery('
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE MONTH(order_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
              AND YEAR(order_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        ')->fetchOne();
        return round((float)$result, 4);
    }
}