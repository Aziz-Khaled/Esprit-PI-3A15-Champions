<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Search products by keyword and sort by a given field/direction.
     * Used by both admin and client AJAX endpoints.
     *
     * @param string|null $keyword  Search term (matches name, description, brand, category)
     * @param string      $sortBy   Field to sort by (name, price, stock, category, createdAt)
     * @param string      $sortDir  Sort direction (ASC or DESC)
     * @return Product[]
     */
    /**
     * Exact same logic as searchAndSort but returns the Query object for pagination.
     */

   
     public function searchAndSortQuery(?string $keyword = null, string $sortBy = 'name', string $sortDir = 'ASC'): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('p');

        if ($keyword && trim($keyword) !== '') {
            $qb->andWhere('p.name LIKE :kw OR p.description LIKE :kw OR p.brand LIKE :kw OR p.category LIKE :kw')
            ->setParameter('kw', '%' . trim($keyword) . '%');
        }

        $allowedSortFields = ['name', 'price', 'stock', 'category', 'createdAt', 'brand', 'avgRating'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'name';
        }

        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $qb->orderBy('p.' . $sortBy, $sortDir);

        return $qb->getQuery();
    }


    /** @return array<int, array<string, mixed>> */
    public function searchAndSort(?string $keyword = null, string $sortBy = 'name', string $sortDir = 'ASC'): array
    {
        return $this->searchAndSortQuery($keyword, $sortBy, $sortDir)->getResult();
    }


    /** @return array<int, array<string, mixed>> */
    public function getCategoryDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.category, COUNT(p.id) as count')
            ->groupBy('p.category')
            ->getQuery()
            ->getResult();
    }

    public function countLowStock(int $threshold = 10): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.stock < :t')
            ->setParameter('t', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /** @return array<int, array<string, mixed>> */
    public function getTopRatedProducts(int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT name, price, avg_rating AS avgRating, category, stock
            FROM product
            WHERE avg_rating IS NOT NULL
            ORDER BY avg_rating DESC
            LIMIT ' . (int)$limit . '
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }


    /** @return array<int, array<string, mixed>> */
    public function getLowStockProducts(int $threshold = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT name, stock, category, price
            FROM product
            WHERE stock < ' . (int)$threshold . ' AND stock > 0
            ORDER BY stock ASC
            LIMIT 10
        ';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }


/** @return array<string, mixed> */
    public function getStockStatusBreakdown(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT
                SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) AS in_stock,
                SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_of_stock
            FROM product
        ';
        return $conn->executeQuery($sql)->fetchAssociative();
    }

    public function getTotalStockValue(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery('SELECT COALESCE(SUM(price * stock), 0) FROM product')->fetchOne();
        return round((float)$result, 4);
    }

    public function getProductsAddedThisMonth(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery('
            SELECT COUNT(id) FROM product
            WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ')->fetchOne();
        return (int)$result;
    }
}
