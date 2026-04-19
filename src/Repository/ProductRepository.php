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
    public function searchAndSortQuery(?string $keyword = null, string $sortBy = 'name', string $sortDir = 'ASC')
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

    public function searchAndSort(?string $keyword = null, string $sortBy = 'name', string $sortDir = 'ASC'): array
    {
        return $this->searchAndSortQuery($keyword, $sortBy, $sortDir)->getResult();
    }

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
}