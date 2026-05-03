<?php

namespace App\Service;

use App\Entity\Product;

class ProductManager
{
    /**
     * Valide les règles métier d'un produit.
     * 1. Le nom est obligatoire.
     * 2. Le prix doit être supérieur à 0.
     */
    public function validate(Product $product): bool
    {
        if (empty($product->getName())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        if ($product->getPrice() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être supérieur à 0');
        }

        return true;
    }
}