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
    public function searchAndSort(?string $keyword = null, string $sortBy = 'name', string $sortDir = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($keyword && trim($keyword) !== '') {
            $qb->andWhere('p.name LIKE :kw OR p.description LIKE :kw OR p.brand LIKE :kw OR p.category LIKE :kw')
               ->setParameter('kw', '%' . trim($keyword) . '%');
        }

        // Whitelist allowed sort fields to prevent injection
        $allowedSortFields = ['name', 'price', 'stock', 'category', 'createdAt', 'brand', 'avgRating'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'name';
        }

        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $qb->orderBy('p.' . $sortBy, $sortDir);

        return $qb->getQuery()->getResult();
    }
}
